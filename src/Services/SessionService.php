<?php

/** SessionService — Session management, CSRF, flash messages, form data, impersonation. */

namespace App\Services;

use App\DTO\FlashMessage;
use App\DTO\FormData;
use App\DTO\SessionUser;

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
     * Store the full user data in session.
     */
    public function setUserSession(SessionUser $user): void
    {
        $_SESSION['user'] = $user->toArray();
    }

    /**
     * Get the current user's full data from session.
     */
    public function getUserSession(): ?SessionUser
    {
        $data = $_SESSION['user'] ?? null;
        if (!is_array($data)) {
            return null;
        }
        return SessionUser::fromSession($data);
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
        $data = $_SESSION['user'] ?? null;
        if (is_array($data)) {
            $user = SessionUser::fromSession($data);
            $_SESSION['user'] = $user->withRole($targetRole)->toArray();
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
        $data = $_SESSION['user'] ?? null;
        if (is_array($data)) {
            $user = SessionUser::fromSession($data);
            $_SESSION['user'] = $user->withRole($realRole)->toArray();
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
     * with 2 forms each) and a warning is logged once per session when
     * eviction starts happening.
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
            $tokens = array_slice($tokens, -$limit, null, true);
            // Log eviction warning only once per session to avoid log spam
            if (empty($_SESSION['csrf_eviction_logged'])) {
                error_log("[SST-CSRF] Evicting {$evicted} old CSRF token(s) — limit={$limit}. User may have many tabs open.");
                $_SESSION['csrf_eviction_logged'] = true;
            }
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
        $_SESSION['flash'] = new FlashMessage($type, $message)->toArray();
    }

    /**
     * Retrieve and clear the flash message from the session.
     */
    public function getFlash(): ?FlashMessage
    {
        if (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) {
            /** @var array{type?: mixed, message?: mixed} $raw */
            $raw = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return FlashMessage::fromSession($raw);
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Form data & errors (from session_form.php)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Store form data in session for repopulation after validation error.
     *
     * Issue #2 — fini le shim FormData|array : tous les appelants passent
     * désormais un FormData (via FormData::fromPost($_POST)). Un array brut
     * doit lever TypeError plutôt que de contourner silencieusement le DTO.
     */
    public function setFormData(FormData $data): void
    {
        $_SESSION['form_data'] = $data->toArray();
    }

    /**
     * Retrieve and clear stored form data.
     */
    public function getFormData(): FormData
    {
        if (isset($_SESSION['form_data']) && is_array($_SESSION['form_data'])) {
            /** @var array<string, mixed> $data */
            $data = $_SESSION['form_data'];
            unset($_SESSION['form_data']);
            return FormData::fromSession($data);
        }
        return new FormData();
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
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(!DEV_MODE);
        }
    }
}
