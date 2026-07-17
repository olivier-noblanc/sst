#!/usr/bin/env php
<?php
/**
 * CSS Class Alignment Checker
 *
 * Detects CSS classes used in HTML templates but absent from stylesheets,
 * and CSS classes defined in stylesheets but never used in HTML.
 *
 * Exit code 1 if any issues found (for CI integration).
 *
 * Usage: php tools/check_css_classes.php [--json] [--unused] [--missing]
 */

$projectDir = dirname(__DIR__);
$outputJson = in_array('--json', $argv);
$filterUnused = in_array('--unused', $argv);
$filterMissing = in_array('--missing', $argv);
$showAll = !$filterUnused && !$filterMissing;

// ═══════════════════════════════════════════════════════════════
// 1. Extract CSS class selectors from stylesheets
// ═══════════════════════════════════════════════════════════════

$cssFiles = [
    $projectDir . '/public/css/style.css',
    $projectDir . '/public/css/login.css',
    $projectDir . '/public/css/guide.css',
];

$cssClasses = [];

foreach ($cssFiles as $cssFile) {
    if (!file_exists($cssFile)) {
        continue;
    }
    $css = file_get_contents($cssFile);
    // Remove comments
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    // Remove @import, @keyframes, @media content is kept for context
    // Match class selectors: .classname (but not .123start or pseudo-classes)
    if (preg_match_all('/\.([a-zA-Z][\w-]*)/', $css, $matches)) {
        foreach ($matches[1] as $class) {
            $cssClasses[$class] = true;
        }
    }
}

$cssClasses = array_keys($cssClasses);
sort($cssClasses);

// ═══════════════════════════════════════════════════════════════
// 2. Extract CSS classes from HTML/PHP templates
// ═══════════════════════════════════════════════════════════════

$htmlDirs = [
    $projectDir . '/templates',
    $projectDir . '/pages',
];

$htmlClasses = [];
$filesScanned = 0;

function scanDirForClasses(string $dir, array &$htmlClasses, int &$filesScanned): void {
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $filesScanned++;
        $content = file_get_contents($file->getPathname());

        // Remove PHP comments
        $content = preg_replace('#/\*.*?\*/#s', '', $content);
        $content = preg_replace('#//.*$#m', '', $content);

        // Extract class="..." attributes
        if (preg_match_all('/class\s*=\s*["\']([^"\']*)["\']/', $content, $matches)) {
            foreach ($matches[1] as $classString) {
                // Split by whitespace and extract individual classes
                $classes = preg_split('/\s+/', trim($classString));
                foreach ($classes as $class) {
                    $class = trim($class);
                    if ($class !== '' && preg_match('/^[a-zA-Z][\w-]*$/', $class)) {
                        $htmlClasses[$class] = true;
                    }
                }
            }
        }

        // Extract dynamic class patterns like 'class-' . $variable
        // and ternary patterns like ($active ? 'class--active' : 'class')
        if (preg_match_all('/\'([a-zA-Z][\w-]*)\'/', $content, $matches)) {
            foreach ($matches[1] as $class) {
                // Only count classes that look like CSS classes (contain --, __, or end with common suffixes)
                if (str_contains($class, '--') || str_contains($class, '__') ||
                    preg_match('/^(btn|card|badge|alert|tab|form|input|table|nav|page|home|workflow|report|user|site|word|settings|welcome|access|error|audit|stat|synth|log|changelog|help|guide|menu|modal|dropdown|tag|label|icon|avatar|header|footer|sidebar|content|container|wrapper|grid|flex|text|bg|border|shadow|ring|cursor|sr-only)/', $class)) {
                    $htmlClasses[$class] = true;
                }
            }
        }
    }
}

foreach ($htmlDirs as $dir) {
    scanDirForClasses($dir, $htmlClasses, $filesScanned);
}

// Also scan public/index.php and public/asset.php
foreach ([$projectDir . '/public/index.php', $projectDir . '/public/asset.php'] as $file) {
    if (file_exists($file)) {
        $filesScanned++;
        $content = file_get_contents($file);
        $content = preg_replace('#/\*.*?\*/#s', '', $content);
        if (preg_match_all('/class\s*=\s*["\']([^"\']*)["\']/', $content, $matches)) {
            foreach ($matches[1] as $classString) {
                $classes = preg_split('/\s+/', trim($classString));
                foreach ($classes as $class) {
                    $class = trim($class);
                    if ($class !== '' && preg_match('/^[a-zA-Z][\w-]*$/', $class)) {
                        $htmlClasses[$class] = true;
                    }
                }
            }
        }
    }
}

$htmlClasses = array_keys($htmlClasses);
sort($htmlClasses);

// ═══════════════════════════════════════════════════════════════
// 3. Compare
// ═══════════════════════════════════════════════════════════════

$unused = array_diff($cssClasses, $htmlClasses);  // In CSS but not in HTML
$missing = array_diff($htmlClasses, $cssClasses);  // In HTML but not in CSS

$unused = array_values($unused);
$missing = array_values($missing);
sort($unused);
sort($missing);

// ═══════════════════════════════════════════════════════════════
// 4. Output
// ═══════════════════════════════════════════════════════════════

$hasIssues = count($unused) > 0 || count($missing) > 0;

if ($outputJson) {
    $result = [
        'css_files' => count($cssFiles),
        'html_files_scanned' => $filesScanned,
        'css_classes_count' => count($cssClasses),
        'html_classes_count' => count($htmlClasses),
        'unused_css' => $unused,
        'missing_css' => $missing,
        'has_issues' => $hasIssues,
    ];
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "=== CSS Class Alignment Checker ===\n\n";
    echo "CSS files: " . count($cssFiles) . "\n";
    echo "HTML files scanned: $filesScanned\n";
    echo "CSS classes defined: " . count($cssClasses) . "\n";
    echo "HTML classes used: " . count($htmlClasses) . "\n\n";

    if ($showAll || $filterUnused) {
        echo "--- UNUSED CSS (in CSS, not in HTML): " . count($unused) . " ---\n";
        if (count($unused) === 0) {
            echo "  (none)\n";
        } else {
            foreach ($unused as $class) {
                echo "  .{$class}\n";
            }
        }
        echo "\n";
    }

    if ($showAll || $filterMissing) {
        echo "--- MISSING CSS (in HTML, not in CSS): " . count($missing) . " ---\n";
        if (count($missing) === 0) {
            echo "  (none)\n";
        } else {
            foreach ($missing as $class) {
                echo "  .{$class}\n";
            }
        }
        echo "\n";
    }

    if ($hasIssues) {
        echo "RESULT: ISSUES FOUND\n";
    } else {
        echo "RESULT: ALL CLEAR\n";
    }
}

exit($hasIssues ? 1 : 0);
