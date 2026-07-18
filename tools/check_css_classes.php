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

// PHP page names, variable names, and constants to exclude
$excludedStrings = [
    // Page names (used in router, not CSS)
    'report_list', 'report_create', 'report_edit', 'report_view', 'report_print',
    'report_respond', 'report_reopen', 'report_abandon', 'report_attachment',
    'site_edit', 'user_edit', 'user_view', 'user_create', 'user_delete', 'user_reactivate',
    'settings', 'statistics', 'synthesis', 'export', 'changelog', 'help', 'guide',
    'preamble', 'access_denied', 'choose_site', 'login', 'logout', 'impersonate',
    // Variable names (PHP identifiers, not CSS)
    'username', 'site_code', 'site_nom', 'site_id', 'site_chosen_at', 'site_text',
    'word_cloud_words', 'report_uuid', 'report_created', 'report_abandoned',
    'logCount', 'logFileSize', 'errorFilter', 'error_log', 'echo',
    // PHP constants/values
    'agent', 'superviseur', 'chsct', 'admin', 'nouveau', 'en_cours', 'traite', 'abandonne',
    'reouvert', 'confidentiel', 'rsst', 'rami', 'dgi',
    // Boolean/string literals
    'true', 'false', 'null', 'new', 'match', 'errors', 'error',
    'reports', 'sites', 'users', 'logs', 'word', 'wordcloud', 'wordcloud-row', 'wordcloud-words',
    // Generic HTML/tag names that appear in class attributes
    'table', 'input', 'label', 'icon', 'page', 'home', 'audit', 'report-detail',
    'welcome-banner__content', 'agent-visibility-warning', 'form-encouragement',
    'btn--lg', 'btn--small', 'input--small', 'echo', 'match', 'new', 'wordcloud-row',
];

// CSS classes that exist in CSS but are built dynamically in PHP/JS
// (not found in static HTML class attributes by the checker)
$dynamicCssClasses = [
    // Registry cards (built via buildRegistryCards())
    'registry-card', 'registry-card--rsst', 'registry-card--rami', 'registry-card--dgi',
    'registry-card__icon', 'registry-card__title', 'registry-card__subtitle',
    'registry-card__desc', 'registry-card__btn', 'registry-card__link',
    'registry-card__stat', 'registry-card__extra', 'registry-cards', 'registry-cards--large',
    // Word cloud (built via JS spiral placement)
    'word-cloud', 'word-cloud__word', 'wc-s1', 'wc-s2', 'wc-s3', 'wc-s4', 'wc-s5',
    'wc-s6', 'wc-s7', 'wc-s8', 'wc-s9', 'wc-s10',
    'wc-c1', 'wc-c2', 'wc-c3', 'wc-c4', 'wc-c5', 'wc-c6',
    // Indicateur cards (built in statistics.php)
    'indicateur-card--nouveau', 'indicateur-card--en-cours', 'indicateur-card--traite',
    // Welcome banner variants
    'welcome-banner--new', 'welcome-banner__legend', 'welcome-banner__legend-text',
    // Print view (built dynamically in report_print.php)
    'print-hint', 'print-view', 'print-view__header', 'print-view__title',
    'print-view__field', 'print-view__label', 'print-view__value',
    // Log entries (built dynamically from log data)
    'log-entry--app', 'log-entry--audit', 'log-entry--backup', 'log-entry--db',
    'log-entry--fatal', 'log-entry--info', 'log-entry--mail', 'log-entry--migration',
    'log-entry--respond', 'log-entry--warning',
    // Badge variants (built dynamically from report status)
    'badge--app', 'badge--audit', 'badge--backup', 'badge--db', 'badge--dgi',
    'badge--fatal', 'badge--info', 'badge--mail', 'badge--migration', 'badge--rami',
    'badge--reouvert', 'badge--respond', 'badge--warning',
    'badge--cat-auth', 'badge--cat-backup', 'badge--cat-config', 'badge--cat-default',
    'badge--cat-export', 'badge--cat-gdpr', 'badge--cat-report', 'badge--cat-site', 'badge--cat-user',
    // Alert variants (built dynamically from flash messages)
    'alert--error', 'alert--success',
    // Breadcrumb (built dynamically in FormattingService)
    'breadcrumb', 'breadcrumb__item', 'breadcrumb__current', 'breadcrumb__separator',
    // Login (standalone page, not in templates/)
    'login-form', 'login-dev-info', 'login-disclaimer',
    // Help notes (built dynamically in help pages)
    'help-note--amber', 'help-note--inline', 'help-screenshot',
    // Confirm dialog (built dynamically)
    'confirm-box', 'confirm-box__actions', 'confirm-dialog__actions',
    // Tag input (JS component)
    'tag-input-container', 'tag-input-tags', 'tag-input-tag', 'tag-input-field', 'tag-input-remove',
    // Sidebar (built dynamically from page list)
    'sidebar__item', 'sidebar__item--active',
    // Tabs (built dynamically in settings)
    'tab--active',
    // Home page (removed agent if/else, now unified)
    'home-welcome-heading', 'home-welcome-subtitle', 'home-action--large',
    // Utility classes (used dynamically)
    'char-counter--warning', 'checkbox-label--top', 'status-dot',
    'quick-access', 'section-header', 'abandon-warning-text',
    // Layout
    'main', 'main-content', 'gap-2', 'justify-between', 'mt-0', 'text-right', 'label--small',
];

$htmlDirs = [
    $projectDir . '/templates',
    $projectDir . '/pages',
];

$htmlClasses = [];
$filesScanned = 0;

function scanDirForClasses(string $dir, array &$htmlClasses, int &$filesScanned, array $excludedStrings): void {
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
                // Exclude PHP page names, variable names, and constants
                if (in_array($class, $excludedStrings, true)) {
                    continue;
                }
                // Only count classes that look like CSS classes (contain --, __, or start with known prefixes)
                if (str_contains($class, '--') || str_contains($class, '__') ||
                    preg_match('/^(btn|card|badge|alert|tab|form|input|table|nav|page|home|workflow|welcome|access|audit|stat|synth|changelog|guide|menu|modal|dropdown|tag|label|icon|avatar|header|footer|sidebar|container|wrapper|grid|flex|bg|border|shadow|ring|cursor|sr-only|wc-|word-cloud|registry-card|print-view|confirm-|char-counter|tag-input|status-dot|help-note|log-entry|indicateur-card|abandon-warning|login-dev|login-disclaimer|login-form|breadcrumb|quick-access|section-header|checkbox-label)/', $class)) {
                    $htmlClasses[$class] = true;
                }
            }
        }
    }
}

foreach ($htmlDirs as $dir) {
    scanDirForClasses($dir, $htmlClasses, $filesScanned, $excludedStrings);
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

// Remove known dynamic CSS classes from unused list
$unused = array_values(array_diff($unused, $dynamicCssClasses));
// Remove excluded strings from missing list (false positives from class="..." extraction)
$missing = array_values(array_diff($missing, $excludedStrings));
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
