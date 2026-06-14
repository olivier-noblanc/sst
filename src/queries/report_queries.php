<?php
/**
 * Report Queries — Application SST DREETS BFC
 * 
 * All SQL queries related to reports (RSST, RAMI, DGI).
 * Reports use UUID as primary key (non-guessable, safe for URLs).
 */

/**
 * Generate a UUID v4.
 * 
 * @return string
 */
function generateUuid(): string {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
        . '-' . dechex((hexdec(substr($hex, 16, 2)) & 0x3F) | 0x80) . substr($hex, 18, 2)
        . '-' . substr($hex, 20, 12);
}

/**
 * Validate UUID v4 format.
 * 
 * @param string $uuid
 * @return bool
 */
function isValidUuid(string $uuid): bool {
    // Accept any well-formed UUID (8-4-4-4-12 hex format).
    // Previously required strict v4 variant bits [89ab], but the old generateUuid()
    // had a bug (| 0x8 instead of & 0x3F | 0x80) producing invalid variant nibbles
    // in ~25% of UUIDs. Accepting all hex variants allows those legacy UUIDs to work.
    // Security is provided by 122 bits of randomness, not by variant bits.
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
}

/**
 * Create a new report.
 * 
 * @param PDO    $pdo     Database connection
 * @param array  $data    Report data
 * @return string         The new report UUID
 */
