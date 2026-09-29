<?php

/**
 * Renderer — Page rendering functions for the Router.
 *
 * Extracted from src/router.php (legacy procedural file, now deleted).
 * These functions are procedural to match the style of routes.php closures.
 */

use App\Router\Router;

/**
 * Render a page with the standard layout (header + sidebar + content + footer).
 */
function renderPageWithLayout(Router $router, string $page, string $csrfToken): void
{
    $currentPageName = $page;
    $pageTitle = $router->getPageTitle($page);

    require __DIR__ . '/../../templates/header.php';
    require __DIR__ . '/../../templates/sidebar.php';
    ?>
    <span id="top" tabindex="-1"></span>
    <main id="main-content" class="main" role="main">
    <?php
    // Flash messages live in the main content area. Rendered outside <main>,
    // the alert sat at the document's top-left corner — underneath the fixed
    // sidebar, on the phantom top-bar offset the shell no longer has. Inside
    // <main> it inherits the sidebar offset, the content padding and the
    // bounded width, so it is visible at the top of the content.
    require __DIR__ . '/../../templates/alert.php';

    $pageFile = __DIR__ . '/../../pages/' . $page . '.php';
    if (file_exists($pageFile)) {
        require $pageFile;
    } else {
        require __DIR__ . '/../../pages/home.php';
    }

    require __DIR__ . '/../../templates/footer.php';
}

/**
 * Render a standalone page without the standard layout (e.g. attachment, print).
 */
function renderStandalonePage(string $pageFile): void
{
    if (file_exists($pageFile)) {
        require $pageFile;
    }
}
