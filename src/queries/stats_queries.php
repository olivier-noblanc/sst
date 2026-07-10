<?php

/**
 * Statistics Queries — Application SST DREETS BFC
 *
 * All SQL queries for statistics, synthesis, and export.
 *
 * All functions delegate to App\Repository\StatsRepository.
 */

require_once __DIR__ . '/rami_stats_queries.php';

use App\Repository\StatsRepository;

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
    return StatsRepository::instance()->getSynthesis($year, $siteId);
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
    return StatsRepository::instance()->getExportData($filters);
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
    return StatsRepository::instance()->getIndicateurs($year, $siteId);
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
    return StatsRepository::instance()->getBySite($year, $siteId);
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
    return StatsRepository::instance()->countByRegistryAndSite($type, $siteId);
}

/**
 * Get available years from report data.
 *
 * @param PDO $pdo  Database connection
 * @return array<int, string>
 */
function getAvailableYears(PDO $pdo): array
{
    return StatsRepository::instance()->getAvailableYears();
}
