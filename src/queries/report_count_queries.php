<?php

/**
 * Report Count Queries — Application SST DREETS BFC
 *
 * Counting and aggregation queries for reports.
 * Split from report_queries.php for readability (max 250 lines per file).
 *
 * All functions delegate to App\Repository\ReportRepository.
 */

use App\Repository\ReportRepository;

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
    return ReportRepository::instance()->countByState($type, $siteId, $seeAllSites);
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
    return ReportRepository::instance()->getResponses($reportUuid);
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
    return ReportRepository::instance()->countActive($type, $siteId, $userId, $confidentialMode);
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
    return ReportRepository::instance()->countActiveForUser($type, $userId);
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
    return ReportRepository::instance()->getAdjacentUuids($report);
}
