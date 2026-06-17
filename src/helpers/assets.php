<?php
/**
 * Asset Helpers — Application SST DREETS BFC
 *
 * CSS inlining, data URI generation, and asset URL building.
 * Extracted from helpers.php for single-responsibility clarity.
 *
 * All assets go through PHP (not served directly by IIS) — see asset.php
 * for the PHP-based static file server.
 */

/**
 * Build a URL for a static asset (CSS, JS, images, fonts).
 *
 * DEPRECATED for CSS/favicons: these are now inlined via inlineCss() and inlineDataUri().
 * assetUrl() is kept for rare cases (attachment downloads, exports, etc.)
 * that still need a separate HTTP request.
 *
 * @param string $path  Asset path relative to public/ (e.g. 'css/style.css')
 * @return string
 */
function assetUrl(string $path): string {
    $version = getAppVersion();
    return 'asset.php?f=' . urlencode($path) . '&v=' . urlencode($version);
}

/**
 * Inline a CSS file directly into HTML as a <style> tag.
 *
 * Reads the CSS file from public/ and returns a <style> element.
 * Eliminates a separate HTTP request, avoids webhint content-type false positives,
 * and removes all IIS dependency for serving static CSS.
 *
 * Since all HTML pages use Cache-Control: no-cache, the browser revalidates
 * on every page load anyway — a separate cached CSS file provides no benefit.
 *
 * Gzip compression (ob_gzhandler) compresses the inline CSS efficiently.
 *
 * @param string $path  CSS path relative to public/ (e.g. 'css/style.css')
 * @return string  HTML <style> tag with CSS content
 */
function inlineCss(string $path): string {
    $filePath = __DIR__ . '/../../public/' . $path;
    if (!file_exists($filePath)) {
        return '<style>/* CSS not found: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . ' */</style>';
    }
    $css = file_get_contents($filePath);
    return '<style>' . $css . '</style>';
}

/**
 * Generate a data URI for a binary file (favicon, logo, etc.).
 *
 * Reads the file and returns a data: URI string suitable for use in
 * <link rel="icon" href="..."> or <img src="...">.
 *
 * Eliminates separate HTTP requests for small static assets,
 * avoids webhint content-type/cache-control issues entirely.
 *
 * @param string $path  File path relative to public/ (e.g. 'favicon.ico')
 * @return string  data URI (e.g. 'data:image/png;base64,...')
 */
function inlineDataUri(string $path): string {
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
