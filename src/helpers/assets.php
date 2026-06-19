<?php

/**
 * Asset Helpers — Application SST DREETS BFC
 *
 * CSS serving via PHP (css.php) and asset URL building.
 * Extracted from helpers.php for single-responsibility clarity.
 *
 * CSS is served through css.php (not IIS static serving) for full control
 * over HTTP caching headers: ETag, Last-Modified, 304 Not Modified,
 * long Cache-Control with version-based cache busting.
 */

/**
 * Generate a <link> tag for a CSS file served through css.php.
 *
 * Instead of inlining CSS as a <style> tag (which bloats every HTML page
 * and prevents browser caching), this outputs a <link rel="stylesheet">
 * pointing to css.php — a PHP script that serves CSS with proper
 * HTTP caching headers (ETag, Last-Modified, 304 Not Modified).
 *
 * The ?v= parameter ensures cache busting when the app is updated:
 * a new version = new URL = browser fetches fresh CSS.
 * Between versions, the browser reuses its cached copy (304 response).
 *
 * @param string $path  CSS path relative to public/ (e.g. 'css/style.css')
 * @return string  HTML <link> tag
 */
function cssLink(string $path): string
{
    $version = getAppVersion();
    $href = 'css.php?f=' . urlencode($path) . '&v=' . urlencode($version);
    return '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Build a URL for a static asset (images, fonts, attachments, exports).
 *
 * Used for non-CSS assets that go through asset.php.
 * CSS should use cssLink() instead (better caching strategy).
 *
 * @param string $path  Asset path relative to public/ (e.g. 'img/logo.png')
 * @return string
 */
function assetUrl(string $path): string
{
    $version = getAppVersion();
    return 'asset.php?f=' . urlencode($path) . '&v=' . urlencode($version);
}

/**
 * Generate a data URI for a binary file (favicon, logo, etc.).
 *
 * Reads the file and returns a data: URI string suitable for use in
 * <link rel="icon" href="..."> or <img src="...">.
 *
 * Eliminates separate HTTP requests for small static assets,
 * avoids IIS cache-control issues for favicons.
 *
 * @param string $path  File path relative to public/ (e.g. 'favicon.ico')
 * @return string  data URI (e.g. 'data:image/png;base64,...')
 */
function inlineDataUri(string $path): string
{
    $filePath = __DIR__ . '/../../public/' . $path;
    if (!file_exists($filePath)) {
        return '';
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mimeTypes = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'webp' => 'image/webp',
    ];

    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
    $data = base64_encode(file_get_contents($filePath));

    return 'data:' . $mime . ';base64,' . $data;
}
