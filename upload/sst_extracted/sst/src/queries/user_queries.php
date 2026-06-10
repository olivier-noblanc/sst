<?php
/**
 * User Queries — Application SST DREETS BFC
 * 
 * All SQL queries related to user management.
 */

/**
 * Get a user by their username.
 * 
 * @param PDO    $pdo       Database connection
 * @param string $username  The username to look up
 * @return array|null
 */
function getUserByUsername(PDO $pdo, string $username): ?array {
    $stmt = $pdo->prepare("
        SELECT u.*, s.code as site_code, s.nom as site_nom
        FROM users u
        LEFT JOIN sites s ON u.site_id = s.id
        WHERE u.username = :username AND u.is_active = 1
    ");
    $stmt->execute([':username' => $username]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get a user by their ID.
 * 
 * @param PDO $pdo  Database connection
 * @param int $id   User ID
 * @return array|null
 */
function getUserById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT u.*, s.code as site_code, s.nom as site_nom
        FROM users u
        LEFT JOIN sites s ON u.site_id = s.id
        WHERE u.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get all active users, optionally filtered by site.
 * 
 * @param PDO    $pdo     Database connection
 * @param int    $siteId  Optional site filter (0 = all)
 * @param bool   $active  Whether to include only active users
 * @return array
 */
function getAllUsers(PDO $pdo, int $siteId = 0, bool $active = true): array {
    $sql = "
        SELECT u.*, s.code as site_code, s.nom as site_nom
        FROM users u
        LEFT JOIN sites s ON u.site_id = s.id
        WHERE 1=1
    ";
    $params = [];

    if ($active) {
        $sql .= " AND u.is_active = 1";
    }

    if ($siteId > 0) {
        $sql .= " AND u.site_id = :site_id";
        $params[':site_id'] = $siteId;
    }

    $sql .= " ORDER BY u.nom, u.prenom";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Create a new user.
 * 
 * @param PDO    $pdo   Database connection
 * @param array  $data  User data
 * @return int          New user ID
 */
function createUser(PDO $pdo, array $data): int {
    $stmt = $pdo->prepare("
        INSERT INTO users (username, nom, prenom, email, role, site_id)
        VALUES (:username, :nom, :prenom, :email, :role, :site_id)
    ");
    $stmt->execute([
        ':username' => $data['username'],
        ':nom'      => $data['nom'],
        ':prenom'   => $data['prenom'],
        ':email'    => $data['email'] ?? null,
        ':role'     => $data['role'] ?? 'agent',
        ':site_id'  => $data['site_id'],
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Update a user's full profile (all fields).
 * 
 * @param PDO   $pdo   Database connection
 * @param int   $id    User ID
 * @param array $data  User data (nom, prenom, email, username, role, site_id)
 * @return bool
 */
function updateUser(PDO $pdo, int $id, array $data): bool {
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
function countActiveUsers(PDO $pdo): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1");
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
function deactivateUser(PDO $pdo, int $id): bool {
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
function reactivateUser(PDO $pdo, int $id): bool {
    $stmt = $pdo->prepare("
        UPDATE users SET is_active = 1, updated_at = datetime('now')
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}
