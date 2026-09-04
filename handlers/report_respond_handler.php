<?php

use App\DTO\FormData;
use App\Services\HttpService;
use App\Services\SessionService;
use App\Enum\ReportState;
use App\Enum\UserRole;
use App\Enum\RespondStatus;
use App\Services\AccessService;
use App\Services\ReportStateMachine;

/**
 * Report Respond Handler — Application SST DREETS BFC
 *
 * Thin controller: validates request → DTO → ReportService → flash + redirect.
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

/** @var array<string, string> $_POST */

use App\DTO\AttachmentData;
use App\DTO\RespondToReportCommand;
use App\Services\ReportService;

$http = new HttpService();
$session = SessionService::getInstance();

$reportUuid = trim((string) ($_POST['report_uuid'] ?? ''));

// Validate nouvel_etat — enum-safe via tryFrom (jamais de whitelist
// dupliquée ici) : la validité complète (transition + rôle) est vérifiée
// contre la machine à états une fois le signalement chargé.
$nouvelEtat = trim((string) ($_POST['nouvel_etat'] ?? ''));
$targetState = ReportState::tryFrom($nouvelEtat);
if ($targetState === null) {
    $session->setFlash('error', 'L\'état sélectionné n\'est pas valide.');
    setFormData(FormData::fromPost($_POST));
    $http->redirect($http->url('report_respond', ['uuid' => $reportUuid]));
}
assert($targetState instanceof ReportState);

// Validate reponse
$reponse = trim((string) ($_POST['reponse'] ?? ''));
if (empty($reponse)) {
    $session->setFlash('error', 'La réponse ne peut pas être vide.');
    setFormErrors(['reponse' => 'La réponse ne peut pas être vide.']);
    setFormData(FormData::fromPost($_POST));
    $http->redirect($http->url('report_respond', ['uuid' => $reportUuid]));
}
if (mb_strlen($reponse, 'UTF-8') > 5000) {
    $session->setFlash('error', 'La réponse ne doit pas dépasser 5000 caractères.');
    setFormErrors(['reponse' => 'Maximum 5000 caractères.']);
    setFormData(FormData::fromPost($_POST));
    $http->redirect($http->url('report_respond', ['uuid' => $reportUuid]));
}

// Validate report respondability + transition — sources uniques :
// AccessService::canRespondToReport (état répondable) puis
// ReportStateMachine::canTransition (matrice + rôle).
// Fiabilisation : une transition absente de la matrice (y compris un état
// identique, ex. EnCours→EnCours qui crashait en InvalidArgumentException
// non interceptée) produit une réponse utilisateur contrôlée (flash +
// redirection avec la saisie préservée), jamais une exception fatale.
$report = fetchReportOrRedirect($reportUuid);
// AGENTS.md / NoForbiddenEnumMethodRule — ::from() est interdit sur une
// valeur non contrôlée : tryFrom + refus contrôlé, jamais de ValueError fatal.
$userRole = UserRole::tryFrom((string) currentUserRole());
if ($userRole === null) {
    $session->setFlash('error', 'Votre rôle de session n\'est pas reconnu. Reconnectez-vous.');
    $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
}
assert($userRole instanceof UserRole);
$access = new AccessService();
if (!$access->canRespondToReport($report, $userRole->value)) {
    $session->setFlash('error', 'Ce signalement ne peut plus recevoir de réponse.');
    $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
}

// État courant : tryFrom — un état DB inconnu (hors CHECK constraint) est
// refusé proprement au lieu de lever une ValueError fatale.
$currentState = ReportState::tryFrom($report->etat);
if ($currentState === null) {
    $session->setFlash('error', 'L\'état actuel de ce signalement n\'est pas reconnu. Contactez un superviseur.');
    $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
}
assert($currentState instanceof ReportState);

$stateMachine = new ReportStateMachine();
if (!$stateMachine->canTransition($currentState, $targetState, $userRole)) {
    $session->setFlash('error', e($stateMachine->getTransitionDescription($currentState, $userRole)));
    setFormData(FormData::fromPost($_POST));
    $http->redirect($http->url('report_respond', ['uuid' => $reportUuid]));
}

// Handle optional attachment
$attachment = null;
if (isset($_FILES['response_attachment']) && is_array($_FILES['response_attachment']) && !empty($_FILES['response_attachment']['tmp_name'])) {
    $fakeErrors = [];
    $att = validateReportAttachment($fakeErrors, 'response_attachment');
    if (!empty($fakeErrors)) {
        $session->setFlash('error', 'Erreur pièce jointe : ' . e(implode(', ', $fakeErrors)));
        setFormData(FormData::fromPost($_POST));
        $http->redirect($http->url('report_respond', ['uuid' => $reportUuid]));
    }
    $attachment = new AttachmentData(blob: $att['blob'], name: $att['name'], mime: $att['mime']);
}

$pdo = getDB();
$userId = $session->getUserSession()->id ?? 0;

try {
    $cmd = new RespondToReportCommand(
        reponse: $reponse,
        nouvelEtat: $targetState,
        attachment: $attachment,
    );

    $service = getContainer()->get(ReportService::class);
    $result = $service->respond($reportUuid, $cmd, $userId);

    if ($result['status'] === RespondStatus::Ok) {
        auditLog($pdo, 'report', 'respond', 'Réponse au signalement ' . (string) $report->reference . ' — état : ' . $nouvelEtat, null, 'report', ['reference' => $report->reference, 'nouvel_etat' => $nouvelEtat], $reportUuid);

        // Fiabilisation (council) — notification de réponse : chemin UNIQUE via
        // l'event 'report.responded' dispatché par ReportService::respond().
        // L'ancien appel direct notifyReportResponse() ici DOUBLAIT l'e-mail
        // du déclarant (handler + listener) à chaque réponse.
        $session->setFlash('success', 'Réponse enregistrée pour le signalement ' . e($report->reference) . '.');
    } else {
        $status = $result['status'];
        if ($status === RespondStatus::Concurrent) {
            $session->setFlash('error', 'Ce signalement a été modifié par un autre superviseur pendant votre saisie. Veuillez recommencer.');
        } else {
            $errorMsg = $result['message'] ?? 'Erreur inconnue';
            error_log('[SST-RESPOND] respondToReport failed: ' . $errorMsg . ' | user_id=' . $userId . ' report_uuid=' . $reportUuid);
            $session->setFlash('error', 'Erreur lors de l\'enregistrement de la réponse : ' . e($errorMsg));
        }
    }
} catch (RuntimeException|InvalidArgumentException $e) {
    // @silent-ok: handler boundary — flash error shown to the user, standard pattern.
    // Audit lifecycle — InvalidArgumentException gérée symétriquement (race).
    $session->setFlash('error', e($e->getMessage()));
    setFormData(FormData::fromPost($_POST));
    $http->redirect($http->url('report_respond', ['uuid' => $reportUuid]));
}

$http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
