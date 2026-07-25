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
 * @param array<string, mixed> $context   Additional context data (will be JSON-encoded)
 * @param string|null $targetUuid UUID of the affected entity (for report entries)
 */
function auditLog(PDO $pdo, string $category, string $action, string $details, ?int $targetId = null, ?string $targetType = null, array $context = [], ?string $targetUuid = null): void
{
    try {
        AuditRepository::instance()->log($category, $action, $details, $targetId, $targetType, $context, $targetUuid);
    } catch (\Throwable $e) {
        // Audit logging must NEVER break the application
        error_log('[SST-AUDIT] Failed to write audit log: ' . $e->getMessage());
    }
}

/**
 * Get audit log entries with optional filtering and pagination.
 *
 * @param PDO    $pdo       Database connection
 * @param array<string, mixed> $filters   Filter options (category, user_id, date_from, date_to, q)
 * @param int    $page      Page number (1-based)
 * @param int    $perPage   Items per page
 * @return array{entries: array<int, array<string, mixed>>, total: int}
 */
function getAuditLog(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 50): array
{
    return AuditRepository::instance()->findPaginated($filters, $page, $perPage);
}

/**
 * Get audit log entries for a specific target entity.
 *
 * @param PDO    $pdo         Database connection
 * @param string $targetType  Entity type (report, user, site)
 * @param int|string $targetId Entity ID (int for user/site, UUID string for report)
 * @return array<int, array<string, mixed>>
 */
function getAuditLogForTarget(PDO $pdo, string $targetType, int|string $targetId): array
{
    return AuditRepository::instance()->findByTarget($targetType, $targetId);
}
