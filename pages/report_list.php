<?php
/**
 * Report List Page — Application SST DREETS BFC
 *
 * Lists reports for a given registry with filters and pagination.
 * URL: index.php?page=report_list&type={rsst|rami|dgi}
 */
/** @var array<string, string> $_GET */
$type = (string) ($_GET['type'] ?? '');

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$config = \App\Services\ConfigService::getInstance();
$session = new \App\Services\SessionService();
$access = new \App\Services\AccessService();

// Validate type against registries table (not hardcoded enum)
if (\App\Repository\RegistryRepository::instance()->findByCode($type) === null) {
    $session->setFlash('error', 'Type de registre invalide.');
    $http->redirect($http->url('home'));
}

// Block access to disabled registries
if (!$config->isRegistryEnabled($type)) {
    $session->setFlash('error', 'Ce registre est désactivé.');
    $http->redirect($http->url('home'));
}

$pageTitle = 'Liste des fiches du registre — ' . (REGISTRY_SHORT_LABELS[$type] ?? strtoupper((string) $type));

$pdo = getContainer()->get(\PDO::class);
/** @var PDO $pdo */
$user = $session->getUserSession();
/** @var array{id: int, site_id: int, role: string} $user */
$userSiteId = (int) ($user['site_id'] ?? 0);
$userId = (int) ($user['id'] ?? 0);
$userRole = (string) ($user['role'] ?? '');
$agentVisibility = $access->getReportVisibility($type);
$seeAllSites = $access->canSeeAllSites();
$noSiteMode = $config->isNoSiteMode();
$chsctScope = $userRole === ROLE_CHSCT ? $access->getChsctReportScope() : null;

// Build filters from GET params
/** @var array<string, mixed> $filters */
$filters = [
    'etat'    => (string) ($_GET['etat'] ?? ''),
    'site_id' => (string) ($_GET['site'] ?? ''),
    'q'       => trim((string) ($_GET['q'] ?? '')),
];

// Apply agent visibility restrictions
if ($agentVisibility === \App\Enum\VisibilityMode::Confidential->value) {
    $filters['force_site_id'] = $userSiteId;
    $filters['linked_agent_id'] = $userId;
    $filters['linked_agent_visibility'] = $agentVisibility;
} elseif ($agentVisibility === \App\Enum\VisibilityMode::AgentChoice->value) {
    $filters['force_site_id'] = $userSiteId;
    $filters['linked_agent_id'] = $userId;
    $filters['linked_agent_visibility'] = $agentVisibility;
} elseif ($agentVisibility === \App\Enum\VisibilityMode::Public->value) {
    $filters['force_site_id'] = $userSiteId;
}

// Pagination
$pageNum = max(1, (int) ($_GET['p'] ?? 1));
$perPage = ITEMS_PER_PAGE;

// Fetch reports
/** @var string */
$filterSiteIdStr = $filters['site_id'] ?? '';
$filterSiteId = (int) $filterSiteIdStr;
/** @var string */
$declarantIdRaw = $filters['declarant_id'] ?? '';
$declarantIdFilter = !empty($declarantIdRaw) ? (int) $declarantIdRaw : null;
/** @var string */
$forceSiteIdRaw = $filters['force_site_id'] ?? '';
$forceSiteIdFilter = !empty($forceSiteIdRaw) ? (int) $forceSiteIdRaw : null;
/** @var string|null */
$filterSearch = $filters['q'] ?? null;
$linkedAgentIdFilter = !empty($filters['linked_agent_id']) ? (int) $filters['linked_agent_id'] : null;
$linkedAgentVisibilityFilter = $filters['linked_agent_visibility'] ?? null;
/** @var array{reports: list<array<string, mixed>>, total: int} $result */
$result = \App\Repository\ReportRepository::instance()->findPaginated(new \App\DTO\ReportFilter(type: $type, etat: $filters['etat'] ?? '', siteId: $filterSiteId, declarantId: $declarantIdFilter, forceSiteId: $forceSiteIdFilter, search: $filterSearch, seeAllSites: $seeAllSites, chsctConsentOnly: $chsctScope === 'consent_only', linkedAgentId: $linkedAgentIdFilter, linkedAgentVisibility: $linkedAgentVisibilityFilter), $pageNum, $perPage);
$reports = $result['reports'];
$totalItems = $result['total'];

// Get all sites for filter dropdown
/** @var list<array<string, mixed>> $allSites */
$allSites = \App\Repository\SiteRepository::instance()->findAll();

// Build base URL for pagination (without &p=)
$baseUrlParams = [
    'page' => 'report_list',
    'type' => $type,
];
if (!empty($filters['etat'])) {
    $baseUrlParams['etat'] = $filters['etat'];
}
if (!empty($filters['site_id'])) {
    $baseUrlParams['site'] = $filters['site_id'];
}
if (!empty($filters['q'])) {
    $baseUrlParams['q'] = $filters['q'];
}
// Same reasoning as HttpService::url() — don't depend on the server's
// php.ini arg_separator.output setting.
$baseUrl = 'index.php?' . http_build_query($baseUrlParams, '', '&');
?>

<h1 class="page-title">
    Liste des fiches — <?php echo $fmt->e(REGISTRY_SHORT_LABELS[$type] ?? strtoupper((string) $type)); ?>
    <a href="<?php echo $http->url('report_create', ['type' => $type]); ?>" class="btn btn--sm btn--primary btn-float-right">+ Nouveau signalement</a>
</h1>

