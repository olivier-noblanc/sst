<?php

/**
 * Report Edit Handler — Thin controller delegating to ReportService.
 */
use App\Services\HttpService;
use App\Services\SessionService;
use App\Repository\ReportAgentRepository;
use App\Enum\ReportType;
use App\DTO\FormData;
use App\DTO\UpdateReportCommand;
use App\Services\ReportService;
use App\Services\CustomFieldsService;

/** @var array<string, string> $_POST */

$http = new HttpService();
$session = SessionService::getInstance();

// CSRF already validated by the router's CsrfMiddleware (applied to every
// POST handler in src/Router/routes.php) — see report_create_handler.php
// for the full explanation of the double-consumption bug this caused.

$reportUuid = trim((string) ($_POST['report_uuid'] ?? ''));
$report = fetchReportOrRedirect($reportUuid);
$user = $session->getUserSession();
$userId = $user->id ?? 0;

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

// Champs dynamiques du registre — validation serveur par champ
// (obligatoire, options de select, longueurs), même règles qu'à la création.
$customFieldsService = getContainer()->get(CustomFieldsService::class);
$customFieldDefs = $customFieldsService->getDefinitions($type);
$errors = array_merge($errors, $customFieldsService->validateSubmission($_POST, $customFieldDefs));

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData(FormData::fromPost($_POST));
    $http->redirect($http->url('report_edit', ['uuid' => $reportUuid]));
}

$cmd = UpdateReportCommand::fromPost($_POST);
/** @var array<string, mixed> $cmdData */
$cmdData = array_merge($cmd->toArray(), [
    'customFields' => $customFieldsService->extractSubmission($_POST, $customFieldDefs),
]);

// Audit #4-High — Si l'utilisateur a uploadé un nouveau fichier, il prime sur
// le flag removeAttachment (logique : cocher "remove" puis changer d'avis en
// uploadant un nouveau = on garde le nouveau). Si removeAttachment=true et
// pas de nouveau fichier → toArray() set attachment_blob=NULL dans le UPDATE.
if (!empty($attachment['blob'])) {
    $cmdData = array_merge($cmdData, [
        'attachmentBlob' => $attachment['blob'],
        'attachmentName' => $attachment['name'],
        'attachmentMime' => $attachment['mime'],
        'removeAttachment' => false,
    ]);
}
$cmd = new UpdateReportCommand(...$cmdData);

// Nouveaux e-mails d'agents à rattacher — calculés AVANT l'édition pour
// rejoindre SA transaction : invitation + message = atomiques avec la
// modification (un échec annule l'édition, pas d'invitation perdue en silence).
$inviteEmails = [];
$linkedEmailsRaw = trim((string) ($_POST['linked_emails'] ?? ''));
if ($linkedEmailsRaw !== '') {
    $service = getContainer()->get(ReportService::class);
    try {
        $linkedEmails = $service->validateLinkedEmails($linkedEmailsRaw, ['email' => $user?->email]);
    } catch (InvalidArgumentException) {
        // @silent-ok: malformed linked-email input — falls back to "no linked agents"
        // rather than blocking the whole report edit over one bad email field.
        $linkedEmails = [];
    }

    if (!empty($linkedEmails)) {
        // Comparaison insensible à la casse (parité avec LOWER(email) côté
        // hasUnconfirmedInvite) : un e-mail ressaisi avec une casse différente
        // désigne le même agent. Avant ce correctif, array_diff (sensible à la
        // casse) laissait passer l'e-mail et ré-invitait un agent déjà rattaché.
        $existingEmails = array_map(
            strtolower(...),
            array_column(ReportAgentRepository::instance()->getLinkedAgents($reportUuid), 'email')
        );
        $inviteEmails = array_values(array_filter(
            $linkedEmails,
            static fn(string $email): bool => !in_array(strtolower($email), $existingEmails, true)
        ));
    }
}

try {
    $service = getContainer()->get(ReportService::class);
    $updated = $service->update($reportUuid, $cmd, $userId, $inviteEmails);

    if ($updated) {
        auditLog(getDB(), 'report', 'edit', 'Signalement modifié : ' . (string) $report->reference, null, 'report', ['reference' => $report->reference], $report->uuid);
        $session->setFlash('success', 'Signalement ' . e((string) $report->reference) . ' modifié avec succès.');
    } else {
        error_log("SST: report_edit failed - uuid=$reportUuid, user_id=$userId, etat=" . $report->etat);
        $session->setFlash('error', 'Impossible de modifier ce signalement. Veuillez contacter un administrateur.');
    }
} catch (RuntimeException $e) {
    // @silent-ok: handler boundary — converts to a user-facing flash error + redirect,
    // the standard error-surfacing mechanism for this HTTP layer. Not silent: the user sees it.
    $session->setFlash('error', e($e->getMessage()));
    setFormData(FormData::fromPost($_POST));
    $http->redirect($http->url('report_edit', ['uuid' => $reportUuid]));
}

$http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
