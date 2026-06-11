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

// Superviseur/CHSCT can always see everything
if (!in_array($userRole, ['superviseur', 'chsct'])) {
    // Agent access control
    if ((int) $report['site_id'] !== $userSiteId) {
        // Agent can never see reports from other sites
        setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
        redirect(url('home'));
    }
    if ($agentVisibility === 'confidential' && (int) $report['is_confidential'] === 1 && (int) $report['declarant_id'] !== $userId) {
        // In confidential mode, agent cannot see other agents' confidential reports
        setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
        redirect(url('home'));
    }
}

// If report is abandoned and user is not declarant nor supervisor/chsct
if ($report['etat'] === 'abandonne' && (int) $report['declarant_id'] !== $userId && !in_array($userRole, ['superviseur', 'chsct'])) {
    setFlash('warning', 'Ce signalement a été abandonné.');
}

$pageTitle = 'Signalement — ' . $report['reference'];

// Get response history
$responses = getReportResponses($pdo, $id);

require __DIR__ . '/../templates/alert.php';
require __DIR__ . '/../templates/report_card.php';
