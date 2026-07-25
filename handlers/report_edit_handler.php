<?php

/**
 * Report Edit Handler — Thin controller delegating to ReportService.
 */
use App\Services\HttpService;
use App\Services\SessionService;
use App\Repository\ReportRepository;
use App\Enum\ReportType;
use App\DTO\UpdateReportCommand;
use App\Services\ReportService;

/** @var array<string, string> $_POST */

$http = new HttpService();
$session = SessionService::getInstance();

// CSRF already validated by the router's CsrfMiddleware (applied to every
// POST handler in src/Router/routes.php) — see report_create_handler.php
// for the full explanation of the double-consumption bug this caused.

$reportUuid = trim((string) ($_POST['report_uuid'] ?? ''));
$report = fetchReportOrRedirect($reportUuid);
$userId = (int) ($session->getUserSession()['id'] ?? 0);
$user = $session->getUserSession();

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
$type = $report->type;
if ($type === ReportType::Rami->value) {
    $pourCompte = isset($_POST['pour_compte']) && $_POST['pour_compte'] === '1';
    $pourCompteNom = trim((string) ($_POST['pour_compte_nom'] ?? ''));
    $pourComptePrenom = trim((string) ($_POST['pour_compte_prenom'] ?? ''));
    $errors = array_merge($errors, validatePourCompte($pourCompte, $pourCompteNom, $pourComptePrenom));
}

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    $http->redirect($http->url('report_edit', ['uuid' => $reportUuid]));
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
    $service = getContainer()->get(ReportService::class);
    $updated = $service->update($reportUuid, $cmd, $userId);

    if ($updated) {
        // Send invite emails for newly linked agents (non-blocking)
        $linkedEmailsRaw = trim((string) ($_POST['linked_emails'] ?? ''));
        if (!empty($linkedEmailsRaw)) {
            try {
                $service = getContainer()->get(ReportService::class);
                $linkedEmails = $service->validateLinkedEmails($linkedEmailsRaw, $user ?? []);
            } catch (\InvalidArgumentException $e) {
                $linkedEmails = [];
            }

            if (!empty($linkedEmails)) {
                $pdo = getDB();
                $existingLinked = ReportRepository::instance()->getLinkedAgents($reportUuid);
                $existingEmails = array_column($existingLinked, 'email');
                $newEmails = array_diff($linkedEmails, $existingEmails);
                if (!empty($newEmails)) {
                    require_once __DIR__ . '/../src/mail.php';
                    sendAgentInviteEmails($pdo, $reportUuid, $newEmails);
                }
            }
        }

        auditLog(getDB(), 'report', 'edit', 'Signalement modifié : ' . (string) $report->reference, null, 'report', ['reference' => $report->reference], $report->uuid);
        $session->setFlash('success', 'Signalement ' . e((string) $report->reference) . ' modifié avec succès.');
    } else {
        error_log("SST: report_edit failed - uuid=$reportUuid, user_id=$userId, etat=" . $report->etat);
        $session->setFlash('error', 'Impossible de modifier ce signalement. Veuillez contacter un administrateur.');
    }
} catch (RuntimeException $e) {
    $session->setFlash('error', e($e->getMessage()));
    setFormData($_POST);
    $http->redirect($http->url('report_edit', ['uuid' => $reportUuid]));
}

$http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
