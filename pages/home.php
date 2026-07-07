<?php
/**
 * Home Page — Application SST DREETS BFC
 *
 * Dashboard with 3 registry cards showing counts and links.
 * Includes a contextual welcome banner for first-time guidance.
 */
$pageTitle = 'Accueil';

$pdo = getDB();
$user = currentUser();
$userSiteId = (int) $user['site_id'];
$agentVisibility = getReportVisibility();
$seeAllSites = canSeeAllSites();
$activeSiteCount = countActiveSites($pdo);
$multiSite = $activeSiteCount > 1;
$noSiteMode = isNoSiteMode($pdo);

// Get counts for each registry type based on report visibility
$userId = (int) $user['id'];
$rsstCount = 0;
$ramiCount = 0;
$dgiCount  = 0;

if ($agentVisibility === 'confidential') {
    $rsstCount = countActiveReportsForUser($pdo, TYPE_RSST, $userId);
    if (isRegistryEnabled(TYPE_RAMI)) $ramiCount = countActiveReportsForUser($pdo, TYPE_RAMI, $userId);
    if (isRegistryEnabled(TYPE_DGI))  $dgiCount  = countActiveReportsForUser($pdo, TYPE_DGI, $userId);
} elseif ($agentVisibility === 'agent_choice') {
    $rsstCount = countActiveReports($pdo, TYPE_RSST, $userSiteId, $userId, true);
    if (isRegistryEnabled(TYPE_RAMI)) $ramiCount = countActiveReports($pdo, TYPE_RAMI, $userSiteId, $userId, true);
    if (isRegistryEnabled(TYPE_DGI))  $dgiCount  = countActiveReports($pdo, TYPE_DGI, $userSiteId, $userId, true);
} else {
    $siteIdFilter = $seeAllSites ? 0 : $userSiteId;
    $rsstCount = countActiveReports($pdo, TYPE_RSST, $siteIdFilter);
    if (isRegistryEnabled(TYPE_RAMI)) $ramiCount = countActiveReports($pdo, TYPE_RAMI, $siteIdFilter);
    if (isRegistryEnabled(TYPE_DGI))  $dgiCount  = countActiveReports($pdo, TYPE_DGI, $siteIdFilter);
}

$totalReports = $rsstCount + $ramiCount + $dgiCount;
$userRole = $user['role'] ?? ROLE_AGENT;
$labelUnite = getConfig('app_label_unite', 'UR');
$roleLabels = getRoleLabels();
$ramiEnabled = isRegistryEnabled(TYPE_RAMI);
$dgiEnabled = isRegistryEnabled(TYPE_DGI);
?>
<?php if ($userRole === ROLE_AGENT): ?>
<h1 class="home-welcome-heading">Bonjour, <?php echo e($user['prenom']); ?></h1>
<p class="home-welcome-subtitle">Que souhaitez-vous faire ?</p>

<div class="registry-cards registry-cards--large">
    <!-- RSST Card -->
    <div class="registry-card registry-card--rsst home-action--large">
        <div>
            <div class="registry-card__icon">&#x1F4CB;</div>
            <div class="registry-card__title">Registre de Santé et de Sécurité au Travail</div>
            <div class="registry-card__subtitle">RSST</div>
            <p class="registry-card__desc"><?php echo e(getConfig('app_rsst_description', 'Risques liés aux locaux, équipements, ergonomie, conditions environnementales')); ?></p>
        </div>
        <div>
            <a href="<?php echo url('report_create', ['type' => TYPE_RSST]); ?>" class="registry-card__btn">Déposer un signalement</a>
            <a href="<?php echo url('report_list', ['type' => TYPE_RSST]); ?>" class="registry-card__link">Voir les signalements</a>
            <div class="registry-card__stat"><?php echo $rsstCount; ?> signalement<?php echo $rsstCount !== 1 ? 's' : ''; ?> enregistré<?php echo $rsstCount !== 1 ? 's' : ''; ?></div>
        </div>
    </div>

    <?php if ($ramiEnabled): ?>
    <!-- RAMI Card -->
    <div class="registry-card registry-card--rami home-action--large">
        <div>
            <div class="registry-card__icon">&#x26A0;&#xFE0F;</div>
            <div class="registry-card__title">Registre des Actes d'Agressions, de Menaces et d'Incivilités</div>
            <div class="registry-card__subtitle">RAMI</div>
            <p class="registry-card__desc">Agressions physiques ou verbales, menaces, incivilités, harcèlement</p>
        </div>
        <div>
            <a href="<?php echo url('report_create', ['type' => TYPE_RAMI]); ?>" class="registry-card__btn">Signaler une agression</a>
            <a href="<?php echo url('report_list', ['type' => TYPE_RAMI]); ?>" class="registry-card__link">Voir les signalements</a>
            <div class="registry-card__stat"><?php echo $ramiCount; ?> signalement<?php echo $ramiCount !== 1 ? 's' : ''; ?> enregistré<?php echo $ramiCount !== 1 ? 's' : ''; ?></div>
    </div>
