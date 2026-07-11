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

// ═══════════════════════════════════════════════════════════════════════════════
// Bootstrap — load all core modules
// ═══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/error_handler.php';

// PSR-4 autoloader — must be loaded before helpers (they use classes)
require_once __DIR__ . '/../src/autoload.php';

require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/session.php';
if (defined('DEV_MODE') && DEV_MODE) {
    require_once __DIR__ . '/../src/session_patch.php';
}
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/audit.php';

// Remove unwanted headers (needs autoloader for HttpService)
removeUnwantedHeaders();

// === Override display_errors from DB config (admin toggle) ===
// If app_display_errors is set to '1' in Settings, show errors even in prod.
// This runs BEFORE the error handler is registered, so fatal errors are also visible.
try {
    if (function_exists('getConfig')) {
        $displayErrorsOverride = getConfig('app_display_errors', '');
        if ($displayErrorsOverride === '1') {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        } elseif ($displayErrorsOverride === '0' && !defined('DEV_MODE') || (defined('DEV_MODE') && !DEV_MODE)) {
            // Explicitly disabled in prod — keep errors hidden
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }
    }
} catch (Exception $e) {
    // DB not available yet (first install) — keep default from config.php
}

// Register custom error handler (email critical errors to admin)
set_error_handler('sstErrorHandler');
register_shutdown_function('sstShutdownHandler');

// Validation user (pas dans autoload)
require_once __DIR__ . '/../src/validation_user.php';

// Load bootstrap middleware (superviseur promotion, site check)
require_once __DIR__ . '/../src/Middleware/bootstrap.php';

// Load auth flow (auto-auth, login, logout)
require_once __DIR__ . '/../src/auth_flow.php';

// Load router (page rendering) and routes
require_once __DIR__ . '/../src/router.php';
require_once __DIR__ . '/../src/Router/routes.php';

// ═══════════════════════════════════════════════════════════════════════════════
// Session & Authentication
// ═══════════════════════════════════════════════════════════════════════════════

startSession();

$page = $_GET['page'] ?? 'home';

// Auto-authenticate via IIS (prod) or session
handleAutoAuth();

// Login page handling (dev mode only)
handleLoginPage($page);

// Superviseur promotion check (every request)
checkSuperviseurPromotion();

// Not authenticated: redirect
handleNotAuthenticated();

// ═══════════════════════════════════════════════════════════════════════════════
// Load middleware & CSRF
// ═══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../src/Middleware/require_auth.php';
require_once __DIR__ . '/../src/Middleware/require_role.php';

$csrfToken = generateCsrfToken();

// ═══════════════════════════════════════════════════════════════════════════════
// Choose site (first-time agent)
// ═══════════════════════════════════════════════════════════════════════════════

if ($page === 'choose_site') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require __DIR__ . '/../handlers/choose_site_handler.php';
        exit;
    }
    $pageFile = __DIR__ . '/../pages/choose_site.php';
    if (file_exists($pageFile)) {
        require $pageFile;
    }
    exit;
}

// Check: user must have a site assigned
checkUserSiteAssignment();

// ═══════════════════════════════════════════════════════════════════════════════
// Route dispatch (unified Router)
// ═══════════════════════════════════════════════════════════════════════════════

$router = getRouter();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($router->dispatchPost($page)) {
        exit;
    }
}

if ($page === 'logout') {
    handleLogout();
}

$page = $router->validatePage($page);
$router->dispatchGet($page);
