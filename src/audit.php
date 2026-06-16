<?php
/**
 * Audit Log — Application SST DREETS BFC
 *
 * General-purpose audit trail for all significant actions.
 * Complements report_responses (which only tracks supervisor responses).
 *
 * Categories:
 *   - auth       : login, logout, login_failed
 *   - report     : create, edit, abandon, respond, attachment_upload
 *   - user       : create, edit, delete, reactivate, role_change
 *   - site       : create, edit, activate, deactivate
 *   - config     : update
 *   - export     : csv_export
 *   - backup     : auto_backup, pre_migration_backup
 *   - gdpr       : data_export, anonymize
 */

/**
 * Log an action to the audit trail.
 *
 * @param PDO    $pdo       Database connection
 * @param string $category  Action category (auth, report, user, site, config, export, backup, gdpr)
 * @param string $action    Specific action (create, edit, delete, etc.)
 * @param string $details   Human-readable description of the action
 * @param int|null $targetId ID of the affected entity (optional)
 * @param string|null $targetType Type of entity (report, user, site, etc.)
 * @param array  $context   Additional context data (will be JSON-encoded)
 */
function auditLog(PDO $pdo, string $category, string $action, string $details, ?int $targetId = null, ?string $targetType = null, array $context = []): void {
    try {
        $userId = currentUserId();
        $username = currentUserUsername() ?: 'system';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';

        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, username, category, action, target_id, target_type, details, context, ip_address)
            VALUES (:user_id, :username, :category, :action, :target_id, :target_type, :details, :context, :ip)
        ");
        $stmt->execute([
            ':user_id'     => $userId,
            ':username'    => $username,
            ':category'    => $category,
            ':action'      => $action,
            ':target_id'   => $targetId,
            ':target_type' => $targetType,
            ':details'     => $details,
            ':context'     => !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            ':ip'          => $ip,
        ]);
    } catch (Exception $e) {
        // Audit logging must NEVER break the application
        error_log('[SST-AUDIT] Failed to write audit log: ' . $e->getMessage());
    }
}

/**
 * Get audit log entries with optional filtering and pagination.
 *
 * @param PDO    $pdo       Database connection
 * @param array  $filters   Filter options (category, user_id, date_from, date_to, q)
 * @param int    $page      Page number (1-based)
 * @param int    $perPage   Items per page
 * @return array            ['entries' => array, 'total' => int]
 */
function getAuditLog(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 50): array {
    $where = "1=1";
    $params = [];

    if (!empty($filters['category'])) {
        $where .= " AND category = :category";
        $params[':category'] = $filters['category'];
    }

    if (!empty($filters['user_id'])) {
        $where .= " AND user_id = :user_id";
        $params[':user_id'] = (int) $filters['user_id'];
    }

    if (!empty($filters['date_from'])) {
        $where .= " AND created_at >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where .= " AND created_at <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }

    if (!empty($filters['q'])) {
        $where .= " AND (details LIKE :q OR username LIKE :q2)";
        $params[':q'] = '%' . $filters['q'] . '%';
        $params[':q2'] = '%' . $filters['q'] . '%';
    }

    if (!empty($filters['username'])) {
        $where .= " AND username = :filter_username";
        $params[':filter_username'] = $filters['username'];
    }

    // Count
    $countSql = "SELECT COUNT(*) FROM audit_log WHERE $where";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Fetch page
    $offset = ($page - 1) * $perPage;
    $sql = "SELECT * FROM audit_log WHERE $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $perPage;
    $params[':offset'] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return ['entries' => $stmt->fetchAll(), 'total' => $total];
}

/**
 * Get audit log entries for a specific target entity.
 *
 * @param PDO    $pdo         Database connection
 * @param string $targetType  Entity type (report, user, site)
 * @param int    $targetId    Entity ID
 * @return array
 */
function getAuditLogForTarget(PDO $pdo, string $targetType, int $targetId): array {
    $stmt = $pdo->prepare("
        SELECT * FROM audit_log
        WHERE target_type = :target_type AND target_id = :target_id
        ORDER BY created_at DESC
    ");
    $stmt->execute([':target_type' => $targetType, ':target_id' => $targetId]);
    return $stmt->fetchAll();
}
