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

// Get linked agents and pending invites (moved from template to avoid DB queries in presentation layer)
$linkedAgents = \App\Repository\ReportRepository::instance()->getLinkedAgents((string) $report['uuid']);
$pendingInvites = \App\Repository\ReportRepository::instance()->getPendingInvites((string) $report['uuid']);

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
// Big confirmation banner after report creation
$justCreated = !empty($_SESSION['report_created']);
unset($_SESSION['report_created']);
if ($justCreated):
    ?>
<div class="confirmation-banner" role="status" aria-live="polite">
    <div class="confirmation-banner__icon" aria-hidden="true">&#x2705;</div>
    <div class="confirmation-banner__content">
        <h2 class="confirmation-banner__title">Signalement bien enregistré !</h2>
        <p class="confirmation-banner__text">
            Votre signalement <strong><?php echo e($report['reference']); ?></strong> a été enregistré dans le registre <?php echo e($reportShortLabel); ?>.
            Un superviseur va le prendre en charge.
        </p>
        <p class="confirmation-banner__text">
            Vous pouvez consulter son état à tout moment depuis la liste des signalements.
        </p>
        <div class="confirmation-banner__actions">
            <a href="<?php echo url('home'); ?>" class="btn btn--primary">Retour à l'accueil</a>
            <a href="<?php echo url('report_list', ['type' => $reportType]); ?>" class="btn btn--outline">Voir mes signalements</a>
        </div>
    </div>
</div>
<?php endif; ?>

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
