<?php

use App\Services\SessionService;
use App\DTO\SessionUser;

/**
 * Session Management — Application SST DREETS BFC
 *
 * Delegates to App\Services\SessionService.
 */

require_once __DIR__ . '/session_form.php';

function startSession(): void
{
    (SessionService::getInstance())->startSession();
}

function isUserLoggedIn(): bool
{
    return (SessionService::getInstance())->isUserLoggedIn();
}

/**
 * @param SessionUser $user
 */
function setUserSession(SessionUser $user): void
{
    (SessionService::getInstance())->setUserSession($user);
}

/**
 * @return SessionUser|null
 */
function getUserSession(): ?SessionUser
{
    return (SessionService::getInstance())->getUserSession();
}

function clearSession(): void
{
    (SessionService::getInstance())->clearSession();
}

function setIntendedUrl(string $url): void
{
    (SessionService::getInstance())->setIntendedUrl($url);
}

function getIntendedUrl(): ?string
{
    return (SessionService::getInstance())->getIntendedUrl();
}

function clearIntendedUrl(): ?string
{
    return (SessionService::getInstance())->clearIntendedUrl();
}

function startImpersonation(string $realRole, string $targetRole): void
{
    (SessionService::getInstance())->startImpersonation($realRole, $targetRole);
}

function stopImpersonation(): ?string
{
    return (SessionService::getInstance())->stopImpersonation();
}

function isImpersonatingRole(): bool
{
    return (SessionService::getInstance())->isImpersonatingRole();
}

function getImpersonatedRole(): ?string
{
    return (SessionService::getInstance())->getImpersonatedRole();
}

function getRealRole(): ?string
{
    return (SessionService::getInstance())->getRealRole();
}

function generateCsrfToken(): string
{
    return (SessionService::getInstance())->generateCsrfToken();
}

function validateCsrfToken(string $token): bool
{
    return (SessionService::getInstance())->validateCsrfToken($token);
}

function setFlash(string $type, string $message): void
{
    (SessionService::getInstance())->setFlash($type, $message);
}

/**
 * @return array{type: string, message: string}|null
 */
function getFlash(): ?array
{
    return (SessionService::getInstance())->getFlash();
}
