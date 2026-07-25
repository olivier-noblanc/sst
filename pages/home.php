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
$userRole = $user['role'] ?? \App\Enum\UserRole::Agent->value;
$labelUnite = $config->get('app_label_unite', 'UR');

// Build registry cards dynamically from the database
$cards = buildRegistryCards();
$totalReports = array_sum(array_column($cards, 'count'));

// Word cloud — per registry, integrated inside each registry card
$enabledRegistries = $config->getEnabledRegistries();
$extraContentMap = [];
foreach ($enabledRegistries as $regCode) {
    $wc = $fmt->buildWordCloud($regCode);
    if (!empty($wc)) {
        $extraContentMap[$regCode] = $wc;
    }
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
