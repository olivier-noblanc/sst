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

$userRole = currentUserRole() !== '' ? currentUserRole() : \App\Enum\UserRole::Agent->value;

// Determine the active registry type for report subpages
$activeRegistryType = $_GET['type'] ?? null;
$reportSubpages = ['report_create', 'report_view', 'report_edit', 'report_abandon', 'report_respond'];
if ($activeRegistryType === null && in_array($currentPage, $reportSubpages, true) && isset($_GET['uuid'])) {
    try {
        $reportUuid = $_GET['uuid'];
        $reportUuidStr = (string) $reportUuid;
        if (strlen($reportUuidStr) === 36) {
            $activeRegistryType = \App\Repository\ReportRepository::instance()->getTypeByUuid($reportUuidStr);
        }
    } catch (Exception) {
        // @silent-ok: cosmetic — highlighting the active menu item, a DB error here
        // must not break rendering of the sidebar (and the rest of the page with it).
    }
}

// Define menu items with role visibility
use App\Enum\UserRole;

$allRoles = [UserRole::Agent->value, UserRole::Superviseur->value, UserRole::Chsct->value];
$supRoles = [UserRole::Superviseur->value, UserRole::Chsct->value];
$supOnly  = [UserRole::Superviseur->value];

$menuItems = [
    ['label' => 'Accueil', 'icon' => '🏠', 'page' => 'home', 'params' => [], 'roles' => $allRoles],
];

// Add registry types from database (dynamic, includes custom registres)
$enabledRegistries = \App\Repository\RegistryRepository::instance()->findEnabled();
foreach ($enabledRegistries as $reg) {
    $menuItems[] = [
        'label'  => $reg['short_label'],
        'icon'   => $reg['icon'],
        'page'   => 'report_list',
        'params' => ['type' => $reg['code']],
        'roles'  => $allRoles,
    ];
}

$menuItems = array_merge($menuItems, [
    ['label' => 'Synthèse',       'icon' => '📊', 'page' => 'synthesis',           'params' => [],  'roles' => $supRoles],
    ['label' => 'Export',         'icon' => '📥', 'page' => 'export',              'params' => [],  'roles' => $supRoles],
    ['label' => 'Statistiques',   'icon' => '📈', 'page' => 'statistics',          'params' => [],  'roles' => $supRoles],
    ['label' => 'Utilisateurs',   'icon' => '👥', 'page' => 'users',               'params' => [],  'roles' => $supOnly],
    ['label' => 'Paramètres',     'icon' => '⚙️', 'page' => 'settings',            'params' => [],  'roles' => $supOnly],
    ['label' => 'Journal',        'icon' => '📜', 'page' => 'logs',                'params' => [],  'roles' => $supOnly],
]);
?>
<!-- Hidden checkbox for CSS-only sidebar toggle (mobile) — tabindex="-1" prevents focus since hidden attr is not always sufficient -->
<input type="checkbox" id="sidebar-toggle" class="sidebar-toggle-checkbox" tabindex="-1" hidden>
<label for="sidebar-toggle" class="sidebar-overlay" aria-hidden="true"></label>
<nav class="sidebar" id="main-nav" role="navigation" aria-label="Menu principal">
    <ul class="sidebar__nav">
        <?php foreach ($menuItems as $item): ?>
            <?php if (in_array($userRole, $item['roles'], true)): ?>
                <?php
                    $isActive = false;
                    $itemPage = $item['page'];
                    $itemParams = $item['params'];
                    $itemType = $itemParams['type'] ?? null;

                    $isActive = ($currentPage === $itemPage);
                    if ($itemType !== null && isset($_GET['type'])) {
                        $isActive = $isActive && ($_GET['type'] === $itemType);
                    }

                    if (!$isActive && in_array($currentPage, $reportSubpages, true) && $activeRegistryType !== null && $itemType !== null) {
                        $isActive = ($activeRegistryType === $itemType);
                    }
                ?>
                <li>
                    <a href="<?php echo new \App\Services\HttpService()->url($itemPage, $itemParams); ?>"
                       class="sidebar__item<?php echo $isActive ? ' sidebar__item--active' : ''; ?>"
                       <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
                        <span class="sidebar__icon" aria-hidden="true"><?php echo e((string) $item['icon']); ?></span>
                        <?php echo e($item['label']); ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

</nav>
