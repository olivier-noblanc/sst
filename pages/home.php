<?php
/**
 * Home Page — Application SST DREETS BFC
 * 
 * Dashboard with 3 registry cards showing counts and links.
 */
$pageTitle = 'Accueil';

$pdo = getDB();
$user = $_SESSION['user'];
$userSiteId = (int) $user['site_id'];
$agentVisibility = getReportVisibility();
$seeAllSites = canSeeAllSites();

// Get counts for each registry type based on report visibility
// - 'all'           → count across all sites (superviseur/chsct)
// - 'public'        → count all reports for agent's site
// - 'agent_choice'  → count public reports + agent's own reports for site
// - 'confidential'  → count only agent's own reports for site (most restrictive)
$userId = (int) $user['id'];
if ($agentVisibility === 'confidential') {
    // Most restrictive: only own reports
    $rsstCount = countActiveReportsForUser($pdo, 'rsst', $userId);
    $ramiCount = countActiveReportsForUser($pdo, 'rami', $userId);
    $dgiCount  = countActiveReportsForUser($pdo, 'dgi', $userId);
} elseif ($agentVisibility === 'agent_choice') {
    // Agent choice mode: public + own (even confidential)
    $rsstCount = countActiveReports($pdo, 'rsst', $userSiteId, $userId, true);
    $ramiCount = countActiveReports($pdo, 'rami', $userSiteId, $userId, true);
    $dgiCount  = countActiveReports($pdo, 'dgi', $userSiteId, $userId, true);
} else {
    $siteIdFilter = $seeAllSites ? 0 : $userSiteId;
    $rsstCount = countActiveReports($pdo, 'rsst', $siteIdFilter);
    $ramiCount = countActiveReports($pdo, 'rami', $siteIdFilter);
    $dgiCount  = countActiveReports($pdo, 'dgi', $siteIdFilter);
}
?>

<h1 class="page-title">Accueil</h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<div class="registry-cards">
    <!-- RSST Card -->
    <div class="registry-card registry-card--rsst">
        <div>
            <div class="registry-card__icon">&#x1F4CB;</div>
            <div class="registry-card__title">Registre de Santé et de Sécurité au Travail</div>
            <div class="registry-card__subtitle">RSST</div>
        </div>
        <div>
            <a href="<?php echo url('report_create', ['type' => 'rsst']); ?>" class="registry-card__btn">Inscrire un signalement</a>
            <div class="registry-card__stat"><?php echo $rsstCount; ?> signalement<?php echo $rsstCount !== 1 ? 's' : ''; ?> enregistré<?php echo $rsstCount !== 1 ? 's' : ''; ?></div>
        </div>
    </div>

    <!-- RAMI Card -->
    <div class="registry-card registry-card--rami">
        <div>
            <div class="registry-card__icon">&#x26A0;&#xFE0F;</div>
            <div class="registry-card__title">Registre des Actes d'Agressions, de Menaces et d'Incivilités</div>
            <div class="registry-card__subtitle">RAMI</div>
        </div>
        <div>
            <a href="<?php echo url('report_create', ['type' => 'rami']); ?>" class="registry-card__btn">Inscrire un signalement</a>
            <div class="registry-card__stat"><?php echo $ramiCount; ?> signalement<?php echo $ramiCount !== 1 ? 's' : ''; ?> enregistré<?php echo $ramiCount !== 1 ? 's' : ''; ?></div>
        </div>
    </div>

    <!-- DGI Card -->
    <div class="registry-card registry-card--dgi">
        <div>
            <div class="registry-card__icon">&#x1F534;</div>
            <div class="registry-card__title">Registre de signalement d'un Danger Grave et Imminent</div>
            <div class="registry-card__subtitle">DGI</div>
        </div>
        <div>
            <a href="<?php echo url('report_create', ['type' => 'dgi']); ?>" class="registry-card__btn">Inscrire un signalement</a>
            <div class="registry-card__stat"><?php echo $dgiCount; ?> signalement<?php echo $dgiCount !== 1 ? 's' : ''; ?> enregistré<?php echo $dgiCount !== 1 ? 's' : ''; ?></div>
        </div>
    </div>
</div>

<?php if (canSeeAllSites()): ?>
<div class="card" style="margin-top:24px;">
    <h3 class="card__subtitle">Accès rapide superviseur</h3>
    <div class="quick-access">
        <a href="<?php echo url('synthesis'); ?>" class="btn btn--outline">&#x1F4CA; Synthèse</a>
        <a href="<?php echo url('statistics'); ?>" class="btn btn--outline">&#x1F4C8; Statistiques</a>
        <a href="<?php echo url('export'); ?>" class="btn btn--outline">&#x1F4E5; Export</a>
        <?php if (hasRole('superviseur')): ?>
        <a href="<?php echo url('users'); ?>" class="btn btn--outline">&#x1F465; Utilisateurs</a>
        <a href="<?php echo url('settings'); ?>" class="btn btn--outline">&#x2699;&#xFE0F; Paramètres</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
