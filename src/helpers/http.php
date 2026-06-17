<?php

/**
 * HTTP & Response Helpers — Application SST DREETS BFC
 *
 * URL building, redirects, file downloads, header management,
 * POST validation, and flash/redirect shortcuts.
 * Extracted from helpers.php for single-responsibility clarity.
 */

/**
 * Build an internal application URL.
 * On IIS: index.php?page=...
 * Via Caddy gateway (dev): index.php?XTransformPort=...&page=...
 *
 * @param string $page   Page name (e.g. 'home', 'report_view')
 * @param array<string, mixed> $params Additional query parameters (e.g. ['id' => 5, 'type' => 'rsst'])
 * @return string
 */
function url(string $page, array $params = []): string
{
    $queryParams = [];
    if (isset($_GET['XTransformPort'])) {
        $queryParams['XTransformPort'] = $_GET['XTransformPort'];
    }
    $queryParams['page'] = $page;
    foreach ($params as $key => $value) {
        $queryParams[$key] = $value;
    }
    return 'index.php?' . http_build_query($queryParams);
}

/**
 * HTTP redirect and exit.
 * Also sets a global variable for CLI/proxy mode where header() is a no-op.
 *
 * @param string $url  The URL to redirect to
 */
function redirect(string $url): void
{
    // Store redirect info for CLI/proxy mode
    $GLOBALS['_PHP_REDIRECT'] = $url;
    header('Location: ' . $url);
    exit;
}

/**
 * Set a cookie (works in both web and CLI/proxy mode).
 */
function setCookieSafe(string $name, string $value = '', int $expires = 0, string $path = '/', bool $httpOnly = true, string $sameSite = 'Lax'): void
{
    $cookieStr = $name . '=' . urlencode($value);
    if ($expires > 0) {
        $cookieStr .= '; expires=' . gmdate('D, d M Y H:i:s T', $expires);
    }
    if ($path) {
        $cookieStr .= '; path=' . $path;
    }
    if ($sameSite) {
        $cookieStr .= '; SameSite=' . $sameSite;
    }
    if ($httpOnly) {
        $cookieStr .= '; HttpOnly';
    }

    $GLOBALS['_PHP_COOKIES'][] = $cookieStr;
    header('Set-Cookie: ' . $cookieStr);
}

/**
 * Remove unwanted HTTP headers (X-Powered-By, Server, Expires, Pragma).
 *
 * These headers disclose server information or use deprecated caching mechanisms.
 * Called once at bootstrap (index.php) instead of being repeated in every file.
 * Before this function existed, the same 4 header_remove() calls were duplicated
 * in 10 different files (index.php, header.php, asset.php, router.php, etc.).
 */
function removeUnwantedHeaders(): void
{
    header_remove('X-Powered-By');
    header_remove('Server');
    header_remove('Expires');
    header_remove('Pragma');
}

/**
 * Send a file download response and exit.
 *
 * Centralises the "clear output buffer + set headers + output content + exit" pattern
 * that was duplicated in 4 files (export_handler, user_edit_handler, report_print,
 * report_attachment). All those files had the same ~15 lines of boilerplate.
 *
 * @param string $content     Binary or text content to send
 * @param string $filename    Download filename
 * @param string $contentType MIME type (e.g. 'text/csv; charset=utf-8')
 * @param string $disposition 'attachment' (default) or 'inline' (for browser preview)
 */
function sendFileDownload(string $content, string $filename, string $contentType, string $disposition = 'attachment'): void
{
    // Disable gzip output buffer for binary/raw file output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    removeUnwantedHeaders();

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '\\"', $filename) . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-cache');
    header('X-Content-Type-Options: nosniff');

    echo $content;
    exit;
}

/**
 * Validate that the request is a POST with a valid CSRF token, and optionally
 * that the current user has one of the required roles.
 *
 * This function centralises the boilerplate that was repeated at the top of
 * every handler (POST check → CSRF check → role check → redirect on failure).
 * It calls redirect() + exit on failure, so code after this call only runs
 * if all checks pass.
 *
 * Usage in a handler:
 *   validatePostRequest(url('home'));                           // POST + CSRF only
 *   validatePostRequest(url('home'), ['superviseur']);          // + role check
 *   validatePostRequest(url('home'), ['superviseur', 'chsct']); // + multi-role
 *
 * @param string      $fallbackUrl  URL to redirect to on POST/CSRF failure
 * @param array<int, string>|null $roles        If non-empty, requires one of these roles
 * @param string|null $csrfToken    Override CSRF token source (default: $_POST['csrf_token'])
 */
function validatePostRequest(string $fallbackUrl, ?array $roles = null, ?string $csrfToken = null): void
{
    // 1. Must be POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect($fallbackUrl);
    }

    // 2. CSRF token validation
    $token = $csrfToken ?? ($_POST['csrf_token'] ?? '');
    if (!validateCsrfToken($token)) {
        setFlash('error', 'Erreur de sécurité. Veuillez réessayer.');
        redirect($fallbackUrl);
    }

    // 3. Role check (optional)
    if ($roles !== null && !empty($roles)) {
        if (!hasAnyRole($roles)) {
            setFlash('error', 'Vous n\'avez pas les permissions nécessaires.');
            redirect(url('home'));
        }
    }
}

/**
 * Set a flash message and redirect in one call.
 *
 * Reduces the repeated setFlash() + redirect() 2-line pattern
 * that appears 80+ times across all handlers.
 *
 * @param string $type    Flash type: 'success', 'error', 'warning', 'info'
 * @param string $message Flash message text
 * @param string $url     Redirect URL
 */
function flashAndRedirect(string $type, string $message, string $url): void
{
    setFlash($type, $message);
    redirect($url);
}
