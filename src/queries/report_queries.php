<?php

/**
 * Report Queries — Application SST DREETS BFC
 *
 * UUID helpers and the read path (getReportByUuid) still used directly
 * by production code (validation.php, mail_notifications.php,
 * NotificationService, response/attachment pages).
 *
 * Write operations live on App\Repository\ReportRepository: the
 * former createReport()/getReportsByRegistry() wrappers here, and
 * updateReport()/abandonReport()/respondToReport() in the now-removed
 * report_response_queries.php, had no callers outside test fixtures
 * (createReport()/updateReport() had even diverged from
 * ReportRepository — no transaction wrapping in the update path).
 */

require_once __DIR__ . '/report_count_queries.php';

use App\Repository\ReportRepository;

/** Generate a UUID v4. */
function generateUuid(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
        . '-' . dechex((hexdec(substr($hex, 16, 2)) & 0x3F) | 0x80) . substr($hex, 18, 2)
        . '-' . substr($hex, 20, 12);
}

/** Validate UUID format (8-4-4-4-12 hex). Accepts all variants for legacy compatibility. */
function isValidUuid(string $uuid): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
}

/** Get a single report by UUID with site and respondent info.
 * @return array<string, mixed>|null
 */
function getReportByUuid(PDO $pdo, string $uuid): ?array
{
    return ReportRepository::instance()->findById($uuid);
}
