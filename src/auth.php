<?php

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

function getAuthenticatedUser(): ?array
{
    return getAuthServiceInstance()->getAuthenticatedUser();
}

function extractUsername(string $authUser): string
{
    return AuthService::extractUsername($authUser);
}

function findOrCreateUser(string $username): ?array
{
    return getAuthServiceInstance()->findOrCreateUser($username);
}

function mockLogin(string $username): ?array
{
    return getAuthServiceInstance()->mockLogin($username);
}

function parseSuperviseurUsernames(string $list): array
{
    return AuthService::parseSuperviseurUsernames($list);
}

function determineProvisionRole(PDO $pdo, string $username): string
{
    return getAuthServiceInstance()->determineRole($username);
}

function checkAndPromoteUser(PDO $pdo, array $user, string $username): array
{
    return getAuthServiceInstance()->checkAndPromote($user, $username);
}
