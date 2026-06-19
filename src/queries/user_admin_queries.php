<?php

/**
 * User Admin Queries — Application SST DREETS BFC
 *
 * Admin functions for managing users.
 * Split from user_queries.php for readability.
 */

require_once __DIR__ . '/user_gdpr_queries.php';

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
    $stmt = $pdo->prepare("
        UPDATE users SET role = :role, updated_at = datetime('now')
        WHERE id = :id
    ");
    $stmt->execute([':role' => $role, ':id' => $id]);
    return $stmt->rowCount() > 0;
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
    $stmt = $pdo->prepare("
        UPDATE users SET site_id = :site_id, updated_at = datetime('now')
        WHERE id = :id
    ");
    $stmt->execute([':site_id' => $siteId, ':id' => $id]);
    return $stmt->rowCount() > 0;
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
    $stmt = $pdo->prepare('
        INSERT INTO users (username, nom, prenom, email, role, site_id)
        VALUES (:username, :nom, :prenom, :email, :role, :site_id)
    ');
    $stmt->execute([
        ':username' => $data['username'],
        ':nom'      => $data['nom'],
        ':prenom'   => $data['prenom'],
        ':email'    => $data['email'] ?? null,
        ':role'     => $data['role'] ?? ROLE_AGENT,
        ':site_id'  => $data['site_id'],
    ]);
    return (int) $pdo->lastInsertId();
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
    $stmt = $pdo->prepare("
        UPDATE users
        SET nom = :nom, prenom = :prenom, email = :email,
            username = :username, role = :role, site_id = :site_id,
            updated_at = datetime('now')
        WHERE id = :id
    ");
    $stmt->execute([
        ':nom'      => $data['nom'],
        ':prenom'   => $data['prenom'],
        ':email'    => !empty($data['email']) ? $data['email'] : null,
        ':username' => $data['username'],
        ':role'     => $data['role'],
        ':site_id'  => $data['site_id'],
        ':id'       => $id,
    ]);
    return $stmt->rowCount() > 0;
}

/**
 * Count active users.
 *
 * @param PDO $pdo  Database connection
 * @return int
 */
function countActiveUsers(PDO $pdo): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE is_active = 1');
    $stmt->execute();
    return (int) $stmt->fetchColumn();
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
    $stmt = $pdo->prepare("
        UPDATE users SET is_active = 0, updated_at = datetime('now')
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
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
    $stmt = $pdo->prepare("
        UPDATE users SET is_active = 1, updated_at = datetime('now')
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}
