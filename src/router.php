<?php
/**
 * Page rendering — Application SST DREETS BFC
 *
 * Rendering functions used by the Router to display pages.
 * Route dispatch and validation have moved to src/Router/Router.php.
 */

// ═══════════════════════════════════════════════════════════════════════════════
// Page rendering
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Render a page with the standard layout (header + sidebar + content + footer).
 *
 * @param string $page        The validated page name
 * @param string $csrfToken   The CSRF token for forms
 */
function renderPageWithLayout(string $page, string $csrfToken): void
{
    $currentPageName = $page;
    $pageTitle = getRouter()->getPageTitle($page);

    require __DIR__ . '/../templates/header.php';
    require __DIR__ . '/../templates/sidebar.php';
    require __DIR__ . '/../templates/alert.php';
    ?>
    <span id="top" tabindex="-1"></span>
    <main id="main-content" class="main" role="main">
    <?php
    $pageFile = __DIR__ . '/../pages/' . $page . '.php';
    if (file_exists($pageFile)) {
        require $pageFile;
    } else {
        require __DIR__ . '/../pages/home.php';
    }

    require __DIR__ . '/../templates/footer.php';
}

/**
 * Render a standalone page without the standard layout (e.g. attachment, print).
 *
 * @param string $pageFile  Full path to the page PHP file
 */
function renderStandalonePage(string $pageFile): void
{
    if (file_exists($pageFile)) {
        require $pageFile;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// Router singleton accessor
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Get the application Router instance (singleton).
 * Populated by createRouter() in src/Router/routes.php.
 */
function getRouter(): \App\Router\Router
{
    static $router = null;
    if ($router === null) {
        $router = createRouter();
    }
    return $router;
}
