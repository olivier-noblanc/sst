<?php
/**
 * Login Handler — Application SST DREETS BFC
 * 
 * Processes the mock login form submission.
 * Only used in DEV_MODE.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('login'));
}

$username = trim($_POST['username'] ?? '');

if (empty($username)) {
    setFlash('error', 'Veuillez saisir un nom d\'utilisateur.');
    redirect(url('login'));
}

// Validate CSRF token (prevent login CSRF)
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
    redirect(url('login'));
}

$user = mockLogin($username);

if ($user) {
    // Regenerate session ID to prevent session fixation attacks
    safeSessionRegenerate(); // Protège contre la fixation de session (désactivé en DEV_MODE)

    // Lazy cron: trigger maintenance tasks on login (no system cron)
    require_once __DIR__ . '/../src/cron.php';
    try {
        runLazyCron($pdo);
    } catch (Exception $e) {
        error_log('[SST-CRON] Lazy cron failed on dev login: ' . $e->getMessage());
    }

    // Redirect to intended URL if set, otherwise home
    $intendedUrl = $_SESSION['intended_url'] ?? url('home');
    unset($_SESSION['intended_url']);
    require_once __DIR__ . '/../src/audit.php';
    auditLog($pdo, 'auth', 'login', 'Connexion : ' . $user['prenom'] . ' ' . $user['nom'], (int) $user['id'], 'user', ['username' => $user['username']]);
    setFlash('success', 'Bienvenue, ' . $user['prenom'] . ' ' . $user['nom'] . ' !');
    redirect($intendedUrl);
} else {
    setFlash('error', 'Erreur lors de la connexion. Veuillez réessayer.');
    redirect(url('login'));
}
