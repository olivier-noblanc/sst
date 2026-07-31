<?php

use App\DTO\SessionUser;
use App\Services\AuthService;

/**
 * Authentication — Application SST DREETS BFC
 *
 * Delegates to App\Services\AuthService.
 */

function getAuthServiceInstance(): AuthService
{
    return getContainer()->get(AuthService::class);
}

function getAuthenticatedUser(): ?SessionUser
{
    return getAuthServiceInstance()->getAuthenticatedUser();
}

function extractUsername(string $authUser): string
{
    return AuthService::extractUsername($authUser);
}

function findOrCreateUser(string $username): ?SessionUser
{
    return getAuthServiceInstance()->findOrCreateUser($username);
}

function mockLogin(string $username): ?SessionUser
{
    return getAuthServiceInstance()->mockLogin($username);
}

/**
 * @return list<string>
 */
function parseSuperviseurUsernames(string $list): array
{
    return AuthService::parseSuperviseurUsernames($list);
}
