<?php
/**
 * Report View Page — Application SST DREETS BFC
 *
 * Displays a single report with all details, response history, and action buttons.
 * URL: index.php?page=report_view&uuid={report_uuid}
 */
$uuid = $_GET['uuid'] ?? '';

if (!isValidUuid($uuid)) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();
$report = getReportByUuid($pdo, $uuid);

if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

// Access control: depends on report visibility setting
$user = $_SESSION['user'];
$userSiteId = (int) $user['site_id'];
$userId = (int) $user['id'];
$userRole = $user['role'];
$reportVisibility = getReportVisibility();

// Superviseur/CHSCT can always see everything
if (!in_array($userRole, ['superviseur', 'chsct'])) {
    // Agent access control
    if ((int) $report['site_id'] !== $userSiteId) {
        // Agent can never see reports from other sites
        setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
        redirect(url('home'));
    }
    if ($reportVisibility === 'confidential' && (int) $report['declarant_id'] !== $userId) {
        // In confidential mode, agent can ONLY see their own reports — not even the title
        setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
        redirect(url('home'));
    }
    if ($reportVisibility === 'agent_choice' && (int) $report['is_confidential'] === 1 && (int) $report['declarant_id'] !== $userId) {
        // In agent_choice mode, agent cannot see other agents' confidential reports
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
$responses = getReportResponses($pdo, $uuid);

require __DIR__ . '/../templates/alert.php';
require __DIR__ . '/../templates/report_card.php';
