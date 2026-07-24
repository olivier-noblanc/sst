<?php

use App\Repository\UserRepository;

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
function checkSuperviseurPromotion(): void
{
    if (!isUserLoggedIn()) {
        return;
    }
    if (!isAgent()) {
        return;
    }
    if (isImpersonatingRole()) {
        return;
    }

    // Priority: DB setting (Settings UI) > environment variable
    $superviseurUsernames = getConfig('app_superviseur_usernames', '');
    if (empty($superviseurUsernames)) {
        $superviseurUsernames = getenv('APP_SUPERVISEUR_USERNAMES') !== false && getenv('APP_SUPERVISEUR_USERNAMES') !== '' ? getenv('APP_SUPERVISEUR_USERNAMES') : '';
    }
    if (empty($superviseurUsernames)) {
        return;
    }

    $users = parseSuperviseurUsernames($superviseurUsernames);
    $currentUsername = strtolower(currentUserUsername());

    if (!in_array($currentUsername, $users, true)) {
        return;
    }

    // Promote in database
    $pdo = getDB();
    $promoted = UserRepository::instance()->promoteToSuperviseur(currentUserId());

    if ($promoted) {
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
function checkUserSiteAssignment(): void
{
    if (!isUserLoggedIn()) {
        return;
    }
    if (currentUserHasSite()) {
        return;
    }

    $pdo = getDB();

    // No sites configured at all — choose_site itself redirects to home in
    // this case (see pages/choose_site.php), so redirecting there again
    // for every site-less user is an infinite home <-> choose_site loop.
    // This was the actual cause behind report_create (and every other
    // site-gated page) being unreachable for any user without a site_id
    // once the app runs with zero active sites.
    if (isNoSiteMode($pdo)) {
        return;
    }

    // Re-check from DB — the handler might have updated the DB
    // but the session didn't persist (edge case on some IIS configs)
    $refreshed = refreshCurrentUser($pdo);

    if ($refreshed && !empty(currentUser()['site_id'])) {
        // DB has the site but session didn't — now fixed by refreshCurrentUser
    } else {
        // Really no site — redirect to choose_site
        redirect(url('choose_site'));
    }
}
