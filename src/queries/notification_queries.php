<?php

/**
 * Notification Settings Queries — Application SST DREETS BFC
 *
 * Notification email settings management.
 * Split from stats_queries.php for readability.
 *
 * All functions delegate to App\Repository\NotificationRepository.
 */

use App\Repository\NotificationRepository;

/**
 * Get notification settings.
 *
 * @param PDO $pdo  Database connection
 * @return array<int, array<string, mixed>>
 */
function getNotificationSettings(PDO $pdo): array
{
    return NotificationRepository::instance()->findAll();
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
    return NotificationRepository::instance()->save($siteId, $type, $registry, $email);
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
    return NotificationRepository::instance()->delete($id);
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
    return NotificationRepository::instance()->deleteByType($type);
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
    return NotificationRepository::instance()->findSiteEmails($siteId);
}

/**
 * Get global notification emails.
 *
 * @param PDO $pdo  Database connection
 * @return array<int, string>    Array of email strings
 */
function getGlobalNotificationEmails(PDO $pdo): array
{
    return NotificationRepository::instance()->findGlobalEmails();
}
