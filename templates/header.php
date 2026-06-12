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

// === Cache-Control: no-cache for all dynamic pages ===
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');

// === Security Headers ===
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// Content-Security-Policy: allow same-origin only (no external resources)
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");
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
                <img src="img/logo-dreets.png" alt="Logo DREETS BFC" class="header__logo-img" width="40" height="40">
            <?php else: ?>
                <span class="header__logo-text"><?php echo e(getConfig('app_nom_organisation', 'DREETS BFC')); ?></span>
            <?php endif; ?>
            <span class="header__title"><?php echo e(APP_NAME); ?></span>
        </div>
        <?php if (isset($_SESSION['user'])): ?>
        <div class="header__user">
            <button type="button" class="header__menu-btn" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="sidebar-nav">&#9776;</button>
            <span class="header__username">
                <?php echo e($_SESSION['user']['prenom'] ?? ''); ?> <?php echo e($_SESSION['user']['nom'] ?? ''); ?>
                <span class="badge <?php echo getRoleBadgeClass($_SESSION['user']['role'] ?? ''); ?> badge--sm"><?php echo e(ROLE_LABELS[$_SESSION['user']['role'] ?? 'agent'] ?? 'Agent'); ?></span>
            </span>
            <a href="<?php echo url('logout'); ?>" class="header__logout" title="Déconnexion">&#8677; Déconnexion</a>
        </div>
        <?php endif; ?>
    </header>
