<?php

use App\Enum\ReportState;

/**
 * Report Respond Handler — Application SST DREETS BFC
 *
 * Thin controller: validates request → DTO → ReportService → flash + redirect.
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

/** @var array<string, string> $_POST */

use App\DTO\RespondToReportCommand;
use App\Services\ReportService;

$reportUuid = trim((string) ($_POST['report_uuid'] ?? ''));

// Validate nouvel_etat
$nouvelEtat = trim((string) ($_POST['nouvel_etat'] ?? ''));
if (!in_array($nouvelEtat, [ReportState::EnCours->value, ReportState::Traite->value], true)) {
    setFlash('error', 'L\'état sélectionné n\'est pas valide.');
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => $reportUuid]));
}

// Validate reponse
$reponse = trim((string) ($_POST['reponse'] ?? ''));
if (empty($reponse)) {
    setFlash('error', 'La réponse ne peut pas être vide.');
    setFormErrors(['reponse' => 'La réponse ne peut pas être vide.']);
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => $reportUuid]));
}
if (mb_strlen($reponse, 'UTF-8') > 5000) {
    setFlash('error', 'La réponse ne doit pas dépasser 5000 caractères.');
    setFormErrors(['reponse' => 'Maximum 5000 caractères.']);
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => $reportUuid]));
}

// Validate report state
$report = fetchReportOrRedirect($reportUuid);

/** @var array<string, string> $report */

if (!in_array($report['etat'], [ReportState::Nouveau->value, ReportState::EnCours->value, ReportState::Reouvert->value], true)) {
    setFlash('error', 'Ce signalement ne peut plus recevoir de réponse.');
    redirect(url('report_view', ['uuid' => $reportUuid]));
}

// Handle optional attachment
$attachment = [];
if (isset($_FILES['response_attachment']) && is_array($_FILES['response_attachment']) && !empty($_FILES['response_attachment']['tmp_name'])) {
    $fakeErrors = [];
    $att = validateReportAttachment($fakeErrors, 'response_attachment');
    if (!empty($fakeErrors)) {
        setFlash('error', 'Erreur pièce jointe : ' . e(implode(', ', $fakeErrors)));
        setFormData($_POST);
        redirect(url('report_respond', ['uuid' => $reportUuid]));
    }
    $attachment = ['blob' => $att['blob'], 'name' => $att['name'], 'mime' => $att['mime']];
}

$pdo = getDB();
$userId = currentUserId();

try {
    $cmd = new RespondToReportCommand(
        reponse: $reponse,
        nouvelEtat: $nouvelEtat,
        attachment: $attachment,
    );

    $service = getContainer()->get(ReportService::class);
    $result = $service->respond($reportUuid, $cmd, $userId);

    if (is_array($result) && ($result['status'] ?? '') === 'ok') {
        auditLog($pdo, 'report', 'respond', 'Réponse au signalement ' . (string) $report['reference'] . ' — état : ' . $nouvelEtat, null, 'report', ['reference' => $report['reference'], 'nouvel_etat' => $nouvelEtat], $reportUuid);

        require_once __DIR__ . '/../src/mail.php';
        notifyReportResponse($pdo, $reportUuid, $userId);

        setFlash('success', 'Réponse enregistrée pour le signalement ' . e($report['reference']) . '.');
    } else {
        $status = $result['status'] ?? '';
        if ($status === 'concurrent') {
            setFlash('error', 'Ce signalement a été modifié par un autre superviseur pendant votre saisie. Veuillez recommencer.');
        } else {
            $errorMsg = $result['message'] ?? 'Erreur inconnue';
            error_log('[SST-RESPOND] respondToReport failed: ' . $errorMsg . ' | user_id=' . $userId . ' report_uuid=' . $reportUuid);
            setFlash('error', 'Erreur lors de l\'enregistrement de la réponse : ' . e($errorMsg));
        }
    }
} catch (RuntimeException $e) {
    setFlash('error', e($e->getMessage()));
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => $reportUuid]));
}

redirect(url('report_view', ['uuid' => $reportUuid]));
