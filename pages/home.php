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
/** @var string */
$siteIdStr = $user['site_id'] ?? '0';
$userSiteId = (int) $siteIdStr;
$agentVisibility = new \App\Services\AccessService()->getReportVisibility(null);
$seeAllSites = new \App\Services\AccessService()->canSeeAllSites();
$noSiteMode = $config->isNoSiteMode();

/** @var string */
$userIdStr = $user['id'] ?? '0';
$userId = (int) $userIdStr;
$rsstCount = 0;
$ramiCount = 0;
$dgiCount  = 0;

if ($agentVisibility === \App\Enum\VisibilityMode::Confidential->value || $agentVisibility === \App\Enum\VisibilityMode::AgentChoice->value) {
    $rsstCount = \App\Repository\ReportRepository::instance()->countVisibleForAgent(\App\Enum\ReportType::Rsst->value, $userId, $userSiteId, $agentVisibility);
    if ($config->isRegistryEnabled(\App\Enum\ReportType::Rami->value)) {
        $ramiCount = \App\Repository\ReportRepository::instance()->countVisibleForAgent(\App\Enum\ReportType::Rami->value, $userId, $userSiteId, $agentVisibility);
    }
    if ($config->isRegistryEnabled(\App\Enum\ReportType::Dgi->value)) {
        $dgiCount = \App\Repository\ReportRepository::instance()->countVisibleForAgent(\App\Enum\ReportType::Dgi->value, $userId, $userSiteId, $agentVisibility);
    }
} else {
    $siteIdFilter = $seeAllSites ? 0 : $userSiteId;
    $rsstCount = \App\Repository\ReportRepository::instance()->countActive(\App\Enum\ReportType::Rsst->value, $siteIdFilter);
    if ($config->isRegistryEnabled(\App\Enum\ReportType::Rami->value)) {
        $ramiCount = \App\Repository\ReportRepository::instance()->countActive(\App\Enum\ReportType::Rami->value, $siteIdFilter);
    }
    if ($config->isRegistryEnabled(\App\Enum\ReportType::Dgi->value)) {
        $dgiCount = \App\Repository\ReportRepository::instance()->countActive(\App\Enum\ReportType::Dgi->value, $siteIdFilter);
    }
}

$totalReports = $rsstCount + $ramiCount + $dgiCount;
$userRole = $user['role'] ?? ROLE_AGENT;
$labelUnite = $config->get('app_label_unite', 'UR');
$ramiEnabled = $config->isRegistryEnabled(\App\Enum\ReportType::Rami->value);
$dgiEnabled = $config->isRegistryEnabled(\App\Enum\ReportType::Dgi->value);

// Word cloud — ALL roles, integrated inside RSST card
$wordCloud = $fmt->buildWordCloud();

$cards = buildRegistryCards($rsstCount, $ramiCount, $dgiCount, $ramiEnabled, $dgiEnabled);
$extraContentMap = [];
if (!empty($wordCloud)) {
    $extraContentMap[\App\Enum\ReportType::Rsst->value] = $wordCloud;
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
