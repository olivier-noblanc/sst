<?php
/**
 * Login Page — Application SST DREETS BFC
 * 
 * MOCK LOGIN FORM — DEV MODE ONLY
 * 
 * In production, IIS handles Windows Authentication before PHP runs.
 * This page is unreachable in prod (index.php redirects away).
 * This form exists ONLY for local development testing.
 */

// === Remove X-Powered-By (PHP version disclosure) ===
header_remove('X-Powered-By');

// === Remove unwanted headers (Server version, deprecated Expires/Pragma) ===
header_remove('Server');
header_remove('Expires');
header_remove('Pragma');

// === Cache-Control: no-cache for this dynamic page ===
header('Cache-Control: no-cache');

// === Security Headers (same as header.php — this page is standalone) ===
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");

$pageTitle = 'Connexion';

// Safety: if somehow accessed in prod, redirect away
if (!DEV_MODE) {
    if (isset($_SESSION['user'])) {
        redirect(url('home'));
    }
    die('Erreur : cette page n\'est pas accessible en production. '
      . 'L\'authentification est gérée par IIS Windows Authentication.');
}

// If already authenticated, redirect to home
if (isset($_SESSION['user'])) {
    redirect(url('home'));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(APP_NAME); ?> — Connexion</title>
    <link rel="stylesheet" href="<?php echo assetUrl('css/style.css'); ?>">
</head>
<body class="login-body">
    <a href="#login-form" class="skip-link">Aller au formulaire de connexion</a>
    <main id="main-content" role="main">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><?php echo e(APP_NAME); ?></h1>
                <p class="login-subtitle"><?php echo e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')); ?></p>
                <p class="login-dev-badge">
                    <?php if (!empty($_SERVER['AUTH_USER'])): ?>
                    🔒 Authentification Windows IIS
                    <?php else: ?>
                    ⚙ Mode sans IIS — authentification par identifiant
                    <?php endif; ?>
                </p>
            </div>

            <?php $flash = getFlash(); ?>
            <?php if ($flash): ?>
                <div class="alert alert--<?php echo e($flash['type']); ?>" role="alert">
                    <?php echo e($flash['message']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo url('login'); ?>" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                <div class="form-group" id="login-form">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required
                           placeholder="Ex: admin.dev ou agent.dev"
                           autocomplete="username"
                           autofocus
                           value="<?php echo e($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password"
                           autocomplete="current-password"
                           placeholder="(non vérifié en mode dev)"
                           aria-describedby="hint_password">
                    <span class="form-hint" id="hint_password">Le mot de passe n'est pas vérifié en mode développement.</span>
                </div>

                <button type="submit" class="btn btn--primary btn--full">Se connecter</button>
            </form>

            <div class="login-dev-info">
                <p><strong>Comptes de test :</strong></p>
                <ul>
                    <li><code>admin.dev</code> — Superviseur (UR21 Côte-d'Or)</li>
                    <li><code>agent.dev</code> — Agent (choix du site au login)</li>
                    <li><code>chsct.dev</code> — Membre CHSCT (UR25 Doubs)</li>
                </ul>
                <p class="text-small text-muted mt-2">
                    Tout autre nom d'utilisateur créera un compte agent automatiquement.
                </p>
                <p class="text-small text-muted mt-2">
                    💡 Pour devenir superviseur, ajoutez votre identifiant dans <em>Paramètres → Application → Logins Windows des superviseurs</em>.
                </p>
                <p class="login-disclaimer">
                    ⚠ Sur un serveur IIS en production, cette page n'existe pas. L'authentification est automatique via Windows Authentication.
                </p>
            </div>
        </div>
    </div>
    </main>
</body>
</html>
