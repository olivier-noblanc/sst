<?php
/**
 * User Queries — Application SST DREETS BFC
 * 
 * All SQL queries related to user management.
 */

/**
 * Base SELECT for user queries with site JOIN.
 * Centralises the "SELECT u.*, s.code as site_code, s.nom as site_nom
 * FROM users u LEFT JOIN sites s ON u.site_id = s.id" pattern
 * that was duplicated across auth.php, index.php, and this file.
 *
 * @return string  SQL fragment (SELECT ... FROM ... LEFT JOIN ...)
 */
function userSelectWithSite(): string {
    return "SELECT u.*, s.code as site_code, s.nom as site_nom
            FROM users u
            LEFT JOIN sites s ON u.site_id = s.id";
}

/**
 * Get a user by their username.
 * 
 * @param PDO    $pdo       Database connection
 * @param string $username  The username to look up
 * @return array|null
 */
function getUserByUsername(PDO $pdo, string $username): ?array {
    $stmt = $pdo->prepare(
        userSelectWithSite() . " WHERE u.username = :username AND u.is_active = 1"
    );
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
    $stmt = $pdo->prepare(
        userSelectWithSite() . " WHERE u.id = :id"
    );
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
    $sql = userSelectWithSite() . " WHERE 1=1";
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
 * Update a user's role.
 * 
 * @param PDO    $pdo   Database connection
 * @param int    $id    User ID
 * @param string $role  New role
 * @return bool
 */
function updateUserRole(PDO $pdo, int $id, string $role): bool {
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
function updateUserSite(PDO $pdo, int $id, int $siteId): bool {
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

/**
 * Export all data related to a user (GDPR right of access).
 * Returns an associative array with user info, reports, and responses.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   User ID
 * @return array
 */
function exportUserData(PDO $pdo, int $id): array {
    $user = getUserById($pdo, $id);
    if (!$user) {
        return [];
    }

    // User's reports (as declarant)
    $stmt = $pdo->prepare("
        SELECT r.uuid, r.reference, r.type, r.objet, r.description, r.date_evenement,
               r.heure_evenement, r.lieu, r.is_confidential, r.etat, r.created_at
        FROM reports r
        WHERE r.declarant_id = :id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([':id' => $id]);
    $reports = $stmt->fetchAll();

    // User's responses (as superviseur)
    $stmt = $pdo->prepare("
        SELECT rr.report_uuid, rr.reponse, rr.nouvel_etat, rr.created_at
        FROM report_responses rr
        WHERE rr.user_id = :id
        ORDER BY rr.created_at DESC
    ");
    $stmt->execute([':id' => $id]);
    $responses = $stmt->fetchAll();

    return [
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'email' => $user['email'],
            'role' => $user['role'],
            'site_id' => $user['site_id'],
            'is_active' => $user['is_active'],
            'created_at' => $user['created_at'],
        ],
        'reports_count' => count($reports),
        'reports' => $reports,
        'responses_count' => count($responses),
        'responses' => $responses,
    ];
}

/**
 * Anonymize a user's personal data (GDPR right to erasure).
 * Replaces names and email with anonymized placeholders.
 * Keeps reports and responses for record-keeping but removes PII.
 * The user is deactivated.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   User ID
 * @return bool
 */
function anonymizeUser(PDO $pdo, int $id): bool {
    $user = getUserById($pdo, $id);
    if (!$user) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        // Anonymize user record
        $stmt = $pdo->prepare("
            UPDATE users
            SET nom = 'Anonymisé',
                prenom = 'Utilisateur',
                email = NULL,
                is_active = 0,
                updated_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);

        // Anonymize declarant name in reports
        $stmt = $pdo->prepare("
            UPDATE reports
            SET declarant_nom = 'Anonymisé',
                declarant_prenom = 'Utilisateur',
                pour_compte_nom = CASE WHEN pour_compte_nom IS NOT NULL THEN 'Anonymisé' ELSE NULL END,
                pour_compte_prenom = CASE WHEN pour_compte_prenom IS NOT NULL THEN 'Utilisateur' ELSE NULL END
            WHERE declarant_id = :id
        ");
        $stmt->execute([':id' => $id]);

        // Anonymize respondent name in reports
        $stmt = $pdo->prepare("
            UPDATE reports
            SET repondant_id = NULL
            WHERE repondant_id = :id AND repondant_id IS NOT NULL
        ");
        $stmt->execute([':id' => $id]);

        // Update FTS for anonymized reports
        try {
            $pdo->exec("DELETE FROM reports_fts");
            $pdo->exec("INSERT INTO reports_fts(uuid, objet, description) SELECT uuid, objet, description FROM reports WHERE uuid IS NOT NULL");
        } catch (Exception $ftsE) {
            // Non-critical
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[SST-DB] anonymizeUser failed: ' . $e->getMessage());
        return false;
    }
}
