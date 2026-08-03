<?php

use App\DTO\FormData;
use App\Services\SessionService;

/**
 * Session Form Data & Errors — Application SST DREETS BFC
 *
 * Delegates to App\Services\SessionService.
 */

/**
 * @param FormData|array<string, mixed> $data
 */
function setFormData(FormData|array $data): void
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
