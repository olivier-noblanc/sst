<?php

/**
 * Login Handler — Application SST DREETS BFC
 *
 * Processes the mock login form submission.
 * Only used in DEV_MODE.
 */

validatePostRequest(url('login'));

$username = trim($_POST['username'] ?? '');

if (empty($username)) {
    setFlash('error', 'Veuillez saisir un nom d\'utilisateur.');
    redirect(url('login'));
}

// Session-based rate limiting: max 5 attempts per 15 minutes
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['login_attempts_start'])) {
    $_SESSION['login_attempts_start'] = time();
}

if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['login_attempts_start']) < 900) {
    $remaining = 900 - (time() - $_SESSION['login_attempts_start']);
    $remainingMin = (int) ceil($remaining / 60);
    setFlash('error', 'Trop de tentatives de connexion. Veuillez réessayer dans ' . $remainingMin . ' minute(s).');
    redirect(url('login'));
}

// Reset counter if 15-minute window has passed
if ((time() - $_SESSION['login_attempts_start']) >= 900) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempts_start'] = time();
}

$user = mockLogin($username);

if ($user) {
    // Reset login attempts on successful login
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempts_start'] = time();
    // Regenerate session ID to prevent session fixation attacks
    safeSessionRegenerate(); // Protège contre la fixation de session (désactivé en DEV_MODE)

    // Lazy cron: trigger maintenance tasks on login (no system cron)
    require_once __DIR__ . '/../src/cron.php';
    try {
        $pdo = getDB();
        runLazyCron($pdo);
    } catch (Exception $e) {
        error_log('[SST-CRON] Lazy cron failed on dev login: ' . $e->getMessage());
    }

    // Redirect to intended URL if set, otherwise home
    $intendedUrl = clearIntendedUrl() ?? url('home');
    $pdo ??= getDB();
    auditLog($pdo, 'auth', 'login', 'Connexion : ' . $user['prenom'] . ' ' . $user['nom'], (int) $user['id'], 'user', ['username' => $user['username']]);
    setFlash('success', 'Bienvenue, ' . $user['prenom'] . ' ' . $user['nom'] . ' !');
    redirect($intendedUrl);
} else {
    $_SESSION['login_attempts']++;
    setFlash('error', 'Erreur lors de la connexion. Veuillez réessayer.');
    redirect(url('login'));
}
