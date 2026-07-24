#!/usr/bin/env php
<?php
/**
 * Inline Style Checker
 *
 * Detects style= attributes in PHP files (templates, pages, handlers).
 * The CSP interdit style-src 'unsafe-inline' — tous les styles doivent
 * aller dans public/css/style.css avec des classes CSS.
 *
 * Exit code 1 if inline styles found (for CI/pre-commit integration).
 *
 * Usage: php tools/check_inline_styles.php [--json]
 */

$projectDir = dirname(__DIR__);
$outputJson = in_array('--json', $argv);

$dirs = ['src', 'pages', 'handlers', 'templates', 'public'];
$excluded = ['vendor', 'lib', 'tests', 'seed', 'tools', 'src/mail', 'src/lib', 'src/mail_notifications.php', 'src/mail_templates.php', 'src/error_notify.php'];

$errors = [];

foreach ($dirs as $dir) {
    $path = $projectDir . '/' . $dir;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = str_replace(['\\', '/'], '/', $file->getPathname());
        $relativePath = str_replace(str_replace(['\\', '/'], '/', $projectDir) . '/', '', $relativePath);
        $skip = false;
        foreach ($excluded as $exc) {
            if (str_starts_with($relativePath, $exc) && (str_ends_with($relativePath, $exc) || str_starts_with(substr($relativePath, strlen($exc)), '/'))) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            continue;
        }

        // Check for style= attribute
        if (preg_match_all('/style\s*=\s*["\'][^"\']*["\']/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                $errors[] = [
                    'file' => $relativePath,
                    'line' => $line,
                    'message' => 'Style inline détecté — utiliser des classes CSS (CSP interdit style-src unsafe-inline)',
                ];
            }
        }
    }
}

if ($outputJson) {
    echo json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    foreach ($errors as $error) {
        echo $error['file'] . ':' . $error['line'] . ' - ' . $error['message'] . PHP_EOL;
    }
}

exit(empty($errors) ? 0 : 1);
