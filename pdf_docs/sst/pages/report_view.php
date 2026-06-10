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

// Access control: depends on agent visibility setting
$user = $_SESSION['user'];
$userSiteId = (int) $user['site_id'];
$userId = (int) $user['id'];
$userRole = $user['role'];
$agentVisibility = getAgentVisibility();

if ($agentVisibility === 'own') {
    // Agent can only see their own reports
    if ((int) $report['declarant_id'] !== $userId) {
        setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
        redirect(url('home'));
    }
} elseif ($agentVisibility === 'site') {
    // Agent can only see reports from their site
    if ((int) $report['site_id'] !== $userSiteId) {
        setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
        redirect(url('home'));
    }
}
// 'all' → no restriction

// If report is abandoned and user is not declarant nor supervisor/chsct
if ($report['etat'] === 'abandonne' && (int) $report['declarant_id'] !== $userId && !in_array($userRole, ['superviseur', 'chsct'])) {
    setFlash('warning', 'Ce signalement a été abandonné.');
}

$pageTitle = 'Signalement — ' . $report['reference'];

// Get response history
$responses = getReportResponses($pdo, $id);

require __DIR__ . '/../templates/alert.php';
require __DIR__ . '/../templates/report_card.php';
