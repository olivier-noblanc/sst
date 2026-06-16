<?php
/**
 * Router / Entry Point — Application SST DREETS BFC
 * 
 * All requests go through this file. It parses the 'page' query
 * parameter and dispatches to the appropriate page or handler.
 *
 * Gzip compression is enabled via ob_gzhandler for all PHP output.
 * This is server-independent (works on Apache, IIS, Nginx, etc.).
 */

// === Remove unwanted headers (PHP version disclosure, deprecated headers) ===
// Loaded at the very top before any other output.
// The removeUnwantedHeaders() function will also be called later after helpers.php is loaded,
// but we need these removed ASAP — before the gzip buffer starts.

// === Enable Gzip compression (PHP-level, server-independent) ===
// Skip gzip on PHP built-in dev server — ob_gzhandler crashes it
$useGzip = (extension_loaded('zlib')
    && !ini_get('zlib.output_compression')
    && isset($_SERVER['HTTP_ACCEPT_ENCODING'])
    && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false
    && php_sapi_name() !== 'cli-server');
if ($useGzip) {
    ob_start('ob_gzhandler');
} else {
    ob_start();
}

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/error_handler.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/session_patch.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/audit.php';

// Now that helpers.php is loaded, use the centralised function
removeUnwantedHeaders();

// === Register custom error handler (email critical errors to admin) ===
set_error_handler('sstErrorHandler');
register_shutdown_function('sstShutdownHandler');

// Composer autoloader (if present — no longer required since FPDF replaces mPDF)
// FPDF is loaded directly in report_print.php via require_once.
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Load query functions
require_once __DIR__ . '/../src/queries/report_queries.php';
require_once __DIR__ . '/../src/queries/user_queries.php';
require_once __DIR__ . '/../src/queries/site_queries.php';
require_once __DIR__ . '/../src/queries/stats_queries.php';

// Load user context helpers (currentUser(), isSuperviseur(), etc.)
require_once __DIR__ . '/../src/user_context.php';

// Load shared validation functions (depends on helpers.php + query files)
require_once __DIR__ . '/../src/validation.php';

// Load bootstrap middleware (superviseur promotion, site check)
require_once __DIR__ . '/../src/middleware/bootstrap.php';

startSession();

// Whitelist of valid pages
$validPages = [
    'home', 'preamble', 'help', 'changelog', 'access_denied', 'choose_site',
    'report_create', 'report_list', 'report_view', 'report_edit',
    'report_print', 'report_attachment', 'report_abandon', 'report_respond',
    'synthesis', 'export', 'statistics',
    'settings', 'site_edit',
    'users', 'user_edit', 'user_view',
    'logs',
    'impersonate', 'logout'
];

$page = $_GET['page'] ?? 'home';

// === AUTO-AUTHENTICATION VIA IIS (prod) ===
// In production, IIS always provides AUTH_USER via Windows Authentication.
// If the user is not yet in session, try to authenticate automatically.
if (!isset($_SESSION['user'])) {
    $autoUser = getAuthenticatedUser();
    if ($autoUser) {
        $_SESSION['user'] = $autoUser;

        // Lazy cron: trigger maintenance tasks on login (no system cron)
        require_once __DIR__ . '/../src/cron.php';
        try {
            runLazyCron(getDB());
        } catch (Exception $e) {
            error_log('[SST-CRON] Lazy cron failed on IIS auto-auth: ' . $e->getMessage());
        }
    }
}

// === LOGIN PAGE: dev mode only ===
// In prod, IIS authenticates before PHP runs, so the login form is unreachable.
// In dev, we show a mock login form for testing.
if ($page === 'login') {
    if (!DEV_MODE) {
        // Prod: login page doesn't make sense, IIS handles auth
        // If we're here, user is already IIS-authenticated or something is wrong
        if (isset($_SESSION['user'])) {
            redirect(url('home'));
        } else {
            // AUTH_USER not set = IIS Windows Auth not configured
            die('Erreur de configuration : l\'authentification Windows IIS n\'est pas active. '
              . 'Vérifiez que Windows Authentication est activée et Anonymous Authentication désactivée dans IIS Manager.');
        }
    }
    // Dev mode: handle login POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require __DIR__ . '/../handlers/login_handler.php';
        exit;
    }
    $pageFile = __DIR__ . '/../pages/login.php';
    if (file_exists($pageFile)) {
        require $pageFile;
    }
    exit;
}

// === SUPERVISEUR PROMOTION CHECK (every request) ===
checkSuperviseurPromotion();

