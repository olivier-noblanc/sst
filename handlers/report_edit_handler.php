<?php

/**
 * Report Edit Handler — Application SST DREETS BFC
 *
 * Thin controller: validates request → DTO → ReportService → flash + redirect.
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

use App\DTO\UpdateReportCommand;
use App\Services\ReportService;

validatePostRequest(url('home'));

// Fetch report
$reportUuid = trim($_POST['report_uuid'] ?? '');
$report = fetchReportOrRedirect($reportUuid);

$user = currentUser();
$userId = currentUserId();

requireReportOwnership($report, $userId, $reportUuid, 'modifier');
requireReportEditable($report, $reportUuid, 'modifié');

$type = $report['type'];

// Validate RAMI fields
$natureAuteur = trim($_POST['nature_auteur'] ?? '');
$typeActe = trim($_POST['type_acte'] ?? '');
$ramiFields = validateRamiFields($natureAuteur, $typeActe);

// Enforce visibility mode
$isConfidential = isset($_POST['is_confidential']) && $_POST['is_confidential'] === '1' ? 1 : 0;
$isConfidential = enforceReportVisibility($isConfidential);

// Validate attachment (optional)
$removeAttachment = isset($_POST['remove_attachment']) && $_POST['remove_attachment'] === '1';
$errors = [];
$attachment = validateReportAttachment($errors);

// RAMI-specific validation
if ($type === TYPE_RAMI) {
    $pourCompte = isset($_POST['pour_compte']) && $_POST['pour_compte'] === '1';
    $errors = array_merge($errors, validatePourCompte(
        $pourCompte,
        trim($_POST['pour_compte_nom'] ?? ''),
        trim($_POST['pour_compte_prenom'] ?? '')
    ));
}

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('report_edit', ['uuid' => $reportUuid]));
}

try {
    $cmd = UpdateReportCommand::fromPost($_POST);

    // Override attachment: set or remove based on user action
    if ($removeAttachment) {
        $cmd = new UpdateReportCommand(...array_merge($cmd->toArray(), [
            'attachmentBlob' => null,
            'attachmentName' => null,
            'attachmentMime' => null,
        ]));
    } elseif ($attachment['blob'] !== null) {
        $cmd = new UpdateReportCommand(...array_merge($cmd->toArray(), [
            'attachmentBlob' => $attachment['blob'],
            'attachmentName' => $attachment['name'],
            'attachmentMime' => $attachment['mime'],
        ]));
    }

    $service = getContainer()->get(ReportService::class);
    $updated = $service->update($reportUuid, $cmd, $userId);

    // Handle linked agents — send new invite emails for newly added addresses
    $linkedEmailsRaw = trim($_POST['linked_emails'] ?? '');
    if (!empty($linkedEmailsRaw)) {
        $pdo = getDB();
        $linkedEmailsList = array_map('trim', explode(',', $linkedEmailsRaw));
        $linkedEmailsList = array_filter($linkedEmailsList, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        $existingLinked = getLinkedAgents($pdo, $reportUuid);
        $existingEmails = array_column($existingLinked, 'email');
        $newEmails = array_diff($linkedEmailsList, $existingEmails);
        if (!empty($newEmails)) {
            require_once __DIR__ . '/../src/mail.php';
            sendAgentInviteEmails($pdo, $reportUuid, $newEmails);
        }
    }

    if ($updated) {
        auditLog(getDB(), 'report', 'edit', 'Signalement modifié : ' . $report['reference'], (int) $report['id'], 'report', ['reference' => $report['reference']]);
        setFlash('success', 'Signalement ' . e($report['reference']) . ' modifié avec succès.');
    } else {
        error_log("SST: report_edit failed - uuid=$reportUuid, user_id=$userId, etat=" . $report['etat']);
        setFlash('error', 'Impossible de modifier ce signalement. Veuillez contacter un administrateur.');
    }
} catch (\InvalidArgumentException $e) {
    setFlash('error', 'Erreur lors de la modification : ' . e($e->getMessage()));
    setFormData($_POST);
    redirect(url('report_edit', ['uuid' => $reportUuid]));
}

redirect(url('report_view', ['uuid' => $reportUuid]));
