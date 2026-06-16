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
    $role = currentUserRole();
    if (empty($role)) {
        require __DIR__ . '/../../pages/access_denied.php';
        exit;
    }

    if (!in_array($role, $roles, true)) {
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
    $currentRole = currentUserRole();
    return !empty($currentRole) && $currentRole === $role;
}

/**
 * Check if the current user has any of the given roles (without exiting).
 * 
 * @param array $roles  Array of role strings
 * @return bool
 */
function hasAnyRole(array $roles): bool {
    $currentRole = currentUserRole();
    return !empty($currentRole) && in_array($currentRole, $roles, true);
}
