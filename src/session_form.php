<?php

/**
 * Session Form Data & Errors — Application SST DREETS BFC
 *
 * Form data and error storage for repopulation after validation failure.
 * Split from session.php for readability.
 */

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
