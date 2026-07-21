<?php

/**
 * User Context — Application SST DREETS BFC
 *
 * A collection of pure helper functions that encapsulate access to the
 * current user's session data. Instead of accessing $_SESSION['user']
 * directly and repeating the same null-checks and casts everywhere,
 * use these functions for cleaner, safer, more readable code.
 *
 * This is NOT a class — just a namespace of procedural functions.
 * The "UserContext" name refers to the conceptual grouping, not OOP.
 *
 * Usage:
 *   $id   = currentUserId();        // int (0 if not logged in)
 *   $role = currentUserRole();       // string ('' if not logged in)
 *   $site = currentUserSiteId();     // int (0 if none)
 *   if (isSuperviseur()) { ... }
 *   if (canAccessReport($report)) { ... }
 */
// ─── Identity ─────────────────────────────────────────────────────────────────
/**
 * Get the current user's full data array from session.
 *
 * @return array<string, mixed>|null  The user array or null if not authenticated
 */
function currentUser(): ?array
{
    return getUserSession();
}

/**
 * Get the current user's ID.
 *
 * @return int  User ID (0 if not logged in)
 */
function currentUserId(): int
{
    $user = getUserSession();
    /** @var int */
    $id = $user['id'] ?? 0;
    return $user !== null ? $id : 0;
}

/**
 * Get the current user's username.
 *
 * @return string  Username ('' if not logged in)
 */
function currentUserUsername(): string
{
    $user = getUserSession();
    if ($user === null) {
        return '';
    }
    $username = $user['username'] ?? '';
    return $username;
}

/**
 * Get the current user's full display name (Prénom Nom).
 *
 * @return string  Display name ('' if not logged in)
 */
function currentUserDisplayName(): string
{
    $user = currentUser();
    if ($user === null) {
        return '';
    }
    $prenom = $user['prenom'] ?? '';
    $nom = $user['nom'] ?? '';
    return trim($prenom . ' ' . $nom);
}

// ─── Role ─────────────────────────────────────────────────────────────────────
/**
 * Get the current user's role.
 * During impersonation, returns the impersonated role (not the real one).
 *
 * @return string  Role: 'agent', 'superviseur', 'chsct', or '' if not logged in
 */
function currentUserRole(): string
{
    $user = getUserSession();
    if ($user === null) {
        return '';
    }
    $role = $user['role'] ?? '';
    return $role;
}

/**
 * Get the current user's real role (ignoring impersonation).
 *
 * @return string  Real role or '' if not logged in
 */
function currentUserRealRole(): string
{
    $realRole = getRealRole();
    if ($realRole !== null) {
        return $realRole;
    }
    return currentUserRole();
}

/**
 * Check if the current user has a specific role.
 * This is an alias for hasRole() that is more readable in context.
 *
 * @param string $role  The role to check
 * @return bool
 */
function isRole(string $role): bool
{
    return currentUserRole() === $role;
}

/**
 * Check if the current user is an agent.
 *
 * @return bool
 */
function isAgent(): bool
{
    return currentUserRole() === ROLE_AGENT;
}

/**
 * Check if the current user is a superviseur.
 *
 * @return bool
 */
function isSuperviseur(): bool
{
    return currentUserRole() === ROLE_SUPERVISEUR;
}

/**
 * Check if the current user is CHSCT/CSA.
 *
 * @return bool
 */
function isChsct(): bool
{
    return currentUserRole() === ROLE_CHSCT;
}

/**
 * Check if the current user is currently impersonating another role.
 *
 * @return bool
 */
function isImpersonating(): bool
{
    return isImpersonatingRole();
}

// ─── Site ─────────────────────────────────────────────────────────────────────
/**
 * Get the current user's site ID.
 *
 * @return int  Site ID (0 if not assigned)
 */
function currentUserSiteId(): int
{
    $user = getUserSession();
    /** @var int */
    $siteId = $user['site_id'] ?? 0;
    return $user !== null ? $siteId : 0;
}

/**
 * Get the current user's site code (e.g. "UD21").
 *
 * @return string  Site code ('' if not assigned)
 */
function currentUserSiteCode(): string
{
    $user = getUserSession();
    if ($user === null) {
        return '';
    }
    $siteCode = $user['site_code'] ?? '';
    return $siteCode;
}

/**
 * Get the current user's site name (e.g. "Unité Départementale Côte-d'Or").
 *
 * @return string  Site name ('' if not assigned)
 */
function currentUserSiteName(): string
{
    $user = getUserSession();
    if ($user === null) {
        return '';
    }
    $siteName = $user['site_nom'] ?? '';
    return $siteName;
}

/**
 * Check if the current user has a site assigned.
 *
 * @return bool
 */
function currentUserHasSite(): bool
{
    $user = getUserSession();
    return !empty($user['site_id']);
}

// ─── Permissions ──────────────────────────────────────────────────────────────
/**
 * Check if the current user can see all sites (superviseur or chsct).
 * Alias for canSeeAllSites() that uses the UserContext pattern.
 *
 * @return bool
 */
function currentUserCanSeeAllSites(): bool
{
    return in_array(currentUserRole(), [ROLE_SUPERVISEUR, ROLE_CHSCT], true);
}

/**
 * Check if the current user can access a specific report.
 * Delegates to the existing canAccessReport() in helpers.php.
 *
 * @param array<string, mixed> $report           Report data from DB
 * @param string|null $forcedVisibility  Override visibility mode (for tests)
 * @return bool
 */
function currentUserCanAccessReport(array $report, ?string $forcedVisibility = null): bool
{
    $user = currentUser();
    if ($user === null) {
        return false;
    }
    return canAccessReport($report, $user, $forcedVisibility);
}

/**
 * Refresh the current user's session data from the database.
 * Useful after role/site changes to keep session in sync.
 *
 * @param PDO $pdo  Database connection
 * @return bool     True if refresh succeeded
 */
function refreshCurrentUser(PDO $pdo): bool
{
    $id = currentUserId();
    if ($id <= 0) {
        return false;
    }
    require_once __DIR__ . '/queries/user_queries.php';
    $freshUser = getUserById($pdo, $id);
    if ($freshUser !== null) {
        // Preserve impersonation state if active
        setUserSession($freshUser);
        if (isImpersonatingRole()) {
            $impersonatedRole = getImpersonatedRole() ?? ($freshUser['role'] ?? '');
            // Direct session write needed here — this is inside the session layer
            if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                $_SESSION['user']['role'] = $impersonatedRole;
            }
        }
        return true;
    }
    return false;
}
