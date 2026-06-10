<?php
/**
 * PHP Built-in Server Router — Development Only
 * 
 * This router file prevents the built-in server from crashing
 * by handling static files and routing PHP requests properly.
 */

// Serve static files directly
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicPath = __DIR__;

// Check if the request is for a static file that exists
if ($uri !== '/' && $uri !== '/index.php' && file_exists($publicPath . $uri)) {
    // Serve the static file
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
    ];
    
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
        readfile($publicPath . $uri);
        return true;
    }
}

// Route everything else through index.php
require __DIR__ . '/index.php';
