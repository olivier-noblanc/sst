<?php

namespace App\Services;

/**
 * SessionManager — OOP wrapper for session functionality.
 *
 * Wraps all global session functions from session.php and session_form.php
 * to provide a clean, injectable service interface.
 *
 * The global functions remain as thin wrappers for backward compatibility.
 */
class SessionManager
{
    // ═══════════════════════════════════════════════════════════════════════════════
    // Session startup
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Start the PHP session with secure settings.
     */
    public function start(): void
    {
        startSession();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // User session — authentication state
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Check if a user is currently logged in.
     */
    public function isLoggedIn(): bool
    {
        return isUserLoggedIn();
    }

    /**
     * Store the full user data array in session.
     *
     * @param UserArray $user User data from DB
     */
    public function setUser(array $user): void
    {
        setUserSession($user);
    }

    /**
     * Get the current user's full data array from session.
     *
     * @return UserArray|null The user array or null if not authenticated
     */
    public function getUser(): ?array
    {
        return getUserSession();
    }

    /**
     * Clear the entire session (used for logout).
     */
    public function clear(): void
    {
        clearSession();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Intended URL — redirect after login
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Store the intended URL for post-login redirect.
     *
     * @param string $url The URL to redirect to after login
     */
    public function setIntendedUrl(string $url): void
    {
        setIntendedUrl($url);
    }

    /**
     * Get the intended URL (without clearing it).
     *
     * @return string|null The URL or null
     */
    public function getIntendedUrl(): ?string
    {
        return getIntendedUrl();
    }

    /**
     * Get and clear the intended URL.
     *
     * @return string|null The URL or null
     */
    public function clearIntendedUrl(): ?string
    {
        return clearIntendedUrl();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Impersonation — role switching by superviseur
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Start impersonating a different role.
     *
     * @param string $realRole The user's real role (before impersonation)
     * @param string $targetRole The role to impersonate
     */
    public function startImpersonation(string $realRole, string $targetRole): void
    {
        startImpersonation($realRole, $targetRole);
    }

    /**
     * Stop impersonation and restore the real role.
     *
     * @return string|null The restored real role, or null if not impersonating
     */
    public function stopImpersonation(): ?string
    {
        return stopImpersonation();
    }

    /**
     * Check if the user is currently impersonating a role.
     */
    public function isImpersonating(): bool
    {
        return isImpersonatingRole();
    }

    /**
     * Get the impersonated role (or null if not impersonating).
     *
     * @return string|null
     */
    public function getImpersonatedRole(): ?string
    {
        return getImpersonatedRole();
    }

    /**
     * Get the real role (before impersonation).
     *
     * @return string|null The real role, or null if not impersonating
     */
    public function getRealRole(): ?string
    {
        return getRealRole();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // CSRF token
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Generate a unique per-form CSRF token and store it in the session.
     *
     * @return string
     */
    public function generateCsrfToken(): string
    {
        return generateCsrfToken();
    }

    /**
     * Validate a CSRF token and consume it (one-time use).
     *
     * @param string $token The token to validate (from form submission)
     */
    public function validateCsrfToken(string $token): bool
    {
        return validateCsrfToken($token);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Flash messages
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Store a flash message in the session.
     *
     * @param string $type Message type: 'success', 'error', 'warning', 'info'
     * @param string $message The message text
     */
    public function setFlash(string $type, string $message): void
    {
        SessionService::getInstance()->setFlash($type, $message);
    }

    /**
     * Retrieve and clear the flash message from the session.
     *
     * @return array{type: string, message: string}|null
     */
    public function getFlash(): ?array
    {
        return getFlash();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Form data
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Store form data in session for repopulation after validation error.
     *
     * @param array<string, mixed> $data Associative array of form field values
     */
    public function setFormData(array $data): void
    {
        setFormData($data);
    }

    /**
     * Retrieve and clear stored form data.
     *
     * @return array<string, mixed>
     */
    public function getFormData(): array
    {
        return getFormData();
    }

    /**
     * Store form errors in session.
     *
     * @param array<string, string> $errors Associative array of field => error message
     */
    public function setFormErrors(array $errors): void
    {
        setFormErrors($errors);
    }

    /**
     * Retrieve and clear stored form errors.
     *
     * @return array<string, string>
     */
    public function getFormErrors(): array
    {
        return getFormErrors();
    }

    /**
     * Get a specific form error for a field.
     *
     * @param array<string, string> $errors The errors array
     * @param string $field The field name
     * @return string|null
     */
    public function getFieldError(array $errors, string $field): ?string
    {
        return getFieldError($errors, $field);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Refresh user from DB
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Refresh the current user's session data from the database.
     * Useful after role/site changes to keep session in sync.
     *
     * @return bool True if refresh succeeded
     */
    public function refreshUser(): bool
    {
        $pdo = getDB();
        return refreshCurrentUser($pdo);
    }
}
