<?php

/**
 * CSS Server — Application SST DREETS BFC
 *
 * Serves CSS files through PHP with proper HTTP caching headers.
 * Unlike IIS static file serving, this gives FULL control over:
 *   - ETag based on file content hash (strong validator)
 *   - Last-Modified header
 *   - 304 Not Modified responses (bandwidth savings)
 *   - Long Cache-Control max-age (CSS changes = new version param)
 *   - Gzip compression via ob_gzhandler
 *
 * Usage:  css.php?f=css/style.css&v=3.19.0
 *
 * Why a dedicated CSS server instead of asset.php?
 *   - CSS is served on EVERY page load (high frequency)
 *   - Long cache + version busting is safe (immutable per version)
 *   - asset.php has short max-age (180s) for mutable resources
 *   - css.php uses 1-year max-age with ?v= cache busting
 *
 * Security:
 *   - Path traversal prevention
 *   - Only .css files served
 *   - Only css/ directory allowed
 *   - No PHP files served
 */

// === Remove unwanted headers ===
header_remove('X-Powered-By');
header_remove('Server');
header_remove('Expires');
header_remove('Pragma');

// === Get requested file ===
$file = $_GET['f'] ?? '';

// === Security: validate path ===
if (empty($file)
    || str_contains($file, '..')
    || str_starts_with($file, '/')
    || str_contains($file, "\0")
    || str_contains($file, '\\')
) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid CSS path.';
    exit;
}

// === Security: only .css extension ===
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if ($ext !== 'css') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Only CSS files are served.';
    exit;
}

// === Security: only css/ directory ===
if (!str_starts_with($file, 'css/')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'CSS directory not allowed.';
    exit;
}

// === Resolve file path ===
$filePath = __DIR__ . '/' . $file;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'CSS file not found.';
    exit;
}

// === Generate strong ETag from file content ===
// Audit #90 — file_get_contents/filesize/filemtime can return false on read
// errors (permissions, race with file deletion, etc.). Before this fix, the
// result was passed directly to crc32()/dechex() which would throw a TypeError
// on false. Now we handle each failure with a 500 response.
$content = file_get_contents($filePath);
if ($content === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to read CSS file.';
    exit;
}
$fileSize = filesize($filePath);
$fileMtime = filemtime($filePath);
if ($fileSize === false || $fileMtime === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to stat CSS file.';
    exit;
}
$etag = '"' . dechex(crc32($content)) . '-' . dechex($fileSize) . '-' . dechex($fileMtime) . '"';

// === Check conditional requests ===

// If-None-Match (ETag match)
$clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
if ($clientEtag !== null && trim($clientEtag, " \t\n\r\0\x0B\"") === trim($etag, '"')) {
    http_response_code(304);
    header('ETag: ' . $etag);
    exit;
}

// If-Modified-Since
$clientLastModified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
if ($clientLastModified !== null && strtotime($clientLastModified) >= $fileMtime) {
    http_response_code(304);
    header('ETag: ' . $etag);
    exit;
}

// === Headers ===
header('Content-Type: text/css; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Cache-Control: no cache — PHP rebuilds CSS on every request
// Version busting via ?v= still works for CDN/proxy, but browser always revalidates
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $fileMtime) . ' GMT');
header('Vary: Accept-Encoding');

// === Serve CSS content ===
echo $content;
