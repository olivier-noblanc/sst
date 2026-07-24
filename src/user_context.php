<?php

use App\Enum\UserRole;
use App\Repository\UserRepository;

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
 *   if (isAgent()) { ... }
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
 * Check if the current user is an agent.
 *
 * @return bool
 */
function isAgent(): bool
{
    return currentUserRole() === UserRole::Agent->value;
}

// ─── Site ─────────────────────────────────────────────────────────────────────
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
    $freshUser = UserRepository::instance()->findById($id);
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
