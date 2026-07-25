<?php

/**
 * Login Handler — Application SST DREETS BFC
 *
 * Processes the mock login form submission.
 * Only used in DEV_MODE.
 * Rate limiting is handled by IIS, not here.
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

/** @var array<string, string> $_POST */

use App\Services\AuthService;
use App\Services\SessionManager;

$http = new \App\Services\HttpService();
$sessionService = \App\Services\SessionService::getInstance();

validatePostRequest($http->url('login'));

$username = trim((string) ($_POST['username'] ?? ''));

if (empty($username)) {
    $sessionService->setFlash('error', 'Veuillez saisir un nom d\'utilisateur.');
    $http->redirect($http->url('login'));
}

$authService = getContainer()->get(AuthService::class);
$session = getContainer()->get(SessionManager::class);

$user = $authService->mockLogin($username);

if ($user !== null) {
    /** @var array<string, string> $user */
    safeSessionRegenerate();

    require_once __DIR__ . '/../src/cron.php';
    runLazyCron(getDB());

    $intendedUrl = (string) ($session->clearIntendedUrl() ?? $http->url('home'));
    auditLog(getDB(), 'auth', 'login', 'Connexion : ' . (string) $user['prenom'] . ' ' . (string) $user['nom'], (int) $user['id'], 'user', ['username' => $user['username']]);
    $sessionService->setFlash('success', 'Bienvenue, ' . (string) $user['prenom'] . ' ' . (string) $user['nom'] . ' !');
    $http->redirect($intendedUrl);
} else {
    $sessionService->setFlash('error', 'Erreur lors de la connexion. Veuillez réessayer.');
    $http->redirect($http->url('login'));
}
