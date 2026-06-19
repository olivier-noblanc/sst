<?php

/**
 * User GDPR Queries — Application SST DREETS BFC
 *
 * GDPR-related query functions for user data export and anonymization.
 * Split from user_admin_queries.php for readability.
 */

/**
 * Export all data related to a user (GDPR right of access).
 * Returns an associative array with user info, reports, and responses.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   User ID
 * @return array<string, mixed>
 */
function exportUserData(PDO $pdo, int $id): array
{
    $user = getUserById($pdo, $id);
    if (!$user) {
        return [];
    }

    // User's reports (as declarant)
    $stmt = $pdo->prepare('
        SELECT r.uuid, r.reference, r.type, r.objet, r.description, r.date_evenement,
               r.heure_evenement, r.lieu, r.is_confidential, r.etat, r.created_at
        FROM reports r
        WHERE r.declarant_id = :id
        ORDER BY r.created_at DESC
    ');
    $stmt->execute([':id' => $id]);
    $reports = $stmt->fetchAll();

    // User's responses (as superviseur)
    $stmt = $pdo->prepare('
        SELECT rr.report_uuid, rr.reponse, rr.nouvel_etat, rr.created_at
        FROM report_responses rr
        WHERE rr.user_id = :id
        ORDER BY rr.created_at DESC
    ');
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
function anonymizeUser(PDO $pdo, int $id): bool
{
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
        $stmt = $pdo->prepare('
            UPDATE reports
            SET repondant_id = NULL
            WHERE repondant_id = :id AND repondant_id IS NOT NULL
        ');
        $stmt->execute([':id' => $id]);

        // Update FTS for anonymized reports
        try {
            $pdo->exec('DELETE FROM reports_fts');
            $pdo->exec('INSERT INTO reports_fts(uuid, objet, description) SELECT uuid, objet, description FROM reports WHERE uuid IS NOT NULL');
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
