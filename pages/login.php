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

// === Cache-Control: no-cache for this dynamic page ===
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');

// === Security Headers (same as header.php — this page is standalone) ===
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");

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
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required
                           placeholder="Ex: admin.dev ou agent.dev"
                           autofocus
                           value="<?php echo e($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password"
                           placeholder="(non vérifié en mode dev)">
                    <span class="form-hint">Le mot de passe n'est pas vérifié en mode développement.</span>
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
                <p style="font-size:12px;color:var(--grey-500);margin-top:8px;">
                    Tout autre nom d'utilisateur créera un compte agent automatiquement.
                </p>
                <p style="font-size:12px;color:var(--grey-500);margin-top:8px;">
                    💡 Pour devenir superviseur, ajoutez votre identifiant dans <em>Paramètres → Application → Logins Windows des superviseurs</em>.
                </p>
                <p style="font-size:11px;color:var(--grey-400);margin-top:12px;border-top:1px solid var(--grey-200);padding-top:8px;">
                    ⚠ Sur un serveur IIS en production, cette page n'existe pas. L'authentification est automatique via Windows Authentication.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
