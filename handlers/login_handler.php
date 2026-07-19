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

validatePostRequest(url('login'));

$username = trim((string) ($_POST['username'] ?? ''));

if (empty($username)) {
    setFlash('error', 'Veuillez saisir un nom d\'utilisateur.');
    redirect(url('login'));
}

/** @var AuthService $authService */
$authService = getContainer()->get(AuthService::class);
/** @var SessionManager $session */
$session = getContainer()->get(SessionManager::class);

$user = $authService->mockLogin($username);

if ($user !== null) {
    /** @var array<string, string> $user */
    safeSessionRegenerate();

    require_once __DIR__ . '/../src/cron.php';
    try {
        runLazyCron(getDB());
    } catch (Exception $e) {
        error_log('[SST-CRON] Lazy cron failed on dev login: ' . $e->getMessage());
    }

    $intendedUrl = (string) ($session->clearIntendedUrl() ?? url('home'));
    auditLog(getDB(), 'auth', 'login', 'Connexion : ' . (string) $user['prenom'] . ' ' . (string) $user['nom'], (int) $user['id'], 'user', ['username' => $user['username']]);
    setFlash('success', 'Bienvenue, ' . (string) $user['prenom'] . ' ' . (string) $user['nom'] . ' !');
    redirect($intendedUrl);
} else {
    setFlash('error', 'Erreur lors de la connexion. Veuillez réessayer.');
    redirect(url('login'));
}
