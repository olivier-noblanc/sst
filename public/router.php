<?php
/**
 * PHP Built-in Server Router — Development Only
 * 
 * This router file prevents the built-in server from crashing
 * by handling static files and routing PHP requests properly.
 *
 * Gzip compression is enabled for both static files and PHP output.
 */

// === Enable Gzip compression for all output ===
if (extension_loaded('zlib')
    && !ini_get('zlib.output_compression')
    && isset($_SERVER['HTTP_ACCEPT_ENCODING'])
    && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false
) {
    ob_start('ob_gzhandler');
} else {
    ob_start();
}

// Serve static files directly
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicPath = __DIR__;

// Check if the request is for a static file that exists
if ($uri !== '/' && $uri !== '/index.php' && file_exists($publicPath . $uri)) {
    // Serve the static file
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css' => 'text/css',
        'js'  => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'json'  => 'application/json',
    ];
    
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);

        // Cache-Control for static assets
        if (in_array($ext, ['css', 'js'])) {
            header('Cache-Control: public, max-age=604800, immutable');
        } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'ico', 'svg'])) {
            header('Cache-Control: public, max-age=2592000');
        } elseif (in_array($ext, ['woff', 'woff2', 'ttf'])) {
            header('Cache-Control: public, max-age=31536000, immutable');
        }

        // Vary: Accept-Encoding for proper caching with gzip
        header('Vary: Accept-Encoding');

        readfile($publicPath . $uri);
        ob_end_flush();
        return true;
    }
}

// Route everything else through index.php
require __DIR__ . '/index.php';
