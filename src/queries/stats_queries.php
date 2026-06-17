<?php
/**
 * Statistics Queries — Application SST DREETS BFC
 * 
 * All SQL queries for statistics, synthesis, and export.
 */

/**
 * Get synthesis data: counts by site, registry type, and state.
 * 
 * @param PDO    $pdo     Database connection
 * @param string $year    Year filter (4-digit)
 * @param int    $siteId  Optional site filter (0 = all)
 * @return array<int, array<string, mixed>>
 */
function getSynthesisData(PDO $pdo, string $year, int $siteId = 0): array {
    $sql = "
        SELECT s.id as site_id, s.code, s.nom,
            r.type,
            SUM(CASE WHEN r.etat = 'nouveau' THEN 1 ELSE 0 END) as nouveau,
            SUM(CASE WHEN r.etat = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN r.etat = 'traite' THEN 1 ELSE 0 END) as traite,
            SUM(CASE WHEN r.etat = 'abandonne' THEN 1 ELSE 0 END) as abandonne,
            COUNT(*) as total
        FROM sites s
        LEFT JOIN reports r ON r.site_id = s.id
            AND r.created_at >= :year_start AND r.created_at < :year_next
    ";

    $params = [':year_start' => $year . '-01-01 00:00:00', ':year_next' => ((int)$year + 1) . '-01-01 00:00:00'];

    if ($siteId > 0) {
        $sql .= " AND r.site_id = :site_id";
        $params[':site_id'] = $siteId;
    }

    $sql .= " GROUP BY s.id, r.type ORDER BY s.code, r.type";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get data for CSV export with flexible filtering.
 * 
 * @param PDO   $pdo      Database connection
 * @param array<string, mixed> $filters  Filter options
 * @return array<int, array<string, mixed>>
 */
function getExportData(PDO $pdo, array $filters = []): array {
    $sql = "
        SELECT r.uuid, r.reference, r.type, r.objet, r.description,
               r.date_evenement, r.heure_evenement, r.lieu,
               r.declarant_id, r.declarant_nom, r.declarant_prenom,
               r.pour_compte_de, r.pour_compte_nom, r.pour_compte_prenom,
               r.nature_auteur, r.type_acte,
               r.site_id, r.is_confidential, r.etat,
               r.repondant_id, r.date_reponse, r.reponse,
               r.attachment_name, r.attachment_mime,
               r.created_at, r.updated_at,
               s.code as site_code, s.nom as site_nom,
               rep.nom as repondant_nom, rep.prenom as repondant_prenom
        FROM reports r
        LEFT JOIN sites s ON r.site_id = s.id
        LEFT JOIN users rep ON r.repondant_id = rep.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filters['type'])) {
        $sql .= " AND r.type = :type";
        $params[':type'] = $filters['type'];
    }

    if (!empty($filters['site_id'])) {
        $sql .= " AND r.site_id = :site_id";
        $params[':site_id'] = $filters['site_id'];
    }

    if (!empty($filters['declarant_id'])) {
        $sql .= " AND r.declarant_id = :declarant_id";
        $params[':declarant_id'] = $filters['declarant_id'];
    }

    if (!empty($filters['date_from'])) {
        $sql .= " AND r.date_evenement >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $sql .= " AND r.date_evenement <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }

    if (!empty($filters['etats']) && is_array($filters['etats'])) {
        $placeholders = [];
        foreach ($filters['etats'] as $i => $etat) {
            $key = ':etat_' . $i;
            $placeholders[] = $key;
            $params[$key] = $etat;
        }
        $sql .= " AND r.etat IN (" . implode(', ', $placeholders) . ")";
    }

    $sql .= " ORDER BY r.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get indicateurs statistics for the statistics page.
 * 
 * @param PDO    $pdo     Database connection
 * @param string $year    Year filter
 * @param int    $siteId  Optional site filter
 * @return array<string, int>
 */
