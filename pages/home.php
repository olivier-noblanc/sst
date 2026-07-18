<?php
/**
 * Home Page — Application SST DREETS BFC
 *
 * Dashboard with registry cards and word cloud for all roles.
 */
$pageTitle = 'Accueil';

$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = \App\Services\ConfigService::getInstance();

$pdo = getContainer()->get(\PDO::class);
$user = new \App\Services\SessionService()->getUserSession();
$userSiteId = (int) $user['site_id'];
$agentVisibility = new \App\Services\AccessService()->getReportVisibility(null);
$seeAllSites = new \App\Services\AccessService()->canSeeAllSites();
$noSiteMode = $config->isNoSiteMode();

$userId = (int) $user['id'];
$rsstCount = 0;
$ramiCount = 0;
$dgiCount  = 0;

if ($agentVisibility === 'confidential') {
    $rsstCount = \App\Repository\ReportRepository::instance()->countActiveForUser(TYPE_RSST, $userId);
    if ($config->isRegistryEnabled(TYPE_RAMI)) $ramiCount = \App\Repository\ReportRepository::instance()->countActiveForUser(TYPE_RAMI, $userId);
    if ($config->isRegistryEnabled(TYPE_DGI)) $dgiCount = \App\Repository\ReportRepository::instance()->countActiveForUser(TYPE_DGI, $userId);
} elseif ($agentVisibility === 'agent_choice') {
    $rsstCount = \App\Repository\ReportRepository::instance()->countActive(TYPE_RSST, $userSiteId, $userId, true);
    if ($config->isRegistryEnabled(TYPE_RAMI)) $ramiCount = \App\Repository\ReportRepository::instance()->countActive(TYPE_RAMI, $userSiteId, $userId, true);
    if ($config->isRegistryEnabled(TYPE_DGI)) $dgiCount = \App\Repository\ReportRepository::instance()->countActive(TYPE_DGI, $userSiteId, $userId, true);
} else {
    $siteIdFilter = $seeAllSites ? 0 : $userSiteId;
    $rsstCount = \App\Repository\ReportRepository::instance()->countActive(TYPE_RSST, $siteIdFilter);
    if ($config->isRegistryEnabled(TYPE_RAMI)) $ramiCount = \App\Repository\ReportRepository::instance()->countActive(TYPE_RAMI, $siteIdFilter);
    if ($config->isRegistryEnabled(TYPE_DGI)) $dgiCount = \App\Repository\ReportRepository::instance()->countActive(TYPE_DGI, $siteIdFilter);
}

$totalReports = $rsstCount + $ramiCount + $dgiCount;
$userRole = $user['role'] ?? ROLE_AGENT;
$labelUnite = $config->get('app_label_unite', 'UR');
$ramiEnabled = $config->isRegistryEnabled(TYPE_RAMI);
$dgiEnabled = $config->isRegistryEnabled(TYPE_DGI);

// Word cloud — ALL roles, integrated inside RSST card
$wordCloud = $fmt->buildWordCloud();

$cards = buildRegistryCards($rsstCount, $ramiCount, $dgiCount, $ramiEnabled, $dgiEnabled);
$extraContentMap = [];
if (!empty($wordCloud)) {
    $extraContentMap['rsst'] = $wordCloud;
}
?>

<h1 class="page-title page-title--compact">Accueil</h1>

<?php if ($totalReports === 0): ?>
<div class="welcome-banner" role="status">
    <div class="welcome-banner__content">
        <h2 class="welcome-banner__title">Bienvenue dans l'Application SST</h2>
        <p class="welcome-banner__text">Aucun signalement n'a encore été enregistré.</p>
        <a href="<?php echo $http->url('help'); ?>" class="welcome-banner__link">Consulter la documentation</a>
    </div>
</div>
<?php endif; ?>

<?php if ($totalReports > 0): ?>
<div class="workflow-legend" role="complementary" aria-label="Légende des états">
    <span class="workflow-legend__item"><span class="badge badge--nouveau">Nouveau</span><span class="workflow-legend__text">En attente</span></span>
    <span class="workflow-legend__arrow" aria-hidden="true">&#x2192;</span>
    <span class="workflow-legend__item"><span class="badge badge--en-cours">En cours</span><span class="workflow-legend__text">Pris en charge</span></span>
    <span class="workflow-legend__arrow" aria-hidden="true">&#x2192;</span>
    <span class="workflow-legend__item"><span class="badge badge--traite">Traité</span><span class="workflow-legend__text">Clôturé</span></span>
    <span class="workflow-legend__item workflow-legend__item--muted"><span class="badge badge--abandonne">Abandonné</span><span class="workflow-legend__text">Non poursuivi</span></span>
</div>
<?php endif; ?>

<?php echo renderRegistryCards($cards, 'compact', $extraContentMap); ?>
