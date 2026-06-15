<?php
/**
 * Header Template — Application SST DREETS BFC
 *
 * Blue header bar with logo, app title, user name, and logout link.
 * Security headers and cache-control sent as HTTP headers (not meta tags)
 * for maximum browser support.
 *
 * CSS and favicons are INLINED (no separate HTTP request).
 * This eliminates webhint false positives on content-type/cache-control
 * and removes all IIS dependency for serving static assets.
 * Gzip compression is handled via ob_gzhandler (started in index.php).
 */

// === Remove X-Powered-By (PHP version disclosure) ===
header_remove('X-Powered-By');

// === Remove Server header version info ===
header_remove('Server');

// === Remove Expires and Pragma headers (deprecated, replaced by Cache-Control) ===
header_remove('Expires');
header_remove('Pragma');

// === Cache-Control for dynamic pages ===
// no-cache alone: browser must revalidate with server before using cached copy
// Do NOT combine no-cache with max-age — it's contradictory per RFC 7234
header('Cache-Control: no-cache');

// === Security Headers ===
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// Content-Security-Policy: allow same-origin + data: URIs (inline assets)
// style-src 'unsafe-inline' needed for inline <style> tag (CSS inlined via inlineCss())
// img-src data: needed for inline data: URIs (favicons, logos via inlineDataUri())
// frame-ancestors 'self' allows same-origin iframes (help.php screenshots)
// while blocking cross-origin framing (anti-clickjacking preserved)
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'self';");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(APP_NAME); ?> — <?php echo e($pageTitle ?? 'Accueil'); ?></title>
    <?php echo inlineCss('css/style.css'); ?>
    <?php $faviconPng = inlineDataUri('favicon.png'); ?>
    <?php $faviconIco = inlineDataUri('favicon.ico'); ?>
    <?php if ($faviconPng): ?><link rel="icon" type="image/png" sizes="64x64" href="<?php echo $faviconPng; ?>"><?php endif; ?>
    <?php if ($faviconIco): ?><link rel="icon" type="image/x-icon" href="<?php echo $faviconIco; ?>"><?php endif; ?>
</head>
<body>
    <a href="#main-content" class="skip-link">Aller au contenu principal</a>
    <header class="header" role="banner">
        <div class="header__logo">
            <?php
            $logoDataUri = inlineDataUri('img/logo-dreets.png');
            if ($logoDataUri): ?>
                <img src="<?php echo $logoDataUri; ?>" alt="Logo DREETS BFC" class="header__logo-img" width="40" height="40">
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
