<?php

/**
 * Report Create Handler — Thin controller delegating to ReportService.
 */

use App\DTO\CreateReportCommand;
use App\Services\ReportService;

validatePostRequest(url('home'));

$type = (string) ($_POST['type'] ?? '');
if (!in_array($type, [TYPE_RSST, TYPE_RAMI, TYPE_DGI])) {
    setFlash('error', 'Type de registre invalide.');
    redirect(url('home'));
}
if (!isRegistryEnabled($type)) {
    setFlash('error', 'Ce registre est désactivé.');
    redirect(url('home'));
}

$pdo = getDB();
$user = currentUser();

// Site validation (skipped in noSiteMode)
$siteId = (int) ($_POST['site_id'] ?? 0);
if (!isNoSiteMode($pdo)) {
    if ($siteId <= 0) {
        setFormErrors(['site_id' => 'L\'unité départementale est obligatoire.']);
        setFormData($_POST);
        redirect(url('report_create', ['type' => $type]));
    }
    $site = getSiteById($pdo, $siteId);
    if (!$site) {
        setFormErrors(['site_id' => 'Unité départementale invalide.']);
        setFormData($_POST);
        redirect(url('report_create', ['type' => $type]));
    }
    if (!canSeeAllSites() && $siteId !== (int) ($user['site_id'] ?? 0)) {
        setFormErrors(['site_id' => 'Vous ne pouvez créer un signalement que pour votre ' . getConfig('app_label_unite', 'UR') . '.']);
        setFormData($_POST);
        redirect(url('report_create', ['type' => $type]));
    }
}

// Linked emails domain validation
$linkedEmailsRaw = trim((string) ($_POST['linked_emails'] ?? ''));
if (!empty($linkedEmailsRaw)) {
    $declarantEmail = (string) ($user['email'] ?? '');
    $emailDomain = '';
    if ($declarantEmail && str_contains($declarantEmail, '@')) {
        $emailDomain = substr($declarantEmail, strrpos($declarantEmail, '@') + 1);
    }
    $linkedEmailsList = array_map(trim(...), explode(',', $linkedEmailsRaw));
    foreach ($linkedEmailsList as $em) {
        if (empty($em)) { continue; }
        if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
            setFormErrors(['linked_emails' => 'Adresse e-mail invalide : ' . e($em)]);
            setFormData($_POST);
            redirect(url('report_create', ['type' => $type]));
        }
        if ($emailDomain) {
            $emDomain = substr($em, strrpos($em, '@') + 1);
            if (strtolower($emDomain) !== strtolower($emailDomain)) {
                setFormErrors(['linked_emails' => 'Seul le domaine @' . e($emailDomain) . ' est autorisé. Adresse refusée : ' . e($em)]);
                setFormData($_POST);
                redirect(url('report_create', ['type' => $type]));
            }
        }
    }
}

try {
    $errors = [];
    $attachment = validateReportAttachment($errors);
    $cmd = CreateReportCommand::fromPost($_POST, $user ?? []);
    $cmd = new CreateReportCommand(...array_merge($cmd->toArray(), [
        'attachmentBlob' => $attachment['blob'],
        'attachmentName' => $attachment['name'],
        'attachmentMime' => $attachment['mime'],
    ]));

    /** @var ReportService $service */
    $service = getContainer()->get(ReportService::class);
    $report = $service->create($cmd);

    // Audit log
    auditLog(getDB(), 'report', 'create', 'Signalement créé : ' . (string) $report['reference'], null, 'report', ['reference' => $report['reference'], 'type' => $type, 'site_id' => $siteId], $report['uuid']);

    // Send linked agent invite emails (non-blocking)
    if (!empty($linkedEmailsRaw)) {
        try {
            require_once __DIR__ . '/../src/mail.php';
            $linkedEmails = array_map(trim(...), explode(',', $linkedEmailsRaw));
            $linkedEmails = array_filter($linkedEmails, fn($e) => filter_var((string) $e, FILTER_VALIDATE_EMAIL) !== false);
            if (!empty($linkedEmails)) {
                sendAgentInviteEmails($pdo, (string) $report['uuid'], $linkedEmails);
            }
        } catch (Throwable $mailEx) {
            error_log('[SST-MAIL] Agent invite error: ' . $mailEx->getMessage());
        }
    }

    setFlash('success', 'Signalement enregistré avec la référence ' . e((string) $report['reference']));
    $_SESSION['report_created'] = true;
    redirect(url('report_view', ['uuid' => $report['uuid']]));

} catch (InvalidArgumentException $e) {
    setFormErrors(['general' => $e->getMessage()]);
    setFormData($_POST);
    redirect(url('report_create', ['type' => $type]));
} catch (Exception $e) {
    error_log('[SST-DB] report_create failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de l\'enregistrement : ' . e($e->getMessage()));
    setFormData($_POST);
    redirect(url('report_create', ['type' => $type]));
}
