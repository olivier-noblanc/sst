<?php

/**
 * Site Queries — Application SST DREETS BFC
 *
 * All SQL queries related to sites (Unités Régionales).
 *
 * All functions delegate to App\Repository\SiteRepository.
 */

use App\Repository\SiteRepository;

/**
 * Get all sites.
 *
 * @param PDO $pdo  Database connection
 * @return array<int, array<string, mixed>>
 */
function getAllSites(PDO $pdo): array
{
    $result = SiteRepository::instance()->findAll();
    /** @var array<int, array<string, mixed>> $result */
    return $result;
}

/**
 * Get all active sites.
 *
 * @param PDO $pdo  Database connection
 * @return array<int, array<string, mixed>>
 */
function getActiveSites(PDO $pdo): array
{
    $result = SiteRepository::instance()->findActive();
    /** @var array<int, array<string, mixed>> $result */
    return $result;
}

/**
 * Get a site by its ID.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   Site ID
 * @return array<string, mixed>|null
 */
function getSiteById(PDO $pdo, int $id): ?array
{
    $result = SiteRepository::instance()->findById($id);
    /** @var array<string, mixed>|null $result */
    return $result;
}

/**
 * Get a site by its code (e.g. "UR25").
 *
 * @param PDO    $pdo   Database connection
 * @param string $code  Site code
 * @return array<string, mixed>|null
 */
function getSiteByCode(PDO $pdo, string $code): ?array
{
    $result = SiteRepository::instance()->findByCode($code);
    /** @var array<string, mixed>|null $result */
    return $result;
}

/**
 * Get a site by its name.
 *
 * @param PDO    $pdo  Database connection
 * @param string $nom  Site name
 * @return array<string, mixed>|null
 */
function getSiteByName(PDO $pdo, string $nom): ?array
{
    $result = SiteRepository::instance()->findByName($nom);
    /** @var array<string, mixed>|null $result */
    return $result;
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
function createSite(PDO $pdo, string $code, string $nom, string $departement = ''): int
{
    return SiteRepository::instance()->create($code, $nom, $departement);
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
function updateSite(PDO $pdo, int $id, string $code, string $nom, string $departement = ''): bool
{
    return SiteRepository::instance()->update($id, $code, $nom, $departement);
}

/**
 * Toggle site active status.
 *
 * @param PDO  $pdo      Database connection
 * @param int  $id       Site ID
 * @param bool $active   Active status
 * @return bool
 */
function toggleSiteActive(PDO $pdo, int $id, bool $active): bool
{
    return SiteRepository::instance()->toggleActive($id, $active);
}

/**
 * Count users assigned to a site.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   Site ID
 * @return int
 */
function countUsersBySite(PDO $pdo, int $id): int
{
    return SiteRepository::instance()->countUsers($id);
}

/**
 * Count reports assigned to a site.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   Site ID
 * @return int
 */
function countReportsBySite(PDO $pdo, int $id): int
{
    return SiteRepository::instance()->countReports($id);
}

/**
 * Delete a site permanently.
 * Only allowed if the site has no users and no reports.
 *
 * @param PDO $pdo  Database connection
 * @param int $id   Site ID
 * @return bool     True if deleted, false if not allowed
 */
function deleteSite(PDO $pdo, int $id): bool
{
    return SiteRepository::instance()->delete($id);
}
