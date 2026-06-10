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
 */

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
 * @return array|null  User data array or null if not authenticated
 */
function getAuthenticatedUser(): ?array {
    // If user is already in session, return it (avoids DB hit on every request)
    if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
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
            $_SESSION['user'] = $user;
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
function extractUsername(string $authUser): string {
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
 * @return array|null       User data or null on failure
 */
function findOrCreateUser(string $username): ?array {
    $pdo = getDB();

    // Look up existing user
    $stmt = $pdo->prepare(
        'SELECT u.*, s.code as site_code, s.nom as site_nom 
         FROM users u 
         LEFT JOIN sites s ON u.site_id = s.id 
         WHERE u.username = :username AND u.is_active = 1'
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user) {
        // Check if existing user should be auto-promoted (e.g. "adm." prefix rule)
        $user = checkAndPromoteUser($pdo, $user, $username);
        return $user;
    }

    // User not found — auto-provision with basic info
    return autoProvisionUser($pdo, $username);
}

/**
 * Auto-provision a new user from their Windows login.
 * Generates display name from username (e.g. "jean.martin" → Jean Martin).
 * Checks admin list for auto-promotion to superviseur.
 * 
 * @param PDO    $pdo       Database connection
 * @param string $username  The username (clean, without domain)
 * @return array|null
 */
function autoProvisionUser(PDO $pdo, string $username): ?array {
    // Determine role: check if username is in admin list
    $role = determineProvisionRole($pdo, $username);

    // Strip admin prefix from username for display name generation
    // e.g. "adm.olivier.noblanc" → "olivier.noblanc" → "Olivier Noblanc"
    $displayNameUsername = $username;
    $adminPrefix = getConfig('app_admin_prefix', 'adm.');
    if (!empty($adminPrefix) && str_starts_with(strtolower($username), strtolower($adminPrefix))) {
        $displayNameUsername = substr($username, strlen($adminPrefix));
    }

    // Generate a display name from the username (e.g. "olivier.noblanc" → Olivier Noblanc)
    $parts = explode('.', $displayNameUsername);
    $prenom = ucfirst($parts[0] ?? $displayNameUsername);
    $nom = ucfirst($parts[1] ?? 'Utilisateur');
    // If there are more than 2 parts, join them for the last name
    if (count($parts) > 2) {
        $nom = ucfirst($parts[1]) . ' ' . ucfirst($parts[2]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (username, nom, prenom, email, role, site_id) 
         VALUES (:username, :nom, :prenom, :email, :role, :site_id)'
    );
    $stmt->execute([
        ':username' => $username,
        ':nom'      => $nom,
        ':prenom'   => $prenom,
        ':email'    => $username . '@dreets.gouv.fr',
        ':role'     => $role,
        ':site_id'  => null,  // NULL = agent must choose their site on first login
    ]);

    $userId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare(
        'SELECT u.*, s.code as site_code, s.nom as site_nom 
         FROM users u 
         LEFT JOIN sites s ON u.site_id = s.id 
         WHERE u.id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

/**
 * Attempt mock login (DEV_MODE only).
 * Called by the login form handler when a user submits the mock login form.
 * 
 * @param string $username  The username from the login form
 * @return array|null       User data or null on failure
 */
function mockLogin(string $username): ?array {
    if (!DEV_MODE) {
        return null;  // Never in production
    }

    $username = trim($username);
    if (empty($username)) {
        return null;
    }

    $user = findOrCreateUser($username);
    if ($user) {
        $_SESSION['user'] = $user;
        return $user;
    }

    return null;
}

/**
 * Determine the role for a newly provisioned user.
 * Two mechanisms for auto-promotion to 'superviseur':
 * 
 * 1. Prefix rule: if username starts with 'app_admin_prefix' (default: "adm."),
 *    the user is automatically promoted to superviseur.
 *    Example: "adm.olivier.noblanc" → Superviseur
 * 
 * 2. Explicit list: if username is in 'app_admin_usernames' (comma-separated),
 *    the user is promoted to superviseur.
 *    Example: "jean.martin, sophie.dupont"
 * 
 * @param PDO    $pdo       Database connection
 * @param string $username  The Windows login username
 * @return string           Role: 'superviseur' or 'agent'
 */
function determineProvisionRole(PDO $pdo, string $username): string {
    // 1. Check admin prefix (e.g. "adm.")
    $adminPrefix = getConfig('app_admin_prefix', 'adm.');
    if (!empty($adminPrefix) && str_starts_with(strtolower($username), strtolower($adminPrefix))) {
        return 'superviseur';
    }

    // 2. Check explicit username list
    $adminUsernames = getConfig('app_admin_usernames', '');
    if (!empty($adminUsernames)) {
        // Comma-separated list: "jean.martin, sophie.dupont"
        $admins = array_map('trim', explode(',', strtolower($adminUsernames)));
        if (in_array(strtolower($username), $admins)) {
            return 'superviseur';
        }
    }
    return 'agent';
}

/**
 * Check if an existing user should be auto-promoted to superviseur.
 * This handles the case where a user was created before the admin
 * prefix rule was established — on their next login, if their username
 * now matches the admin prefix, their role is upgraded.
 * 
 * @param PDO    $pdo       Database connection
 * @param array  $user      The existing user data from DB
 * @param string $username  The username (for prefix check)
 * @return array            Updated user data (role may be upgraded)
 */
function checkAndPromoteUser(PDO $pdo, array $user, string $username): array {
    // Only promote agents and managers — not CHSCT members
    if (!in_array($user['role'], ['agent', 'manager'])) {
        return $user;
    }

    // Check if username matches admin prefix
    $adminPrefix = getConfig('app_admin_prefix', 'adm.');
    $shouldPromote = false;

    if (!empty($adminPrefix) && str_starts_with(strtolower($username), strtolower($adminPrefix))) {
        $shouldPromote = true;
    }

    // Also check explicit username list
    if (!$shouldPromote) {
        $adminUsernames = getConfig('app_admin_usernames', '');
        if (!empty($adminUsernames)) {
            $admins = array_map('trim', explode(',', strtolower($adminUsernames)));
            if (in_array(strtolower($username), $admins)) {
                $shouldPromote = true;
            }
        }
    }

    if ($shouldPromote) {
        $stmt = $pdo->prepare("UPDATE users SET role = 'superviseur', updated_at = datetime('now') WHERE id = :id");
        $stmt->execute([':id' => $user['id']]);
        $user['role'] = 'superviseur';

        // Log the promotion
        error_log("SST App: Auto-promoted user '$username' to superviseur (admin prefix/list rule)");
    }

    return $user;
}
