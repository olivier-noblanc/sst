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
     */
    public function redirect(string $url): void
    {
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
            $this->redirect($fallbackUrl);
        }

        $token = $csrfToken ?? ($_POST['csrf_token'] ?? '');
        if (!\validateCsrfToken($token)) {
            \setFlash('error', 'Erreur de sécurité. Veuillez réessayer.');
            $this->redirect($fallbackUrl);
        }

        if ($roles !== null && !empty($roles)) {
            if (!\hasAnyRole($roles)) {
                \setFlash('error', 'Vous n\'avez pas les permissions nécessaires.');
                $this->redirect($this->url('home'));
            }
        }
    }

    /**
     * Set a flash message and redirect in one call.
     */
    public function flashAndRedirect(string $type, string $message, string $url): void
    {
        \setFlash($type, $message);
        $this->redirect($url);
    }
}
