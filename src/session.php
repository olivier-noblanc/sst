<?php

/**
 * Session Management — Application SST DREETS BFC
 *
 * Handles session startup, CSRF token generation/validation,
 * flash message storage/retrieval, and session state access.
 *
 * All $_SESSION access is centralized through these functions.
 * No other file should read or write $_SESSION directly.
 */

// ═══════════════════════════════════════════════════════════════════════════════
// Session startup
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Start the PHP session with secure settings.
 */
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        // Set Secure flag ONLY if the request is actually HTTPS
        // The app may run on HTTP in intranet environments (IIS without SSL)
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
 *
 * @return bool
 */
function isUserLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

/**
 * Store the full user data array in session.
 *
 * @param array<string, mixed> $user  User data from DB
 */
function setUserSession(array $user): void
{
    $_SESSION['user'] = $user;
}

/**
 * Get the current user's full data array from session.
 *
 * @return array<string, mixed>|null  The user array or null if not authenticated
 */
function getUserSession(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Clear the entire session (used for logout).
 */
function clearSession(): void
{
    $_SESSION = [];
}

// ═══════════════════════════════════════════════════════════════════════════════
// Intended URL — redirect after login
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Store the intended URL for post-login redirect.
 *
 * @param string $url  The URL to redirect to after login
 */
function setIntendedUrl(string $url): void
{
    $_SESSION['intended_url'] = $url;
}

/**
 * Get the intended URL (without clearing it).
 *
 * @return string|null  The URL or null
 */
function getIntendedUrl(): ?string
{
    return $_SESSION['intended_url'] ?? null;
}

/**
 * Get and clear the intended URL.
 *
 * @return string|null  The URL or null
 */
function clearIntendedUrl(): ?string
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
 * Saves the real role and switches the user's effective role.
 *
 * @param string $realRole    The user's real role (before impersonation)
 * @param string $targetRole  The role to impersonate
 */
function startImpersonation(string $realRole, string $targetRole): void
{
    $_SESSION['real_role'] = $realRole;
    $_SESSION['impersonated_role'] = $targetRole;
    $_SESSION['user']['role'] = $targetRole;
}

/**
 * Stop impersonation and restore the real role.
 *
 * @return string|null  The restored real role, or null if not impersonating
 */
function stopImpersonation(): ?string
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
 *
 * @return bool
 */
function isImpersonatingRole(): bool
{
    return isset($_SESSION['real_role']);
}

/**
 * Get the impersonated role (or null if not impersonating).
 *
 * @return string|null
 */
function getImpersonatedRole(): ?string
{
    return $_SESSION['impersonated_role'] ?? null;
}

/**
 * Get the real role (before impersonation).
 *
 * @return string|null  The real role, or null if not impersonating
 */
function getRealRole(): ?string
{
    return $_SESSION['real_role'] ?? null;
}

// ═══════════════════════════════════════════════════════════════════════════════
// CSRF token
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Generate a unique per-form CSRF token and store it in the session.
 * Each call produces a new token; multiple tokens can be valid simultaneously
 * (one per open form/tab). Tokens are one-time use — see validateCsrfToken().
 *
 * Garbage collection keeps only the last 20 tokens to prevent session bloat.
 *
 * @return string
 */
function generateCsrfToken(): string
{
    startSession();
    $token = bin2hex(random_bytes(32));
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    $_SESSION['csrf_tokens'][$token] = time();
    // Garbage collection: keep only last 20 tokens
    if (count($_SESSION['csrf_tokens']) > 20) {
        $_SESSION['csrf_tokens'] = array_slice($_SESSION['csrf_tokens'], -20, null, true);
    }
    return $token;
}

/**
 * Validate a CSRF token and consume it (one-time use).
 * The token is removed from the session after successful validation
 * so it cannot be replayed.
 *
 * @param string $token  The token to validate (from form submission)
 * @return bool
 */
function validateCsrfToken(string $token): bool
{
    startSession();
    if (empty($token) || !isset($_SESSION['csrf_tokens'][$token])) {
        return false;
    }
    // Consume the token (one-time use)
    unset($_SESSION['csrf_tokens'][$token]);
    return true;
}

// ═══════════════════════════════════════════════════════════════════════════════
// Flash messages
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Store a flash message in the session.
 *
 * @param string $type     Message type: 'success', 'error', 'warning', 'info'
 * @param string $message  The message text
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message from the session.
 *
 * @return array{type: string, message: string}|null  ['type' => string, 'message' => string] or null
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ═══════════════════════════════════════════════════════════════════════════════
// Form data & errors (repopulation after validation failure)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Store form data in session for repopulation after validation error.
 *
 * @param array<string, mixed> $data  Associative array of form field values
 */
function setFormData(array $data): void
{
    $_SESSION['form_data'] = $data;
}

/**
 * Retrieve and clear stored form data.
 *
 * @return array<string, mixed>
 */
function getFormData(): array
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
 *
 * @param array<string, string> $errors  Associative array of field => error message
 */
function setFormErrors(array $errors): void
{
    $_SESSION['form_errors'] = $errors;
}

/**
 * Retrieve and clear stored form errors.
 *
 * @return array<string, string>
 */
function getFormErrors(): array
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
 *
 * @param array<string, string> $errors  The errors array
 * @param string $field   The field name
 * @return string|null
 */
function getFieldError(array $errors, string $field): ?string
{
    return $errors[$field] ?? null;
}
