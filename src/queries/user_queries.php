<?php

/**
 * User Queries — Application SST DREETS BFC
 *
 * All SQL queries related to user management.
 *
 * All functions delegate to App\Repository\UserRepository.
 */

use App\Repository\UserRepository;

/**
 * Base SELECT for user queries with site JOIN.
 * Centralises the "SELECT u.*, s.code as site_code, s.nom as site_nom
 * FROM users u LEFT JOIN sites s ON u.site_id = s.id" pattern
 * that was duplicated across auth.php, index.php, and this file.
 *
 * @return string  SQL fragment (SELECT ... FROM ... LEFT JOIN ...)
 */
function userSelectWithSite(): string
{
    return 'SELECT u.*, s.code as site_code, s.nom as site_nom
            FROM users u
            LEFT JOIN sites s ON u.site_id = s.id';
}

/**
 * Get a user by their username.
 *
 * @param PDO    $pdo       Database connection
 * @param string $username  The username to look up
 * @return array<string, mixed>|null
 */
function getUserByUsername(PDO $pdo, string $username): ?array
{
    return UserRepository::instance()->findByUsername($username);
}

/**
 * Get a user by their ID.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   User ID
 * @return array<string, mixed>|null
 */
function getUserById(PDO $pdo, int $id): ?array
{
    return UserRepository::instance()->findById($id);
}

/**
 * Get all active users with a given role.
 *
 * @param PDO    $pdo   Database connection
 * @param string $role  Role constant (ROLE_AGENT, ROLE_SUPERVISEUR, ROLE_CHSCT)
 * @return array<int, array<string, mixed>>
 */
function getUsersByRole(PDO $pdo, string $role): array
{
    return UserRepository::instance()->findByRole($role);
}

/**
 * Get all active users, optionally filtered by site.
 *
 * @param PDO    $pdo     Database connection
 * @param int    $siteId  Optional site filter (0 = all)
 * @param bool   $active  Whether to include only active users
 * @return array<int, array<string, mixed>>
 */
function getAllUsers(PDO $pdo, int $siteId = 0, bool $active = true): array
{
    return UserRepository::instance()->findAll($siteId, $active);
}
