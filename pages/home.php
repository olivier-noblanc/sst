<?php
/**
 * Home Page — Application SST DREETS BFC
 *
 * Dashboard with 3 registry cards showing counts and links.
 * Includes a contextual welcome banner for first-time guidance.
 */
$pageTitle = 'Accueil';

$pdo = getContainer()->get(\PDO::class);
$user = (new \App\Services\SessionService())->getUserSession();
$userSiteId = (int) $user['site_id'];
$agentVisibility = (new \App\Services\AccessService())->getReportVisibility();
$seeAllSites = (new \App\Services\AccessService())->canSeeAllSites();
$activeSiteCount = \App\Services\ConfigService::getInstance()->countActiveSites();
$multiSite = $activeSiteCount > 1;
$noSiteMode = \App\Services\ConfigService::getInstance()->isNoSiteMode();

// Get counts for each registry type based on report visibility
$userId = (int) $user['id'];
$rsstCount = 0;
$ramiCount = 0;
$dgiCount  = 0;

if ($agentVisibility === 'confidential') {
    $rsstCount = \App\Repository\ReportRepository::instance()->countActiveForUser(TYPE_RSST, $userId);
    if (\App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_RAMI)) {
        $ramiCount = \App\Repository\ReportRepository::instance()->countActiveForUser(TYPE_RAMI, $userId);
    }
    if (\App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_DGI)) {
        $dgiCount  = \App\Repository\ReportRepository::instance()->countActiveForUser(TYPE_DGI, $userId);
    }
} elseif ($agentVisibility === 'agent_choice') {
    $rsstCount = \App\Repository\ReportRepository::instance()->countActive(TYPE_RSST, $userSiteId, $userId, true);
    if (\App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_RAMI)) {
        $ramiCount = \App\Repository\ReportRepository::instance()->countActive(TYPE_RAMI, $userSiteId, $userId, true);
    }
    if (\App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_DGI)) {
        $dgiCount  = \App\Repository\ReportRepository::instance()->countActive(TYPE_DGI, $userSiteId, $userId, true);
    }
} else {
    $siteIdFilter = $seeAllSites ? 0 : $userSiteId;
    $rsstCount = \App\Repository\ReportRepository::instance()->countActive(TYPE_RSST, $siteIdFilter);
    if (\App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_RAMI)) {
        $ramiCount = \App\Repository\ReportRepository::instance()->countActive(TYPE_RAMI, $siteIdFilter);
    }
    if (\App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_DGI)) {
        $dgiCount  = \App\Repository\ReportRepository::instance()->countActive(TYPE_DGI, $siteIdFilter);
    }
}

$totalReports = $rsstCount + $ramiCount + $dgiCount;
$userRole = $user['role'] ?? ROLE_AGENT;
$labelUnite = \App\Services\ConfigService::getInstance()->get('app_label_unite', 'UR');
$roleLabels = \App\Services\ConfigService::getInstance()->getRoleLabels();
$ramiEnabled = \App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_RAMI);
$dgiEnabled = \App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_DGI);
?>
<?php if ($userRole === ROLE_AGENT): ?>
<h1 class="home-welcome-heading">Bonjour, <?php echo (new \App\Services\FormattingService())->e($user['prenom']); ?></h1>
<p class="home-welcome-subtitle">Que souhaitez-vous faire ?</p>

<?php $cards = buildRegistryCards($rsstCount, $ramiCount, $dgiCount, $ramiEnabled, $dgiEnabled); ?>
<?php echo renderRegistryCards($cards, 'large'); ?>
<?php if ($rsstCount > 0 && hasRole(ROLE_AGENT)): ?>
<?php $wordCloud = (new \App\Services\FormattingService())->buildWordCloud($pdo, TYPE_RSST); ?>
<?php if (!empty($wordCloud)): ?>
<div class="card mt-4">
    <h3 class="card__subtitle">Nuage de mots — RSST</h3>
    <p class="text-muted text-small mb-3">Mots les plus fréquents dans les signalements RSST.</p>
    <?php echo $wordCloud; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php else: ?>
<h1 class="page-title page-title--compact">Accueil</h1>

<?php if ($totalReports === 0 && in_array($userRole, [ROLE_SUPERVISEUR, ROLE_CHSCT])): ?>
<div class="welcome-banner" role="status">
    <div class="welcome-banner__content">
        <h2 class="welcome-banner__title">Bienvenue dans l'Application SST</h2>
        <p class="welcome-banner__text">
            Aucun signalement n'a encore été enregistré.<?php if (!$noSiteMode): ?> Les agents de vos <?php echo (new \App\Services\FormattingService())->e($labelUnite); ?>s pourront créer des signalements dans les<?php if ($ramiEnabled || $dgiEnabled): ?> trois<?php else: ?> différents<?php endif; ?> registres ci-dessous.<?php endif; ?>
            En tant que <strong><?php echo (new \App\Services\FormattingService())->e(ROLE_LABELS[$userRole] ?? $userRole); ?></strong>, vous pourrez les consulter, les filtrer et y répondre.
        </p>
        <a href="<?php echo (new \App\Services\HttpService())->url('help'); ?>" class="welcome-banner__link">Consulter la documentation</a>
    </div>
</div>
<?php endif; ?>

<!-- Workflow legend — always visible for quick reference -->
<?php if ($totalReports > 0): ?>
<div class="workflow-legend" role="complementary" aria-label="Légende des états">
    <span class="workflow-legend__item">
        <span class="badge badge--nouveau">Nouveau</span>
        <span class="workflow-legend__text">En attente</span>
    </span>
    <span class="workflow-legend__arrow" aria-hidden="true">&#x2192;</span>
    <span class="workflow-legend__item">
        <span class="badge badge--en-cours">En cours</span>
        <span class="workflow-legend__text">Pris en charge</span>
    </span>
    <span class="workflow-legend__arrow" aria-hidden="true">&#x2192;</span>
    <span class="workflow-legend__item">
        <span class="badge badge--traite">Traité</span>
        <span class="workflow-legend__text">Clôturé</span>
    </span>
    <span class="workflow-legend__item workflow-legend__item--muted">
        <span class="badge badge--abandonne">Abandonné</span>
        <span class="workflow-legend__text">Non poursuivi</span>
    </span>
</div>
<?php endif; ?>

<?php $cards = buildRegistryCards($rsstCount, $ramiCount, $dgiCount, $ramiEnabled, $dgiEnabled); ?>
<?php echo renderRegistryCards($cards, 'compact'); ?>
<?php endif; ?>

<?php if ((new \App\Services\AccessService())->canSeeAllSites()): ?>
<div class="card mt-6">
    <h3 class="card__subtitle">Accès rapide superviseur</h3>
    <div class="quick-access">
        <a href="<?php echo (new \App\Services\HttpService())->url('synthesis'); ?>" class="btn btn--outline">&#x1F4CA; Synthèse</a>
        <a href="<?php echo (new \App\Services\HttpService())->url('statistics'); ?>" class="btn btn--outline">&#x1F4C8; Statistiques</a>
        <a href="<?php echo (new \App\Services\HttpService())->url('export'); ?>" class="btn btn--outline">&#x1F4E5; Export</a>
        <?php if (hasRole(ROLE_SUPERVISEUR)): ?>
        <a href="<?php echo (new \App\Services\HttpService())->url('users'); ?>" class="btn btn--outline">&#x1F465; Utilisateurs</a>
        <a href="<?php echo (new \App\Services\HttpService())->url('settings'); ?>" class="btn btn--outline">&#x2699;&#xFE0F; Paramètres</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
