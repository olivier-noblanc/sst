<?php

/**
 * Report Respond Handler — Application SST DREETS BFC
 *
 * Thin controller: validates request → DTO → ReportService → flash + redirect.
 */

require_once __DIR__ . '/../src/DTO/RespondToReportCommand.php';
require_once __DIR__ . '/../src/Container/Container.php';
require_once __DIR__ . '/../src/Repository/ReportRepository.php';
require_once __DIR__ . '/../src/Event/EventDispatcher.php';
require_once __DIR__ . '/../src/Services/ReportService.php';
require_once __DIR__ . '/../src/bootstrap_services.php';

validatePostRequest(url('home'), [ROLE_SUPERVISEUR]);

$reportUuid = trim($_POST['report_uuid'] ?? '');

// Validate nouvel_etat
$nouvelEtat = trim($_POST['nouvel_etat'] ?? '');
if (!in_array($nouvelEtat, [ETAT_EN_COURS, ETAT_TRAITE])) {
    setFlash('error', 'L\'état sélectionné n\'est pas valide.');
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => $reportUuid]));
}

// Validate reponse
$reponse = trim($_POST['reponse'] ?? '');
if (empty($reponse)) {
    setFlash('error', 'La réponse ne peut pas être vide.');
    setFormErrors(['reponse' => 'La réponse ne peut pas être vide.']);
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => $reportUuid]));
}
if (strlen($reponse) > 5000) {
    setFlash('error', 'La réponse ne doit pas dépasser 5000 caractères.');
    setFormErrors(['reponse' => 'Maximum 5000 caractères.']);
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => $reportUuid]));
}

// Validate report state
$report = fetchReportOrRedirect($reportUuid);
if (!in_array($report['etat'], [ETAT_NOUVEAU, ETAT_EN_COURS, ETAT_REOUVERT])) {
    setFlash('error', 'Ce signalement ne peut plus recevoir de réponse.');
    redirect(url('report_view', ['uuid' => $reportUuid]));
}

// Handle optional attachment
$attachment = [];
if (!empty($_FILES['response_attachment']['tmp_name'])) {
    $fakeErrors = [];
    $att = validateReportAttachment($fakeErrors);
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

    if ($result['status'] === 'true') {
        auditLog($pdo, 'report', 'respond', 'Réponse au signalement ' . $report['reference'] . ' — état : ' . $nouvelEtat, (int) ($report['id'] ?? 0), 'report', ['reference' => $report['reference'], 'nouvel_etat' => $nouvelEtat]);

        try {
            require_once __DIR__ . '/../src/mail.php';
            notifyReportResponse($pdo, $reportUuid, $userId);
        } catch (Exception $mailEx) {
            error_log('[SST-MAIL] Notification error: ' . $mailEx->getMessage());
        }

        setFlash('success', 'Réponse enregistrée pour le signalement ' . e($report['reference']) . '.');
    } else {
        if ($result['status'] === 'concurrent') {
            setFlash('error', 'Ce signalement a été modifié par un autre superviseur pendant votre saisie. Veuillez recommencer.');
        } else {
            $errorMsg = $result['message'] ?? 'Erreur inconnue';
            error_log('[SST-RESPOND] respondToReport failed: ' . $errorMsg . ' | user_id=' . $userId . ' report_uuid=' . $reportUuid);
            setFlash('error', 'Erreur lors de l\'enregistrement de la réponse : ' . e($errorMsg));
        }
    }
} catch (\RuntimeException $e) {
    setFlash('error', e($e->getMessage()));
    setFormData($_POST);
    redirect(url('report_respond', ['uuid' => $reportUuid]));
}

redirect(url('report_view', ['uuid' => $reportUuid]));
