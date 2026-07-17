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

/**
 * @return array<string, mixed>|null
 */
function getAuthenticatedUser(): ?array
{
    return getAuthServiceInstance()->getAuthenticatedUser();
}

function extractUsername(string $authUser): string
{
    return AuthService::extractUsername($authUser);
}

/**
 * @return array<string, mixed>|null
 */
function findOrCreateUser(string $username): ?array
{
    return getAuthServiceInstance()->findOrCreateUser($username);
}

/**
 * @return array<string, mixed>|null
 */
function mockLogin(string $username): ?array
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

function determineProvisionRole(PDO $pdo, string $username): string
{
    return getAuthServiceInstance()->determineRole($username);
}

/**
 * @param array<string, mixed> $user
 * @return array<string, mixed>
 */
function checkAndPromoteUser(PDO $pdo, array $user, string $username): array
{
    return getAuthServiceInstance()->checkAndPromote($user, $username);
}
