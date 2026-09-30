<?php
/**
 * Header Template — Application SST DREETS BFC
 *
 * Document head + shell opener. The shell no longer renders an autonomous top
 * bar: navigation, user identity, impersonation control and logout all live in
 * the sidebar (templates/sidebar.php), and the main content starts at the very
 * top of the viewport. This template still owns the HTTP security headers, the
 * CSP, the document <head>, the <body> opening, the accessibility skip links
 * and the global banners (outbox / impersonation).
 *
 * Security headers and cache-control sent as HTTP headers (not meta tags)
 * for maximum browser support.
 *
 * CSS is served through css.php (PHP script) for proper HTTP caching:
 * ETag + Last-Modified + 304 Not Modified responses.
 * Favicons are inlined as data: URIs (tiny, no extra HTTP request).
 */
/** @var string $csrfToken */

// === Cache-Control for dynamic pages ===
// no-cache alone: browser must revalidate with server before using cached copy
// Do NOT combine no-cache with max-age — it's contradictory per RFC 7234
header('Cache-Control: no-cache');

// === Security Headers ===
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// Content-Security-Policy:
// - CSS served via css.php + some inline styles still required (tab_registres
//   cards have inline styles for dynamic colors) → style-src 'self' 'unsafe-inline'
// - script-src 'unsafe-inline' still needed by pages that keep inline handlers
//   (logs.php confirm, settings tabs) → kept until they migrate to external JS.
//   The file-upload field no longer uses inline script (external
//   public/js/attachment-input.js served by js.php).
// - img-src data: needed for inline data: URIs (favicons, logos via inlineDataUri())
// - frame-ancestors 'none' : no iframing allowed (screenshots are now <img>, not <iframe>)
// Audit #79 — comment was misleading (said "no more unsafe-inline" but the header
// still had it). Now accurately reflects the current state.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");
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
    <?php if ($faviconPng !== ''): ?><link rel="icon" type="image/png" sizes="64x64" href="<?php echo $faviconPng; ?>"><?php endif; ?>
    <?php if ($faviconIco !== ''): ?><link rel="icon" type="image/x-icon" href="<?php echo $faviconIco; ?>"><?php endif; ?>
</head>
<body>
    <a href="#main-content" class="skip-link">Aller au contenu principal</a>
    <a href="#main-nav" class="skip-link">Aller à la navigation</a>
    <?php require __DIR__ . '/outbox_banner.php'; ?>
    <?php require __DIR__ . '/impersonate_banner.php'; ?>
