<?php

use App\Services\SessionService;

/**
 * Session Form Data & Errors — Application SST DREETS BFC
 *
 * Delegates to App\Services\SessionService.
 */

function setFormData(array $data): void
{
    (new SessionService())->setFormData($data);
}

function getFormData(): array
{
    return (new SessionService())->getFormData();
}

function setFormErrors(array $errors): void
{
    (new SessionService())->setFormErrors($errors);
}

function getFormErrors(): array
{
    return (new SessionService())->getFormErrors();
}

function getFieldError(array $errors, string $field): ?string
{
    return (new SessionService())->getFieldError($errors, $field);
}
