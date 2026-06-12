<?php
/**
 * Header Template — Application SST DREETS BFC
 *
 * Blue header bar with logo, app title, user name, and logout link.
 * Security headers and cache-control sent as HTTP headers (not meta tags)
 * for maximum browser support.
 *
 * Gzip compression is handled via ob_gzhandler (started in index.php).
 */

// === Remove X-Powered-By (PHP version disclosure) ===
header_remove('X-Powered-By');

// === Remove Server header version info ===
// IIS may add its own Server header; we clear it via PHP
if (function_exists('header_remove')) {
    header_remove('Server');
}

// === Remove Expires and Pragma headers (deprecated, replaced by Cache-Control) ===
header_remove('Expires');
header_remove('Pragma');

// === Cache-Control for dynamic pages ===
// no-cache = browser must revalidate with server before using cached copy
// max-age=0 = stale immediately, must revalidate
// This is the correct approach for dynamic HTML (not no-store which breaks back/forward)
header('Cache-Control: no-cache, max-age=0');

// === Security Headers ===
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// Content-Security-Policy: allow same-origin only (no external resources)
// frame-ancestors 'none' replaces X-Frame-Options (broader support, stronger)
// No X-Frame-Options header — CSP frame-ancestors is the modern replacement
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(APP_NAME); ?> — <?php echo e($pageTitle ?? 'Accueil'); ?></title>
    <link rel="stylesheet" href="<?php echo assetUrl('css/style.css'); ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="favicon.png">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body>
    <a href="#main-content" class="skip-link">Aller au contenu principal</a>
    <header class="header" role="banner">
        <div class="header__logo">
            <?php if (file_exists(__DIR__ . '/../public/img/logo-dreets.png')): ?>
                <img src="<?php echo assetUrl('img/logo-dreets.png'); ?>" alt="Logo DREETS BFC" class="header__logo-img" width="40" height="40">
            <?php else: ?>
                <span class="header__logo-text"><?php echo e(getConfig('app_nom_organisation', 'DREETS BFC')); ?></span>
            <?php endif; ?>
            <span class="header__title"><?php echo e(APP_NAME); ?></span>
        </div>
        <?php if (isset($_SESSION['user'])): ?>
        <div class="header__user">
            <label for="sidebar-toggle" class="header__menu-btn" aria-label="Ouvrir le menu" tabindex="0">&#9776;</label>
            <span class="header__username">
                <?php echo e($_SESSION['user']['prenom'] ?? ''); ?> <?php echo e($_SESSION['user']['nom'] ?? ''); ?>
                <span class="badge <?php echo getRoleBadgeClass($_SESSION['user']['role'] ?? ''); ?> badge--sm"><?php echo e(ROLE_LABELS[$_SESSION['user']['role'] ?? 'agent'] ?? 'Agent'); ?></span>
            </span>
            <a href="<?php echo url('logout'); ?>" class="header__logout" title="Déconnexion">&#8677; Déconnexion</a>
        </div>
        <?php endif; ?>
    </header>
