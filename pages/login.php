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

// Headers already cleaned by removeUnwantedHeaders() in index.php bootstrap

// === Cache-Control: no-cache for this dynamic page ===
header('Cache-Control: no-cache');

// === Security Headers (same as header.php — this page is standalone) ===
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");

$pageTitle = 'Connexion';

// Safety: if somehow accessed in prod, redirect away
if (!DEV_MODE) {
    if (isUserLoggedIn()) {
        redirect(url('home'));
    }
    die('Erreur : cette page n\'est pas accessible en production. '
      . 'L\'authentification est gérée par IIS Windows Authentication.');
}

// If already authenticated, redirect to home
if (isUserLoggedIn()) {
    redirect(url('home'));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(APP_NAME); ?> — Connexion</title>
    <?php echo inlineCss('css/style.css'); ?>
    <?php $faviconPng = inlineDataUri('favicon.png'); ?>
    <?php $faviconIco = inlineDataUri('favicon.ico'); ?>
    <?php if ($faviconPng): ?><link rel="icon" type="image/png" sizes="64x64" href="<?php echo $faviconPng; ?>"><?php endif; ?>
    <?php if ($faviconIco): ?><link rel="icon" type="image/x-icon" href="<?php echo $faviconIco; ?>"><?php endif; ?>
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
                    Connexion
                    <?php endif; ?>
                </p>
            </div>

            <?php $flash = getFlash(); ?>
            <?php if ($flash): ?>
                <div class="alert alert--<?php echo e($flash['type']); ?>" role="alert">
                    <?php echo e($flash['message']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo url('login'); ?>" class="login-form" id="quick-login-form">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                <input type="hidden" id="username" name="username" value="">
                <input type="hidden" id="password" name="password" value="test">
            </form>

            <p style="text-align:center;font-size:18px;margin:20px 0 8px 0;color:#555;">Choisissez votre profil</p>
            <p style="text-align:center;font-size:15px;color:#1e40af;margin:0 0 16px 0;font-weight:600;">Cliquez sur votre profil. En cas de doute, choisissez Agent.</p>
            <div class="login-quick-buttons">
                <div class="login-btn-wrapper">
                    <button type="button" class="btn btn--primary" style="background:#1e3a5f;" onclick="document.getElementById('username').value='admin.dev';document.getElementById('quick-login-form').submit();">
                        Superviseur
                    </button>
                    <span class="login-btn-desc">Gestion et suivi des signalements</span>
                </div>
                <div class="login-btn-wrapper">
                    <button type="button" class="btn btn--primary" style="background:#3b82f6;" onclick="document.getElementById('username').value='agent.dev';document.getElementById('quick-login-form').submit();">
                        Agent
                    </button>
                    <span class="login-btn-desc">Signaler un événement</span>
                </div>
                <div class="login-btn-wrapper">
                    <button type="button" class="btn btn--primary" style="background:#6b7280;" onclick="document.getElementById('username').value='chsct.dev';document.getElementById('quick-login-form').submit();">
                        <?php echo e(getRoleLabel('chsct')); ?>
                    </button>
                    <span class="login-btn-desc">Consultation et synthèse</span>
                </div>
            </div>
            <p class="text-small text-muted" style="text-align:center;margin-top:12px;">
                En utilisation normale, la connexion est automatique.
            </p>
        </div>
    </div>
    </main>
<style>
.login-quick-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 12px;
}
.login-btn-wrapper {
    text-align: center;
}
.login-quick-buttons .btn {
    justify-content: center;
    font-size: 18px;
    min-height: 56px;
    padding: 14px 28px;
    width: 100%;
}
.login-btn-desc {
    display: block;
    font-size: 14px;
    color: #6b7280;
    margin-top: 4px;
    font-style: italic;
}
</style>
</body>
</html>
