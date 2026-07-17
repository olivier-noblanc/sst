<?php

/**
 * Report Edit Handler — Thin controller delegating to ReportService.
 */

use App\DTO\UpdateReportCommand;
use App\Services\ReportService;

/** @var array<string, string> $_POST */

validatePostRequest(url('home'));

$reportUuid = trim((string) ($_POST['report_uuid'] ?? ''));
$report = fetchReportOrRedirect($reportUuid);

/** @var array<string, string> $report */

$userId = currentUserId();
$user = currentUser();

/** @var array<string, string> $user */
requireReportOwnership($report, $userId, $reportUuid, 'modifier');
requireReportEditable($report, $reportUuid, 'modifié');

// Handle attachment removal
$removeAttachment = isset($_POST['remove_attachment']) && $_POST['remove_attachment'] === '1';
$errors = [];
$attachment = validateReportAttachment($errors);

// Validate report fields (same rules as creation)
$dateEvenement = trim((string) ($_POST['date_evenement'] ?? ''));
$heureEvenement = trim((string) ($_POST['heure_evenement'] ?? ''));
$lieu = trim((string) ($_POST['lieu'] ?? ''));
$objet = trim((string) ($_POST['objet'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$fieldErrors = validateReportFields($dateEvenement, $objet, $description, $lieu, $heureEvenement);
$errors = array_merge($errors, $fieldErrors);

// RAMI-specific validation
$type = $report['type'];
if ($type === TYPE_RAMI) {
    $pourCompte = isset($_POST['pour_compte']) && $_POST['pour_compte'] === '1';
    $pourCompteNom = trim((string) ($_POST['pour_compte_nom'] ?? ''));
    $pourComptePrenom = trim((string) ($_POST['pour_compte_prenom'] ?? ''));
    $errors = array_merge($errors, validatePourCompte($pourCompte, $pourCompteNom, $pourComptePrenom));
}

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('report_edit', ['uuid' => $reportUuid]));
}

$cmd = UpdateReportCommand::fromPost($_POST);
/** @var array<string, string> $cmdData */
$cmdData = array_merge($cmd->toArray(), [
    'attachmentBlob' => $removeAttachment ? null : ($attachment['blob'] ?? null),
    'attachmentName' => $removeAttachment ? null : ($attachment['name'] ?? null),
    'attachmentMime' => $removeAttachment ? null : ($attachment['mime'] ?? null),
]);
$cmd = new UpdateReportCommand(...$cmdData);

try {
    /** @var ReportService $service */
    $service = getContainer()->get(ReportService::class);
    $updated = $service->update($reportUuid, $cmd, $userId);

    if ($updated) {
        // Send invite emails for newly linked agents (non-blocking)
        $linkedEmailsRaw = trim((string) ($_POST['linked_emails'] ?? ''));
        if (!empty($linkedEmailsRaw)) {
            try {
                $pdo = getDB();
                $linkedEmailsList = array_map(trim(...), explode(',', $linkedEmailsRaw));
                $linkedEmailsList = array_filter($linkedEmailsList, fn($e) => filter_var((string) $e, FILTER_VALIDATE_EMAIL) !== false);

                // Domain validation: only allow emails from the declarant's domain
                // Fail-closed: if we can't determine the domain, reject all invites
                $declarantEmail = (string) ($user['email'] ?? '');
                if ($declarantEmail && str_contains($declarantEmail, '@')) {
                    $emailDomain = substr($declarantEmail, strrpos($declarantEmail, '@') + 1);
                    $linkedEmailsList = array_filter($linkedEmailsList, function (string $em) use ($emailDomain): bool {
                        $emDomain = substr($em, strrpos($em, '@') + 1);
                        return strtolower($emDomain) === strtolower($emailDomain);
                    });
                } else {
                    $linkedEmailsList = [];
                }

                $existingLinked = getLinkedAgents($pdo, $reportUuid);
                $existingEmails = array_column($existingLinked, 'email');
                $newEmails = array_diff($linkedEmailsList, $existingEmails);
                if (!empty($newEmails)) {
                    require_once __DIR__ . '/../src/mail.php';
                    sendAgentInviteEmails($pdo, $reportUuid, $newEmails);
                }
            } catch (Throwable $mailEx) {
                error_log('[SST-MAIL] Agent invite error: ' . $mailEx->getMessage());
            }
        }

        auditLog(getDB(), 'report', 'edit', 'Signalement modifié : ' . (string) $report['reference'], null, 'report', ['reference' => $report['reference']], $report['uuid']);
        setFlash('success', 'Signalement ' . e((string) $report['reference']) . ' modifié avec succès.');
    } else {
        error_log("SST: report_edit failed - uuid=$reportUuid, user_id=$userId, etat=" . (string) ($report['etat'] ?? ''));
        setFlash('error', 'Impossible de modifier ce signalement. Veuillez contacter un administrateur.');
    }
} catch (RuntimeException $e) {
    setFlash('error', e($e->getMessage()));
    setFormData($_POST);
    redirect(url('report_edit', ['uuid' => $reportUuid]));
} catch (Exception $e) {
    error_log('[SST-EDIT] Unexpected error: ' . $e->getMessage());
    setFlash('error', 'Une erreur inattendue est survenue.');
    setFormData($_POST);
    redirect(url('report_edit', ['uuid' => $reportUuid]));
}

redirect(url('report_view', ['uuid' => $reportUuid]));
