<?php

/**
 * Middleware: Require Authentication — Application SST DREETS BFC
 *
 * Checks that the user is authenticated.
 *
 * In PROD: if AUTH_USER is missing = IIS misconfiguration → fatal error
 * In DEV:  redirect to mock login form
 *
 * This middleware runs AFTER index.php has already attempted auto-auth
 * via getAuthenticatedUser(). So if we reach here without a user,
 * it means auto-auth failed.
 */

if (!isUserLoggedIn()) {
    if (DEV_MODE) {
        // Dev: redirect to mock login form
        setIntendedUrl($_SERVER['REQUEST_URI'] ?? '');
        redirect(url('login'));
    } else {
        // Prod: should never happen — AUTH_USER should always be set by IIS
        die('Erreur de configuration : impossible d\'authentifier l\'utilisateur. '
          . 'Vérifiez que Windows Authentication est activée dans IIS Manager '
          . 'et que Anonymous Authentication est désactivée.');
    }
}
