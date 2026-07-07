<?php

/**
 * Notification Settings Queries — Application SST DREETS BFC
 *
 * Notification email settings management.
 * Split from stats_queries.php for readability.
 */

/**
 * Get notification settings.
 *
 * @param PDO $pdo  Database connection
 * @return array<int, array<string, mixed>>
 */
function getNotificationSettings(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT ns.*, s.code as site_code, s.nom as site_nom
        FROM notification_settings ns
        LEFT JOIN sites s ON ns.site_id = s.id
        ORDER BY ns.type, s.code, ns.registry
    ');
    return $stmt->fetchAll();
}

/**
 * Save a notification email setting.
 *
 * @param PDO    $pdo       Database connection
 * @param int    $siteId    Site ID (null for global)
 * @param string $type      'site' or 'global'
 * @param string $registry  'rsst', 'rami', 'dgi', or 'all'
 * @param string $email     Email address
 * @return int
 */
function saveNotificationSetting(PDO $pdo, ?int $siteId, string $type, string $registry, string $email): int
{
    // Prevent duplicates: delete existing matching setting first
    $stmt = $pdo->prepare('DELETE FROM notification_settings WHERE site_id = :site_id AND type = :type AND registry = :registry AND email = :email');
    $stmt->execute([
        ':site_id'  => $siteId,
        ':type'     => $type,
        ':registry' => $registry,
        ':email'    => $email,
    ]);

    $stmt = $pdo->prepare('
        INSERT INTO notification_settings (site_id, type, registry, email)
        VALUES (:site_id, :type, :registry, :email)
    ');
    $stmt->execute([
        ':site_id'  => $siteId,
        ':type'     => $type,
        ':registry' => $registry,
        ':email'    => $email,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Delete a notification setting.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   Setting ID
 * @return bool
 */
function deleteNotificationSetting(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM notification_settings WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

/**
 * Delete all notification settings by type ('site' or 'global').
 *
 * @param PDO    $pdo   Database connection
 * @param string $type  'site' or 'global'
 * @return int   Number of deleted rows
 */
function deleteNotificationSettingsByType(PDO $pdo, string $type): int
{
    $stmt = $pdo->prepare('DELETE FROM notification_settings WHERE type = :type');
    $stmt->execute([':type' => $type]);
    return $stmt->rowCount();
}

/**
 * Get notification emails for a specific site.
 *
 * @param PDO $pdo     Database connection
 * @param int $siteId  Site ID
 * @return array<int, string>       Array of email strings
 */
function getSiteNotificationEmails(PDO $pdo, int $siteId): array
{
    $stmt = $pdo->prepare("SELECT email FROM notification_settings WHERE site_id = :site_id AND type = 'site'");
    $stmt->execute([':site_id' => $siteId]);
    return array_column($stmt->fetchAll(), 'email');
}

/**
 * Get global notification emails.
 *
 * @param PDO $pdo  Database connection
 * @return array<int, string>    Array of email strings
 */
function getGlobalNotificationEmails(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT email FROM notification_settings WHERE type = 'global'");
    return array_column($stmt->fetchAll(), 'email');
}
