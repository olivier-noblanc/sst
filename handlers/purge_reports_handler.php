<?php

/**
 * Purge Reports Handler — Application SST DREETS BFC
 *
 * POST handler : purge applicative FK-safe des signalements et de leurs
 * données liées, plus l'outbox e-mail et les sessions, déclenchée par le
 * superviseur depuis l'onglet « Maintenance » des paramètres.
 *
 * Garde-fous vérifiés au dernier moment :
 *   1. rôle Superviseur + CSRF (RoleMiddleware + CsrfMiddleware, routes.php) ;
 *   2. confirmation explicite (confirm_purge) ;
 *   3. présence du fichier sentinelle erase.txt à la racine (PurgeService).
 *
 * Le fichier erase.txt n'est supprimé qu'après un succès complet ; en cas
 * d'absence ou d'échec, la purge est refusée / échoue et le fichier est
 * conservé. Users, sites, config et registries ne sont jamais touchés.
 *
 * Pas de try/catch : un échec DB remonte (crash hard, jamais silencieux —
 * AGENTS.md). La sentinelle étant supprimée en dernier, elle est préservée
 * si quoi que ce soit échoue en cours de route.
 */

use App\Services\HttpService;
use App\Services\PurgeService;
use App\Services\SessionService;

$http = new HttpService();
$session = SessionService::getInstance();
$settingsUrl = $http->url('settings', ['tab' => 'maintenance']);

if (($_POST['confirm_purge'] ?? '') !== '1') {
    $session->setFlash('error', 'Confirmation requise : cochez la case de confirmation pour lancer la purge.');
    $http->redirect($settingsUrl);
}

$purge = getContainer()->get(PurgeService::class);

if (!$purge->isArmed()) {
    $session->setFlash(
        'error',
        'Purge refusée : le fichier erase.txt est absent à la racine de l\'application. Demandez à un technicien de l\'armer.'
    );
    $http->redirect($settingsUrl);
}

$counts = $purge->purgeAll();

$session->setFlash(
    'success',
    sprintf(
        'Purge terminée : %d signalement(s) et leurs données liées supprimés, %d message(s) d\'outbox et %d session(s) purgés. Le fichier erase.txt a été supprimé.',
        $counts['reports'] ?? 0,
        $counts['email_outbox'] ?? 0,
        $counts['sessions'] ?? 0,
    )
);
$http->redirect($settingsUrl);