function getStatisticsIndicateurs(PDO $pdo, string $year = '', int $siteId = 0): array {
    $params = [];

    $sql = "
        SELECT
            COUNT(*) as total_reports,
            SUM(CASE WHEN etat = 'nouveau' THEN 1 ELSE 0 END) as total_nouveau,
            SUM(CASE WHEN etat = 'en_cours' THEN 1 ELSE 0 END) as total_en_cours,
            SUM(CASE WHEN etat = 'traite' THEN 1 ELSE 0 END) as total_traite,
            SUM(CASE WHEN etat = 'abandonne' THEN 1 ELSE 0 END) as total_abandonne,
            SUM(CASE WHEN type = 'rsst' THEN 1 ELSE 0 END) as total_rsst,
            SUM(CASE WHEN type = 'rami' THEN 1 ELSE 0 END) as total_rami,
            SUM(CASE WHEN type = 'dgi' THEN 1 ELSE 0 END) as total_dgi
        FROM reports
        WHERE 1=1
    ";

    if (!empty($year)) {
        $sql .= " AND created_at >= :year_start AND created_at < :year_next";
        $params[':year_start'] = $year . '-01-01 00:00:00';
        $params[':year_next'] = ((int)$year + 1) . '-01-01 00:00:00';
    }

    if ($siteId > 0) {
        $sql .= " AND site_id = :site_id";
        $params[':site_id'] = $siteId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();

    return [
        'total_reports'   => (int) ($result['total_reports'] ?? 0),
        'total_nouveau'   => (int) ($result['total_nouveau'] ?? 0),
        'total_en_cours'  => (int) ($result['total_en_cours'] ?? 0),
        'total_traite'    => (int) ($result['total_traite'] ?? 0),
        'total_abandonne' => (int) ($result['total_abandonne'] ?? 0),
        'total_rsst'      => (int) ($result['total_rsst'] ?? 0),
        'total_rami'      => (int) ($result['total_rami'] ?? 0),
        'total_dgi'       => (int) ($result['total_dgi'] ?? 0),
    ];
}

/**
 * Get statistics broken down by site (UR).
 * 
 * @param PDO    $pdo     Database connection
 * @param string $year    Year filter
 * @param int    $siteId  Optional site filter
 * @return array<int, array<string, mixed>>
 */
function getStatsBySite(PDO $pdo, string $year = '', int $siteId = 0): array {
    $sql = "
        SELECT s.code, s.nom,
            COUNT(r.uuid) as total,
            SUM(CASE WHEN r.etat = 'nouveau' THEN 1 ELSE 0 END) as nouveau,
            SUM(CASE WHEN r.etat = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN r.etat = 'traite' THEN 1 ELSE 0 END) as traite,
            SUM(CASE WHEN r.etat = 'abandonne' THEN 1 ELSE 0 END) as abandonne,
            SUM(CASE WHEN r.type = 'rsst' THEN 1 ELSE 0 END) as rsst,
            SUM(CASE WHEN r.type = 'rami' THEN 1 ELSE 0 END) as rami,
            SUM(CASE WHEN r.type = 'dgi' THEN 1 ELSE 0 END) as dgi
        FROM sites s
        LEFT JOIN reports r ON r.site_id = s.id
    ";

    $params = [];
    $where = [];

    if (!empty($year)) {
        $where[] = "r.created_at >= :year_start AND r.created_at < :year_next";
        $params[':year_start'] = $year . '-01-01 00:00:00';
        $params[':year_next'] = ((int)$year + 1) . '-01-01 00:00:00';
    }

    if ($siteId > 0) {
        $where[] = "r.site_id = :site_id";
        $params[':site_id'] = $siteId;
    }

    if (!empty($where)) {
        $sql .= " AND " . implode(" AND ", $where);
    }

    $sql .= " GROUP BY s.id ORDER BY s.code";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Count reports by registry and site.
 * 
 * @param PDO    $pdo     Database connection
 * @param string $type    Registry type
 * @param int    $siteId  Site ID
 * @return int
 */
function countReportsByRegistryAndSite(PDO $pdo, string $type, int $siteId): int {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reports
        WHERE type = :type AND site_id = :site_id AND etat != 'abandonne'
    ");
    $stmt->execute([':type' => $type, ':site_id' => $siteId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get available years from report data.
 * 
 * @param PDO $pdo  Database connection
 * @return array<int, string>
 */
function getAvailableYears(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT DISTINCT strftime('%Y', created_at) as year
        FROM reports
        ORDER BY year DESC
    ");
    return array_column($stmt->fetchAll(), 'year');
}

/**
 * Get RAMI statistics by nature_auteur and type_acte.
 * Returns counts grouped by nature_auteur and type_acte for RAMI reports.
 * 
 * @param PDO    $pdo     Database connection
 * @param string $year    Year filter
 * @return array{by_nature_auteur: array<int, mixed>, by_type_acte: array<int, mixed>}  ['by_nature_auteur' => [...], 'by_type_acte' => [...]]
 */
function getRamiStructuredStats(PDO $pdo, string $year = ''): array {
    $params = [];
    $yearFilter = '';
    if (!empty($year)) {
        $yearFilter = " AND created_at >= :year_start AND created_at < :year_next";
        $params[':year_start'] = $year . '-01-01 00:00:00';
        $params[':year_next'] = ((int)$year + 1) . '-01-01 00:00:00';
    }

    // By nature_auteur
    $sqlNature = "SELECT nature_auteur, COUNT(*) as count
        FROM reports
        WHERE type = 'rami' AND nature_auteur IS NOT NULL AND nature_auteur != ''{$yearFilter}
        GROUP BY nature_auteur
        ORDER BY count DESC";
    $stmt = $pdo->prepare($sqlNature);
    $stmt->execute($params);
    $byNature = $stmt->fetchAll();

    // By type_acte
    $sqlType = "SELECT type_acte, COUNT(*) as count
        FROM reports
        WHERE type = 'rami' AND type_acte IS NOT NULL AND type_acte != ''{$yearFilter}
        GROUP BY type_acte
        ORDER BY count DESC";
    $stmt = $pdo->prepare($sqlType);
    $stmt->execute($params);
    $byType = $stmt->fetchAll();

    return ['by_nature_auteur' => $byNature, 'by_type_acte' => $byType];
}

/**
 * Get notification settings.
 * 
 * @param PDO $pdo  Database connection
 * @return array<int, array<string, mixed>>
 */
function getNotificationSettings(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT ns.*, s.code as site_code, s.nom as site_nom
        FROM notification_settings ns
        LEFT JOIN sites s ON ns.site_id = s.id
        ORDER BY ns.type, s.code, ns.registry
    ");
    return $stmt->fetchAll();
}

/**
 * Save a notification email setting.
 * 
 * @param PDO    $pdo       Database connection
 * @param int    $siteId    Site ID (null for global)
 * @param string $type      'site' or 'global'
 * @param string $registry  'rsst', 'rami', 'dgi', or 'all'
 * @param string $email     Email address
 * @return int
 */
function saveNotificationSetting(PDO $pdo, ?int $siteId, string $type, string $registry, string $email): int {
    $stmt = $pdo->prepare("
        INSERT INTO notification_settings (site_id, type, registry, email)
        VALUES (:site_id, :type, :registry, :email)
    ");
    $stmt->execute([
        ':site_id' => $siteId,
        ':type'    => $type,
        ':registry'=> $registry,
        ':email'   => $email,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Delete a notification setting.
 * 
 * @param PDO $pdo  Database connection
 * @param int $id   Setting ID
 * @return bool
 */
function deleteNotificationSetting(PDO $pdo, int $id): bool {
    $stmt = $pdo->prepare("DELETE FROM notification_settings WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

/**
 * Delete all notification settings by type ('site' or 'global').
 * 
 * @param PDO    $pdo   Database connection
 * @param string $type  'site' or 'global'
 * @return int   Number of deleted rows
 */
function deleteNotificationSettingsByType(PDO $pdo, string $type): int {
    $stmt = $pdo->prepare("DELETE FROM notification_settings WHERE type = :type");
    $stmt->execute([':type' => $type]);
    return $stmt->rowCount();
}

/**
 * Get notification emails for a specific site.
 * 
 * @param PDO $pdo     Database connection
 * @param int $siteId  Site ID
 * @return array<int, string>       Array of email strings
 */
function getSiteNotificationEmails(PDO $pdo, int $siteId): array {
    $stmt = $pdo->prepare("SELECT email FROM notification_settings WHERE site_id = :site_id AND type = 'site'");
    $stmt->execute([':site_id' => $siteId]);
    return array_column($stmt->fetchAll(), 'email');
}

/**
 * Get global notification emails.
 * 
 * @param PDO $pdo  Database connection
 * @return array<int, string>    Array of email strings
 */
function getGlobalNotificationEmails(PDO $pdo): array {
    $stmt = $pdo->query("SELECT email FROM notification_settings WHERE type = 'global'");
    return array_column($stmt->fetchAll(), 'email');
}
