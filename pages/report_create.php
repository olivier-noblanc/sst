<?php
/**
 * Report Create Page — Application SST DREETS BFC
 *
 * Form for creating a new RSST, RAMI, or DGI report.
 * URL: index.php?page=report_create&type={rsst|rami|dgi}
 */
$type = $_GET['type'] ?? '';

// Validate type
if (!in_array($type, ['rsst', 'rami', 'dgi'])) {
    setFlash('error', 'Type de registre invalide.');
    redirect(url('home'));
}

$pageTitle = 'Inscrire un signalement — ' . (REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type));

$pdo = getDB();
$user = $_SESSION['user'];
$sites = getAllSites($pdo);

// For agents, restrict site dropdown to their own site only
$canSelectSite = canSeeAllSites();

$action = url('report_create', ['type' => $type]);

// Prepare variables for the shared form template
$isEdit = false;
$report = null;
$formErrors = getFormErrors();
$formData = getFormData();

require __DIR__ . '/../templates/alert.php';
require __DIR__ . '/../templates/report_form.php';
