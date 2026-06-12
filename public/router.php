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

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicPath = __DIR__;

// Legacy: asset.php?f=...&v=... (kept for attachment downloads, exports)
if ($uri === '/asset.php') {
    require $publicPath . '/asset.php';
    return true;
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