// === NOT AUTHENTICATED: redirect ===
if (!isset($_SESSION['user'])) {
    if (DEV_MODE) {
        // Dev: redirect to mock login form
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect(url('login'));
    } else {
        // Prod: IIS should have provided AUTH_USER but didn't = misconfiguration
        die('Erreur de configuration : AUTH_USER non disponible. '
          . 'Vérifiez que Windows Authentication est activée dans IIS Manager.');
    }
}

// === LOAD MIDDLEWARE (needed by both handlers and pages) ===
require_once __DIR__ . '/../src/middleware/require_auth.php';
require_once __DIR__ . '/../src/middleware/require_role.php';

// === GENERATE CSRF TOKEN (needed by both handlers and pages) ===
$csrfToken = generateCsrfToken();

// === CHOOSE SITE PAGE: agent must select site on first login ===
if ($page === 'choose_site') {
    // Handle POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require __DIR__ . '/../handlers/choose_site_handler.php';
        exit;
    }
    // Show the form (only if user has no site yet)
    $pageFile = __DIR__ . '/../pages/choose_site.php';
    if (file_exists($pageFile)) {
        require $pageFile;
    }
    exit;
}

// === CHECK: user must have a site assigned ===
checkUserSiteAssignment();

// === HANDLE POST REQUESTS ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handlerMap = [
        'report_create'  => __DIR__ . '/../handlers/report_create_handler.php',
        'report_edit'    => __DIR__ . '/../handlers/report_edit_handler.php',
        'report_abandon' => __DIR__ . '/../handlers/report_abandon_handler.php',
        'report_respond' => __DIR__ . '/../handlers/report_respond_handler.php',
        'export'         => __DIR__ . '/../handlers/export_handler.php',
        'settings'       => __DIR__ . '/../handlers/settings_handler.php',
        'site_edit'      => __DIR__ . '/../handlers/site_edit_handler.php',
        'smtp_test'      => __DIR__ . '/../handlers/smtp_test_handler.php',
        'user_edit'      => __DIR__ . '/../handlers/user_edit_handler.php',
        'user_create'    => __DIR__ . '/../handlers/user_create_handler.php',
        'user_delete'    => __DIR__ . '/../handlers/user_delete_handler.php',
        'user_reactivate' => __DIR__ . '/../handlers/user_reactivate_handler.php',
        'impersonate'    => __DIR__ . '/../handlers/impersonate_handler.php',
    ];
    if (isset($handlerMap[$page])) {
        require $handlerMap[$page];
        exit;
    }
}

// === LOGOUT ===
if ($page === 'logout') {
    // Clear PHP session completely
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();

    if (DEV_MODE) {
        // Dev: go back to mock login form
        redirect(url('login'));
    } else {
        // Prod: IIS will re-authenticate automatically on next request
        // User effectively "re-logs in" immediately via Windows Auth
        // This clears the PHP session cache (role changes etc. will be refreshed)
        redirect(url('home'));
    }
}

// === ATTACHMENT DOWNLOAD: special handling (raw file, no layout) ===
if ($page === 'report_attachment') {
    $pageFile = __DIR__ . '/../pages/report_attachment.php';
    if (file_exists($pageFile)) {
        require $pageFile;
    } else {
        redirect(url('home'));
    }
    exit;
}

// === PRINT PAGE: special handling (no header/sidebar) ===
if ($page === 'report_print') {
    $pageFile = __DIR__ . '/../pages/report_print.php';
    if (file_exists($pageFile)) {
        require $pageFile;
    } else {
        redirect(url('home'));
    }
    exit;
}

// === VALIDATE PAGE ===
if (!in_array($page, $validPages)) {
    $page = 'home';
}

// === LOAD MIDDLEWARE ===
// Already loaded above before POST handlers

// === RENDER PAGE WITH LAYOUT ===
$currentPageName = $page;

// CSRF token already generated above

// Determine page title BEFORE rendering header (header uses $pageTitle in <title>)
$pageTitle = match($page) {
    'home'            => 'Accueil',
    'preamble'        => 'Préambule',
    'help'            => 'Documentation',
    'changelog'       => 'Historique des modifications',
    'choose_site'     => 'Choisir mon site',
    'report_create'   => 'Inscrire un signalement — ' . (REGISTRY_SHORT_LABELS[$_GET['type'] ?? ''] ?? ''),
    'report_list'     => 'Liste des fiches — ' . (REGISTRY_SHORT_LABELS[$_GET['type'] ?? ''] ?? ''),
    'report_view'     => 'Signalement', // Will be refined by page file
    'report_edit'     => 'Modifier le signalement',
    'report_abandon'  => 'Abandonner le signalement',
    'report_respond'  => 'Répondre au signalement',
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
