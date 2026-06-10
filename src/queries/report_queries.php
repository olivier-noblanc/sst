<?php
/**
 * Report Queries — Application SST DREETS BFC
 * 
 * All SQL queries related to reports (RSST, RAMI, DGI).
 */

/**
 * Create a new report.
 * 
 * @param PDO    $pdo     Database connection
 * @param array  $data    Report data
 * @return string         The new report reference
 */
function createReport(PDO $pdo, array $data): string {
    // Generate reference
    $year = (int) date('Y');
    $year2 = date('y');
    $seq = getNextSequence($pdo, $data['type'], $year);
    $reference = generateReference($data['type'], $year2, $seq);

    $stmt = $pdo->prepare("
        INSERT INTO reports (
            reference, type, objet, description, date_evenement, heure_evenement,
            lieu, declarant_id, declarant_nom, declarant_prenom,
            pour_compte_de, pour_compte_nom, pour_compte_prenom,
            site_id, is_confidential, etat
        ) VALUES (
            :reference, :type, :objet, :description, :date_evenement, :heure_evenement,
            :lieu, :declarant_id, :declarant_nom, :declarant_prenom,
            :pour_compte_de, :pour_compte_nom, :pour_compte_prenom,
            :site_id, :is_confidential, 'nouveau'
        )
    ");

    $stmt->execute([
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
        ':site_id'           => $data['site_id'],
        ':is_confidential'   => isset($data['is_confidential']) ? (int) $data['is_confidential'] : 1,
    ]);

    return $reference;
}

/**
 * Get the last insert ID after creating a report.
 * 
 * @param PDO $pdo  Database connection
 * @return int
 */
function getLastInsertId(PDO $pdo): int {
    return (int) $pdo->lastInsertId();
}

/**
 * Get a single report by ID with site and respondent info.
 * 
 * @param PDO $pdo  Database connection
 * @param int $id   Report ID
 * @return array|null
 */
function getReportById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT r.*, s.code as site_code, s.nom as site_nom,
               rep.nom as repondant_nom, rep.prenom as repondant_prenom
        FROM reports r
        LEFT JOIN sites s ON r.site_id = s.id
        LEFT JOIN users rep ON r.repondant_id = rep.id
        WHERE r.id = :id
    ");
    $stmt->execute([':id' => $id]);
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
    // Agent sees public reports + their own (even confidential)
    if (!empty($filters['confidential_filter'])) {
        $where .= " AND (r.is_confidential = 0 OR r.declarant_id = :cf_declarant_id)";
        $params[':cf_declarant_id'] = (int) $filters['confidential_filter'];
    }

    // Own-only filter for agents (confidential mode — most restrictive)
    // Agent sees ONLY their own reports, nothing from others
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

    // Force site filter (for agent visibility: always filter by site for agents)
    if (!empty($filters['force_site_id'])) {
        $where .= " AND r.site_id = :force_site_id";
        $params[':force_site_id'] = (int) $filters['force_site_id'];
    }

    // Search query
    if (!empty($filters['q'])) {
        $where .= " AND (r.objet LIKE :q OR r.description LIKE :q2)";
        $params[':q'] = '%' . $filters['q'] . '%';
        $params[':q2'] = '%' . $filters['q'] . '%';
    }

    // Filter by declarant (used in some specific contexts)
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
 * @param PDO   $pdo   Database connection
 * @param int   $id    Report ID
 * @param array $data  Updated data
 * @param int   $userId  The declarant's user ID (for ownership check)
 * @return bool
 */
function updateReport(PDO $pdo, int $id, array $data, int $userId): bool {
    $stmt = $pdo->prepare("
        UPDATE reports
        SET objet = :objet, description = :description,
            date_evenement = :date_evenement, heure_evenement = :heure_evenement,
            lieu = :lieu, pour_compte_nom = :pour_compte_nom,
            pour_compte_prenom = :pour_compte_prenom,
            is_confidential = :is_confidential,
            updated_at = datetime('now')
        WHERE id = :id AND declarant_id = :user_id AND etat IN ('nouveau', 'en_cours')
    ");

    $stmt->execute([
        ':objet'             => $data['objet'],
        ':description'       => $data['description'],
        ':date_evenement'    => $data['date_evenement'],
        ':heure_evenement'   => $data['heure_evenement'] ?? null,
        ':lieu'              => $data['lieu'] ?? null,
        ':pour_compte_nom'   => $data['pour_compte_nom'] ?? null,
        ':pour_compte_prenom'=> $data['pour_compte_prenom'] ?? null,
        ':is_confidential'   => isset($data['is_confidential']) ? (int) $data['is_confidential'] : 1,
        ':id'                => $id,
        ':user_id'           => $userId,
    ]);

    return $stmt->rowCount() > 0;
}

/**
 * Abandon a report (soft delete).
 * 
 * @param PDO   $pdo     Database connection
 * @param int   $id      Report ID
 * @param int   $userId  The declarant's user ID
 * @return bool
 */
function abandonReport(PDO $pdo, int $id, int $userId): bool {
    $stmt = $pdo->prepare("
        UPDATE reports
        SET etat = 'abandonne', updated_at = datetime('now')
        WHERE id = :id AND declarant_id = :user_id AND etat IN ('nouveau', 'en_cours')
    ");
    $stmt->execute([':id' => $id, ':user_id' => $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Respond to a report (by superviseur only).
 * Also inserts into the response history table.
 * 
 * @param PDO    $pdo          Database connection
 * @param int    $id           Report ID
 * @param int    $userId       The responding user's ID
 * @param string $reponse      Response text
 * @param string $nouvelEtat   New state ('en_cours' or 'traite')
 * @return bool
 */
function respondToReport(PDO $pdo, int $id, int $userId, string $reponse, string $nouvelEtat): bool {
    // Update the report
    $stmt = $pdo->prepare("
        UPDATE reports
        SET etat = :nouvel_etat,
            reponse = :reponse,
            repondant_id = :user_id,
            date_reponse = datetime('now'),
            updated_at = datetime('now')
        WHERE id = :id AND etat IN ('nouveau', 'en_cours')
    ");
    $stmt->execute([
        ':nouvel_etat' => $nouvelEtat,
        ':reponse'     => $reponse,
        ':user_id'     => $userId,
        ':id'          => $id,
    ]);

    $updated = $stmt->rowCount() > 0;

    if ($updated) {
        // Insert into response history
        $stmt = $pdo->prepare("
            INSERT INTO report_responses (report_id, user_id, reponse, nouvel_etat)
            VALUES (:report_id, :user_id, :reponse, :nouvel_etat)
        ");
        $stmt->execute([
            ':report_id'   => $id,
            ':user_id'     => $userId,
            ':reponse'     => $reponse,
            ':nouvel_etat' => $nouvelEtat,
        ]);
    }

    return $updated;
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
 * @param PDO $pdo       Database connection
 * @param int $reportId  Report ID
 * @return array
 */
function getReportResponses(PDO $pdo, int $reportId): array {
    $stmt = $pdo->prepare("
        SELECT rr.*, u.nom, u.prenom
        FROM report_responses rr
        LEFT JOIN users u ON rr.user_id = u.id
        WHERE rr.report_id = :report_id
        ORDER BY rr.created_at ASC
    ");
    $stmt->execute([':report_id' => $reportId]);
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
