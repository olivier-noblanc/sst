<?php

/**
 * User GDPR Queries — Application SST DREETS BFC
 *
 * GDPR-related query functions for user data export and anonymization.
 * Split from user_admin_queries.php for readability.
 *
 * All functions delegate to App\Repository\UserRepository.
 */

use App\Repository\UserRepository;

/**
 * Export all data related to a user (GDPR right of access).
 * Returns an associative array with user info, reports, and responses.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   User ID
 * @return array<string, mixed>
 */
function exportUserData(PDO $pdo, int $id): array
{
    return UserRepository::instance()->exportData($id);
}

/**
 * Anonymize a user's personal data (GDPR right to erasure).
 * Replaces names and email with anonymized placeholders.
 * Keeps reports and responses for record-keeping but removes PII.
 * The user is deactivated.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   User ID
 * @return bool
 */
function anonymizeUser(PDO $pdo, int $id): bool
{
    return UserRepository::instance()->anonymize($id);
}
