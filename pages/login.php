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
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");

$pageTitle = 'Connexion';

// Safety: if somehow accessed in prod, redirect away
if (!DEV_MODE) {
    if (new \App\Services\SessionService()->isUserLoggedIn()) {
        new \App\Services\HttpService()->redirect(new \App\Services\HttpService()->url('home'));
    }
    die('Erreur : cette page n\'est pas accessible en production. '
      . 'L\'authentification est gérée par IIS Windows Authentication.');
}

// If already authenticated, redirect to home
if (new \App\Services\SessionService()->isUserLoggedIn()) {
    new \App\Services\HttpService()->redirect(new \App\Services\HttpService()->url('home'));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo new \App\Services\FormattingService()->e(APP_NAME); ?> — Connexion</title>
    <?php echo new \App\Services\AssetService()->cssLink('css/style.css'); ?>
    <?php $faviconPng = new \App\Services\AssetService()->inlineDataUri('favicon.png'); ?>
    <?php $faviconIco = new \App\Services\AssetService()->inlineDataUri('favicon.ico'); ?>
    <?php if ($faviconPng !== ''): ?><link rel="icon" type="image/png" sizes="64x64" href="<?php echo $faviconPng; ?>"><?php endif; ?>
    <?php if ($faviconIco !== ''): ?><link rel="icon" type="image/x-icon" href="<?php echo $faviconIco; ?>"><?php endif; ?>
</head>
<body class="login-body">
    <a href="#login-form" class="skip-link">Aller au formulaire de connexion</a>
    <main id="main-content" role="main">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><?php echo new \App\Services\FormattingService()->e(APP_NAME); ?></h1>
                <p class="login-subtitle"><?php echo new \App\Services\FormattingService()->e(getConfigService()->get('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')); ?></p>
                <p class="login-dev-badge">
                    <?php if (!empty($_SERVER['AUTH_USER'])): ?>
                    Authentification Windows IIS
                    <?php else: ?>
                    Connexion
                    <?php endif; ?>
                </p>
            </div>

            <?php $flash = new \App\Services\SessionService()->getFlash(); ?>
            <?php if ($flash !== null): ?>
                <div class="alert alert--<?php echo new \App\Services\FormattingService()->e($flash->type); ?>" role="alert">
                    <?php echo new \App\Services\FormattingService()->e($flash->message); ?>
                </div>
            <?php endif; ?>

            <p class="login-choose-profile">Choisissez votre profil</p>
            <p class="login-choose-hint">Cliquez sur votre profil. En cas de doute, choisissez Agent.</p>
            <div class="login-quick-buttons">
                <div class="login-btn-wrapper">
                    <form method="POST" action="<?php echo new \App\Services\HttpService()->url('login'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo new \App\Services\FormattingService()->e(new \App\Services\SessionService()->generateCsrfToken()); ?>">
                        <input type="hidden" name="username" value="admin.dev">
                        <input type="hidden" name="password" value="test">
                        <button type="submit" class="btn btn--primary login-btn--superviseur">Superviseur</button>
                    </form>
                    <span class="login-btn-desc">Gestion et suivi des signalements</span>
                </div>
                <div class="login-btn-wrapper">
                    <form method="POST" action="<?php echo new \App\Services\HttpService()->url('login'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo new \App\Services\FormattingService()->e(new \App\Services\SessionService()->generateCsrfToken()); ?>">
                        <input type="hidden" name="username" value="agent.dev">
                        <input type="hidden" name="password" value="test">
                        <button type="submit" class="btn btn--primary login-btn--agent">Agent</button>
                    </form>
                    <span class="login-btn-desc">Signaler un événement</span>
                </div>
                <div class="login-btn-wrapper">
                    <form method="POST" action="<?php echo new \App\Services\HttpService()->url('login'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo new \App\Services\FormattingService()->e(new \App\Services\SessionService()->generateCsrfToken()); ?>">
                        <input type="hidden" name="username" value="chsct.dev">
                        <input type="hidden" name="password" value="test">
                        <button type="submit" class="btn btn--primary login-btn--chsct"><?php echo new \App\Services\FormattingService()->e(getConfigService()->getRoleLabel('chsct')); ?></button>
                    </form>
                    <span class="login-btn-desc">Consultation et synthèse</span>
                </div>
            </div>
            <p class="text-small text-muted login-dev-notice">
                En utilisation normale, la connexion est automatique.
            </p>
        </div>
    </div>
    </main>
<?php echo new \App\Services\AssetService()->cssLink('css/login.css'); ?>
</body>
</html>
