<?php

/**
 * Migration — Index Creation
 *
 * Ensures all required indexes exist. Uses CREATE INDEX IF NOT EXISTS
 * so it is safe to run on every request without side effects.
 *
 * @param PDO $pdo
 */
function migrateIndexes(PDO $pdo): void
{
    $indexes = [
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_reports_uuid ON reports(uuid)',
        'CREATE INDEX IF NOT EXISTS idx_reports_type ON reports(type)',
        'CREATE INDEX IF NOT EXISTS idx_reports_etat ON reports(etat)',
        'CREATE INDEX IF NOT EXISTS idx_reports_site_id ON reports(site_id)',
        'CREATE INDEX IF NOT EXISTS idx_reports_declarant_id ON reports(declarant_id)',
        'CREATE INDEX IF NOT EXISTS idx_reports_created_at ON reports(created_at)',
        'CREATE INDEX IF NOT EXISTS idx_reports_type_etat ON reports(type, etat)',
        'CREATE INDEX IF NOT EXISTS idx_reports_type_site ON reports(type, site_id)',
        // Composite indexes for common query patterns
        'CREATE INDEX IF NOT EXISTS idx_reports_type_site_etat ON reports(type, site_id, etat)',
        'CREATE INDEX IF NOT EXISTS idx_reports_type_declarant_etat ON reports(type, declarant_id, etat)',
        'CREATE INDEX IF NOT EXISTS idx_reports_type_date_evenement ON reports(type, date_evenement)',
        'CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)',
        'CREATE INDEX IF NOT EXISTS idx_users_site_id ON users(site_id)',
        'CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)',
        'CREATE INDEX IF NOT EXISTS idx_report_responses_report_uuid ON report_responses(report_uuid)',
        'CREATE INDEX IF NOT EXISTS idx_notification_settings_site_id ON notification_settings(site_id)',
        'CREATE INDEX IF NOT EXISTS idx_audit_log_category ON audit_log(category)',
        'CREATE INDEX IF NOT EXISTS idx_audit_log_user_id ON audit_log(user_id)',
        'CREATE INDEX IF NOT EXISTS idx_audit_log_target ON audit_log(target_type, target_id)',
        'CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log(created_at)',
        'CREATE INDEX IF NOT EXISTS idx_report_access_log_report_uuid ON report_access_log(report_uuid)',
        'CREATE INDEX IF NOT EXISTS idx_report_access_log_user_id ON report_access_log(user_id)',
        'CREATE INDEX IF NOT EXISTS idx_report_access_log_accessed_at ON report_access_log(accessed_at)',
    ];

    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            error_log('Index migration warning: ' . $e->getMessage());
        }
    }
}
