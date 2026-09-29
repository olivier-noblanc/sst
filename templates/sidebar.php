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
    ['label' => 'Accueil', 'icon' => '🏠', 'page' => 'home', 'params' => [], 'roles' => $allRoles, 'group' => 'Navigation'],
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
        'group'  => 'Registres',
    ];
}

$menuItems = array_merge($menuItems, [
    ['label' => 'Synthèse',       'icon' => '📊', 'page' => 'synthesis',    'params' => [],  'roles' => $supRoles, 'group' => 'Pilotage'],
    ['label' => 'Export',         'icon' => '📥', 'page' => 'export',       'params' => [],  'roles' => $supRoles, 'group' => 'Pilotage'],
    ['label' => 'Statistiques',   'icon' => '📈', 'page' => 'statistics',   'params' => [],  'roles' => $supRoles, 'group' => 'Pilotage'],
    ['label' => 'Utilisateurs',   'icon' => '👥', 'page' => 'users',        'params' => [],  'roles' => $supOnly,  'group' => 'Administration'],
    ['label' => 'Paramètres',     'icon' => '⚙️', 'page' => 'settings',     'params' => [],  'roles' => $supOnly,  'group' => 'Administration'],
    ['label' => 'Journal',        'icon' => '📜', 'page' => 'logs',         'params' => [],  'roles' => $supOnly,  'group' => 'Administration'],
]);

// Resolve visibility + active state for every item once. The grouping in the
// view below only inserts section headers; the active-detection logic itself
// is unchanged from the previous flat list.
$visibleItems = [];
foreach ($menuItems as $item) {
    if (!in_array($userRole, $item['roles'], true)) {
        continue;
    }
    $itemPage = $item['page'];
    $itemType = $item['params']['type'] ?? null;

    $isActive = ($currentPage === $itemPage);
    if ($itemType !== null && isset($_GET['type'])) {
        $isActive = $isActive && ($_GET['type'] === $itemType);
    }

    if (!$isActive && in_array($currentPage, $reportSubpages, true) && $activeRegistryType !== null && $itemType !== null) {
        $isActive = ($activeRegistryType === $itemType);
    }

    $item['active'] = $isActive;
    $visibleItems[] = $item;
}
?>
<!-- Hidden checkbox for CSS-only sidebar toggle (mobile) — tabindex="-1" prevents focus since hidden attr is not always sufficient -->
<input type="checkbox" id="sidebar-toggle" class="sidebar-toggle-checkbox" tabindex="-1" hidden>
<label for="sidebar-toggle" class="sidebar-overlay" aria-hidden="true"></label>
<nav class="sidebar" id="main-nav" role="navigation" aria-label="Menu principal">
    <div class="sidebar__brand">
        <span class="sidebar__brand-mark" aria-hidden="true">SST</span>
        <span class="sidebar__brand-text">
            <span class="sidebar__brand-title"><?php echo e(getConfigService()->get('app_nom_organisation', 'DREETS BFC')); ?></span>
            <span class="sidebar__brand-sub">Application SST</span>
        </span>
    </div>
    <ul class="sidebar__nav">
        <?php $lastGroup = null; ?>
        <?php foreach ($visibleItems as $item): ?>
            <?php if ($item['group'] !== $lastGroup): ?>
                <?php $lastGroup = $item['group']; ?>
                <li class="sidebar__group"><span class="sidebar__group-title"><?php echo e((string) $lastGroup); ?></span></li>
            <?php endif; ?>
            <li>
                <a href="<?php echo new \App\Services\HttpService()->url($item['page'], $item['params']); ?>"
                   class="sidebar__item<?php echo $item['active'] ? ' sidebar__item--active' : ''; ?>"
                   <?php echo $item['active'] ? 'aria-current="page"' : ''; ?>>
                    <span class="sidebar__icon" aria-hidden="true"><?php echo e((string) $item['icon']); ?></span>
                    <?php echo e($item['label']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="sidebar__footer">
        <span class="sidebar__footer-app">Application SST</span>
        <span class="sidebar__footer-version">v<?php echo e(getAppVersion()); ?></span>
    </div>
</nav>
