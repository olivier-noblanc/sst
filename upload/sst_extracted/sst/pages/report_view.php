<?php
/**
 * Report View Page — Application SST DREETS BFC
 *
 * Displays a single report with all details, response history, and action buttons.
 * URL: index.php?page=report_view&id={report_id}
 */
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();
$report = getReportById($pdo, $id);

if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

// Access control: confidentiality check
// Le signalement est confidentiel : seuls le déclarant, les superviseurs,
// les managers et les membres CHSCT peuvent y accéder.
if (!canAccessReport($report)) {
    setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
    redirect(url('home'));
}

$user = $_SESSION['user'];
$userId = (int) $user['id'];
$userRole = $user['role'];

// If report is abandoned and user is not declarant nor supervisor/manager/chsct
if ($report['etat'] === 'abandonne' && (int) $report['declarant_id'] !== $userId && !in_array($userRole, ['superviseur', 'manager', 'chsct'])) {
    setFlash('warning', 'Ce signalement a été abandonné.');
}

$pageTitle = 'Signalement — ' . $report['reference'];

// Get response history
$responses = getReportResponses($pdo, $id);

require __DIR__ . '/../templates/alert.php';
require __DIR__ . '/../templates/report_card.php';
