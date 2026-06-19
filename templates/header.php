<?php
/**
 * Header Template — Application SST DREETS BFC
 *
 * Blue header bar with logo, app title, user name, and logout link.
 * Security headers and cache-control sent as HTTP headers (not meta tags)
 * for maximum browser support.
 *
 * CSS is served through css.php (PHP script) for proper HTTP caching:
 * ETag + Last-Modified + 304 Not Modified responses.
 * Favicons are inlined as data: URIs (tiny, no extra HTTP request).
 */

// === Cache-Control for dynamic pages ===
// no-cache alone: browser must revalidate with server before using cached copy
// Do NOT combine no-cache with max-age — it's contradictory per RFC 7234
header('Cache-Control: no-cache');

// === Security Headers ===
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// Content-Security-Policy: CSS served via css.php — no more 'unsafe-inline' for style-src
// script-src 'unsafe-inline' needed for file-upload filename update in report_form.php
// img-src data: needed for inline data: URIs (favicons, logos via inlineDataUri())
// frame-ancestors 'none' : no iframing allowed (screenshots are now <img>, not <iframe>)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(APP_NAME); ?> — <?php echo e($pageTitle ?? 'Accueil'); ?></title>
    <?php echo cssLink('css/style.css'); ?>
    <?php $faviconPng = inlineDataUri('favicon.png'); ?>
    <?php $faviconIco = inlineDataUri('favicon.ico'); ?>
    <?php if ($faviconPng): ?><link rel="icon" type="image/png" sizes="64x64" href="<?php echo $faviconPng; ?>"><?php endif; ?>
    <?php if ($faviconIco): ?><link rel="icon" type="image/x-icon" href="<?php echo $faviconIco; ?>"><?php endif; ?>
</head>
<body>
    <a href="#main-content" class="skip-link">Aller au contenu principal</a>
    <a href="#main-nav" class="skip-link">Aller à la navigation</a>
    <header class="header" role="banner">
        <div class="header__logo">
            <?php
            $logoDataUri = inlineDataUri('img/logo-dreets.png');
            if ($logoDataUri): ?>
                <img src="<?php echo $logoDataUri; ?>" alt="Logo DREETS BFC" class="header__logo-img" width="40" height="40">
            <?php else: ?>
                <span class="header__logo-text"><?php echo e(getConfig('app_nom_organisation', 'DREETS BFC')); ?></span>
            <?php endif; ?>
            <span class="header__title">Application SST</span>
        </div>
        <?php if (isUserLoggedIn()): ?>
        <div class="header__user">
            <label for="sidebar-toggle" class="header__menu-btn" aria-label="Ouvrir le menu" tabindex="0">&#9776;</label>
            <span class="header__username">
                <?php echo e(currentUserDisplayName()); ?>
                <span class="badge <?php echo getRoleBadgeClass(currentUserRole()); ?> badge--sm"><?php echo e(getRoleLabel(currentUserRole())); ?></span>
            </span>
            <?php
            // Impersonation dropdown: only for superviseurs who are NOT already impersonating
            $isImpersonating = isImpersonatingRole();
            $realRole = getRealRole() ?? currentUserRole();
            if ($realRole === ROLE_SUPERVISEUR && !$isImpersonating):
            ?>
            <div class="impersonate-dropdown">
                <input type="checkbox" id="impersonate-toggle" class="impersonate-toggle" aria-hidden="true">
                <label for="impersonate-toggle" class="impersonate-btn" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false" title="Incarner un rôle"><span class="impersonate-icon" aria-hidden="true"></span> Incarner</label>
                <div class="impersonate-menu" role="menu">
                    <form method="POST" action="<?php echo url('impersonate'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                        <input type="hidden" name="action" value="start">
                        <input type="hidden" name="target_role" value="<?php echo ROLE_AGENT; ?>">
                        <button type="submit" class="impersonate-menu__item" role="menuitem">Agent</button>
                    </form>
                    <form method="POST" action="<?php echo url('impersonate'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                        <input type="hidden" name="action" value="start">
                        <input type="hidden" name="target_role" value="<?php echo ROLE_CHSCT; ?>">
                        <button type="submit" class="impersonate-menu__item" role="menuitem"><?php echo e(getRoleLabel(ROLE_CHSCT)); ?></button>
                    </form>
                    <label for="impersonate-toggle" class="impersonate-menu__item impersonate-menu__close" role="menuitem" tabindex="0">&#10005; Fermer</label>
                </div>
            </div>
            <?php endif; ?>
            <a href="<?php echo url('logout'); ?>" class="header__logout" title="Déconnexion">&#8677; Déconnexion</a>
        </div>
        <?php endif; ?>
    </header>
    <?php require __DIR__ . '/impersonate_banner.php'; ?>
