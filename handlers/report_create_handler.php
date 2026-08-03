<?php

/**
 * Report Create Handler — Thin controller delegating to ReportService.
 */
use App\Services\HttpService;
use App\Services\SessionService;
use App\Repository\RegistryRepository;
use App\Repository\SiteRepository;
use App\DTO\FormData;
use App\DTO\CreateReportCommand;
use App\DTO\SiteId;
use App\Services\ReportService;

/** @var array<string, string> $_POST */

$http = new HttpService();
$session = SessionService::getInstance();
$config = getConfigService();

// CSRF already validated by the router's CsrfMiddleware (applied to every
// POST handler in src/Router/routes.php). Calling validatePostRequest()
// here too consumed the same one-time-use token twice — the middleware's
// check succeeded and deleted it, then this second check failed on the
// already-deleted token, silently redirecting home on every submission.

$type = (string) ($_POST['type'] ?? '');
if (RegistryRepository::instance()->findByCode($type) === null) {
    $session->setFlash('error', 'Type de registre invalide.');
    $http->redirect($http->url('home'));
}
if (!$config->isRegistryEnabled($type)) {
    $session->setFlash('error', 'Ce registre est désactivé.');
    $http->redirect($http->url('home'));
}

$pdo = getDB();
$user = $session->getUserSession();

// Site validation (skipped in noSiteMode)
$siteId = (int) ($_POST['site_id'] ?? 0);
if (!isNoSiteMode($pdo)) {
    if ($siteId <= 0) {
        setFormErrors(['site_id' => 'L\'unité départementale est obligatoire.']);
        setFormData(FormData::fromPost($_POST));
        $http->redirect($http->url('report_create', ['type' => $type]));
    }
    $site = SiteRepository::instance()->findById($siteId);
    if ($site === null || empty($site['is_active'])) {
        setFormErrors(['site_id' => 'Unité invalide ou désactivée.']);
        setFormData(FormData::fromPost($_POST));
        $http->redirect($http->url('report_create', ['type' => $type]));
    }
    if (!canSeeAllSites() && $siteId !== ($user->siteId ?? 0)) {
        setFormErrors(['site_id' => 'Vous ne pouvez créer un signalement que pour votre ' . $config->get('app_label_unite', 'UR') . '.']);
        setFormData(FormData::fromPost($_POST));
        $http->redirect($http->url('report_create', ['type' => $type]));
    }
}

// Linked emails domain validation
$linkedEmailsRaw = trim((string) ($_POST['linked_emails'] ?? ''));
$linkedEmails = [];
if (!empty($linkedEmailsRaw)) {
    try {
        $reportService = getContainer()->get(ReportService::class);
        $linkedEmails = $reportService->validateLinkedEmails($linkedEmailsRaw, ['email' => $user?->email]);
    } catch (InvalidArgumentException $e) {
        // @silent-ok: form validation error surfaced via setFormErrors(), shown to the user.
        setFormErrors(['linked_emails' => e($e->getMessage())]);
        setFormData(FormData::fromPost($_POST));
        $http->redirect($http->url('report_create', ['type' => $type]));
    }
}

try {
    $errors = [];
    $attachment = validateReportAttachment($errors);
    $cmd = CreateReportCommand::fromPost($_POST, ['id' => $user->id ?? 0, 'nom' => $user->nom ?? '', 'prenom' => $user->prenom ?? '']);
    /** @var array<string, mixed> $cmdData */
    $cmdData = array_merge($cmd->toArray(), [
        'type' => $cmd->type,
        'attachmentBlob' => $attachment['blob'],
        'attachmentName' => $attachment['name'],
        'attachmentMime' => $attachment['mime'],
    ]);
    $cmdData['siteId'] = SiteId::fromInput((int) ($cmdData['siteId'] ?? 0));
    $cmd = new CreateReportCommand(...$cmdData);

    $service = getContainer()->get(ReportService::class);
    $report = $service->create($cmd);
    // Audit log
    auditLog(getDB(), 'report', 'create', 'Signalement créé : ' . (string) $report->reference, null, 'report', ['reference' => $report->reference, 'type' => $type, 'site_id' => $siteId], $report->uuid);

    // Send linked agent invite emails (non-blocking)
    if (!empty($linkedEmails)) {
        require_once __DIR__ . '/../src/mail.php';
        sendAgentInviteEmails($pdo, (string) $report->uuid, $linkedEmails);
    }

    $session->setFlash('success', 'Signalement enregistré avec la référence ' . e((string) $report->reference));
    $_SESSION['report_created'] = true;
    $http->redirect($http->url('report_view', ['uuid' => $report->uuid]));

} catch (InvalidArgumentException $e) {
    // @silent-ok: form validation error surfaced via setFormErrors(), shown to the user.
    setFormErrors(['general' => $e->getMessage()]);
    setFormData(FormData::fromPost($_POST));
    $http->redirect($http->url('report_create', ['type' => $type]));
}
