<?php

/** HttpService — URL building, redirects, file downloads, header management, POST validation. */

namespace App\Services;

class HttpService
{
    /**
     * Build an internal application URL.
     *
     * @param string $page   Page name (e.g. 'home', 'report_view')
     * @param array<string, mixed> $params Additional query parameters
     * @return string
     */
    public function url(string $page, array $params = []): string
    {
        $queryParams = [];
        // Audit #94 — XTransformPort is an IIS Express dev-only parameter
        // (used by Visual Studio to track which port the dev server runs on).
        // Before this fix, it was propagated to every URL the app generated,
        // polluting production URLs and confusing users. Now only propagated
        // in DEV_MODE.
        if (defined('DEV_MODE') && DEV_MODE && isset($_GET['XTransformPort'])) {
            $queryParams['XTransformPort'] = $_GET['XTransformPort'];
        }
        $queryParams['page'] = $page;
        foreach ($params as $key => $value) {
            $queryParams[$key] = $value;
        }
        // Explicit separator: http_build_query() otherwise falls back to
        // the server's php.ini arg_separator.output setting, which some
        // PHP configurations set to "&amp;" (an old "HTML-safe by default"
        // convention) — producing literal "&amp;" text in every URL this
        // app generates, not just emails, and breaking every link with more
        // than one query parameter. Don't depend on server configuration.
        return 'index.php?' . http_build_query($queryParams, '', '&');
    }

    /**
     * HTTP redirect and exit.
     *
     * Appends `result=<flash type>` to the redirect URL whenever a flash
     * message was just set (setFlash() before this call) — purely a debug
     * aid: inert for routing/behavior (nothing reads this query param),
     * but makes success vs failure visible directly in the Location
     * header — from curl -v, browser devtools, or server access logs —
     * without needing to inspect the rendered page or session content.
     * Without it, two very different outcomes (e.g. settings saved vs.
     * rejected by validation) can redirect to the exact same URL, making
     * them indistinguishable from the outside.
     */
    public function redirect(string $url): void
    {
        if (isset($_SESSION['flash']['type']) && is_string($_SESSION['flash']['type'])) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . 'result=' . rawurlencode($_SESSION['flash']['type']);
        }
        $GLOBALS['_PHP_REDIRECT'] = $url;
        header('Location: ' . $url);
        exit;
    }

    /**
     * Remove unwanted HTTP headers and set security headers.
     */
    public function removeUnwantedHeaders(): void
    {
        header_remove('X-Powered-By');
        header_remove('Server');
        header_remove('Expires');
        header_remove('Pragma');

        // Security headers
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Send a file download response and exit.
     */
    public function sendFileDownload(string $content, string $filename, string $contentType, string $disposition = 'attachment'): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $this->removeUnwantedHeaders();

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '\\"', $filename) . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache');
        header('X-Content-Type-Options: nosniff');

        echo $content;
        exit;
    }

    /**
     * Validate that the request is a POST with a valid CSRF token.
     *
     * @param list<string>|null $roles
     */
    public function validatePostRequest(string $fallbackUrl, ?array $roles = null, ?string $csrfToken = null): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log('[SST-HTTP] validatePostRequest: non-POST request reached a POST-only handler (method=' . $_SERVER['REQUEST_METHOD'] . ', fallback=' . $fallbackUrl . ')');
            $this->redirect($fallbackUrl);
        }

        // PHP silently empties $_POST and $_FILES (no error, nothing to catch)
        // when the request body exceeds php.ini's post_max_size — this looks
        // identical to an empty form submission or a CSRF failure downstream,
        // which is confusing and gives no actionable signal. Detect it here
        // via CONTENT_LENGTH (still populated even though $_POST is empty)
        // and surface a specific message instead.
        if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            error_log('[SST-HTTP] validatePostRequest: POST body silently truncated by PHP (content_length=' . ($_SERVER['CONTENT_LENGTH'] ?? '?') . ', post_max_size=' . ini_get('post_max_size') . ')');
            SessionService::getInstance()->setFlash('error', 'Le fichier joint est trop volumineux pour être envoyé. Réduisez sa taille et réessayez.');
            $this->redirect($fallbackUrl);
        }

        $token = $csrfToken ?? ($_POST['csrf_token'] ?? '');
        if (!\validateCsrfToken($token)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $caller = $trace[1]['file'] ?? ($trace[0]['file'] ?? 'unknown');
            error_log('[SST-HTTP] validatePostRequest: CSRF token rejected (token_present=' . ($token !== '' ? 'yes' : 'no') . ', fallback=' . $fallbackUrl . ', called_from=' . $caller . ', post_keys=' . implode(',', array_keys($_POST)) . ')');
            SessionService::getInstance()->setFlash('error', 'Erreur de sécurité. Veuillez réessayer.');
            $this->redirect($fallbackUrl);
        }

        if ($roles !== null && !empty($roles)) {
            if (!\hasAnyRole($roles)) {
                error_log('[SST-HTTP] validatePostRequest: role check failed (required=' . implode(',', $roles) . ')');
                SessionService::getInstance()->setFlash('error', 'Vous n\'avez pas les permissions nécessaires.');
                $this->redirect($this->url('home'));
            }
        }
    }

    /**
     * Set a flash message and redirect in one call.
     */
    public function flashAndRedirect(string $type, string $message, string $url): void
    {
        SessionService::getInstance()->setFlash($type, $message);
        $this->redirect($url);
    }
}
