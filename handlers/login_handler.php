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

// Debug logging for E2E troubleshooting - BEFORE validatePostRequest
if (defined('DEV_MODE') && DEV_MODE) {
    error_log('[SST-LOGIN] === POST START ===');
    error_log('[SST-LOGIN] $_COOKIE: ' . json_encode($_COOKIE));
    error_log('[SST-LOGIN] $_POST: ' . json_encode($_POST));
    error_log('[SST-LOGIN] session_status=' . session_status() . ', session_id=' . (session_id() ?: 'none'));
    error_log('[SST-LOGIN] $_SESSION keys: ' . json_encode(array_keys($_SESSION ?? [])));
    $postCsrf = $_POST['csrf_token'] ?? 'none';
    error_log('[SST-LOGIN] CSRF from POST: ' . substr($postCsrf, 0, 16) . '...');
    $csrfInSession = $_SESSION['csrf_tokens'] ?? [];
    error_log('[SST-LOGIN] CSRF in session: ' . count($csrfInSession) . ' tokens, keys=' . json_encode(array_keys($csrfInSession)));
}

$http = new HttpService();
$sessionService = SessionService::getInstance();

if (defined('DEV_MODE') && DEV_MODE) {
    error_log('[SST-LOGIN] Calling validatePostRequest...');
}
validatePostRequest($http->url('login'));
if (defined('DEV_MODE') && DEV_MODE) {
    error_log('[SST-LOGIN] validatePostRequest PASSED');
    error_log('[SST-LOGIN] $_SESSION after validate: ' . json_encode(array_keys($_SESSION ?? [])));
}

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
