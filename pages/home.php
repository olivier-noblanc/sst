<?php
/**
 * Home Page — Application SST DREETS BFC
 *
 * Dashboard with registry cards and word cloud for all roles.
 */
$pageTitle = 'Accueil';

$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = getConfigService();

$pdo = getContainer()->get(\PDO::class);
$user = new \App\Services\SessionService()->getUserSession();
$userRole = $user->role ?? \App\Enum\UserRole::Agent->value;
$labelUnite = $config->get('app_label_unite', 'UR');

// Build registry cards dynamically from the database
$cards = buildRegistryCards();
$totalReports = array_sum(array_map(fn($c) => $c->count, $cards));

// Word cloud — per registry, integrated inside each registry card
$enabledRegistries = $config->getEnabledRegistries();
$extraContentMap = [];
foreach ($enabledRegistries as $regCode) {
    $wc = $fmt->buildWordCloud($regCode);
    if (!empty($wc)) {
        $extraContentMap[$regCode] = $wc;
    }
}

// An agent never sees the “signalements enregistrés” counter on their home
// (same rule as the registry cards): the hero KPI tiles are therefore hidden
// for agents, and only the non-agent roles get the dashboard summary.
$showDashboardStats = !isAgent();
$displayName = currentUserDisplayName();
$orgName = $config->get('app_nom_organisation', 'DREETS BFC');
?>

<section class="home-hero" aria-labelledby="home-hero-title">
    <div class="home-hero__text">
        <p class="home-hero__eyebrow"><?php echo e($orgName); ?> &mdash; Tableau de bord SST</p>
        <h1 class="home-hero__title" id="home-hero-title">Bonjour<?php echo $displayName !== '' ? ', ' . e($displayName) : ''; ?></h1>
        <p class="home-hero__subtitle">Créez, suivez et clôturez les signalements SST de vos registres depuis cet espace.</p>
    </div>
    <?php if ($showDashboardStats): ?>
    <div class="home-hero__stats">
        <div class="home-stat">
            <span class="home-stat__value"><?php echo e((string) $totalReports); ?></span>
            <span class="home-stat__label">Signalements</span>
        </div>
        <div class="home-stat">
            <span class="home-stat__value"><?php echo e((string) count($cards)); ?></span>
            <span class="home-stat__label">Registres actifs</span>
        </div>
    </div>
    <?php endif; ?>
</section>

<?php if ($totalReports === 0): ?>
<div class="welcome-banner" role="status">
    <div class="welcome-banner__content">
        <h2 class="welcome-banner__title">Bienvenue dans l'Application SST</h2>
        <p class="welcome-banner__text">Aucun signalement n'a encore été enregistré.</p>
        <a href="<?php echo $http->url('changelog'); ?>" class="welcome-banner__link">Consulter les nouveautés</a>
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

<h2 class="home-section-title">Vos registres</h2>

<?php echo renderRegistryCards($cards, 'compact', $extraContentMap); ?>
