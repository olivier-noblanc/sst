<?php

use App\Services\SessionService;
use App\Services\HttpService;
use App\Services\ConfigService;
use App\Services\AccessService;
use App\Repository\SiteRepository;

/**
 * Report Create Page — Application SST DREETS BFC
 *
 * Form for creating a new RSST, RAMI, or DGI report.
 * URL: index.php?page=report_create&type={rsst|rami|dgi}
 */
$type = $_GET['type'] ?? '';

// Validate type
if (!in_array($type, [TYPE_RSST, TYPE_RAMI, TYPE_DGI])) {
    SessionService::getInstance()->setFlash('error', 'Type de registre invalide.');
    new HttpService()->redirect(new HttpService()->url('home'));
}

// Block access to disabled registries
if (!ConfigService::getInstance()->isRegistryEnabled($type)) {
    SessionService::getInstance()->setFlash('error', 'Ce registre est désactivé.');
    new HttpService()->redirect(new HttpService()->url('home'));
}

$pageTitle = 'Signaler un événement — ' . (REGISTRY_SHORT_LABELS[$type] ?? strtoupper((string) $type));

$pdo = getContainer()->get(PDO::class);
$user = SessionService::getInstance()->getUserSession();

// For agents, restrict site dropdown to their own site only
$canSelectSite = new AccessService()->canSeeAllSites();
if ($canSelectSite) {
    $sites = SiteRepository::instance()->findAll();
} else {
    // Agent: only show their own site
    $mySite = SiteRepository::instance()->findById((int) $user['site_id']);
    $sites = $mySite ? [$mySite] : [];
}

$action = new HttpService()->url('report_create', ['type' => $type]);

// Prepare variables for the shared form template
$isEdit = false;
$report = null;
$formErrors = SessionService::getInstance()->getFormErrors();
$formData = SessionService::getInstance()->getFormData();

require __DIR__ . '/../templates/report_form.php';
