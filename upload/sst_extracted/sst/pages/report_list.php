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
$agentVisibility = getAgentVisibility();
$seeAllSites = canSeeAllSites();

// Build filters from GET params
$filters = [
    'etat'    => $_GET['etat'] ?? '',
    'site_id' => $_GET['site'] ?? '',
    'q'       => trim($_GET['q'] ?? ''),
];

// Apply agent visibility restrictions
if ($agentVisibility === 'own') {
    // Agent sees only their own reports (also implies their site only)
    $filters['declarant_id'] = $userId;
    $filters['force_site_id'] = $userSiteId;
} elseif ($agentVisibility === 'site') {
    // Agent sees all reports from their site only
    $filters['force_site_id'] = $userSiteId;
}
// else: 'all' — no restrictions

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
    <a href="<?php echo url('report_create', ['type' => $type]); ?>" class="btn btn--sm btn--warning" style="float:right;margin-top:-4px;">+ Nouveau signalement</a>
</h1>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<div class="filter-bar">
    <form method="GET" action="index.php" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;width:100%;">
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

        <div class="form-group" style="flex:1;min-width:200px;">
            <label for="q">Recherche</label>
            <input type="text" id="q" name="q" value="<?php echo e($filters['q']); ?>" placeholder="Rechercher dans l'objet ou la description...">
        </div>

        <button type="submit" class="btn btn--primary">Filtrer</button>
    </form>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Date</th>
                    <th>Objet</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th><?php echo e(getConfig('app_label_unite', 'UR')); ?></th>
                    <th>État</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;color:var(--grey-500);padding:24px;">
                        Aucun signalement trouvé.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                    <?php
                        $isDeclarant = ((int) $report['declarant_id'] === $userId);
                        $canEdit = $isDeclarant && in_array($report['etat'], ['nouveau', 'en_cours']);
                        $canRespond = in_array($userRole, ['superviseur']) && in_array($report['etat'], ['nouveau', 'en_cours']);
                        $canAbandon = in_array($userRole, ['superviseur']) && !in_array($report['etat'], ['abandonne', 'traite']);
                    ?>
                    <tr>
                        <td><strong><?php echo e($report['reference']); ?></strong></td>
                        <td><?php echo formatDateFR($report['date_evenement']); ?></td>
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
                            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                <a href="<?php echo url('report_view', ['id' => $report['id']]); ?>" class="btn btn--sm btn--outline">Voir</a>
                                <?php if ($canEdit): ?>
                                <a href="<?php echo url('report_edit', ['id' => $report['id']]); ?>" class="btn btn--sm btn--secondary">Modifier</a>
                                <?php endif; ?>
                                <?php if ($canRespond): ?>
                                <a href="<?php echo url('report_respond', ['id' => $report['id']]); ?>" class="btn btn--sm btn--primary">Répondre</a>
                                <?php endif; ?>
                                <?php if ($canAbandon): ?>
                                <a href="<?php echo url('report_abandon', ['id' => $report['id']]); ?>" class="btn btn--sm btn--danger">Abandonner</a>
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
    require __DIR__ . '/../templates/pagination.php';
}
?>
