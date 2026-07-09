<?php

/**
 * Statistics Queries — Application SST DREETS BFC
 *
 * All SQL queries for statistics, synthesis, and export.
 */
require_once __DIR__ . '/rami_stats_queries.php';

/**
 * Get synthesis data: counts by site, registry type, and state.
 *
 * @param PDO    $pdo     Database connection
 * @param string $year    Year filter (4-digit)
 * @param int    $siteId  Optional site filter (0 = all)
 * @return array<int, array<string, mixed>>
 */
function getSynthesisData(PDO $pdo, string $year, int $siteId = 0): array
{
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

    $params = [':year_start' => $year . '-01-01 00:00:00', ':year_next' => ((int) $year + 1) . '-01-01 00:00:00'];

    if ($siteId > 0) {
        $sql .= ' AND r.site_id = :site_id';
        $params[':site_id'] = $siteId;
    }

    $sql .= ' GROUP BY s.id, r.type ORDER BY s.code, r.type';

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
function getExportData(PDO $pdo, array $filters = []): array
{
    $sql = '
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
    ';
    $params = [];

    if (!empty($filters['type'])) {
        $sql .= ' AND r.type = :type';
        $params[':type'] = $filters['type'];
    }

    if (!empty($filters['site_id'])) {
        $sql .= ' AND r.site_id = :site_id';
        $params[':site_id'] = $filters['site_id'];
    }

    if (!empty($filters['declarant_id'])) {
        $sql .= ' AND r.declarant_id = :declarant_id';
        $params[':declarant_id'] = $filters['declarant_id'];
    }

    if (!empty($filters['date_from'])) {
        $sql .= ' AND r.date_evenement >= :date_from';
        $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $sql .= ' AND r.date_evenement <= :date_to';
        $params[':date_to'] = $filters['date_to'];
    }

    if (!empty($filters['etats']) && is_array($filters['etats'])) {
        $placeholders = [];
        foreach ($filters['etats'] as $i => $etat) {
            $key = ':etat_' . $i;
            $placeholders[] = $key;
            $params[$key] = $etat;
        }
        $sql .= ' AND r.etat IN (' . implode(', ', $placeholders) . ')';
    }

    $sql .= ' ORDER BY r.created_at DESC';

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
function getStatisticsIndicateurs(PDO $pdo, string $year = '', int $siteId = 0): array
{
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
        $sql .= ' AND created_at >= :year_start AND created_at < :year_next';
        $params[':year_start'] = $year . '-01-01 00:00:00';
        $params[':year_next'] = ((int) $year + 1) . '-01-01 00:00:00';
    }

    if ($siteId > 0) {
        $sql .= ' AND site_id = :site_id';
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
function getStatsBySite(PDO $pdo, string $year = '', int $siteId = 0): array
{
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
        $where[] = 'r.created_at >= :year_start AND r.created_at < :year_next';
        $params[':year_start'] = $year . '-01-01 00:00:00';
        $params[':year_next'] = ((int) $year + 1) . '-01-01 00:00:00';
    }

    if ($siteId > 0) {
        $where[] = 'r.site_id = :site_id';
        $params[':site_id'] = $siteId;
    }

    if (!empty($where)) {
        $sql .= ' AND ' . implode(' AND ', $where);
    }

    $sql .= ' GROUP BY s.id ORDER BY s.code';

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
function countReportsByRegistryAndSite(PDO $pdo, string $type, int $siteId): int
{
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
function getAvailableYears(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT DISTINCT strftime('%Y', created_at) as year
        FROM reports
        ORDER BY year DESC
    ");
    return array_column($stmt->fetchAll(), 'year');
}
