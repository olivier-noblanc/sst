<?php

/**
 * Report Response Queries — Application SST DREETS BFC
 *
 * Functions for responding to and updating reports.
 * Split from report_queries.php for readability (max 250 lines per file).
 */

/**
 * Update a report (edit by declarant).
 *
 * @param PDO    $pdo     Database connection
 * @param string $uuid    Report UUID
 * @param array<string, mixed> $data    Updated data
 * @param int    $userId  The declarant's user ID (for ownership check)
 * @return bool
 */
function updateReport(PDO $pdo, string $uuid, array $data, int $userId): bool
{
    // Build dynamic SET clause and params
    $setClauses = [
        'objet = :objet',
        'description = :description',
        'date_evenement = :date_evenement',
        'heure_evenement = :heure_evenement',
        'lieu = :lieu',
        'pour_compte_nom = :pour_compte_nom',
        'pour_compte_prenom = :pour_compte_prenom',
        'nature_auteur = :nature_auteur',
        'type_acte = :type_acte',
        'is_confidential = :is_confidential',
        'consent_syndicat = :consent_syndicat',
        'pole = :pole',
        'service_affectation = :service_affectation',
        'telephone_mobile = :telephone_mobile',
        'site_text = :site_text',
    ];

    $params = [
        ':objet'             => $data['objet'],
        ':description'       => $data['description'],
        ':date_evenement'    => $data['date_evenement'],
        ':heure_evenement'   => $data['heure_evenement'] ?? null,
        ':lieu'              => $data['lieu'] ?? null,
        ':pour_compte_nom'   => $data['pour_compte_nom'] ?? null,
        ':pour_compte_prenom' => $data['pour_compte_prenom'] ?? null,
        ':nature_auteur'     => $data['nature_auteur'] ?? null,
        ':type_acte'         => $data['type_acte'] ?? null,
        ':is_confidential'   => isset($data['is_confidential']) ? (int) $data['is_confidential'] : 1,
        ':consent_syndicat'  => isset($data['consent_syndicat']) ? (int) $data['consent_syndicat'] : 0,
        ':pole'              => $data['pole'] ?? null,
        ':service_affectation' => $data['service_affectation'] ?? null,
        ':telephone_mobile'  => $data['telephone_mobile'] ?? null,
        ':site_text'         => $data['site_text'] ?? null,
    ];

    // Attachment columns: only update if explicitly present in $data
    if (array_key_exists('attachment_blob', $data)) {
        $setClauses[] = 'attachment_blob = :attachment_blob';
        $setClauses[] = 'attachment_name = :attachment_name';
        $setClauses[] = 'attachment_mime = :attachment_mime';
        $params[':attachment_blob'] = $data['attachment_blob'];
        $params[':attachment_name'] = $data['attachment_name'] ?? null;
        $params[':attachment_mime'] = $data['attachment_mime'] ?? null;
    }

    $setClauses[] = "updated_at = datetime('now')";
    $params[':uuid'] = $uuid;
    $params[':user_id'] = $userId;

    $sql = 'UPDATE reports SET ' . implode(', ', $setClauses)
        . " WHERE uuid = :uuid AND declarant_id = :user_id AND etat IN ('" . ETAT_NOUVEAU . "', '" . ETAT_EN_COURS . "')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $updated = $stmt->rowCount() > 0;

    // Update FTS5 index if report was updated
    if ($updated) {
        try {
            $pdo->prepare('DELETE FROM reports_fts WHERE uuid = :uuid')->execute([':uuid' => $uuid]);
            $pdo->prepare('INSERT INTO reports_fts(uuid, objet, description) VALUES (:uuid, :objet, :description)')
                ->execute([':uuid' => $uuid, ':objet' => $data['objet'], ':description' => $data['description']]);
        } catch (Exception $ftsE) {
            error_log('[SST-DB] FTS5 update warning: ' . $ftsE->getMessage());
        }
    }

    return $updated;
}

/**
 * Abandon a report (soft delete).
 *
 * @param PDO    $pdo     Database connection
 * @param string $uuid    Report UUID
 * @param int    $userId  The declarant's user ID
 * @return bool
 */
