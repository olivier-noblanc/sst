<?php

use App\Services\SessionService;

/**
 * Session Form Data & Errors — Application SST DREETS BFC
 *
 * Delegates to App\Services\SessionService.
 */

function setFormData(array $data): void
{
    SessionService::getInstance()->setFormData($data);
}

function getFormData(): array
{
    return SessionService::getInstance()->getFormData();
}

function setFormErrors(array $errors): void
{
    SessionService::getInstance()->setFormErrors($errors);
}

function getFormErrors(): array
{
    return SessionService::getInstance()->getFormErrors();
}

function getFieldError(array $errors, string $field): ?string
{
    return SessionService::getInstance()->getFieldError($errors, $field);
}
