<?php
/**
 * Sidebar Template — Application SST DREETS BFC
 * 
 * Dark grey sidebar with navigation menu.
 * Menu items are shown/hidden based on the user's role.
 */
if (!isset($currentPage)) {
    $currentPage = $_GET['page'] ?? 'home';
}

$userRole = $_SESSION['user']['role'] ?? 'agent';

// Determine the active registry type for report subpages
// Pages like report_create, report_view, report_edit, report_abandon, report_respond
// should highlight the corresponding registry (RSST/RAMI/DGI) menu item
$activeRegistryType = $_GET['type'] ?? null;
$reportSubpages = ['report_create', 'report_view', 'report_edit', 'report_abandon', 'report_respond'];
if (!$activeRegistryType && in_array($currentPage, $reportSubpages) && isset($_GET['uuid'])) {
    // For view/edit/abandon/respond pages, try to get the type from the report
    try {
        $pdo = getDB();
        $reportUuid = $_GET['uuid'] ?? '';
        if (strlen($reportUuid) === 36) {
            $stmt = $pdo->prepare('SELECT type FROM reports WHERE uuid = :uuid');
            $stmt->execute([':uuid' => $reportUuid]);
            $row = $stmt->fetch();
            if ($row) {
                $activeRegistryType = $row['type'];
            }
        }
    } catch (Exception $e) {
        // Ignore database errors in sidebar
    }
}

// Define menu items with role visibility
$menuItems = [
    ['label' => 'Accueil',        'icon' => '🏠', 'page' => 'home',                'params' => [],                                  'roles' => ['agent','superviseur','chsct']],
    ['label' => 'RSST',           'icon' => '📋', 'page' => 'report_list',         'params' => ['type' => 'rsst'],                  'roles' => ['agent','superviseur','chsct']],
    ['label' => 'RAMI',           'icon' => '⚠️', 'page' => 'report_list',         'params' => ['type' => 'rami'],                  'roles' => ['agent','superviseur','chsct']],
    ['label' => 'DGI',            'icon' => '🔴', 'page' => 'report_list',         'params' => ['type' => 'dgi'],                   'roles' => ['agent','superviseur','chsct']],
    ['label' => 'Synthèse',       'icon' => '📊', 'page' => 'synthesis',           'params' => [],                                  'roles' => ['superviseur','chsct']],
    ['label' => 'Export',         'icon' => '📥', 'page' => 'export',              'params' => [],                                  'roles' => ['superviseur','chsct']],
    ['label' => 'Statistiques',   'icon' => '📈', 'page' => 'statistics',          'params' => [],                                  'roles' => ['superviseur','chsct']],
    ['label' => 'Utilisateurs',   'icon' => '👥', 'page' => 'users',               'params' => [],                                  'roles' => ['superviseur']],
    ['label' => 'Paramètres',     'icon' => '⚙️', 'page' => 'settings',            'params' => [],                                  'roles' => ['superviseur']],
];
?>
<nav class="sidebar" role="navigation" aria-label="Menu principal">
    <ul class="sidebar__nav">
        <?php foreach ($menuItems as $item): ?>
            <?php if (in_array($userRole, $item['roles'])): ?>
                <?php
                    // Determine if this item is active
                    $isActive = false;
                    $itemPage = $item['page'];
                    $itemParams = $item['params'] ?? [];
                    $itemType = $itemParams['type'] ?? null;
                    
                    // Direct match
                    $isActive = ($currentPage === $itemPage);
                    if ($itemType !== null && isset($_GET['type'])) {
                        $isActive = $isActive && ($_GET['type'] === $itemType);
                    }
                    
                    // Also match report subpages (create, view, edit, abandon, respond)
                    // that have the same registry type
                    if (!$isActive && in_array($currentPage, $reportSubpages) && $activeRegistryType && $itemType !== null) {
                        $isActive = ($activeRegistryType === $itemType);
                    }
                ?>
                <li>
                    <a href="<?php echo url($itemPage, $itemParams); ?>"
                       class="sidebar__item<?php echo $isActive ? ' sidebar__item--active' : ''; ?>"
                       <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
                        <span class="sidebar__icon" aria-hidden="true"><?php echo $item['icon']; ?></span>
                        <?php echo e($item['label']); ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <div class="sidebar__footer">
        <a href="<?php echo url('help'); ?>" class="sidebar__item<?php echo $currentPage === 'help' ? ' sidebar__item--active' : ''; ?>">
            <span class="sidebar__icon" aria-hidden="true">📚</span>
            Documentation
        </a>
        <a href="<?php echo url('preamble'); ?>" class="sidebar__item<?php echo $currentPage === 'preamble' ? ' sidebar__item--active' : ''; ?>">
            <span class="sidebar__icon" aria-hidden="true">📖</span>
            Préambule
        </a>
    </div>
</nav>
