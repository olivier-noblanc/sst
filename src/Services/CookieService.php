<?php

/** CookieService — HTTP cookie & session cookie parameter handling. */

namespace App\Services;

class CookieService
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Is the current request served over HTTPS?
     * Guards the session cookie 'secure' flag.
     */
    public function isSecureRequest(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    /**
     * Apply secure session cookie parameters (httponly, samesite, secure).
     * Must be called before session_start().
     */
    public function configureSessionCookie(): void
    {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_path', '/');
        if ($this->isSecureRequest()) {
            ini_set('session.cookie_secure', '1');
        }
    }

    /**
     * Remove a legacy session cookie that conflicts with the canonical session name.
     *
     * @param string $legacyName The old session cookie name to clear (e.g. 'PHPSESSID')
     */
    public function clearLegacySessionCookie(string $legacyName): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        if (!isset($_COOKIE[$legacyName])) {
            return;
        }

        unset($_COOKIE[$legacyName]);

        if (headers_sent()) {
            return;
        }

        $params = session_get_cookie_params();
        setcookie(
            $legacyName,
            '',
            [
                'expires'  => time() - 3600,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]
        );
    }
}
