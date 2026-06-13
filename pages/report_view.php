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

// Access control: centralized via canAccessReport()
$user = $_SESSION['user'];

if (!canAccessReport($report, $user)) {
    setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
    redirect(url('home'));
}

// Log confidential report access by supervisor/CHSCT
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

<nav class="breadcrumb" aria-label="Fil d'Ariane">
    <a href="<?php echo url('home'); ?>" class="breadcrumb__item">Accueil</a>
    <span class="breadcrumb__separator">/</span>
    <a href="<?php echo url('report_list', ['type' => $reportType]); ?>" class="breadcrumb__item"><?php echo e($reportShortLabel); ?></a>
    <span class="breadcrumb__separator">/</span>
    <span class="breadcrumb__current"><?php echo e($report['reference']); ?></span>
</nav>

<?php
require __DIR__ . '/../templates/alert.php';
require __DIR__ . '/../templates/report_card.php';
