<?php

/**
 * Report Create Page — Application SST DREETS BFC
 *
 * Form for creating a new RSST, RAMI, or DGI report.
 * URL: index.php?page=report_create&type={rsst|rami|dgi}
 */
$type = $_GET['type'] ?? '';

// Validate type
if (!in_array($type, [TYPE_RSST, TYPE_RAMI, TYPE_DGI])) {
    (new \App\Services\SessionService())->setFlash('error', 'Type de registre invalide.');
    (new \App\Services\HttpService())->redirect((new \App\Services\HttpService())->url('home'));
}

// Block access to disabled registries
if (!\App\Services\ConfigService::getInstance()->isRegistryEnabled($type)) {
    (new \App\Services\SessionService())->setFlash('error', 'Ce registre est désactivé.');
    (new \App\Services\HttpService())->redirect((new \App\Services\HttpService())->url('home'));
}

$pageTitle = 'Signaler un événement — ' . (REGISTRY_SHORT_LABELS[$type] ?? strtoupper((string) $type));

$pdo = getContainer()->get(\PDO::class);
$user = (new \App\Services\SessionService())->getUserSession();

// For agents, restrict site dropdown to their own site only
$canSelectSite = (new \App\Services\AccessService())->canSeeAllSites();
if ($canSelectSite) {
    $sites = \App\Repository\SiteRepository::instance()->findAll();
} else {
    // Agent: only show their own site
    $mySite = \App\Repository\SiteRepository::instance()->findById((int) $user['site_id']);
    $sites = $mySite ? [$mySite] : [];
}

$action = (new \App\Services\HttpService())->url('report_create', ['type' => $type]);

// Prepare variables for the shared form template
$isEdit = false;
$report = null;
$formErrors = (new \App\Services\SessionService())->getFormErrors();
$formData = (new \App\Services\SessionService())->getFormData();

require __DIR__ . '/../templates/report_form.php';
