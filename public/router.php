<?php
/**
 * PHP Built-in Server Router — Development Only
 * 
 * This router file prevents the built-in server from crashing
 * by handling static files and routing PHP requests properly.
 *
 * Static files: served with correct Content-Type + Cache-Control + security headers.
 * Gzip: enabled for PHP output only (not for already-compressed static files).
 */

// === Remove unwanted headers ===
header_remove('X-Powered-By');
header_remove('Server');
header_remove('Expires');
header_remove('Pragma');

// Serve static files directly (before any output buffering)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicPath = __DIR__;

if ($uri !== '/' && $uri !== '/index.php' && file_exists($publicPath . $uri)) {
    $ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

    // MIME types with charset for text formats
    $mimeTypes = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/vnd.microsoft.icon',
        'webp'  => 'image/webp',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'json'  => 'application/json; charset=utf-8',
    ];

    if (isset($mimeTypes[$ext])) {
        // Set Content-Type with proper charset
        header('Content-Type: ' . $mimeTypes[$ext]);

        // Cache-Control for static assets with cache busting support
        // If ?v= parameter is present, allow longer cache (asset is versioned)
        $hasVersionParam = isset($_GET['v']);
        if (in_array($ext, ['css', 'js'])) {
            if ($hasVersionParam) {
                header('Cache-Control: public, max-age=604800'); // 7 days when versioned
            } else {
                header('Cache-Control: public, max-age=180'); // 3 min unversioned
            }
        } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'webp'])) {
            if ($hasVersionParam) {
                header('Cache-Control: public, max-age=2592000'); // 30 days when versioned
            } else {
                header('Cache-Control: public, max-age=180'); // 3 min unversioned
            }
        } elseif (in_array($ext, ['woff', 'woff2', 'ttf', 'otf'])) {
            if ($hasVersionParam) {
                header('Cache-Control: public, max-age=31536000'); // 1 year when versioned
            } else {
                header('Cache-Control: public, max-age=180'); // 3 min unversioned
            }
        }

        // Vary: Accept-Encoding for proper caching
        header('Vary: Accept-Encoding');

        // X-Content-Type-Options: always set for all static assets
        header('X-Content-Type-Options: nosniff');

        readfile($publicPath . $uri);
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