function createReport(PDO $pdo, array $data): string {
    // Transaction: sequence increment + report INSERT must be atomic.
    // Without this, two concurrent requests could get the same sequence number
    // or a sequence could be consumed without a report being created.
    $pdo->beginTransaction();
    try {
        // Generate reference
        $year = (int) date('Y');
        $year2 = date('y');
        $seq = getNextSequence($pdo, $data['type'], $year);
        $reference = generateReference($data['type'], $year2, $seq);

        // Generate UUID v4
        $uuid = generateUuid();

        $stmt = $pdo->prepare("
            INSERT INTO reports (
                uuid, reference, type, objet, description, date_evenement, heure_evenement,
                lieu, declarant_id, declarant_nom, declarant_prenom,
                pour_compte_de, pour_compte_nom, pour_compte_prenom,
                nature_auteur, type_acte,
                site_id, is_confidential, etat,
                attachment_blob, attachment_name, attachment_mime
            ) VALUES (
                :uuid, :reference, :type, :objet, :description, :date_evenement, :heure_evenement,
                :lieu, :declarant_id, :declarant_nom, :declarant_prenom,
                :pour_compte_de, :pour_compte_nom, :pour_compte_prenom,
                :nature_auteur, :type_acte,
                :site_id, :is_confidential, 'nouveau',
                :attachment_blob, :attachment_name, :attachment_mime
            )
        ");

        $stmt->execute([
            ':uuid'              => $uuid,
            ':reference'         => $reference,
            ':type'              => $data['type'],
            ':objet'             => $data['objet'],
            ':description'       => $data['description'],
            ':date_evenement'    => $data['date_evenement'],
            ':heure_evenement'   => $data['heure_evenement'] ?? null,
            ':lieu'              => $data['lieu'] ?? null,
            ':declarant_id'      => $data['declarant_id'],
            ':declarant_nom'     => $data['declarant_nom'],
            ':declarant_prenom'  => $data['declarant_prenom'],
            ':pour_compte_de'    => $data['pour_compte_de'] ?? null,
            ':pour_compte_nom'   => $data['pour_compte_nom'] ?? null,
            ':pour_compte_prenom'=> $data['pour_compte_prenom'] ?? null,
            ':nature_auteur'     => $data['nature_auteur'] ?? null,
            ':type_acte'         => $data['type_acte'] ?? null,
            ':site_id'           => $data['site_id'],
            ':is_confidential'   => isset($data['is_confidential']) ? (int) $data['is_confidential'] : 1,
            ':attachment_blob'   => $data['attachment_blob'] ?? null,
            ':attachment_name'   => $data['attachment_name'] ?? null,
            ':attachment_mime'   => $data['attachment_mime'] ?? null,
        ]);

        $pdo->commit();

        // Update FTS5 index
        try {
            $pdo->prepare("INSERT INTO reports_fts(uuid, objet, description) VALUES (:uuid, :objet, :description)")
                ->execute([':uuid' => $uuid, ':objet' => $data['objet'], ':description' => $data['description']]);
        } catch (Exception $ftsE) {
            // Non-critical: FTS5 may not be available
            error_log('[SST-DB] FTS5 insert warning: ' . $ftsE->getMessage());
        }

        return $uuid;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[SST-DB] createReport transaction failed: ' . $e->getMessage());
        throw $e; // Re-throw so the handler can show an error to the user
    }
}

/**
 * Get a single report by UUID with site and respondent info.
 * 
 * @param PDO    $pdo   Database connection
 * @param string $uuid  Report UUID
 * @return array|null
 */
function getReportByUuid(PDO $pdo, string $uuid): ?array {
    if (!isValidUuid($uuid)) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT r.*, s.code as site_code, s.nom as site_nom,
               rep.nom as repondant_nom, rep.prenom as repondant_prenom
        FROM reports r
        LEFT JOIN sites s ON r.site_id = s.id
        LEFT JOIN users rep ON r.repondant_id = rep.id
        WHERE r.uuid = :uuid
    ");
    $stmt->execute([':uuid' => $uuid]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get reports by registry type with filtering and pagination.
 * 
 * @param PDO    $pdo          Database connection
 * @param string $type         Registry type (rsst, rami, dgi)
 * @param array  $filters      Filter options (etat, site_id, q)
 * @param int    $userSiteId   Current user's site ID
 * @param bool   $seeAllSites  Whether user can see all sites
 * @param int    $page         Page number (1-based)
 * @param int    $perPage      Items per page
 * @return array               ['reports' => array, 'total' => int]
 */
function getReportsByRegistry(PDO $pdo, string $type, array $filters, int $userSiteId, bool $seeAllSites, int $page = 1, int $perPage = 20): array {
    $where = "r.type = :type";
    $params = [':type' => $type];

    // Site visibility
    if (!$seeAllSites) {
        $where .= " AND r.site_id = :user_site_id";
        $params[':user_site_id'] = $userSiteId;
    }

    // Confidentiality filter for agents (agent_choice mode)
    if (!empty($filters['confidential_filter'])) {
        $where .= " AND (r.is_confidential = 0 OR r.declarant_id = :cf_declarant_id)";
        $params[':cf_declarant_id'] = (int) $filters['confidential_filter'];
    }

    // Own-only filter for agents (confidential mode)
    if (!empty($filters['own_only'])) {
        $where .= " AND r.declarant_id = :own_only_declarant_id";
        $params[':own_only_declarant_id'] = (int) $filters['own_only'];
    }

    // Filter by etat
    if (!empty($filters['etat'])) {
        $where .= " AND r.etat = :etat";
        $params[':etat'] = $filters['etat'];
    }

    // Filter by site
    if (!empty($filters['site_id']) && $seeAllSites) {
        $where .= " AND r.site_id = :filter_site_id";
        $params[':filter_site_id'] = $filters['site_id'];
    }

    // Force site filter
    if (!empty($filters['force_site_id'])) {
        $where .= " AND r.site_id = :force_site_id";
        $params[':force_site_id'] = (int) $filters['force_site_id'];
    }

    // Search query — uses FTS5 full-text search if available, falls back to LIKE
    if (!empty($filters['q'])) {
        // Check if FTS5 table exists
        static $hasFts = null;
        if ($hasFts === null) {
            try {
                $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='reports_fts'");
                $hasFts = ($check !== false && $check->fetch() !== false);
            } catch (Exception $e) {
                $hasFts = false;
            }
        }

        if ($hasFts) {
            // FTS5 search: match objet and description, return matching UUIDs
            $where .= " AND r.uuid IN (SELECT uuid FROM reports_fts WHERE reports_fts MATCH :q_fts)";
            // Sanitize query for FTS5: remove special operators, keep words
            $ftsQuery = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $filters['q']);
            $ftsQuery = trim($ftsQuery);
            if ($ftsQuery === '') {
                // If query was only special chars, fall back to LIKE
                $where = str_replace("AND r.uuid IN (SELECT uuid FROM reports_fts WHERE reports_fts MATCH :q_fts)", "AND (r.objet LIKE :q OR r.description LIKE :q2)", $where);
                $params[':q'] = '%' . $filters['q'] . '%';
                $params[':q2'] = '%' . $filters['q'] . '%';
            } else {
                $params[':q_fts'] = $ftsQuery;
            }
        } else {
            // Fallback: LIKE search
            $where .= " AND (r.objet LIKE :q OR r.description LIKE :q2)";
            $params[':q'] = '%' . $filters['q'] . '%';
            $params[':q2'] = '%' . $filters['q'] . '%';
        }
    }

    // Filter by declarant
    if (!empty($filters['declarant_id']) && empty($filters['confidential_filter'])) {
        $where .= " AND r.declarant_id = :declarant_id";
        $params[':declarant_id'] = (int) $filters['declarant_id'];
    }

    // Count total
    $countSql = "SELECT COUNT(*) as total FROM reports r WHERE $where";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Fetch page
    $offset = ($page - 1) * $perPage;
    $sql = "
        SELECT r.*, s.code as site_code, s.nom as site_nom
        FROM reports r
        LEFT JOIN sites s ON r.site_id = s.id
        WHERE $where
        ORDER BY r.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $params[':limit'] = $perPage;
    $params[':offset'] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();

    return ['reports' => $reports, 'total' => $total];
}

/**
 * Get reports by site.
 * 
 * @param PDO $pdo     Database connection
 * @param int $siteId  Site ID
 * @return array
 */
function getReportsBySite(PDO $pdo, int $siteId): array {
    $stmt = $pdo->prepare("
        SELECT r.*, s.code as site_code, s.nom as site_nom
        FROM reports r
        LEFT JOIN sites s ON r.site_id = s.id
        WHERE r.site_id = :site_id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([':site_id' => $siteId]);
    return $stmt->fetchAll();
}

/**
 * Update a report (edit by declarant).
 * 
 * @param PDO    $pdo     Database connection
 * @param string $uuid    Report UUID
 * @param array  $data    Updated data
 * @param int    $userId  The declarant's user ID (for ownership check)
 * @return bool
 */
function updateReport(PDO $pdo, string $uuid, array $data, int $userId): bool {
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
    ];

    $params = [
        ':objet'             => $data['objet'],
        ':description'       => $data['description'],
        ':date_evenement'    => $data['date_evenement'],
        ':heure_evenement'   => $data['heure_evenement'] ?? null,
        ':lieu'              => $data['lieu'] ?? null,
        ':pour_compte_nom'   => $data['pour_compte_nom'] ?? null,
        ':pour_compte_prenom'=> $data['pour_compte_prenom'] ?? null,
        ':nature_auteur'     => $data['nature_auteur'] ?? null,
        ':type_acte'         => $data['type_acte'] ?? null,
        ':is_confidential'   => isset($data['is_confidential']) ? (int) $data['is_confidential'] : 1,
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
        . " WHERE uuid = :uuid AND declarant_id = :user_id AND etat IN ('nouveau', 'en_cours')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $updated = $stmt->rowCount() > 0;

    // Update FTS5 index if report was updated
    if ($updated) {
        try {
            $pdo->prepare("DELETE FROM reports_fts WHERE uuid = :uuid")->execute([':uuid' => $uuid]);
            $pdo->prepare("INSERT INTO reports_fts(uuid, objet, description) VALUES (:uuid, :objet, :description)")
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
function abandonReport(PDO $pdo, string $uuid, int $userId): bool {
    $stmt = $pdo->prepare("
        UPDATE reports
        SET etat = 'abandonne', updated_at = datetime('now')
        WHERE uuid = :uuid AND declarant_id = :user_id AND etat IN ('nouveau', 'en_cours')
    ");
    $stmt->execute([':uuid' => $uuid, ':user_id' => $userId]);
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
 * @return string 'true' on success, 'concurrent' if already modified, 'error' on DB failure
 */
function respondToReport(PDO $pdo, string $uuid, int $userId, string $reponse, string $nouvelEtat): array {
    // Transaction: UPDATE reports + INSERT report_responses must be atomic.
    // Without this, a crash between the two queries would leave reports.reponse
    // updated but no history entry in report_responses = data inconsistency.
    //
    // Returns: ['status' => 'true']     — success
    //          ['status' => 'concurrent'] — report was modified by another session
    //          ['status' => 'error', 'message' => '...'] — database exception
    $pdo->beginTransaction();
    try {
        // Update the report
        $stmt = $pdo->prepare("
            UPDATE reports
            SET etat = :nouvel_etat,
                reponse = :reponse,
                repondant_id = :user_id,
                date_reponse = datetime('now'),
                updated_at = datetime('now')
            WHERE uuid = :uuid AND etat IN ('nouveau', 'en_cours')
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
        $stmt = $pdo->prepare("
            INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat)
            VALUES (:report_uuid, :user_id, :reponse, :nouvel_etat)
        ");
        $stmt->execute([
            ':report_uuid' => $uuid,
            ':user_id'     => $userId,
            ':reponse'     => $reponse,
            ':nouvel_etat' => $nouvelEtat,
        ]);

        $pdo->commit();
        return ['status' => 'true'];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[SST-DB] respondToReport transaction failed: ' . $e->getMessage());
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Count reports by state for a given registry type.
 * 
 * @param PDO    $pdo         Database connection
 * @param string $type        Registry type
 * @param int    $siteId      Optional site filter
 * @param bool   $seeAllSites Whether to filter by site
 * @return array
 */
function countReportsByState(PDO $pdo, string $type, int $siteId = 0, bool $seeAllSites = true): array {
    $sql = "SELECT etat, COUNT(*) as count FROM reports WHERE type = :type AND etat != 'abandonne'";
    $params = [':type' => $type];

    if (!$seeAllSites && $siteId > 0) {
        $sql .= " AND site_id = :site_id";
        $params[':site_id'] = $siteId;
    }

    $sql .= " GROUP BY etat";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $counts = [
        'nouveau'  => 0,
        'en_cours' => 0,
        'traite'   => 0,
        'total'    => 0,
    ];

    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['etat']] = (int) $row['count'];
        $counts['total'] += (int) $row['count'];
    }

    return $counts;
}

/**
 * Get response history for a report.
 * 
 * @param PDO    $pdo         Database connection
 * @param string $reportUuid  Report UUID
 * @return array
 */
function getReportResponses(PDO $pdo, string $reportUuid): array {
    $stmt = $pdo->prepare("
        SELECT rr.*, u.nom, u.prenom
        FROM report_responses rr
        LEFT JOIN users u ON rr.user_id = u.id
        WHERE rr.report_uuid = :report_uuid
        ORDER BY rr.created_at ASC
    ");
    $stmt->execute([':report_uuid' => $reportUuid]);
    return $stmt->fetchAll();
}

/**
 * Count all active (non-abandoned) reports for a registry type.
 * 
 * @param PDO    $pdo         Database connection
 * @param string $type        Registry type
 * @param int    $siteId      Site ID for agent filtering (0 = all)
 * @return int
 */
function countActiveReports(PDO $pdo, string $type, int $siteId = 0, int $userId = 0, bool $confidentialMode = false): int {
    $sql = "SELECT COUNT(*) FROM reports WHERE type = :type AND etat != 'abandonne'";
    $params = [':type' => $type];

    if ($siteId > 0) {
        $sql .= " AND site_id = :site_id";
        $params[':site_id'] = $siteId;
    }

    if ($confidentialMode && $userId > 0) {
        $sql .= " AND (is_confidential = 0 OR declarant_id = :user_id)";
        $params[':user_id'] = $userId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * Count all active (non-abandoned) reports for a specific user (declarant).
 * Used for dashboard display.
 * 
 * @param PDO    $pdo     Database connection
 * @param string $type    Registry type
 * @param int    $userId  User ID (declarant)
 * @return int
 */
function countActiveReportsForUser(PDO $pdo, string $type, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE type = :type AND etat != 'abandonne' AND declarant_id = :user_id");
    $stmt->execute([':type' => $type, ':user_id' => $userId]);
    return (int) $stmt->fetchColumn();
}
