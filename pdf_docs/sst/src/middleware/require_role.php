<?php
/**
 * Middleware: Require Role — Application SST DREETS BFC
 * 
 * Checks that the current user has one of the required roles.
 * If not, renders the access denied page and exits.
 */

/**
 * Check if the current user has one of the required roles.
 * If not, renders the access denied page and stops execution.
 * 
 * @param array $roles  Array of allowed role strings
 */
function requireRole(array $roles): void {
    if (!isset($_SESSION['user']['role'])) {
        require __DIR__ . '/../../pages/access_denied.php';
        exit;
    }

    if (!in_array($_SESSION['user']['role'], $roles)) {
        require __DIR__ . '/../../pages/access_denied.php';
        exit;
    }
}

/**
 * Check if the current user has a specific role (without exiting).
 * 
 * @param string $role  The role to check
 * @return bool
 */
function hasRole(string $role): bool {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === $role;
}

/**
 * Check if the current user has any of the given roles (without exiting).
 * 
 * @param array $roles  Array of role strings
 * @return bool
 */
function hasAnyRole(array $roles): bool {
    return isset($_SESSION['user']['role']) && in_array($_SESSION['user']['role'], $roles);
}
