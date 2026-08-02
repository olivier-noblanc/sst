<?php

/**
 * JS Server — Application SST DREETS BFC
 *
 * Serves JavaScript files through PHP with proper HTTP caching headers.
 * Same security model as css.php — IIS blocks direct access to js/ directory.
 *
 * Usage:  js.php?f=js/wordcloud.js&v=3.43.0
 *
 * Security:
 *   - Path traversal prevention
 *   - Only .js files served
 *   - Only js/ directory allowed
 *   - No PHP files served
 */

header_remove('X-Powered-By');
header_remove('Server');
header_remove('Expires');
header_remove('Pragma');

$file = $_GET['f'] ?? '';

if ($file === ''
    || str_contains($file, '..')
    || str_starts_with($file, '/')
    || str_contains($file, "\0")
    || str_contains($file, '\\')
) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid JS path.';
    exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if ($ext !== 'js') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Only JS files are served.';
    exit;
}

if (!str_starts_with($file, 'js/')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'JS directory not allowed.';
    exit;
}

$filePath = __DIR__ . '/' . $file;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'JS file not found.';
    exit;
}

$content = file_get_contents($filePath);
if ($content === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to read JS file.';
    exit;
}
$fileSize = filesize($filePath);
$fileMtime = filemtime($filePath);
$etag = '"' . dechex(crc32($content)) . '-' . dechex($fileSize !== false ? $fileSize : 0) . '-' . dechex($fileMtime !== false ? $fileMtime : 0) . '"';

$clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
if ($clientEtag !== null && trim($clientEtag, " \t\n\r\0\x0B\"") === trim($etag, '"')) {
    http_response_code(304);
    header('ETag: ' . $etag);
    exit;
}

$clientLastModified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
if ($clientLastModified !== null && $fileMtime !== false && strtotime($clientLastModified) >= $fileMtime) {
    http_response_code(304);
    header('ETag: ' . $etag);
    exit;
}

header('Content-Type: application/javascript; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $fileMtime !== false ? $fileMtime : 0) . ' GMT');
header('Vary: Accept-Encoding');

echo $content;
