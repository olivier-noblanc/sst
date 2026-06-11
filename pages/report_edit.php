<?php
/**
 * Report Edit Page — Application SST DREETS BFC
 *
 * Edit form for own reports. Only the declarant can edit, and only
 * if the report state is 'nouveau' or 'en_cours'.
 * URL: index.php?page=report_edit&uuid={report_uuid}
 */
$uuid = $_GET['uuid'] ?? '';

if ($uuid === '' || strlen($uuid) !== 36) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();
$report = getReportByUuid($pdo, $uuid);

if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

// Access control: only the declarant can edit
$user = $_SESSION['user'];
$userId = (int) $user['id'];

if ((int) $report['declarant_id'] !== $userId) {
    setFlash('error', 'Vous ne pouvez modifier que vos propres signalements.');
    redirect(url('report_view', ['uuid' => $uuid]));
}

// Check state: can only edit if nouveau or en_cours
if (!in_array($report['etat'], ['nouveau', 'en_cours'])) {
    setFlash('error', 'Ce signalement ne peut plus être modifié (état : ' . (ETAT_LABELS[$report['etat']] ?? $report['etat']) . ').');
    redirect(url('report_view', ['uuid' => $uuid]));
}

$pageTitle = 'Modifier le signalement — ' . $report['reference'];

$type = $report['type'];
$action = url('report_edit', ['uuid' => $uuid]);

// Prepare variables for the shared form template
$isEdit = true;
$sites = getAllSites($pdo);
$formErrors = getFormErrors();
$formData = getFormData();

require __DIR__ . '/../templates/alert.php';
require __DIR__ . '/../templates/report_form.php';
