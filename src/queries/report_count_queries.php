<?php

/**
 * Report Count Queries — Application SST DREETS BFC
 *
 * Counting and aggregation queries for reports.
 * Split from report_queries.php for readability (max 250 lines per file).
 */

/**
 * Count reports by state for a given registry type.
 *
 * @param PDO    $pdo         Database connection
 * @param string $type        Registry type
 * @param int    $siteId      Optional site filter
 * @param bool   $seeAllSites Whether to filter by site
 * @return array<string, int>
 */
function countReportsByState(PDO $pdo, string $type, int $siteId = 0, bool $seeAllSites = true): array
{
    $sql = "SELECT etat, COUNT(*) as count FROM reports WHERE type = :type AND etat != '" . ETAT_ABANDONNE . "'";
    $params = [':type' => $type];

    if (!$seeAllSites && $siteId > 0) {
        $sql .= ' AND site_id = :site_id';
        $params[':site_id'] = $siteId;
    }

    $sql .= ' GROUP BY etat';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $counts = [
        ETAT_NOUVEAU  => 0,
        ETAT_EN_COURS => 0,
        ETAT_TRAITE   => 0,
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
 * @return array<int, array<string, mixed>>
 */
function getReportResponses(PDO $pdo, string $reportUuid): array
{
    $stmt = $pdo->prepare('
        SELECT rr.*, u.nom, u.prenom
        FROM report_responses rr
        LEFT JOIN users u ON rr.user_id = u.id
        WHERE rr.report_uuid = :report_uuid
        ORDER BY rr.created_at ASC
    ');
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
function countActiveReports(PDO $pdo, string $type, int $siteId = 0, int $userId = 0, bool $confidentialMode = false): int
{
    $sql = "SELECT COUNT(*) FROM reports WHERE type = :type AND etat != '" . ETAT_ABANDONNE . "'";
    $params = [':type' => $type];

    if ($siteId > 0) {
        $sql .= ' AND site_id = :site_id';
        $params[':site_id'] = $siteId;
    }

    if ($confidentialMode && $userId > 0) {
        $sql .= ' AND (is_confidential = 0 OR declarant_id = :user_id)';
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
function countActiveReportsForUser(PDO $pdo, string $type, int $userId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE type = :type AND etat != '" . ETAT_ABANDONNE . "' AND declarant_id = :user_id");
    $stmt->execute([':type' => $type, ':user_id' => $userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get adjacent report UUIDs (previous and next) for prev/next navigation.
 * Uses created_at ordering within the same registry type.
 *
 * @param PDO   $pdo     Database connection
 * @param array<string, mixed> $report  Current report data (must have 'uuid', 'type', 'created_at')
 * @return array{prev: string|null, next: string|null}
 */
function getAdjacentReportUuids(PDO $pdo, array $report): array
{
    $type = $report['type'] ?? 'rsst';
    $createdAt = $report['created_at'] ?? '';
    $uuid = $report['uuid'] ?? '';

    $result = ['prev' => null, 'next' => null];

    // Previous: same type, created before this report, most recent first
    $stmt = $pdo->prepare('
        SELECT uuid FROM reports
        WHERE type = :type AND created_at < :created_at
        ORDER BY created_at DESC
        LIMIT 1
    ');
    $stmt->execute([':type' => $type, ':created_at' => $createdAt]);
    $prev = $stmt->fetchColumn();
    if ($prev) {
        $result['prev'] = $prev;
    }

    // Also check same timestamp but alphabetically before (tie-break)
    $stmt2 = $pdo->prepare('
        SELECT uuid FROM reports
        WHERE type = :type AND created_at = :created_at AND uuid < :uuid
        ORDER BY uuid DESC
        LIMIT 1
    ');
    $stmt2->execute([':type' => $type, ':created_at' => $createdAt, ':uuid' => $uuid]);
    $prevTie = $stmt2->fetchColumn();
    if ($prevTie && !$result['prev']) {
        $result['prev'] = $prevTie;
    }

    // Next: same type, created after this report, earliest first
    $stmt3 = $pdo->prepare('
        SELECT uuid FROM reports
        WHERE type = :type AND created_at > :created_at
        ORDER BY created_at ASC
        LIMIT 1
    ');
    $stmt3->execute([':type' => $type, ':created_at' => $createdAt]);
    $next = $stmt3->fetchColumn();
    if ($next) {
        $result['next'] = $next;
    }

    // Tie-break: same timestamp, uuid after current
    $stmt4 = $pdo->prepare('
        SELECT uuid FROM reports
        WHERE type = :type AND created_at = :created_at AND uuid > :uuid
        ORDER BY uuid ASC
        LIMIT 1
    ');
    $stmt4->execute([':type' => $type, ':created_at' => $createdAt, ':uuid' => $uuid]);
    $nextTie = $stmt4->fetchColumn();
    if ($nextTie && !$result['next']) {
        $result['next'] = $nextTie;
    }

    return $result;
}
