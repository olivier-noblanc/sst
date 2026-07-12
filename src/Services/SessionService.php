<?php

/** SessionService — Session management, CSRF, flash messages, form data, impersonation. */

namespace App\Services;

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
     */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Lax');
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                ini_set('session.cookie_secure', '1');
            }
            session_name('SST_SESSION');
            session_start();
        }
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
     */
    public function setUserSession(array $user): void
    {
        $_SESSION['user'] = $user;
    }

    /**
     * Get the current user's full data array from session.
     */
    public function getUserSession(): ?array
    {
        return $_SESSION['user'] ?? null;
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
        return $_SESSION['intended_url'] ?? null;
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
        $_SESSION['user']['role'] = $targetRole;
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
        $_SESSION['user']['role'] = $realRole;
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
        return $_SESSION['impersonated_role'] ?? null;
    }

    /**
     * Get the real role (before impersonation).
     */
    public function getRealRole(): ?string
    {
        return $_SESSION['real_role'] ?? null;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // CSRF token
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Generate a unique per-form CSRF token and store it in the session.
     */
    public function generateCsrfToken(): string
    {
        $this->startSession();
        $token = bin2hex(random_bytes(32));
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        $_SESSION['csrf_tokens'][$token] = time();
        if (count($_SESSION['csrf_tokens']) > 20) {
            $_SESSION['csrf_tokens'] = array_slice($_SESSION['csrf_tokens'], -20, null, true);
        }
        return $token;
    }

    /**
     * Validate a CSRF token and consume it (one-time use).
     */
    public function validateCsrfToken(string $token): bool
    {
        $this->startSession();
        if (empty($token) || !isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }
        unset($_SESSION['csrf_tokens'][$token]);
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
     */
    public function getFlash(): ?array
    {
        if (isset($_SESSION['flash'])) {
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
     */
    public function setFormData(array $data): void
    {
        $_SESSION['form_data'] = $data;
    }

    /**
     * Retrieve and clear stored form data.
     */
    public function getFormData(): array
    {
        if (isset($_SESSION['form_data'])) {
            $data = $_SESSION['form_data'];
            unset($_SESSION['form_data']);
            return $data;
        }
        return [];
    }

    /**
     * Store form errors in session.
     */
    public function setFormErrors(array $errors): void
    {
        $_SESSION['form_errors'] = $errors;
    }

    /**
     * Retrieve and clear stored form errors.
     */
    public function getFormErrors(): array
    {
        if (isset($_SESSION['form_errors'])) {
            $errors = $_SESSION['form_errors'];
            unset($_SESSION['form_errors']);
            return $errors;
        }
        return [];
    }

    /**
     * Get a specific form error for a field.
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
