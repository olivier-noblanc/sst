<?php

/** SessionService — Session management, CSRF, flash messages, form data, impersonation. */

namespace App\Services;

/**
 * @phpstan-import-type UserArray from \App\Repository\UserRepository
 */
class SessionService
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    // ═══════════════════════════════════════════════════════════════════════════════
    // Session startup
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Start the PHP session with secure settings.
     *
     * Clears any legacy PHPSESSID cookie to prevent session fragmentation
     * when the canonical session name is SST_SESSION.
     */
    public function startSession(): void
    {
        $this->clearLegacySessionCookie('PHPSESSID');

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                ini_set('session.cookie_secure', '1');
            }
            // Force garbage collection settings explicitly — don't rely on
            // the server's php.ini. Debian/Ubuntu-packaged PHP sets
            // gc_probability=0 by default (cleanup handled by an external
            // cron job instead, see /etc/cron.d/php*) — this app runs on
            // Windows/IIS with no such cron, so if the deployed php.ini
            // ever mirrors that convention (or is otherwise misconfigured),
            // SQLiteSessionHandler::gc() would simply never run and the
            // sessions table would grow forever, silently, with no
            // application-level signal that anything is wrong.
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
            ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24)); // 24h
            session_name('SST_SESSION');

            session_start();
        }
    }

    /**
     * Remove a legacy session cookie that conflicts with the canonical session name.
     *
     * @param string $legacyName The old session cookie name to clear (e.g. 'PHPSESSID')
     */
    private function clearLegacySessionCookie(string $legacyName): void
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
                'httponly'  => $params['httponly'],
                'samesite' => $params['samesite'],
            ]
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // User session — authentication state
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Check if a user is currently logged in.
     */
    public function isUserLoggedIn(): bool
    {
        return isset($_SESSION['user']);
    }

    /**
     * Store the full user data array in session.
     * @param UserArray $user
     */
    public function setUserSession(array $user): void
    {
        $_SESSION['user'] = $user;
    }

    /**
     * Get the current user's full data array from session.
     *
     * @return UserArray|null
     */
    public function getUserSession(): ?array
    {
        /** @var UserArray|null $user */
        $user = $_SESSION['user'] ?? null;
        return $user;
    }

    /**
     * Clear the entire session (used for logout).
     */
    public function clearSession(): void
    {
        $_SESSION = [];
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Intended URL — redirect after login
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Store the intended URL for post-login redirect.
     */
    public function setIntendedUrl(string $url): void
    {
        $_SESSION['intended_url'] = $url;
    }

    /**
     * Get the intended URL (without clearing it).
     */
    public function getIntendedUrl(): ?string
    {
        $url = $_SESSION['intended_url'] ?? null;
        return $url;
    }

    /**
     * Get and clear the intended URL.
     */
    public function clearIntendedUrl(): ?string
    {
        $url = $_SESSION['intended_url'] ?? null;
        unset($_SESSION['intended_url']);
        return $url;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Impersonation — role switching by superviseur
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Start impersonating a different role.
     */
    public function startImpersonation(string $realRole, string $targetRole): void
    {
        $_SESSION['real_role'] = $realRole;
        $_SESSION['impersonated_role'] = $targetRole;
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['role'] = $targetRole;
        }
        // Audit #33 — regenerate session ID on impersonation start.
        // Prevents session fixation: if an attacker steals the session cookie
        // before impersonation starts, they can't hijack the impersonated role.
        // Same logic as login — session ID changes, old cookie is invalidated.
        if (function_exists('safeSessionRegenerate')) {
            \safeSessionRegenerate();
        }
    }

    /**
     * Stop impersonation and restore the real role.
     */
    public function stopImpersonation(): ?string
    {
        if (!isset($_SESSION['real_role'])) {
            return null;
        }
        $realRole = $_SESSION['real_role'];
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['role'] = $realRole;
        }
        unset($_SESSION['real_role']);
        unset($_SESSION['impersonated_role']);
        return $realRole;
    }

    /**
     * Check if the user is currently impersonating a role.
     */
    public function isImpersonatingRole(): bool
    {
        return isset($_SESSION['real_role']);
    }

    /**
     * Get the impersonated role (or null if not impersonating).
     */
    public function getImpersonatedRole(): ?string
    {
        $role = $_SESSION['impersonated_role'] ?? null;
        return $role;
    }

    /**
     * Get the real role (before impersonation).
     */
    public function getRealRole(): ?string
    {
        $role = $_SESSION['real_role'] ?? null;
        return $role;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // CSRF token
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Generate a unique per-form CSRF token and store it in the session.
     *
     * Audit #28 — Before this fix, the limit was 20 tokens. If a user had
     * multiple tabs open (e.g. report_create, report_edit, settings), the
     * oldest CSRF tokens were silently evicted → "Erreur de sécurité" on
     * submit with no explanation. Now the limit is 50 (enough for ~25 tabs
     * with 2 forms each) and a warning is logged when eviction happens.
     */
    public function generateCsrfToken(): string
    {
        $this->startSession();
        $token = bin2hex(random_bytes(32));
        /** @var array<string, int> $tokens */
        $tokens = is_array($_SESSION['csrf_tokens'] ?? null) ? $_SESSION['csrf_tokens'] : [];
        $tokens[$token] = time();
        $limit = 50;
        if (count($tokens) > $limit) {
            $evicted = count($tokens) - $limit;
            error_log("[SST-CSRF] Evicting {$evicted} old CSRF token(s) — limit={$limit}. User may have many tabs open.");
            $tokens = array_slice($tokens, -$limit, null, true);
        }
        $_SESSION['csrf_tokens'] = $tokens;
        return $token;
    }

    /**
     * Validate a CSRF token and consume it (one-time use).
     */
    public function validateCsrfToken(string $token): bool
    {
        $this->startSession();
        /** @var array<string, int> $tokens */
        $tokens = is_array($_SESSION['csrf_tokens'] ?? null) ? $_SESSION['csrf_tokens'] : [];
        if (empty($token) || !isset($tokens[$token])) {
            return false;
        }
        unset($tokens[$token]);
        $_SESSION['csrf_tokens'] = $tokens;
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Flash messages
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Store a flash message in the session.
     */
    public function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Retrieve and clear the flash message from the session.
     *
     * @return array{type: string, message: string}|null
     */
    public function getFlash(): ?array
    {
        if (isset($_SESSION['flash'])) {
            /** @var array{type: string, message: string} $flash */
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Form data & errors (from session_form.php)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Store form data in session for repopulation after validation error.
     * @param array<string, mixed> $data
     */
    public function setFormData(array $data): void
    {
        $_SESSION['form_data'] = $data;
    }

    /**
     * Retrieve and clear stored form data.
     *
     * @return array<string, mixed>
     */
    public function getFormData(): array
    {
        if (isset($_SESSION['form_data'])) {
            /** @var array<string, mixed> $data */
            $data = $_SESSION['form_data'];
            unset($_SESSION['form_data']);
            return $data;
        }
        return [];
    }

    /**
     * Store form errors in session.
     * @param array<string, string> $errors
     */
    public function setFormErrors(array $errors): void
    {
        $_SESSION['form_errors'] = $errors;
    }

    /**
     * Retrieve and clear stored form errors.
     *
     * @return array<string, string>
     */
    public function getFormErrors(): array
    {
        if (isset($_SESSION['form_errors'])) {
            /** @var array<string, string> $errors */
            $errors = $_SESSION['form_errors'];
            unset($_SESSION['form_errors']);
            return $errors;
        }
        return [];
    }

    /**
     * Get a specific form error for a field.
     * @param array<string, string|null> $errors
     */
    public function getFieldError(array $errors, string $field): ?string
    {
        return $errors[$field] ?? null;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Session patch (from session_patch.php)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Safe session regeneration that works with PHP built-in server.
     */
    public function safeSessionRegenerate(): void
    {
        session_regenerate_id(!DEV_MODE);
    }
}
