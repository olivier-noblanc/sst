<?php
/**
 * Sidebar Template — Application SST DREETS BFC
 *
 * Dark grey sidebar with navigation menu.
 * Menu items are shown/hidden based on the user's role.
 * Includes mobile overlay support.
 */
if (!isset($currentPage)) {
    $currentPage = $_GET['page'] ?? 'home';
}

$userRole = $_SESSION['user']['role'] ?? 'agent';

// Determine the active registry type for report subpages
$activeRegistryType = $_GET['type'] ?? null;
$reportSubpages = ['report_create', 'report_view', 'report_edit', 'report_abandon', 'report_respond'];
if (!$activeRegistryType && in_array($currentPage, $reportSubpages) && isset($_GET['uuid'])) {
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
    ['label' => 'Accueil',        'icon' => '&#127968;', 'page' => 'home',                'params' => [],                                  'roles' => ['agent','superviseur','chsct']],
    ['label' => 'RSST',           'icon' => '&#128203;', 'page' => 'report_list',         'params' => ['type' => 'rsst'],                  'roles' => ['agent','superviseur','chsct']],
    ['label' => 'RAMI',           'icon' => '&#9888;',   'page' => 'report_list',         'params' => ['type' => 'rami'],                  'roles' => ['agent','superviseur','chsct']],
    ['label' => 'DGI',            'icon' => '&#128308;', 'page' => 'report_list',         'params' => ['type' => 'dgi'],                   'roles' => ['agent','superviseur','chsct']],
    ['label' => 'Synthèse',       'icon' => '&#128202;', 'page' => 'synthesis',           'params' => [],                                  'roles' => ['superviseur','chsct']],
    ['label' => 'Export',         'icon' => '&#128229;', 'page' => 'export',              'params' => [],                                  'roles' => ['superviseur','chsct']],
    ['label' => 'Statistiques',   'icon' => '&#128200;', 'page' => 'statistics',          'params' => [],                                  'roles' => ['superviseur','chsct']],
    ['label' => 'Utilisateurs',   'icon' => '&#128101;', 'page' => 'users',               'params' => [],                                  'roles' => ['superviseur']],
    ['label' => 'Paramètres',     'icon' => '&#9881;',   'page' => 'settings',            'params' => [],                                  'roles' => ['superviseur']],
];
?>
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
<nav class="sidebar" id="sidebar-nav" role="navigation" aria-label="Menu principal">
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

<script>
// Mobile sidebar toggle — minimal JS, progressive enhancement
(function() {
    var menuBtn = document.querySelector('.header__menu-btn');
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.querySelector('.sidebar-overlay');
    if (!menuBtn || !sidebar) return;

    // Focus trap: get all focusable elements inside sidebar
    function getFocusableElements() {
        var links = sidebar.querySelectorAll('a[href], button[href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        return Array.prototype.slice.call(links).filter(function(el) {
            return !el.disabled && el.offsetParent !== null;
        });
    }

    function toggleMenu(open) {
        var isOpen = typeof open === 'boolean' ? open : !sidebar.classList.contains('sidebar--open');
        sidebar.classList.toggle('sidebar--open', isOpen);
        overlay.classList.toggle('sidebar-overlay--visible', isOpen);
        overlay.setAttribute('aria-hidden', !isOpen);
        menuBtn.setAttribute('aria-expanded', isOpen);
        menuBtn.textContent = isOpen ? '\u2715' : '\u2630';

        // Focus management
        if (isOpen) {
            // Focus first item when opening
            var focusable = getFocusableElements();
            if (focusable.length > 0) {
                setTimeout(function() { focusable[0].focus(); }, 50);
            }
        }
    }

    menuBtn.addEventListener('click', function() { toggleMenu(); });
    overlay.addEventListener('click', function() { toggleMenu(false); });

    // Close sidebar on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('sidebar--open')) {
            toggleMenu(false);
            menuBtn.focus();
        }
    });

    // Focus trap: keep focus within sidebar when open
    sidebar.addEventListener('keydown', function(e) {
        if (e.key !== 'Tab' || !sidebar.classList.contains('sidebar--open')) return;

        var focusable = getFocusableElements();
        if (focusable.length === 0) return;

        var firstEl = focusable[0];
        var lastEl = focusable[focusable.length - 1];

        if (e.shiftKey) {
            // Shift+Tab: if on first element, wrap to last
            if (document.activeElement === firstEl) {
                e.preventDefault();
                lastEl.focus();
            }
        } else {
            // Tab: if on last element, wrap to first
            if (document.activeElement === lastEl) {
                e.preventDefault();
                firstEl.focus();
            }
        }
    });

    // Close sidebar when clicking a link (mobile)
    sidebar.querySelectorAll('.sidebar__item').forEach(function(link) {
        link.addEventListener('click', function() { toggleMenu(false); });
    });
})();
</script>
