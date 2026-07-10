<?php
/**
 * Sidebar Template — Application SST DREETS BFC
 *
 * Dark grey sidebar with navigation menu.
 * Menu items are shown/hidden based on the user's role.
 * Uses CSS-only checkbox hack for mobile toggle (zero JavaScript).
 */
if (!isset($currentPage)) {
    $currentPage = $_GET['page'] ?? 'home';
}

$userRole = currentUserRole() ?: ROLE_AGENT;

// Determine the active registry type for report subpages
$activeRegistryType = $_GET['type'] ?? null;
$reportSubpages = ['report_create', 'report_view', 'report_edit', 'report_abandon', 'report_respond'];
if (!$activeRegistryType && in_array($currentPage, $reportSubpages) && isset($_GET['uuid'])) {
    try {
        $pdo = getDB();
        $reportUuid = $_GET['uuid'] ?? '';
        if (strlen((string) $reportUuid) === 36) {
            $stmt = $pdo->prepare('SELECT type FROM reports WHERE uuid = :uuid');
            $stmt->execute([':uuid' => $reportUuid]);
            $row = $stmt->fetch();
            if ($row) {
                $activeRegistryType = $row['type'];
            }
        }
    } catch (Exception) {
        // Ignore database errors in sidebar
    }
}

// Define menu items with role visibility
// RAMI and DGI items will be conditionally added below based on toggles
$menuItems = [
    ['label' => 'Accueil',        'icon' => '&#127968;', 'page' => 'home',                'params' => [],                                  'roles' => ['agent','superviseur','chsct']],
    ['label' => 'RSST',           'icon' => '&#128203;', 'page' => 'report_list',         'params' => ['type' => 'rsst'],                  'roles' => ['agent','superviseur','chsct']],
];

// Conditionally add RAMI if enabled
if (isRegistryEnabled(TYPE_RAMI)) {
    $menuItems[] = ['label' => 'RAMI', 'icon' => '&#9888;', 'page' => 'report_list', 'params' => ['type' => 'rami'], 'roles' => ['agent','superviseur','chsct']];
}

// Conditionally add DGI if enabled
if (isRegistryEnabled(TYPE_DGI)) {
    $menuItems[] = ['label' => 'DGI', 'icon' => '&#128308;', 'page' => 'report_list', 'params' => ['type' => 'dgi'], 'roles' => ['agent','superviseur','chsct']];
}

$menuItems = array_merge($menuItems, [
    ['label' => 'Synthèse',       'icon' => '&#128202;', 'page' => 'synthesis',           'params' => [],                                  'roles' => ['superviseur','chsct']],
    ['label' => 'Export',         'icon' => '&#128229;', 'page' => 'export',              'params' => [],                                  'roles' => ['superviseur','chsct']],
    ['label' => 'Statistiques',   'icon' => '&#128200;', 'page' => 'statistics',          'params' => [],                                  'roles' => ['superviseur','chsct']],
    ['label' => 'Utilisateurs',   'icon' => '&#128101;', 'page' => 'users',               'params' => [],                                  'roles' => ['superviseur']],
    ['label' => 'Paramètres',     'icon' => '&#9881;',   'page' => 'settings',            'params' => [],                                  'roles' => ['superviseur']],
    ['label' => 'Journal',        'icon' => '&#128220;', 'page' => 'logs',                'params' => [],                                  'roles' => ['superviseur']],
]);
?>
<!-- Hidden checkbox for CSS-only sidebar toggle (mobile) — tabindex="-1" prevents focus since hidden attr is not always sufficient -->
<input type="checkbox" id="sidebar-toggle" class="sidebar-toggle-checkbox" tabindex="-1" hidden>
<label for="sidebar-toggle" class="sidebar-overlay" aria-hidden="true"></label>
<nav class="sidebar" id="main-nav" role="navigation" aria-label="Menu principal">
    <ul class="sidebar__nav">
        <?php foreach ($menuItems as $item): ?>
            <?php if (in_array($userRole, $item['roles'])): ?>
                <?php
                    $isActive = false;
                    $itemPage = $item['page'];
                    $itemParams = $item['params'] ?? [];
                    $itemType = $itemParams['type'] ?? null;

                    $isActive = ($currentPage === $itemPage);
                    if ($itemType !== null && isset($_GET['type'])) {
                        $isActive = $isActive && ($_GET['type'] === $itemType);
                    }

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
        <a href="<?php echo url('guide'); ?>" class="sidebar__item<?php echo $currentPage === 'guide' ? ' sidebar__item--active' : ''; ?>">
            <span class="sidebar__icon" aria-hidden="true">&#128196;</span>
            Guide rapide
        </a>
        <a href="<?php echo url('help'); ?>" class="sidebar__item<?php echo $currentPage === 'help' ? ' sidebar__item--active' : ''; ?>">
            <span class="sidebar__icon" aria-hidden="true">&#128218;</span>
            Documentation
        </a>
        <a href="<?php echo url('preamble'); ?>" class="sidebar__item<?php echo $currentPage === 'preamble' ? ' sidebar__item--active' : ''; ?>">
            <span class="sidebar__icon" aria-hidden="true">&#128214;</span>
            Préambule
        </a>
    </div>
</nav>
