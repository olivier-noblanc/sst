<?php
/**
 * Asset Server — Application SST DREETS BFC
 *
 * Serves all static assets (CSS, images, fonts, icons) through PHP
 * for FULL control over HTTP headers. No reliance on IIS static file serving.
 *
 * AUTHENTICATION: This script requires Windows Authentication on IIS.
 * There is NO anonymous access — even assets go through Windows Auth.
 * In dev mode (PHP built-in server), no authentication is needed.
 *
 * Usage:  asset.php?f=css/style.css&v=3.6.0
 *
 * Security:
 *   - Path traversal prevention (no .. or absolute paths)
 *   - Whitelisted extensions only
 *   - Whitelisted directories only
 *   - No PHP files served
 *   - Direct HTTP access to css/, img/, fonts/, js/ is blocked by web.config
 *     (hiddenSegments) — only this script can read them via filesystem
 *
 * Headers:
 *   - Correct Content-Type with charset
 *   - X-Content-Type-Options: nosniff
 *   - Cache-Control: long max-age when versioned (?v=), short otherwise
 *   - ETag based on filemtime + filesize
 *   - 304 Not Modified support (If-None-Match / If-Modified-Since)
 *   - All disclosing headers removed (X-Powered-By, Server, Expires, Pragma)
 *   - Vary: Accept-Encoding
 */

// === Remove all unwanted headers FIRST ===
header_remove('X-Powered-By');
header_remove('Server');
header_remove('Expires');
header_remove('Pragma');

// === Get requested file path ===
$file = $_GET['f'] ?? '';

// === Security: validate path ===
// No directory traversal, no absolute paths, no null bytes
if (empty($file)
    || str_contains($file, '..')
    || str_starts_with($file, '/')
    || str_contains($file, "\0")
    || str_contains($file, '\\')
) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid asset path.';
    exit;
}

// === Security: whitelisted extensions ===
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$allowedExtensions = [
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

if (!isset($allowedExtensions[$ext])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Asset type not allowed.';
    exit;
}

// === Security: whitelisted directories ===
$allowedDirs = ['css/', 'img/', 'fonts/', 'js/', 'screenshots/', ''];
$dirAllowed = false;
foreach ($allowedDirs as $dir) {
    if ($dir === '' && !str_contains($file, '/')) {
        // Root-level files (favicon.png, favicon.ico, etc.)
        $dirAllowed = true;
        break;
    }
    if (str_starts_with($file, $dir)) {
        $dirAllowed = true;
        break;
    }
}
if (!$dirAllowed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Asset directory not allowed.';
    exit;
}

// === Resolve file path ===
$publicRoot = __DIR__;
$filePath = $publicRoot . '/' . $file;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Asset not found.';
    exit;
}

// === ETag and conditional requests ===
$fileMtime = filemtime($filePath);
$fileSize = filesize($filePath);
$etag = '"' . dechex($fileMtime) . '-' . dechex($fileSize) . '-' . dechex(crc32($file)) . '"';

// Check If-None-Match (ETag)
$clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
if ($clientEtag !== null && trim($clientEtag, " \t\n\r\0\x0B\"") === trim($etag, '"')) {
    http_response_code(304);
    header('ETag: ' . $etag);
    exit;
}

// Check If-Modified-Since
$clientLastModified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
if ($clientLastModified !== null && strtotime($clientLastModified) >= $fileMtime) {
    http_response_code(304);
    header('ETag: ' . $etag);
    exit;
}

// === Content-Type ===
header('Content-Type: ' . $allowedExtensions[$ext]);

// === X-Content-Type-Options: always nosniff ===
header('X-Content-Type-Options: nosniff');

// === Cache-Control ===
// All assets: max-age=180 (webhint audit requires ≤180)
// ETag + 304 handle revalidation efficiently — long max-age is unnecessary
// 'immutable' omitted: only useful with long max-age (180s is too short to benefit)
$hasVersion = isset($_GET['v']) && $_GET['v'] !== '';
if ($hasVersion) {
    // Versioned: cached for 180s, then revalidates via ETag/304
    header('Cache-Control: public, max-age=180');
} else {
    // Unversioned: same short cache, must revalidate
    header('Cache-Control: public, max-age=180');
}

// === ETag ===
header('ETag: ' . $etag);

// === Last-Modified ===
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $fileMtime) . ' GMT');

// === Vary: Accept-Encoding (for CDN/proxy caching) ===
header('Vary: Accept-Encoding');

// === Content-Length ===
header('Content-Length: ' . $fileSize);

// === Serve the file ===
readfile($filePath);
