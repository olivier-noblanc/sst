<?php
/**
 * Router — Application SST DREETS BFC
 *
 * Page whitelist, handler dispatch map, and page title mapping.
 * Extracted from public/index.php for testability and single-responsibility.
 *
 * The router is intentionally procedural (no class) to match the
 * application's architecture. All functions are pure or read-only.
 */

// ═══════════════════════════════════════════════════════════════════════════════
// Page whitelist
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Get the whitelist of valid page names.
 *
 * @return string[]
 */
function getValidPages(): array
{
    return [
        'home', 'preamble', 'help', 'guide', 'changelog', 'access_denied', 'choose_site',
        'report_create', 'report_list', 'report_view', 'report_edit',
        'report_print', 'report_attachment', 'report_abandon', 'report_respond', 'report_reopen',
        'agent_confirm',
        'synthesis', 'export', 'statistics',
        'settings', 'site_edit',
        'users', 'user_edit', 'user_view',
        'logs',
        'impersonate', 'logout',
    ];
}

/**
 * Validate a page name against the whitelist.
 * Returns the page name if valid, 'home' otherwise.
 *
 * @param string $page  The raw page parameter from $_GET
 * @return string       Validated page name
 */
function validatePage(string $page): string
{
    return in_array($page, getValidPages(), true) ? $page : 'home';
}

// ═══════════════════════════════════════════════════════════════════════════════
// Handler dispatch
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Get the map of page names to POST handler file paths.
 *
 * @return array<string, string>  page => file path
 */
function getHandlerMap(): array
{
    return [
        'report_create'   => __DIR__ . '/../handlers/report_create_handler.php',
        'report_edit'     => __DIR__ . '/../handlers/report_edit_handler.php',
        'report_abandon'  => __DIR__ . '/../handlers/report_abandon_handler.php',
        'report_respond'  => __DIR__ . '/../handlers/report_respond_handler.php',
        'report_reopen'   => __DIR__ . '/../handlers/report_reopen_handler.php',
        'export'          => __DIR__ . '/../handlers/export_handler.php',
        'settings'        => __DIR__ . '/../handlers/settings_handler.php',
        'site_edit'       => __DIR__ . '/../handlers/site_edit_handler.php',
        'smtp_test'       => __DIR__ . '/../handlers/smtp_test_handler.php',
        'user_edit'       => __DIR__ . '/../handlers/user_edit_handler.php',
        'user_create'     => __DIR__ . '/../handlers/user_create_handler.php',
        'user_delete'     => __DIR__ . '/../handlers/user_delete_handler.php',
        'user_reactivate' => __DIR__ . '/../handlers/user_reactivate_handler.php',
        'impersonate'     => __DIR__ . '/../handlers/impersonate_handler.php',
        'agent_confirm'   => __DIR__ . '/../handlers/agent_confirm_handler.php',
    ];
}

/**
 * Dispatch a POST request to the appropriate handler.
 *
 * @param string $page  The page name
 * @return bool         True if a handler was found and executed
 */
function dispatchPostHandler(string $page): bool
{
    $handlerMap = getHandlerMap();
    if (isset($handlerMap[$page])) {
        require $handlerMap[$page];
        return true;
    }
    return false;
}

// ═══════════════════════════════════════════════════════════════════════════════
// Page titles
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Get the page title for a given page name.
 * Used in <title> and heading elements.
 *
 * @param string $page  The validated page name
 * @return string       Human-readable page title
 */
function getPageTitle(string $page): string
{
    return match($page) {
        'home'            => 'Accueil',
        'preamble'        => 'Préambule',
        'help'            => 'Documentation',
        'guide'           => 'Guide rapide — Comment signaler',
        'changelog'       => 'Historique des modifications',
        'choose_site'     => 'Choisir mon site',
        'report_create'   => 'Signaler un événement — ' . (REGISTRY_SHORT_LABELS[$_GET['type'] ?? ''] ?? ''),
        'report_list'     => 'Liste des fiches — ' . (REGISTRY_SHORT_LABELS[$_GET['type'] ?? ''] ?? ''),
        'report_view'     => 'Signalement',
        'report_edit'     => 'Modifier le signalement',
        'report_abandon'  => 'Abandonner le signalement',
        'report_respond'  => 'Répondre au signalement',
        'report_reopen'   => 'Réouvrir le signalement',
        'synthesis'       => 'Synthèse des signalements',
        'export'          => 'Export des données',
        'statistics'      => 'Statistiques',
        'settings'        => 'Paramètres — Notifications',
        'users'           => 'Gestion des utilisateurs',
        'user_edit'       => 'Éditer l\'utilisateur',
        'user_view'       => 'Profil utilisateur',
        'site_edit'       => 'Éditer le site',
        'access_denied'   => 'Accès refusé',
        'user_create'     => 'Créer un utilisateur',
        'logs'            => 'Journal',
        default           => 'Accueil',
    };
}

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
    $pageTitle = getPageTitle($page);

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
