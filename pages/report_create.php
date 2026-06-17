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
    setFlash('error', 'Type de registre invalide.');
    redirect(url('home'));
}

$pageTitle = 'Signaler un événement — ' . (REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type));

$pdo = getDB();
$user = currentUser();

// For agents, restrict site dropdown to their own site only
$canSelectSite = canSeeAllSites();
if ($canSelectSite) {
    $sites = getAllSites($pdo);
} else {
    // Agent: only show their own site
    $mySite = getSiteById($pdo, (int) $user['site_id']);
    $sites = $mySite ? [$mySite] : [];
}

$action = url('report_create', ['type' => $type]);

// Prepare variables for the shared form template
$isEdit = false;
$report = null;
$formErrors = getFormErrors();
$formData = getFormData();

require __DIR__ . '/../templates/report_form.php';
