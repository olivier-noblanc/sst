<?php

/**
 * Authentication — Application SST DREETS BFC
 *
 * Two authentication modes:
 *
 * 1. PRODUCTION (DEV_MODE=false):
 *    IIS handles Windows Authentication BEFORE PHP runs.
 *    $_SERVER['AUTH_USER'] is ALWAYS set (format: "DOMAIN\username").
 *    PHP extracts the username, looks up or auto-creates the user in DB.
 *    No LDAP, no login form — just AUTH_USER.
 *
 * 2. DEVELOPMENT (DEV_MODE=true):
 *    No IIS, so we use a mock login form (see pages/login.php).
 *    Users like "admin.dev", "agent.dev" are seeded in the database.
 *    Any unknown username auto-creates an agent account.
 *
 * Role attribution (superviseur only):
 *    - A superviseur can assign the superviseur role to another user via the UI
 *    - A username list in config (app_superviseur_usernames) allows bootstrap
 *      for first install: users in this list are auto-promoted on first login
 */

require_once __DIR__ . '/auth_provision.php';

/**
 * Get the currently authenticated user.
 *
 * In PROD: reads $_SERVER['AUTH_USER'] from IIS Windows Auth,
 *          extracts username (strips domain), looks up or auto-creates user.
 * In DEV:  returns null (login is handled by the mock login form).
 *
 * This function is called on EVERY request in index.php to auto-authenticate
 * the user if they're not yet in the session.
 *
 * @return array<string, mixed>|null  User data array or null if not authenticated
 */
function getAuthenticatedUser(): ?array
{
    // If user is already in session, return it (avoids DB hit on every request)
    if (isUserLoggedIn()) {
        return getUserSession();
    }

    // Production mode: IIS provides AUTH_USER automatically
    if (!DEV_MODE) {
        $authUser = $_SERVER['AUTH_USER'] ?? '';
        if (empty($authUser)) {
            // IIS Windows Auth not configured — this is a misconfiguration
            return null;
        }

        $username = extractUsername($authUser);
        if (empty($username)) {
            return null;
        }

        $user = findOrCreateUser($username);
        if ($user) {
            setUserSession($user);
            return $user;
        }
    }

    // Dev mode: no automatic auth — user must use the mock login form
    return null;
}

/**
 * Extract a clean username from the AUTH_USER server variable.
 * IIS passes it as "DOMAIN\username" or "username@domain".
 * The domain part must be stripped — only the login is kept.
 *
 * Examples:
 *   "DREETS-BFC\jean.martin" → "jean.martin"
 *   "DREETS-BFC\ADMIN.SUPER" → "admin.super"  (lowercased)
 *   "jean.martin@dreets-bfc.gouv.fr" → "jean.martin"
 *   "jean.martin" → "jean.martin"
 *
 * @param string $authUser  The raw AUTH_USER value from IIS
 * @return string           Clean lowercase username
 */
function extractUsername(string $authUser): string
{
    $authUser = trim($authUser);
    if (empty($authUser)) {
        return '';
    }

    // Handle DOMAIN\username format (most common with IIS Windows Auth)
    if (strpos($authUser, '\\') !== false) {
        $parts = explode('\\', $authUser);
        return strtolower(trim(end($parts)));
    }
    // Handle username@domain format (UPN style)
    if (strpos($authUser, '@') !== false) {
        $parts = explode('@', $authUser);
        return strtolower(trim($parts[0]));
    }
    // Already just a username
    return strtolower($authUser);
}

/**
 * Find an existing user in the database, or create one.
 * No LDAP — just uses the username from IIS.
 *
 * @param string $username  The clean username (without domain prefix)
 * @return array<string, mixed>|null       User data or null on failure
 */
function findOrCreateUser(string $username): ?array
{
    $pdo = getDB();

    // Look up existing user (including deactivated — they may need reactivation)
    require_once __DIR__ . '/queries/user_queries.php';
    $stmt = $pdo->prepare(
        userSelectWithSite() . ' WHERE u.username = :username'
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user) {
        // If user is deactivated, deny login with clear message
        if (!$user['is_active']) {
            return null;  // Will be caught as "auth failed" — superviseur must reactivate
        }
        // Check if existing user should be auto-promoted via config list
        $user = checkAndPromoteUser($pdo, $user, $username);
        return $user;
    }

    // User not found — auto-provision with basic info
    return autoProvisionUser($pdo, $username);
}

/**
 * Attempt mock login (DEV_MODE only).
 * Called by the login form handler when a user submits the mock login form.
 *
 * @param string $username  The username from the login form
 * @return array<string, mixed>|null       User data or null on failure
 */
function mockLogin(string $username): ?array
{
    if (!DEV_MODE) {
        return null;  // Never in production
    }

    $username = trim($username);
    if (empty($username)) {
        return null;
    }

    $user = findOrCreateUser($username);
    if ($user) {
        setUserSession($user);
        return $user;
    }

    return null;
}

/**
 * Parse a comma-separated superviseur username list into a normalized array.
 *
 * Used in multiple places: auth provisioning, promotion checks, and middleware.
 *
 * @param string $list  Comma-separated username list (e.g. "jean.martin, sophie.dupont")
 * @return array<int, string>        Lowercased, trimmed array of usernames
 */
function parseSuperviseurUsernames(string $list): array
{
    return array_map('trim', explode(',', strtolower($list)));
}

/**
 * Determine the role for a newly provisioned user.
 *
 * Mechanism: if username is in 'app_superviseur_usernames' (comma-separated),
 * the user is promoted to superviseur.
 * Example: "jean.martin, sophie.dupont"
 *
 * This is useful for first install: add superviseur logins to the config
 * so they are automatically promoted when they first connect.
 *
 * @param PDO    $pdo       Database connection
 * @param string $username  The Windows login username
 * @return string           Role: 'superviseur' or 'agent'
 */
function determineProvisionRole(PDO $pdo, string $username): string
{
    // Check superviseur username list
    $superviseurUsernames = getConfig('app_superviseur_usernames', '');
    if (!empty($superviseurUsernames)) {
        $users = parseSuperviseurUsernames($superviseurUsernames);
        if (in_array(strtolower($username), $users)) {
            return ROLE_SUPERVISEUR;
        }
    }
    return ROLE_AGENT;
}

/**
 * Check if an existing user should be auto-promoted to superviseur.
 * This handles the case where a user was created before the
 * superviseur username list was established — on their next login,
 * if their username is now in the list, their role is upgraded.
 *
 * @param PDO    $pdo       Database connection
 * @param array<string, mixed> $user      The existing user data from DB
 * @param string $username  The username (for list check)
 * @return array<string, mixed>            Updated user data (role may be upgraded)
 */
function checkAndPromoteUser(PDO $pdo, array $user, string $username): array
{
    // Only promote agents — not CSA/CHSCT or already-superviseur users
    if ($user['role'] !== ROLE_AGENT) {
        return $user;
    }

    // Check superviseur username list
    $superviseurUsernames = getConfig('app_superviseur_usernames', '');
    if (!empty($superviseurUsernames)) {
        $users = parseSuperviseurUsernames($superviseurUsernames);
        if (in_array(strtolower($username), $users)) {
            $stmt = $pdo->prepare("UPDATE users SET role = '" . ROLE_SUPERVISEUR . "', updated_at = datetime('now') WHERE id = :id");
            $stmt->execute([':id' => $user['id']]);
            $user['role'] = ROLE_SUPERVISEUR;

            // Log the promotion
            error_log("SST App: Auto-promoted user '$username' to superviseur (config list rule)");
        }
    }

    return $user;
}
