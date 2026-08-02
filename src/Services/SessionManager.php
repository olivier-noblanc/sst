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
    // User session — authentication state
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Check if a user is currently logged in.
     */
    public function isLoggedIn(): bool
    {
        return isUserLoggedIn();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Intended URL — redirect after login
    // ═══════════════════════════════════════════════════════════════════════════════

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
}
