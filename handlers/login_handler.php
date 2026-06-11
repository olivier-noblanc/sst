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
    // session_regenerate_id(false) // Disabled for dev server;
    
    // Redirect to intended URL if set, otherwise home
    $intendedUrl = $_SESSION['intended_url'] ?? url('home');
    unset($_SESSION['intended_url']);
    setFlash('success', 'Bienvenue, ' . $user['prenom'] . ' ' . $user['nom'] . ' !');
    redirect($intendedUrl);
} else {
    setFlash('error', 'Erreur lors de la connexion. Veuillez réessayer.');
    redirect(url('login'));
}
