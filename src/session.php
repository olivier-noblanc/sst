<?php
/**
 * Session Management — Application SST DREETS BFC
 * 
 * Handles session startup, CSRF token generation/validation,
 * and flash message storage/retrieval.
 */

/**
 * Start the PHP session with secure settings.
 */
function startSession(): void {
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

/**
 * Generate a CSRF token and store it in the session.
 * Returns the token value for inclusion in forms.
 * 
 * @return string
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token against the session-stored token.
 * 
 * @param string $token  The token to validate (from form submission)
 * @return bool
 */
function validateCsrfToken(string $token): bool {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Store a flash message in the session.
 * 
 * @param string $type     Message type: 'success', 'error', 'warning', 'info'
 * @param string $message  The message text
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message from the session.
 * 
 * @return array|null  ['type' => string, 'message' => string] or null
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Store form data in session for repopulation after validation error.
 * 
 * @param array $data  Associative array of form field values
 */
function setFormData(array $data): void {
    $_SESSION['form_data'] = $data;
}

/**
 * Retrieve and clear stored form data.
 * 
 * @return array
 */
function getFormData(): array {
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
 * @param array $errors  Associative array of field => error message
 */
function setFormErrors(array $errors): void {
    $_SESSION['form_errors'] = $errors;
}

/**
 * Retrieve and clear stored form errors.
 * 
 * @return array
 */
function getFormErrors(): array {
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
 * @param array  $errors  The errors array
 * @param string $field   The field name
 * @return string|null
 */
function getFieldError(array $errors, string $field): ?string {
    return $errors[$field] ?? null;
}
