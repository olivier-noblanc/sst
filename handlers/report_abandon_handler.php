<?php

use App\Enum\ReportState;
use App\Enum\UserRole;
use App\Services\HttpService;
use App\Services\SessionService;
use App\Services\ReportStateMachine;

/**
 * Report Abandon Handler — Application SST DREETS BFC
 *
 * Thin controller: validates request → ReportService → flash + redirect.
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

/** @var array<string, string> $_POST */

use App\Services\ReportService;

$http = new HttpService();
$session = SessionService::getInstance();

$reportUuid = trim((string) ($_POST['report_uuid'] ?? ''));
$report = fetchReportOrRedirect($reportUuid);
$user = $session->getUserSession();
$userId = $user->id ?? 0;
$type = $report->type;

requireReportOwnership($report, $userId, $reportUuid, 'abandonner');

// Fiabilisation (audit lifecycle) — le guard est ALIGNÉ SUR LA MATRICE
// (autorité) : Abandonne est atteignable depuis Nouveau/EnCours/Traite/Reouvert
// pour le rôle Agent. L'ancien requireReportEditable ([Nouveau, EnCours]) était
// plus restrictif que la matrice : un agent ne pouvait pas abandonner un
// signalement Réouvert alors que la machine et l'UI l'autorisaient.
$abandonStateMachine = new ReportStateMachine();
$abandonCurrentState = ReportState::tryFrom($report->etat);
$abandonRole = UserRole::tryFrom((string) currentUserRole());
if ($abandonCurrentState === null || $abandonRole === null
    || !$abandonStateMachine->canTransition($abandonCurrentState, ReportState::Abandonne, $abandonRole)) {
    $session->setFlash('error', 'Ce signalement ne peut pas être abandonné (état : '
        . e(ETAT_LABELS[$report->etat] ?? $report->etat) . ').');
    $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
}

$pdo = getDB();

try {
    $service = getContainer()->get(ReportService::class);
    $abandoned = $service->abandon($reportUuid, $userId);

    if ($abandoned) {
        auditLog($pdo, 'report', 'abandon', 'Signalement abandonné : ' . $report->reference, null, 'report', ['reference' => $report->reference], $reportUuid);

        // Fiabilisation (council) — notification d'abandon : chemin UNIQUE via
        // l'event 'report.abandoned' dispatché par ReportService::abandon()
        // (NotificationService::notifyReportAbandon → destinataires du site +
        // globaux, site_id null géré). L'ancien bloc d'envoi direct ici
        // DOUBLAIT chaque e-mail de notification d'abandon.

        $session->setFlash('success', 'Signalement ' . e((string) $report->reference) . ' abandonné.');
        $http->redirect($http->url('report_list', ['type' => $type]));
    } else {
        $session->setFlash('error', 'Impossible d\'abandonner le signalement. Il a peut-être été modifié entre-temps. (uuid=' . e($reportUuid) . ', etat=' . e($report->etat) . ')');
        $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
    }
} catch (RuntimeException|InvalidArgumentException $e) {
    // @silent-ok: handler boundary — flash error shown to the user, standard pattern.
    // Audit lifecycle — InvalidArgumentException (transition absente) est gérée
    // symétriquement à RuntimeException : race condition entre le GET et le POST.
    $session->setFlash('error', e($e->getMessage()));
    $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
}
