<?php

/**
 * Report Create Handler — Application SST DREETS BFC
 *
 * Thin controller: validates request → DTO → ReportService → flash + redirect.
 */

require_once __DIR__ . '/../src/DTO/CreateReportCommand.php';
require_once __DIR__ . '/../src/Container/Container.php';
require_once __DIR__ . '/../src/Repository/ReportRepository.php';
require_once __DIR__ . '/../src/Event/EventDispatcher.php';
require_once __DIR__ . '/../src/Services/ReportService.php';
require_once __DIR__ . '/../src/bootstrap_services.php';

validatePostRequest(url('home'));

// Pre-validation: type and registry enabled
$type = $_POST['type'] ?? '';
if (!in_array($type, [TYPE_RSST, TYPE_RAMI, TYPE_DGI])) {
    setFlash('error', 'Type de registre invalide.');
    redirect(url('home'));
}
if (!isRegistryEnabled($type)) {
    setFlash('error', 'Ce registre est désactivé.');
    redirect(url('home'));
}

// Validate attachment (optional, before DTO — DTO sets nulls)
$errors = [];
$attachment = validateReportAttachment($errors);

// Validate site (needs DB lookup)
$pdo = getDB();
$siteId = (int) ($_POST['site_id'] ?? 0);
$noSiteMode = isNoSiteMode($pdo);
if (!$noSiteMode) {
    if ($siteId <= 0) {
        $errors['site_id'] = 'L\'unité départementale est obligatoire.';
    } else {
        $site = getSiteById($pdo, $siteId);
        if (!$site) {
            $errors['site_id'] = 'Unité départementale invalide.';
        }
    }
    $user = currentUser();
    if (!canSeeAllSites() && $siteId !== (int) $user['site_id']) {
        $errors['site_id'] = 'Vous ne pouvez créer un signalement que pour votre ' . getConfig('app_label_unite', 'UR') . '.';
    }
}

// Validate linked emails domain
$linkedEmailsRaw = trim($_POST['linked_emails'] ?? '');
if (!empty($linkedEmailsRaw)) {
    $user = currentUser();
    $declarantEmail = $user['email'] ?? '';
    $emailDomain = '';
    if ($declarantEmail && strpos($declarantEmail, '@') !== false) {
        $emailDomain = substr($declarantEmail, strrpos($declarantEmail, '@') + 1);
    }
    $linkedEmailsList = array_map('trim', explode(',', $linkedEmailsRaw));
    foreach ($linkedEmailsList as $em) {
        if (empty($em)) continue;
        if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $errors['linked_emails'] = 'Adresse e-mail invalide : ' . e($em);
            break;
        }
        if ($emailDomain) {
            $emDomain = substr($em, strrpos($em, '@') + 1);
            if (strtolower($emDomain) !== strtolower($emailDomain)) {
                $errors['linked_emails'] = 'Seul le domaine @' . e($emailDomain) . ' est autorisé. Adresse refusée : ' . e($em);
                break;
            }
        }
    }
}

if (!empty($errors)) {
    setFormErrors($errors);
    setFormData($_POST);
    redirect(url('report_create', ['type' => $type]));
}

try {
    $cmd = CreateReportCommand::fromPost($_POST, currentUser());
    // Override attachment fields from validated upload (DTO defaults to null)
    $cmd = new CreateReportCommand(...array_merge($cmd->toArray(), [
        'attachmentBlob' => $attachment['blob'],
        'attachmentName' => $attachment['name'],
        'attachmentMime' => $attachment['mime'],
    ]));

    $service = getContainer()->get(ReportService::class);
    $report = $service->create($cmd);

    // Audit log
    auditLog($pdo, 'report', 'create', 'Signalement créé : ' . $report['reference'], (int) $report['id'], 'report', ['reference' => $report['reference'], 'type' => $type, 'site_id' => $siteId]);

    // Notifications (non-blocking)
    try {
        require_once __DIR__ . '/../src/mail.php';
        notifyNewReport($pdo, $report['uuid'], $type, $siteId);
        if ($type === TYPE_RAMI && !empty($report['pour_compte_nom'])) {
            notifyPourCompte($pdo, $report['uuid']);
        }
        if (!empty($linkedEmailsRaw)) {
            $linkedEmails = array_map('trim', explode(',', $linkedEmailsRaw));
            $linkedEmails = array_filter($linkedEmails, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
            if (!empty($linkedEmails)) {
                sendAgentInviteEmails($pdo, $report['uuid'], $linkedEmails);
            }
        }
    } catch (Exception $mailEx) {
        error_log('[SST-MAIL] Notification error: ' . $mailEx->getMessage());
    }

    setFlash('success', 'Signalement enregistré avec la référence ' . e($report['reference']));
    $_SESSION['report_created'] = true;
    redirect(url('report_view', ['uuid' => $report['uuid']]));
} catch (\InvalidArgumentException $e) {
    setFlash('error', 'Erreur lors de l\'enregistrement du signalement : ' . e($e->getMessage()));
    setFormData($_POST);
    redirect(url('report_create', ['type' => $type]));
}
