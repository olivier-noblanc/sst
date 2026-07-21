<?php

/**
 * Report Count Queries — Application SST DREETS BFC
 *
 * Counting and aggregation queries for reports, still called from
 * production code (statistics/dashboard display).
 *
 * countReportsByState(), countActiveReports() and
 * countActiveReportsForUser() were removed as dead code: pure
 * delegating wrappers around ReportRepository with no callers outside
 * this file's own test fixtures (countReportsByState) or none at all
 * (the other two, unused anywhere in the codebase).
 *
 * All functions delegate to App\Repository\ReportRepository.
 */

use App\Repository\ReportRepository;

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
