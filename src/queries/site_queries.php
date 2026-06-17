<?php
/**
 * Site Queries — Application SST DREETS BFC
 * 
 * All SQL queries related to sites (Unités Régionales).
 */

/**
 * Get all sites.
 * 
 * @param PDO $pdo  Database connection
 * @return array<int, array<string, mixed>>
 */
function getAllSites(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM sites ORDER BY code ASC");
    return $stmt->fetchAll();
}

/**
 * Get all active sites.
 * 
 * @param PDO $pdo  Database connection
 * @return array<int, array<string, mixed>>
 */
function getActiveSites(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM sites WHERE is_active = 1 ORDER BY code ASC");
    return $stmt->fetchAll();
}

/**
 * Get a site by its ID.
 * 
 * @param PDO $pdo  Database connection
 * @param int $id   Site ID
 * @return array<string, mixed>|null
 */
function getSiteById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get a site by its code (e.g. "UR25").
 * 
 * @param PDO    $pdo   Database connection
 * @param string $code  Site code
 * @return array<string, mixed>|null
 */
function getSiteByCode(PDO $pdo, string $code): ?array {
    $stmt = $pdo->prepare("SELECT * FROM sites WHERE code = :code");
    $stmt->execute([':code' => $code]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get a site by its name.
 * 
 * @param PDO    $pdo  Database connection
 * @param string $nom  Site name
 * @return array<string, mixed>|null
 */
function getSiteByName(PDO $pdo, string $nom): ?array {
    $stmt = $pdo->prepare("SELECT * FROM sites WHERE nom = :nom");
    $stmt->execute([':nom' => $nom]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Create a new site.
 * 
 * @param PDO    $pdo         Database connection
 * @param string $code        Site code (e.g. "UR21")
 * @param string $nom         Site name (e.g. "UR Côte-d'Or")
 * @param string $departement Department (e.g. "Côte-d'Or")
 * @return int    The new site ID
 */
function createSite(PDO $pdo, string $code, string $nom, string $departement = ''): int {
    $stmt = $pdo->prepare('INSERT INTO sites (code, nom, departement) VALUES (:code, :nom, :departement)');
    $stmt->execute([
        ':code'        => $code,
        ':nom'         => $nom,
        ':departement' => $departement,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Update a site.
 * 
 * @param PDO    $pdo         Database connection
 * @param int    $id          Site ID
 * @param string $code        Site code
 * @param string $nom         Site name
 * @param string $departement Department
 * @return bool
 */
function updateSite(PDO $pdo, int $id, string $code, string $nom, string $departement = ''): bool {
    $stmt = $pdo->prepare('UPDATE sites SET code = :code, nom = :nom, departement = :departement WHERE id = :id');
    $stmt->execute([
        ':code'        => $code,
        ':nom'         => $nom,
        ':departement' => $departement,
        ':id'          => $id,
    ]);
    return $stmt->rowCount() > 0;
}

/**
 * Toggle site active status.
 * 
 * @param PDO  $pdo      Database connection
 * @param int  $id       Site ID
 * @param bool $active   Active status
 * @return bool
 */
function toggleSiteActive(PDO $pdo, int $id, bool $active): bool {
    $stmt = $pdo->prepare('UPDATE sites SET is_active = :active WHERE id = :id');
    $stmt->execute([':active' => $active ? 1 : 0, ':id' => $id]);
    return $stmt->rowCount() > 0;
}

/**
 * Count users assigned to a site.
 * 
 * @param PDO $pdo  Database connection
 * @param int $id   Site ID
 * @return int
 */
function countUsersBySite(PDO $pdo, int $id): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE site_id = :id AND is_active = 1');
    $stmt->execute([':id' => $id]);
    return (int) $stmt->fetchColumn();
}

/**
 * Count reports assigned to a site.
 * 
 * @param PDO $pdo  Database connection
 * @param int $id   Site ID
 * @return int
 */
function countReportsBySite(PDO $pdo, int $id): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE site_id = :id');
    $stmt->execute([':id' => $id]);
    return (int) $stmt->fetchColumn();
}

/**
 * Delete a site permanently.
 * Only allowed if the site has no users and no reports.
 * 
 * @param PDO $pdo  Database connection
 * @param int $id   Site ID
 * @return bool     True if deleted, false if not allowed
 */
function deleteSite(PDO $pdo, int $id): bool {
    // Safety: refuse deletion if site has active users
    $userCount = countUsersBySite($pdo, $id);
    if ($userCount > 0) {
        return false;
    }

    // Safety: refuse deletion if site has reports
    $reportCount = countReportsBySite($pdo, $id);
    if ($reportCount > 0) {
        return false;
    }

    // Delete notification settings linked to this site
    $stmt = $pdo->prepare('DELETE FROM notification_settings WHERE site_id = :id');
    $stmt->execute([':id' => $id]);

    // Delete the site
    $stmt = $pdo->prepare('DELETE FROM sites WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}
