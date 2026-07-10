<?php

use App\Services\SessionService;

/**
 * Session Management — Application SST DREETS BFC
 *
 * Delegates to App\Services\SessionService.
 */

require_once __DIR__ . '/session_form.php';

function startSession(): void
{
    (new SessionService())->startSession();
}

function isUserLoggedIn(): bool
{
    return (new SessionService())->isUserLoggedIn();
}

function setUserSession(array $user): void
{
    (new SessionService())->setUserSession($user);
}

function getUserSession(): ?array
{
    return (new SessionService())->getUserSession();
}

function clearSession(): void
{
    (new SessionService())->clearSession();
}

function setIntendedUrl(string $url): void
{
    (new SessionService())->setIntendedUrl($url);
}

function getIntendedUrl(): ?string
{
    return (new SessionService())->getIntendedUrl();
}

function clearIntendedUrl(): ?string
{
    return (new SessionService())->clearIntendedUrl();
}

function startImpersonation(string $realRole, string $targetRole): void
{
    (new SessionService())->startImpersonation($realRole, $targetRole);
}

function stopImpersonation(): ?string
{
    return (new SessionService())->stopImpersonation();
}

function isImpersonatingRole(): bool
{
    return (new SessionService())->isImpersonatingRole();
}

function getImpersonatedRole(): ?string
{
    return (new SessionService())->getImpersonatedRole();
}

function getRealRole(): ?string
{
    return (new SessionService())->getRealRole();
}

function generateCsrfToken(): string
{
    return (new SessionService())->generateCsrfToken();
}

function validateCsrfToken(string $token): bool
{
    return (new SessionService())->validateCsrfToken($token);
}

function setFlash(string $type, string $message): void
{
    (new SessionService())->setFlash($type, $message);
}

function getFlash(): ?array
{
    return (new SessionService())->getFlash();
}
