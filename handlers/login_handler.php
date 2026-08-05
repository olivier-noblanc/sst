<?php

use App\Services\HttpService;
use App\Services\SessionService;

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
use App\Services\CronService;

// Debug logging for E2E troubleshooting
if (defined('DEV_MODE') && DEV_MODE) {
    $sessionId = session_id() ?: 'no-session-id';
    $postUsername = $_POST['username'] ?? 'none';
    $postCsrf = $_POST['csrf_token'] ?? 'none';
    $csrfTokens = $_SESSION['csrf_tokens'] ?? [];
    error_log("[SST-LOGIN] POST received - session_id=$sessionId, username=$postUsername, csrf_provided=" . substr($postCsrf, 0, 8) . "..., csrf_in_session=" . count($csrfTokens));
}

$http = new HttpService();
$sessionService = SessionService::getInstance();

validatePostRequest($http->url('login'));

$username = trim((string) ($_POST['username'] ?? ''));

if (empty($username)) {
    $sessionService->setFlash('error', 'Veuillez saisir un nom d\'utilisateur.');
    $http->redirect($http->url('login'));
}

$authService = getContainer()->get(AuthService::class);
$session = getContainer()->get(SessionManager::class);
$cronService = getContainer()->get(CronService::class);

$user = $authService->mockLogin($username);

if ($user !== null) {
    safeSessionRegenerate();

    $cronService->runLazyCron();

    $intendedUrl = (string) ($session->clearIntendedUrl() ?? $http->url('home'));
    auditLog(getDB(), 'auth', 'login', 'Connexion : ' . $user->prenom . ' ' . $user->nom, $user->id, 'user', ['username' => $user->username]);
    $sessionService->setFlash('success', 'Bienvenue, ' . $user->prenom . ' ' . $user->nom . ' !');
    $http->redirect($intendedUrl);
} else {
    $sessionService->setFlash('error', 'Erreur lors de la connexion. Veuillez réessayer.');
    $http->redirect($http->url('login'));
}