</div>
<?php endif; ?>
<?php if ($rsstCount > 0 && hasRole(ROLE_AGENT)): ?>
<?php $wordCloud = buildWordCloud($pdo, TYPE_RSST); ?>
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
            Aucun signalement n'a encore été enregistré.<?php if (!$noSiteMode): ?> Les agents de vos <?php echo e($labelUnite); ?>s pourront créer des signalements dans les<?php if ($ramiEnabled || $dgiEnabled): ?> trois<?php else: ?> différents<?php endif; ?> registres ci-dessous.<?php endif; ?>
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
            <p class="registry-card__desc"><?php echo e(getConfig('app_rsst_description', 'Risques liés aux locaux, équipements, ergonomie, conditions environnementales')); ?></p>
        </div>
        <div>
            <a href="<?php echo url('report_create', ['type' => TYPE_RSST]); ?>" class="registry-card__btn">Déposer un signalement</a>
            <a href="<?php echo url('report_list', ['type' => TYPE_RSST]); ?>" class="registry-card__link">Voir les signalements</a>
            <div class="registry-card__stat"><?php echo $rsstCount; ?> signalement<?php echo $rsstCount !== 1 ? 's' : ''; ?> enregistré<?php echo $rsstCount !== 1 ? 's' : ''; ?></div>
        </div>
    </div>

    <?php if ($ramiEnabled): ?>
    <!-- RAMI Card -->
    <div class="registry-card registry-card--rami">
        <div>
            <div class="registry-card__icon">&#x26A0;&#xFE0F;</div>
            <div class="registry-card__title">Registre des Actes d'Agressions, de Menaces et d'Incivilités</div>
            <div class="registry-card__subtitle">RAMI</div>
            <p class="registry-card__desc">Agressions physiques ou verbales, menaces, incivilités, harcèlement</p>
        </div>
        <div>
            <a href="<?php echo url('report_create', ['type' => TYPE_RAMI]); ?>" class="registry-card__btn">Signaler une agression</a>
            <a href="<?php echo url('report_list', ['type' => TYPE_RAMI]); ?>" class="registry-card__link">Voir les signalements</a>
            <div class="registry-card__stat"><?php echo $ramiCount; ?> signalement<?php echo $ramiCount !== 1 ? 's' : ''; ?> enregistré<?php echo $ramiCount !== 1 ? 's' : ''; ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($dgiEnabled): ?>
    <!-- DGI Card -->
    <div class="registry-card registry-card--dgi">
        <div>
            <div class="registry-card__icon">&#x1F534;</div>
            <div class="registry-card__title">Registre de signalement d'un Danger Grave et Imminent</div>
            <div class="registry-card__subtitle">DGI</div>
            <p class="registry-card__desc">Danger nécessitant une action immédiate, droit de retrait</p>
        </div>
        <div>
            <a href="<?php echo url('report_create', ['type' => TYPE_DGI]); ?>" class="registry-card__btn">Signaler un danger urgent</a>
            <a href="<?php echo url('report_list', ['type' => TYPE_DGI]); ?>" class="registry-card__link">Voir les signalements</a>
            <div class="registry-card__stat"><?php echo $dgiCount; ?> signalement<?php echo $dgiCount !== 1 ? 's' : ''; ?> enregistré<?php echo $dgiCount !== 1 ? 's' : ''; ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (canSeeAllSites()): ?>
<div class="card mt-6">
    <h3 class="card__subtitle">Accès rapide superviseur</h3>
    <div class="quick-access">
        <a href="<?php echo url('synthesis'); ?>" class="btn btn--outline">&#x1F4CA; Synthèse</a>
        <a href="<?php echo url('statistics'); ?>" class="btn btn--outline">&#x1F4C8; Statistiques</a>
        <a href="<?php echo url('export'); ?>" class="btn btn--outline">&#x1F4E5; Export</a>
        <?php if (hasRole(ROLE_SUPERVISEUR)): ?>
        <a href="<?php echo url('users'); ?>" class="btn btn--outline">&#x1F465; Utilisateurs</a>
        <a href="<?php echo url('settings'); ?>" class="btn btn--outline">&#x2699;&#xFE0F; Paramètres</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
