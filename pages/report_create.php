<?php

use App\Repository\RegistryRepository;
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
/** @var string */
$type = $_GET['type'] ?? '';

// Validate type against registries table (not hardcoded enum)
if (RegistryRepository::instance()->findByCode($type) === null) {
    SessionService::getInstance()->setFlash('error', 'Type de registre invalide.');
    new HttpService()->redirect(new HttpService()->url('home'));
}

// Block access to disabled registries
if (!getConfigService()->isRegistryEnabled($type)) {
    SessionService::getInstance()->setFlash('error', 'Ce registre est désactivé.');
    new HttpService()->redirect(new HttpService()->url('home'));
}

$pdo = getContainer()->get(PDO::class);
$user = SessionService::getInstance()->getUserSession();

// For agents, restrict site dropdown to their own site only
$canSelectSite = new AccessService()->canSeeAllSites();
if ($canSelectSite) {
    $sites = SiteRepository::instance()->findAll();
} else {
    // Agent: only show their own site
    /** @var string */
    $mySiteIdStr = $user['site_id'] ?? '0';
    $mySite = SiteRepository::instance()->findById((int) $mySiteIdStr);
    $sites = $mySite !== null ? [$mySite] : [];
}

$action = new HttpService()->url('report_create', ['type' => $type]);

// Prepare variables for the shared form template
$isEdit = false;
$report = null;
$formErrors = SessionService::getInstance()->getFormErrors();
$formData = SessionService::getInstance()->getFormData();

require __DIR__ . '/../templates/report_form.php';
