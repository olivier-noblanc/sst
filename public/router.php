<?php
/**
 * PHP Built-in Server Router — Development Only
 * 
 * This router file prevents the built-in server from crashing
 * by handling static files and routing PHP requests properly.
 *
 * Handles both /assets/css/style.css?v=... (new format) and
 * asset.php?f=...&v=... (legacy format, backward compatible).
 *
 * Static files: served with correct Content-Type + Cache-Control + security headers.
 * Gzip: enabled for PHP output only (not for already-compressed static files).
 */

// === Remove unwanted headers ===
header_remove('X-Powered-By');
header_remove('Server');
header_remove('Expires');
header_remove('Pragma');

// Serve static files via /assets/... URL pattern (primary)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicPath = __DIR__;

// Handle /assets/... → serve file directly with proper headers
// e.g. /assets/css/style.css?v=3.7.3 → public/css/style.css
if (preg_match('#^/assets/(css|img|fonts|js)/(.+)$#', $uri, $matches)) {
    $filePath = $publicPath . '/' . $matches[1] . '/' . $matches[2];
    serveStaticAsset($filePath, $matches[2]);
    return true;
}
// Handle /assets/filename.ext → root-level (favicon.ico, favicon.png)
if (preg_match('#^/assets/([^/]+)$#', $uri, $matches)) {
    $filePath = $publicPath . '/' . $matches[1];
    serveStaticAsset($filePath, $matches[1]);
    return true;
}

// Legacy: asset.php?f=...&v=... (backward compatible)
if ($uri === '/asset.php') {
    $file = $_GET['f'] ?? '';
    if (!empty($file) && !str_contains($file, '..') && !str_starts_with($file, '/')) {
        $filePath = $publicPath . '/' . $file;
        if (file_exists($filePath) && is_file($filePath)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            serveStaticAsset($filePath, $ext);
            return true;
        }
    }
    http_response_code(404);
    echo 'Asset not found.';
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

/**
 * Serve a static asset with proper Content-Type, Cache-Control, and security headers.
 */
function serveStaticAsset(string $filePath, string $filenameOrExt): void {
    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Asset not found.';
        return;
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // MIME types — same as asset.php for consistency
    $mimeTypes = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml; charset=utf-8',
        'ico'   => 'image/vnd.microsoft.icon; charset=utf-8',
        'webp'  => 'image/webp',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'json'  => 'application/json; charset=utf-8',
        'webmanifest' => 'application/manifest+json; charset=utf-8',
    ];

    if (!isset($mimeTypes[$ext])) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Asset type not allowed.';
        return;
    }

    // Content-Type
    header('Content-Type: ' . $mimeTypes[$ext]);

    // X-Content-Type-Options
    header('X-Content-Type-Options: nosniff');

    // Cache-Control: max-age=180 for all (webhint audit ≤180)
    header('Cache-Control: public, max-age=180');

    // ETag
    $fileMtime = filemtime($filePath);
    $fileSize = filesize($filePath);
    $etag = '"' . dechex($fileMtime) . '-' . dechex($fileSize) . '-' . dechex(crc32($filenameOrExt)) . '"';

    // 304 Not Modified
    $clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
    if ($clientEtag !== null && trim($clientEtag, " \t\n\r\0\x0B\"") === trim($etag, '"')) {
        http_response_code(304);
        header('ETag: ' . $etag);
        return;
    }

    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $fileMtime) . ' GMT');
    header('Vary: Accept-Encoding');
    header('Content-Length: ' . $fileSize);

    readfile($filePath);
}
