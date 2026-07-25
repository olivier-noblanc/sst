<?php
/**
 * Report View Page — Application SST DREETS BFC
 *
 * Displays a single report with all details, response history, and action buttons.
 * URL: index.php?page=report_view&uuid={report_uuid}
 */
/** @var string */
$uuid = $_GET['uuid'] ?? '';
$report = fetchReportOrRedirect($uuid);

// Access control: centralized via canAccessReport()
$user = currentUser();
if ($user === null) {
    setFlash('error', 'Accès refusé.');
    redirect(url('home'));
    exit;
}

if (!canAccessReport($report, $user)) {
    setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
    redirect(url('home'));
}

// Log confidential report access by supervisor/CHSCT
$pdo = getDB();
logConfidentialReportAccess($pdo, $report, $user);

// If report is abandoned and user is not declarant nor supervisor/chsct
/** @var string */
$declarantIdRaw = $report['declarant_id'] ?? '0';
/** @var string */
$userIdRaw = $user['id'] ?? '0';
/** @var string */
$userRole = $user['role'] ?? '';
if ($report['etat'] === \App\Enum\ReportState::Abandonne->value && (int) $declarantIdRaw !== (int) $userIdRaw && !in_array($userRole, [\App\Enum\UserRole::Superviseur->value, \App\Enum\UserRole::Chsct->value], true)) {
    setFlash('warning', 'Ce signalement a été abandonné.');
}

/** @var string */
$reference = $report['reference'] ?? '';
$pageTitle = 'Signalement — ' . $reference;

// Get response history
$responses = \App\Repository\ReportRepository::instance()->getResponses($uuid);

// Get linked agents and pending invites (moved from template to avoid DB queries in presentation layer)
/** @var string */
$reportUuid = $report['uuid'] ?? '';
$linkedAgents = \App\Repository\ReportRepository::instance()->getLinkedAgents($reportUuid);
$pendingInvites = \App\Repository\ReportRepository::instance()->getPendingInvites($reportUuid);

// Breadcrumb data
/** @var string */
$reportType = $report['type'] ?? \App\Enum\ReportType::Rsst->value;
$reportShortLabel = getRegistryShortLabel($reportType);
?>

<?php echo renderBreadcrumb([
    ['url' => url('home'), 'label' => 'Accueil'],
    ['url' => url('report_list', ['type' => $reportType]), 'label' => $reportShortLabel],
    ['label' => $reference],
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
            Votre signalement <strong><?php echo e($reference); ?></strong> a été enregistré dans le registre <?php echo e($reportShortLabel); ?>.
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
    $adjacent = \App\Repository\ReportRepository::instance()->getAdjacentUuids($report);
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
