<?php
/**
 * Authentication Flow — Application SST DREETS BFC
 *
 * Auto-authentication, login, and logout handling.
 * Extracted from public/index.php for testability and separation of concerns.
 *
 * These functions handle the complete auth lifecycle:
 *   - handleAutoAuth(): IIS Windows Auth auto-login
 *   - handleLoginPage(): Dev mock login form
 *   - handleNotAuthenticated(): Redirect unauthenticated users
 *   - handleLogout(): Clear session and redirect
 */

// ═══════════════════════════════════════════════════════════════════════════════
// Auto-authentication via IIS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Handle auto-authentication via IIS Windows Authentication.
 *
 * In production, IIS always provides AUTH_USER. If the user is not yet
 * in session, this function authenticates them automatically and triggers
 * lazy cron maintenance tasks.
 */
function handleAutoAuth(): void {
    if (isUserLoggedIn()) {
        return;
    }

    $autoUser = getAuthenticatedUser();
    if ($autoUser) {
        setUserSession($autoUser);

        // Lazy cron: trigger maintenance tasks on login (no system cron)
        require_once __DIR__ . '/cron.php';
        try {
            runLazyCron(getDB());
        } catch (Exception $e) {
            error_log('[SST-CRON] Lazy cron failed on IIS auto-auth: ' . $e->getMessage());
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// Login page (dev mode only)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Handle the login page.
 *
 * In production, the login page is unreachable — IIS handles auth.
 * In dev mode, shows a mock login form for testing.
 * If the request is a POST, delegates to the login handler.
 *
 * This function always calls exit — it never returns.
 *
 * @param string $page  The current page name (must be 'login')
 */
function handleLoginPage(string $page): void {
    if ($page !== 'login') {
        return;
    }

    if (!DEV_MODE) {
        // Prod: login page doesn't make sense, IIS handles auth
        if (isUserLoggedIn()) {
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

    // Show the mock login form
    $pageFile = __DIR__ . '/../pages/login.php';
    if (file_exists($pageFile)) {
        require $pageFile;
    }
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
// Not-authenticated redirect
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Handle the case where the user is not authenticated.
 *
 * In dev: saves the intended URL and redirects to the mock login form.
 * In prod: dies with a configuration error (IIS should have provided AUTH_USER).
 */
function handleNotAuthenticated(): void {
    if (isUserLoggedIn()) {
        return;
    }

    if (DEV_MODE) {
        setIntendedUrl($_SERVER['REQUEST_URI'] ?? '');
        redirect(url('login'));
    } else {
        die('Erreur de configuration : AUTH_USER non disponible. '
          . 'Vérifiez que Windows Authentication est activée dans IIS Manager.');
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// Logout
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Handle logout: clear session, destroy cookie, redirect.
 *
 * In dev: redirects to the mock login form.
 * In prod: redirects to home (IIS will re-authenticate automatically).
 */
function handleLogout(): void {
    // Clear PHP session completely
    clearSession();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();

    if (DEV_MODE) {
        redirect(url('login'));
    } else {
        redirect(url('home'));
    }
}
