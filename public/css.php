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
$content = file_get_contents($filePath);
$etag = '"' . dechex(crc32($content)) . '-' . dechex(filesize($filePath)) . '-' . dechex(filemtime($filePath)) . '"';

// === Check conditional requests ===

// If-None-Match (ETag match)
$clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
if ($clientEtag !== null && trim($clientEtag, " \t\n\r\0\x0B\"") === trim($etag, '"')) {
    http_response_code(304);
    header('ETag: ' . $etag);
    exit;
}

// If-Modified-Since
$fileMtime = filemtime($filePath);
$clientLastModified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
if ($clientLastModified !== null && strtotime($clientLastModified) >= $fileMtime) {
    http_response_code(304);
    header('ETag: ' . $etag);
    exit;
}

// === Headers ===
header('Content-Type: text/css; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Cache-Control: long max-age with version busting
// When ?v= is present (always, via cssLink()), the URL is immutable
// until the version changes → safe to cache for 1 year
$hasVersion = isset($_GET['v']) && $_GET['v'] !== '';
if ($hasVersion) {
    header('Cache-Control: public, max-age=31536000, immutable');
} else {
    // Fallback: short cache for unversioned requests (shouldn't happen)
    header('Cache-Control: public, max-age=180');
}

header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $fileMtime) . ' GMT');
header('Vary: Accept-Encoding');

// === Serve CSS content ===
echo $content;