<?php if ($userRole === ROLE_SUPERVISEUR): ?>
<p class="mb-3"><a href="<?php echo $http->url('export'); ?>" class="btn btn--sm btn--outline">&#x1F4E5; Exporter les signalements filtrés</a></p>
<?php endif; ?>

<?php echo $fmt->renderBreadcrumb([
    ['url' => $http->url('home'), 'label' => 'Accueil'],
    ['label' => REGISTRY_SHORT_LABELS[$type] ?? strtoupper((string) $type)],
]); ?>


<div class="filter-bar">
    <form method="GET" action="index.php" class="flex flex-wrap gap-4 items-center w-full">
        <input type="hidden" name="page" value="report_list">
        <input type="hidden" name="type" value="<?php echo $fmt->e($type); ?>">

        <div class="form-group">
            <label for="etat">État</label>
            <select id="etat" name="etat">
                <option value="">Tous</option>
                <?php foreach (\App\Enum\ReportState::cases() as $state): ?>
                <option value="<?php echo $fmt->e($state->value); ?>" <?php echo $filters['etat'] === $state->value ? 'selected' : ''; ?>><?php echo $fmt->e($state->label()); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($seeAllSites && !$noSiteMode): ?>
        <div class="form-group">
            <label for="site">Site (<?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?>)</label>
            <select id="site" name="site">
                <option value="">Tous</option>
                <?php foreach ($allSites as $site): ?>
                <?php /** @var array{id: int, code: string, nom: string} $site */ ?>
                <option value="<?php echo $fmt->e((string) ($site['id'] ?? '')); ?>" <?php echo ($filters['site_id'] ?? '') === (string) ($site['id'] ?? '') ? 'selected' : ''; ?>>
                    <?php echo $fmt->e((string) ($site['code'] ?? '') . ' — ' . (string) ($site['nom'] ?? '')); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-group filter-search-group">
            <label for="q">Recherche</label>
            <input type="text" id="q" name="q" value="<?php echo $fmt->e($filters['q']); ?>" placeholder="Rechercher dans l'objet ou la description...">
        </div>

        <button type="submit" class="btn btn--primary">Filtrer</button>
    </form>
</div>

<div class="card">
    <div class="table-wrapper table-wrapper--responsive">
        <table aria-label="Liste des signalements <?php echo $fmt->e(REGISTRY_SHORT_LABELS[$type] ?? strtoupper((string) $type)); ?>">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Date</th>
                    <th>Objet</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <?php if (!$noSiteMode): ?><th><?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?></th><?php endif; ?>
                    <th>État</th>
                    <th>Visibilité</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                <tr>
                    <td colspan="<?php echo $noSiteMode ? '8' : '9'; ?>" class="empty-state">
                        <div class="empty-state__icon">&#128203;</div>
                        <div class="empty-state__title">Aucun signalement trouvé</div>
                        <div class="empty-state__cta">
                            <a href="<?php echo $http->url('report_create', ['type' => $type]); ?>" class="btn btn--primary btn--sm">+ <?php echo e(getConfig('app_report_create_label', 'Signaler un événement')); ?></a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                    <?php
                        /** @var array<string, mixed> $report */
                        $canEdit = $access->canEditReport((array) $report, $userId);
                        $canRespond = $access->canRespondToReport((array) $report, $userRole);
                        ?>
                    <tr>
                        <td data-label="Référence"><strong><?php echo $fmt->e($report['reference'] ?? ''); ?></strong></td>
                        <?php
                            $reportDateEvenement = $report['date_evenement'] ?? '';
                        $reportObjet = $report['objet'] ?? '';
                        $reportEtat = $report['etat'] ?? '';
                        $reportUuid = $report['uuid'] ?? '';
                        ?>
                        <td data-label="Date"><?php echo $fmt->e($fmt->formatDateFR($reportDateEvenement)); ?></td>
                        <td data-label="Objet"><?php echo $fmt->e($fmt->truncate($reportObjet, 50)); ?></td>
                        <td data-label="Nom"><?php echo $fmt->e($report['declarant_nom'] ?? ''); ?></td>
                        <td data-label="Prénom"><?php echo $fmt->e($report['declarant_prenom'] ?? ''); ?></td>
                        <?php if (!$noSiteMode): ?><td data-label="<?php echo $fmt->e($config->get('app_label_unite', 'UR')); ?>"><?php echo $fmt->e($report['site_code'] ?? '—'); ?></td><?php endif; ?>
                        <td data-label="État">
                            <span class="badge <?php echo $fmt->getEtatBadgeClass($reportEtat); ?>">
                                <?php echo $fmt->e(ETAT_LABELS[$reportEtat] ?? $reportEtat); ?>
                            </span>
                        </td>
                        <td data-label="Visibilité">
                            <?php if (!empty($report['is_confidential'])): ?>
                            <span class="badge badge--confidential">&#128274; Confidentiel</span>
                            <?php else: ?>
                            <span class="badge badge--public">Public</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <div class="btn-group">
                                <a href="<?php echo $http->url('report_view', ['uuid' => $reportUuid]); ?>" class="btn btn--sm btn--outline">Voir</a>
                                <?php if ($canEdit): ?>
                                <a href="<?php echo $http->url('report_edit', ['uuid' => $reportUuid]); ?>" class="btn btn--sm btn--secondary">Modifier</a>
                                <?php endif; ?>
                                <?php if ($canRespond): ?>
                                <a href="<?php echo $http->url('report_respond', ['uuid' => $reportUuid]); ?>" class="btn btn--sm btn--primary">Répondre</a>
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
