<?php
/**
 * Middleware — Application SST DREETS BFC
 *
 * Request-level middleware that runs on every page load.
 * Extracted from public/index.php to reduce router complexity.
 *
 * Two middleware functions:
 *   1. checkSuperviseurPromotion() — auto-promote agents listed in config
 *   2. checkUserSiteAssignment() — redirect to choose_site if no site assigned
 */

/**
 * Check if the current agent should be auto-promoted to superviseur.
 *
 * If app_superviseur_usernames is set in config (Settings UI) or
 * APP_SUPERVISEUR_USERNAMES env var, check if the current agent's username
 * is in that list. If so, promote them immediately.
 *
 * Skip during impersonation — we don't want to accidentally promote
 * a superviseur who is temporarily impersonating an agent.
 */
function checkSuperviseurPromotion(): void {
    if (!isset($_SESSION['user'])) {
        return;
    }
    if ($_SESSION['user']['role'] !== 'agent') {
        return;
    }
    if (isset($_SESSION['impersonated_role'])) {
        return;
    }

    // Priority: DB setting (Settings UI) > environment variable
    $superviseurUsernames = getConfig('app_superviseur_usernames', '');
    if (empty($superviseurUsernames)) {
        $superviseurUsernames = getenv('APP_SUPERVISEUR_USERNAMES') ?: '';
    }
    if (empty($superviseurUsernames)) {
        return;
    }

    $users = array_map('trim', explode(',', strtolower($superviseurUsernames)));
    $currentUsername = strtolower($_SESSION['user']['username']);

    if (!in_array($currentUsername, $users)) {
        return;
    }

    // Promote in database
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE users SET role = 'superviseur', updated_at = datetime('now') WHERE id = :id AND role = 'agent'");
    $stmt->execute([':id' => (int) $_SESSION['user']['id']]);

    if ($stmt->rowCount() > 0) {
        // Promotion applied — refresh session from DB
        refreshCurrentUser($pdo);
        error_log("SST App: Auto-promoted user '$currentUsername' to superviseur (config list rule, session refresh)");
    }
}

/**
 * Ensure the authenticated user has a site assigned.
 *
 * If the user has no site_id in session, re-check from DB first
 * (the handler might have updated the DB but session didn't persist
 * on some IIS configs). If still no site, redirect to choose_site.
 *
 * This MUST be called after authentication and choose_site handling.
 */
function checkUserSiteAssignment(): void {
    if (!isset($_SESSION['user'])) {
        return;
    }
    if (!empty($_SESSION['user']['site_id'])) {
        return;
    }

    // Re-check from DB — the handler might have updated the DB
    // but the session didn't persist (edge case on some IIS configs)
    $pdo = getDB();
    $refreshed = refreshCurrentUser($pdo);

    if ($refreshed && !empty(currentUser()['site_id'])) {
        // DB has the site but session didn't — now fixed by refreshCurrentUser
    } else {
        // Really no site — redirect to choose_site
        redirect(url('choose_site'));
    }
}
