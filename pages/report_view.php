<?php
/**
 * Report View Page — Application SST DREETS BFC
 *
 * Displays a single report with all details, response history, and action buttons.
 * URL: index.php?page=report_view&uuid={report_uuid}
 */
$uuid = $_GET['uuid'] ?? '';
$report = fetchReportOrRedirect($uuid);

// Access control: centralized via canAccessReport()
$user = currentUser();

if (!canAccessReport($report, $user)) {
    setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
    redirect(url('home'));
}

// Log confidential report access by supervisor/CHSCT
$pdo = getDB();
logConfidentialReportAccess($pdo, $report, $user);

// If report is abandoned and user is not declarant nor supervisor/chsct
if ($report['etat'] === 'abandonne' && (int) $report['declarant_id'] !== (int) $user['id'] && !in_array($user['role'], ['superviseur', 'chsct'])) {
    setFlash('warning', 'Ce signalement a été abandonné.');
}

$pageTitle = 'Signalement — ' . $report['reference'];

// Get response history
$responses = getReportResponses($pdo, $uuid);

// Breadcrumb data
$reportType = $report['type'] ?? 'rsst';
$reportShortLabel = REGISTRY_SHORT_LABELS[$reportType] ?? strtoupper($reportType);
?>

<?php echo renderBreadcrumb([
    ['url' => url('home'), 'label' => 'Accueil'],
    ['url' => url('report_list', ['type' => $reportType]), 'label' => $reportShortLabel],
    ['label' => $report['reference']],
]); ?>

<?php
require __DIR__ . '/../templates/report_card.php';
