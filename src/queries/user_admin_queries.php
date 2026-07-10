<?php

/**
 * User Admin Queries — Application SST DREETS BFC
 *
 * Admin functions for managing users.
 * Split from user_queries.php for readability.
 *
 * All functions delegate to App\Repository\UserRepository.
 */

require_once __DIR__ . '/user_gdpr_queries.php';

use App\Repository\UserRepository;

/**
 * Update a user's role.
 *
 * @param PDO    $pdo   Database connection
 * @param int    $id    User ID
 * @param string $role  New role
 * @return bool
 */
function updateUserRole(PDO $pdo, int $id, string $role): bool
{
    return UserRepository::instance()->updateRole($id, $role);
}

/**
 * Update a user's site assignment.
 *
 * @param PDO $pdo     Database connection
 * @param int $id      User ID
 * @param int $siteId  New site ID
 * @return bool
 */
function updateUserSite(PDO $pdo, int $id, int $siteId): bool
{
    return UserRepository::instance()->updateSite($id, $siteId);
}

/**
 * Create a new user.
 *
 * @param PDO    $pdo   Database connection
 * @param array<string, mixed> $data  User data
 * @return int          New user ID
 */
function createUser(PDO $pdo, array $data): int
{
    return UserRepository::instance()->create($data);
}

/**
 * Update a user's full profile (all fields).
 *
 * @param PDO   $pdo   Database connection
 * @param int   $id    User ID
 * @param array<string, mixed> $data  User data (nom, prenom, email, username, role, site_id)
 * @return bool
 */
function updateUser(PDO $pdo, int $id, array $data): bool
{
    return UserRepository::instance()->update($id, $data);
}

/**
 * Count active users.
 *
 * @param PDO $pdo  Database connection
 * @return int
 */
function countActiveUsers(PDO $pdo): int
{
    return UserRepository::instance()->countActive();
}

/**
 * Soft-delete a user (deactivate).
 *
 * @param PDO $pdo  Database connection
 * @param int $id   User ID
 * @return bool
 */
function deactivateUser(PDO $pdo, int $id): bool
{
    return UserRepository::instance()->deactivate($id);
}

/**
 * Reactivate a user.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   User ID
 * @return bool
 */
function reactivateUser(PDO $pdo, int $id): bool
{
    return UserRepository::instance()->reactivate($id);
}
