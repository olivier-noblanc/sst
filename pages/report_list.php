<?php
/**
 * Report List Page — Application SST DREETS BFC
 *
 * Lists reports for a given registry with filters and pagination.
 * URL: index.php?page=report_list&type={rsst|rami|dgi}
 */
$type = $_GET['type'] ?? '';

// Validate type
if (!in_array($type, ['rsst', 'rami', 'dgi'])) {
    setFlash('error', 'Type de registre invalide.');
    redirect(url('home'));
}

$pageTitle = 'Liste des fiches du registre — ' . (REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type));

$pdo = getDB();
$user = $_SESSION['user'];
$userSiteId = (int) $user['site_id'];
$userId = (int) $user['id'];
$userRole = $user['role'];
$agentVisibility = getReportVisibility();
$seeAllSites = canSeeAllSites();

// Build filters from GET params
$filters = [
    'etat'    => $_GET['etat'] ?? '',
    'site_id' => $_GET['site'] ?? '',
    'q'       => trim($_GET['q'] ?? ''),
];

// Apply agent visibility restrictions
if ($agentVisibility === 'confidential') {
    $filters['force_site_id'] = $userSiteId;
    $filters['own_only'] = $userId;
} elseif ($agentVisibility === 'agent_choice') {
    $filters['force_site_id'] = $userSiteId;
    $filters['confidential_filter'] = $userId;
} elseif ($agentVisibility === 'public') {
    $filters['force_site_id'] = $userSiteId;
}

// Pagination
$pageNum = max(1, (int) ($_GET['p'] ?? 1));
$perPage = ITEMS_PER_PAGE;

// Fetch reports
$result = getReportsByRegistry($pdo, $type, $filters, $userSiteId, $seeAllSites, $pageNum, $perPage);
$reports = $result['reports'];
$totalItems = $result['total'];

// Get all sites for filter dropdown
$allSites = getAllSites($pdo);

// Build base URL for pagination (without &p=)
$baseUrlParams = [
    'page' => 'report_list',
    'type' => $type,
];
if (!empty($filters['etat'])) $baseUrlParams['etat'] = $filters['etat'];
if (!empty($filters['site_id'])) $baseUrlParams['site'] = $filters['site_id'];
if (!empty($filters['q'])) $baseUrlParams['q'] = $filters['q'];
$baseUrl = 'index.php?' . http_build_query($baseUrlParams);
?>

<h1 class="page-title">
    Liste des fiches du registre <?php echo e(REGISTRY_LABELS[$type] ?? strtoupper($type)); ?>
    <a href="<?php echo url('report_create', ['type' => $type]); ?>" class="btn btn--sm btn--primary btn-float-right">+ Nouveau signalement</a>
</h1>

<nav class="breadcrumb" aria-label="Fil d'Ariane">
    <a href="<?php echo url('home'); ?>" class="breadcrumb__item">Accueil</a>
    <span class="breadcrumb__separator">/</span>
    <span class="breadcrumb__current"><?php echo e(REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type)); ?></span>
</nav>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<div class="filter-bar">
    <form method="GET" action="index.php" class="flex flex-wrap gap-4 items-center w-full">
        <input type="hidden" name="page" value="report_list">
        <input type="hidden" name="type" value="<?php echo e($type); ?>">

        <div class="form-group">
            <label for="etat">État</label>
            <select id="etat" name="etat">
                <option value="">Tous</option>
                <option value="nouveau" <?php echo $filters['etat'] === 'nouveau' ? 'selected' : ''; ?>>Nouveau</option>
                <option value="en_cours" <?php echo $filters['etat'] === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                <option value="traite" <?php echo $filters['etat'] === 'traite' ? 'selected' : ''; ?>>Traité</option>
                <option value="abandonne" <?php echo $filters['etat'] === 'abandonne' ? 'selected' : ''; ?>>Abandonné</option>
            </select>
        </div>

        <?php if ($seeAllSites): ?>
        <div class="form-group">
            <label for="site">Site (<?php echo e(getConfig('app_label_unite', 'UR')); ?>)</label>
            <select id="site" name="site">
                <option value="">Tous</option>
                <?php foreach ($allSites as $site): ?>
                <option value="<?php echo e($site['id']); ?>" <?php echo $filters['site_id'] === (string) $site['id'] ? 'selected' : ''; ?>>
                    <?php echo e($site['code'] . ' — ' . $site['nom']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-group filter-search-group">
            <label for="q">Recherche</label>
            <input type="text" id="q" name="q" value="<?php echo e($filters['q']); ?>" placeholder="Rechercher dans l'objet ou la description...">
        </div>

        <button type="submit" class="btn btn--primary">Filtrer</button>
    </form>
</div>

<div class="card">
    <div class="table-wrapper">
        <table aria-label="Liste des signalements <?php echo e(REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type)); ?>">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Date</th>
                    <th>Objet</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th><?php echo e(getConfig('app_label_unite', 'UR')); ?></th>
                    <th>État</th>
                    <th>Visibilité</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                <tr>
                    <td colspan="9" class="empty-state">
                        Aucun signalement trouvé.
                        <div class="empty-state__cta">
                            <a href="<?php echo url('report_create', ['type' => $type]); ?>" class="btn btn--primary btn--sm">+ Inscrire un signalement</a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                    <?php
                        $isDeclarant = ((int) $report['declarant_id'] === $userId);
                        $canEdit = $isDeclarant && in_array($report['etat'], ['nouveau', 'en_cours']);
                        $canRespond = in_array($userRole, ['superviseur']) && in_array($report['etat'], ['nouveau', 'en_cours']);
                    ?>
                    <tr>
                        <td><strong><?php echo e($report['reference']); ?></strong></td>
                        <td><?php echo e(formatDateFR($report['date_evenement'])); ?></td>
                        <td><?php echo e(truncate($report['objet'], 50)); ?></td>
                        <td><?php echo e($report['declarant_nom']); ?></td>
                        <td><?php echo e($report['declarant_prenom']); ?></td>
                        <td><?php echo e($report['site_code'] ?? '—'); ?></td>
                        <td>
                            <span class="badge <?php echo getEtatBadgeClass($report['etat']); ?>">
                                <?php echo e(ETAT_LABELS[$report['etat']] ?? $report['etat']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($report['is_confidential'])): ?>
                            <span class="badge badge--confidential">&#128274; Confidentiel</span>
                            <?php else: ?>
                            <span class="badge badge--public">Public</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="<?php echo url('report_view', ['uuid' => $report['uuid']]); ?>" class="btn btn--sm btn--outline">Voir</a>
                                <?php if ($canEdit): ?>
                                <a href="<?php echo url('report_edit', ['uuid' => $report['uuid']]); ?>" class="btn btn--sm btn--secondary">Modifier</a>
                                <?php endif; ?>
                                <?php if ($canRespond): ?>
                                <a href="<?php echo url('report_respond', ['uuid' => $report['uuid']]); ?>" class="btn btn--sm btn--primary">Répondre</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Pagination
$totalPages = (int) ceil($totalItems / $perPage);
if ($totalPages > 1) {
    // Override $currentPage (set by sidebar as page name) with numeric page number
    $currentPage = $pageNum;
    require __DIR__ . '/../templates/pagination.php';
}
?>
