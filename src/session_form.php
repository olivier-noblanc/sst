<?php

use App\DTO\FormData;
use App\Services\SessionService;

/**
 * Session Form Data & Errors — Application SST DREETS BFC
 *
 * Delegates to App\Services\SessionService.
 */

/**
 * Store form data in session for repopulation after validation error.
 *
 * Issue #2 — fini le shim FormData|array : les appelants doivent passer un
 * FormData (via FormData::fromPost($_POST)).
 */
function setFormData(FormData $data): void
{
    SessionService::getInstance()->setFormData($data);
}

function getFormData(): FormData
{
    return SessionService::getInstance()->getFormData();
}

/**
 * @param array<string, string> $errors
 */
function setFormErrors(array $errors): void
{
    SessionService::getInstance()->setFormErrors($errors);
}

/**
 * @return array<string, string>
 */
function getFormErrors(): array
{
    return SessionService::getInstance()->getFormErrors();
}

/**
 * @param array<string, string|null> $errors
 */
function getFieldError(array $errors, string $field): ?string
{
    return SessionService::getInstance()->getFieldError($errors, $field);
}