function abandonReport(PDO $pdo, string $uuid, int $userId): bool
{
    $stmt = $pdo->prepare("
        UPDATE reports
        SET etat = '" . ETAT_ABANDONNE . "', updated_at = datetime('now')
        WHERE uuid = :uuid AND etat IN ('" . ETAT_NOUVEAU . "', '" . ETAT_EN_COURS . "')
    ");
    $stmt->execute([':uuid' => $uuid]);
    return $stmt->rowCount() > 0;
}

/**
 * Respond to a report (by superviseur only).
 * Also inserts into the response history table.
 *
 * @param PDO    $pdo          Database connection
 * @param string $uuid         Report UUID
 * @param int    $userId       The responding user's ID
 * @param string $reponse      Response text
 * @param string $nouvelEtat   New state ('en_cours' or 'traite')
 * @param array  $attachment   Optional attachment data ['blob', 'name', 'mime']
 * @return array{status: string, message?: string} Status result
 */
function respondToReport(PDO $pdo, string $uuid, int $userId, string $reponse, string $nouvelEtat, array $attachment = []): array
{
    // Transaction: UPDATE reports + INSERT report_responses must be atomic.
    // Without this, a crash between the two queries would leave reports.reponse
    // updated but no history entry in report_responses = data inconsistency.
    //
    // P0-1: 'reouvert' is now a valid state for responding (alongside 'nouveau' and 'en_cours').
    // P0-2: When responding to a 'reouvert' report, archive the existing response into
    //       report_responses BEFORE overwriting, to preserve the initial supervisor response
    //       (legal principle of register immutability).
    //
    // Returns: ['status' => 'true']     — success
    //          ['status' => 'concurrent'] — report was modified by another session
    //          ['status' => 'error', 'message' => '...'] — database exception
    $pdo->beginTransaction();
    try {
        // P0-2: If the report is in 'reouvert' state, archive the current response
        // into report_responses before it gets overwritten by the UPDATE below.
        // This preserves the initial supervisor response for legal compliance.
        $checkStmt = $pdo->prepare('SELECT etat, reponse, repondant_id, date_reponse FROM reports WHERE uuid = :uuid');
        $checkStmt->execute([':uuid' => $uuid]);
        $current = $checkStmt->fetch();

        if ($current && $current['etat'] === ETAT_REOUVERT && !empty($current['reponse'])) {
            // Archive the original response before it's overwritten
            $archiveStmt = $pdo->prepare('
                INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat)
                VALUES (:report_uuid, :user_id, :reponse, :nouvel_etat)
            ');
            $archiveStmt->execute([
                ':report_uuid' => $uuid,
                ':user_id'     => (int) $current['repondant_id'],
                ':reponse'     => '[Réponse initiale archivée] ' . $current['reponse'],
                ':nouvel_etat' => ETAT_TRAITE,
            ]);
        }

        // Update the report (P0-1: 'reouvert' added to valid states)
        $stmt = $pdo->prepare("
            UPDATE reports
            SET etat = :nouvel_etat,
                reponse = :reponse,
                repondant_id = :user_id,
                date_reponse = datetime('now'),
                updated_at = datetime('now')
            WHERE uuid = :uuid AND etat IN ('" . ETAT_NOUVEAU . "', '" . ETAT_EN_COURS . "', '" . ETAT_REOUVERT . "')
        ");
        $stmt->execute([
            ':nouvel_etat' => $nouvelEtat,
            ':reponse'     => $reponse,
            ':user_id'     => $userId,
            ':uuid'        => $uuid,
        ]);

        $updated = $stmt->rowCount() > 0;

        if (!$updated) {
            // No row matched — the report was likely modified by another supervisor
            // between the handler's check and this UPDATE (race condition).
            $pdo->rollBack();
            return ['status' => 'concurrent'];
        }

        // Insert into response history
        // report_id is nullable (migrated) — we only need report_uuid
        $stmt = $pdo->prepare('
            INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat, attachment_blob, attachment_name, attachment_mime)
            VALUES (:report_uuid, :user_id, :reponse, :nouvel_etat, :attachment_blob, :attachment_name, :attachment_mime)
        ');
        $stmt->execute([
            ':report_uuid' => $uuid,
            ':user_id'     => $userId,
            ':reponse'     => $reponse,
            ':nouvel_etat' => $nouvelEtat,
            ':attachment_blob' => $attachment['blob'] ?? null,
            ':attachment_name' => $attachment['name'] ?? null,
            ':attachment_mime' => $attachment['mime'] ?? null,
        ]);

        $pdo->commit();
        return ['status' => 'true'];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[SST-DB] respondToReport transaction failed: ' . $e->getMessage());
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
