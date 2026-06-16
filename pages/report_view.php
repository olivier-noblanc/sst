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
if ($report['etat'] === ETAT_ABANDONNE && (int) $report['declarant_id'] !== (int) $user['id'] && !in_array($user['role'], [ROLE_SUPERVISEUR, ROLE_CHSCT])) {
    setFlash('warning', 'Ce signalement a été abandonné.');
}

$pageTitle = 'Signalement — ' . $report['reference'];

// Get response history
$responses = getReportResponses($pdo, $uuid);

// Breadcrumb data
$reportType = $report['type'] ?? TYPE_RSST;
$reportShortLabel = REGISTRY_SHORT_LABELS[$reportType] ?? strtoupper($reportType);
?>

<?php echo renderBreadcrumb([
    ['url' => url('home'), 'label' => 'Accueil'],
    ['url' => url('report_list', ['type' => $reportType]), 'label' => $reportShortLabel],
    ['label' => $report['reference']],
]); ?>

<?php
// Previous/Next navigation for the same registry list
$adjacent = getAdjacentReportUuids($pdo, $report);
?>

<?php
require __DIR__ . '/../templates/report_card.php';
?>

<?php if (!empty($adjacent['prev']) || !empty($adjacent['next'])): ?>
<nav class="report-nav" aria-label="Navigation entre signalements">
    <?php if (!empty($adjacent['prev'])): ?>
    <a href="<?php echo url('report_view', ['uuid' => $adjacent['prev']]); ?>" class="report-nav__link">
        &#8592; Précédent
    </a>
    <?php else: ?>
    <span></span>
    <?php endif; ?>
    <?php if (!empty($adjacent['next'])): ?>
    <a href="<?php echo url('report_view', ['uuid' => $adjacent['next']]); ?>" class="report-nav__link report-nav__link--next">
        Suivant &#8594;
    </a>
    <?php endif; ?>
</nav>
<?php endif; ?>
