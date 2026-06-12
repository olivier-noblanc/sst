<?php
/**
 * Home Page — Application SST DREETS BFC
 * 
 * Dashboard with 3 registry cards showing counts and links.
 * Includes a contextual welcome banner for first-time guidance.
 */
$pageTitle = 'Accueil';

$pdo = getDB();
$user = $_SESSION['user'];
$userSiteId = (int) $user['site_id'];
$agentVisibility = getReportVisibility();
$seeAllSites = canSeeAllSites();

// Get counts for each registry type based on report visibility
$userId = (int) $user['id'];
if ($agentVisibility === 'confidential') {
    $rsstCount = countActiveReportsForUser($pdo, 'rsst', $userId);
    $ramiCount = countActiveReportsForUser($pdo, 'rami', $userId);
    $dgiCount  = countActiveReportsForUser($pdo, 'dgi', $userId);
} elseif ($agentVisibility === 'agent_choice') {
    $rsstCount = countActiveReports($pdo, 'rsst', $userSiteId, $userId, true);
    $ramiCount = countActiveReports($pdo, 'rami', $userSiteId, $userId, true);
    $dgiCount  = countActiveReports($pdo, 'dgi', $userSiteId, $userId, true);
} else {
    $siteIdFilter = $seeAllSites ? 0 : $userSiteId;
    $rsstCount = countActiveReports($pdo, 'rsst', $siteIdFilter);
    $ramiCount = countActiveReports($pdo, 'rami', $siteIdFilter);
    $dgiCount  = countActiveReports($pdo, 'dgi', $siteIdFilter);
}

$totalReports = $rsstCount + $ramiCount + $dgiCount;
$userRole = $user['role'] ?? 'agent';
$labelUnite = getConfig('app_label_unite', 'UR');
?>

<h1 class="page-title">Accueil</h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<!-- Welcome banner — contextual guidance based on user state -->
<?php if ($totalReports === 0 && $userRole === 'agent'): ?>
<div class="welcome-banner welcome-banner--new" role="status">
    <div class="welcome-banner__content">
        <h2 class="welcome-banner__title">Bienvenue dans l'Application SST</h2>
        <p class="welcome-banner__text">
            Vous pouvez ici <strong>signaler un événement</strong> lié à la santé, la sécurité ou l'intégrité au travail.
            Choisissez le registre correspondant à votre situation ci-dessous, puis cliquez sur <strong>« Inscrire un signalement »</strong>.
        </p>
        <div class="welcome-banner__legend">
            <span class="badge badge--nouveau">Nouveau</span> <span class="welcome-banner__legend-text">Signalement créé, en attente de traitement</span>
            <span class="badge badge--en-cours">En cours</span> <span class="welcome-banner__legend-text">Prise en charge par un superviseur</span>
            <span class="badge badge--traite">Traité</span> <span class="welcome-banner__legend-text">Signalement clôturé</span>
        </div>
        <a href="<?php echo url('help'); ?>" class="welcome-banner__link">Consulter la documentation</a>
    </div>
</div>
<?php elseif ($totalReports === 0 && in_array($userRole, ['superviseur', 'chsct'])): ?>
<div class="welcome-banner" role="status">
    <div class="welcome-banner__content">
        <h2 class="welcome-banner__title">Bienvenue dans l'Application SST</h2>
        <p class="welcome-banner__text">
            Aucun signalement n'a encore été enregistré. Les agents de vos <?php echo e($labelUnite); ?>s pourront créer des signalements dans les trois registres ci-dessous.
            En tant que <strong><?php echo e(ROLE_LABELS[$userRole] ?? $userRole); ?></strong>, vous pourrez les consulter, les filtrer et y répondre.
        </p>
        <a href="<?php echo url('help'); ?>" class="welcome-banner__link">Consulter la documentation</a>
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

<div class="registry-cards">
    <!-- RSST Card -->
    <div class="registry-card registry-card--rsst">
        <div>
            <div class="registry-card__icon">&#x1F4CB;</div>
            <div class="registry-card__title">Registre de Santé et de Sécurité au Travail</div>
            <div class="registry-card__subtitle">RSST</div>
            <p class="registry-card__desc">Risques liés aux locaux, équipements, ergonomie, conditions environnementales</p>
        </div>
        <div>
            <a href="<?php echo url('report_create', ['type' => 'rsst']); ?>" class="registry-card__btn">Inscrire un signalement</a>
            <a href="<?php echo url('report_list', ['type' => 'rsst']); ?>" class="registry-card__link">Voir les signalements</a>
            <div class="registry-card__stat"><?php echo $rsstCount; ?> signalement<?php echo $rsstCount !== 1 ? 's' : ''; ?> enregistré<?php echo $rsstCount !== 1 ? 's' : ''; ?></div>
        </div>
    </div>

    <!-- RAMI Card -->
    <div class="registry-card registry-card--rami">
        <div>
            <div class="registry-card__icon">&#x26A0;&#xFE0F;</div>
            <div class="registry-card__title">Registre des Actes d'Agressions, de Menaces et d'Incivilités</div>
            <div class="registry-card__subtitle">RAMI</div>
            <p class="registry-card__desc">Agressions physiques ou verbales, menaces, incivilités, harcèlement</p>
        </div>
        <div>
            <a href="<?php echo url('report_create', ['type' => 'rami']); ?>" class="registry-card__btn">Inscrire un signalement</a>
            <a href="<?php echo url('report_list', ['type' => 'rami']); ?>" class="registry-card__link">Voir les signalements</a>
            <div class="registry-card__stat"><?php echo $ramiCount; ?> signalement<?php echo $ramiCount !== 1 ? 's' : ''; ?> enregistré<?php echo $ramiCount !== 1 ? 's' : ''; ?></div>
        </div>
    </div>

    <!-- DGI Card -->
    <div class="registry-card registry-card--dgi">
        <div>
            <div class="registry-card__icon">&#x1F534;</div>
            <div class="registry-card__title">Registre de signalement d'un Danger Grave et Imminent</div>
            <div class="registry-card__subtitle">DGI</div>
            <p class="registry-card__desc">Danger nécessitant une action immédiate, droit de retrait</p>
        </div>
        <div>
            <a href="<?php echo url('report_create', ['type' => 'dgi']); ?>" class="registry-card__btn">Inscrire un signalement</a>
            <a href="<?php echo url('report_list', ['type' => 'dgi']); ?>" class="registry-card__link">Voir les signalements</a>
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
