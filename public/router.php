<?php

/**
 * PHP Built-in Server Router — Development Only
 *
 * This router file prevents the built-in server from crashing
 * by handling static files and routing PHP requests properly.
 *
 * CSS and favicons are inlined in HTML — no separate requests needed.
 * asset.php is kept for attachment downloads and exports only.
 *
 * Gzip: enabled for PHP output only (not for already-compressed static files).
 */

// === Remove unwanted headers ===
header_remove('X-Powered-By');
header_remove('Server');
header_remove('Expires');
header_remove('Pragma');

// Audit: parse_url can return false on malformed URLs. Fall back to '/' so
// the rest of the router treats it as the homepage instead of crashing on
// str_starts_with($uri, ...) with $uri === false (TypeError on PHP 8.x).
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!is_string($uri) || $uri === '') {
    $uri = '/';
}
$publicPath = __DIR__;

// Legacy: asset.php?f=...&v=... (kept for attachment downloads, exports)
if ($uri === '/asset.php') {
    require $publicPath . '/asset.php';
    return true;
}

// CSS server: css.php?f=css/style.css&v=... (proper Content-Type + caching)
if ($uri === '/css.php') {
    require $publicPath . '/css.php';
    return true;
}

// Static files: js/, css/, img/ — serve directly with correct Content-Type
$staticDir = $publicPath . $uri;
if (str_starts_with($uri, '/js/') || str_starts_with($uri, '/css/') || str_starts_with($uri, '/img/')) {
    if (is_file($staticDir)) {
        $ext = strtolower(pathinfo($staticDir, PATHINFO_EXTENSION));
        $mimeMap = [
            'js'  => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
        ];
        $mime = $mimeMap[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache');
        readfile($staticDir);
        return true;
    }
}

// === Enable Gzip compression for PHP output only ===
if (extension_loaded('zlib')
    && !ini_get('zlib.output_compression')
    && isset($_SERVER['HTTP_ACCEPT_ENCODING'])
    && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false
) {
    ob_start('ob_gzhandler');
} else {
    ob_start();
}

// Route everything else through index.php
require __DIR__ . '/index.php';
