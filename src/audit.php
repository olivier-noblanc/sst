<?php

/**
 * Audit Log — Application SST DREETS BFC
 *
 * Delegates to App\Repository\AuditRepository.
 */

use App\Repository\AuditRepository;

/**
 * Log an action to the audit trail.
 *
 * @param PDO    $pdo       Database connection
 * @param string $category  Action category (auth, report, user, site, config, export, backup, gdpr)
 * @param string $action    Specific action (create, edit, delete, etc.)
 * @param string $details   Human-readable description of the action
 * @param int|null $targetId ID of the affected entity (optional, for integer-keyed entities)
 * @param string|null $targetType Type of entity (report, user, site, etc.)
 * @param array<string, string|int|bool|null> $context   Additional context data (will be JSON-encoded)
 * @param string|null $targetUuid UUID of the affected entity (for report entries)
 */
function auditLog(PDO $pdo, string $category, string $action, string $details, ?int $targetId = null, ?string $targetType = null, array $context = [], ?string $targetUuid = null): void
{
    try {
        AuditRepository::instance()->log($category, $action, $details, $targetId, $targetType, $context, $targetUuid);
    } catch (Throwable $e) {
        // @silent-ok: audit logging must NEVER break the application
        error_log('[SST-AUDIT] Failed to write audit log: ' . $e->getMessage());
    }
}

/**
 * Get audit log entries with optional filtering and pagination.
 *
 * @param PDO    $pdo       Database connection
 * @param array<string, string|int|null> $filters   Filter options (category, user_id, date_from, date_to, q)
 * @param int    $page      Page number (1-based)
 * @param int    $perPage   Items per page
 * @return array{entries: array<int, array<string, string|int|null>>, total: int}
 */
function getAuditLog(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 50): array
{
    return AuditRepository::instance()->findPaginated($filters, $page, $perPage);
}

/**
 * Build a flat, audit-friendly context array from export filters.
 *
 * Issue #1 — Avant ce fix, export_handler.php faisait json_encode($filters)
 * puis AuditRepository::log() re-json_encode le context → double-encoding
 * (les filtres étaient stockés comme un string JSON dans un object JSON).
 * Cette fonction aplatit tous les filtres en clés scalaires filter_*,
 * compatibles avec array<string, string|int|bool|null>.
 *
 * @param array{type?: string, site_id?: int, declarant_id?: int, date_from?: string, date_to?: string, etats?: string|list<string>} $filters
 * @param int $count  Number of exported reports
 * @return array<string, string|int|bool|null>  Flat context (JSON-encodable sans nested array)
 */
function buildExportAuditContext(array $filters, int $count): array
{
    $context = ['count' => $count];

    if (isset($filters['type'])) {
        $context['filter_type'] = $filters['type'];
    }
    if (isset($filters['site_id'])) {
        $context['filter_site_id'] = $filters['site_id'];
    }
    if (isset($filters['declarant_id'])) {
        $context['filter_declarant_id'] = $filters['declarant_id'];
    }
    if (isset($filters['date_from'])) {
        $context['filter_date_from'] = $filters['date_from'];
    }
    if (isset($filters['date_to'])) {
        $context['filter_date_to'] = $filters['date_to'];
    }
    if (isset($filters['etats'])) {
        $etats = $filters['etats'];
        $etatsList = is_array($etats) ? $etats : [$etats];
        $context['filter_etats'] = implode(',', array_map('strval', $etatsList));
    }

    return $context;
}

/**
 * Get audit log entries for a specific target entity.
 *
 * @param PDO    $pdo         Database connection
 * @param string $targetType  Entity type (report, user, site)
 * @param int|string $targetId Entity ID (int for user/site, UUID string for report)
 * @return array<int, array<string, string|int|null>>
 */
function getAuditLogForTarget(PDO $pdo, string $targetType, int|string $targetId): array
{
    return AuditRepository::instance()->findByTarget($targetType, $targetId);
}
