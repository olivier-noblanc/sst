<?php
/**
 * Configuration Helpers — Application SST DREETS BFC
 *
 * Config read/write, cache management, and version detection.
 * Extracted from helpers.php for single-responsibility clarity.
 */

/**
 * Get a configuration value from the config_app table.
 */
function getConfig(string $cle, string $default = ''): string {
    static $cache = [];
    if (isset($GLOBALS['_config_cache_cleared']) && $GLOBALS['_config_cache_cleared']) {
        $cache = [];
        $GLOBALS['_config_cache_cleared'] = false;
    }
    if (isset($cache[$cle])) {
        return $cache[$cle];
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
        $stmt->execute([':cle' => $cle]);
        $result = $stmt->fetchColumn();
        $value = ($result !== false && $result !== null) ? (string) $result : $default;
    } catch (Exception $e) {
        $value = $default;
    }
    $cache[$cle] = $value;
    return $value;
}

/**
 * Update (or insert) a configuration value in the config_app table.
 */
function updateConfig(PDO $pdo, string $cle, string $valeur): void {
    $stmt = $pdo->prepare('INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) 
        VALUES (:cle, :valeur, "", "", "", 1)
        ON CONFLICT(cle) DO UPDATE SET valeur = :valeur2, updated_at = datetime("now")');
    $stmt->execute([':cle' => $cle, ':valeur' => $valeur, ':valeur2' => $valeur]);
    clearConfigCache();
}

/**
 * Clear the static cache used by getConfig().
 */
function clearConfigCache(): void {
    $GLOBALS['_config_cache_cleared'] = true;
}

/**
 * Get the application version from the first entry in CHANGELOG.md.
 * Parses the first "## [x.y.z]" heading to extract the version number.
 * Falls back to '0.0.0' if the changelog is unreadable (visible problem, not hidden).
 * The version is NEVER stored in the database — it is derived from the changelog.
 *
 * Path resolution tries multiple locations in order:
 *   1. CHANGELOG_PATH constant (if defined — e.g. in config.php)
 *   2. dirname(__DIR__, 2) . '/CHANGELOG.md'  (project root: src/helpers → src → root)
 *   3. dirname(__DIR__) . '/CHANGELOG.md'      (src/ directory)
 *   4. $_SERVER['DOCUMENT_ROOT'] fallback       (IIS: resolves from web root)
 *   5. $_SERVER['SCRIPT_FILENAME'] fallback     (entry point directory)
 */
function getAppVersion(): string {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    // Try multiple path resolution strategies for robustness across deployments
    $candidatePaths = [];

    // 1. Explicit override via constant
    if (defined('CHANGELOG_PATH')) {
        $candidatePaths[] = CHANGELOG_PATH;
    }

    // 2. Project root (src/helpers → src → project root)
    $candidatePaths[] = dirname(__DIR__, 2) . '/CHANGELOG.md';

    // 3. Relative to src/ directory (dirname of helpers parent)
    $candidatePaths[] = dirname(__DIR__) . '/CHANGELOG.md';

    // 4. IIS: resolve from document root (public/) going one level up
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidatePaths[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/../CHANGELOG.md';
    }

    // 5. Entry point directory (where index.php lives) going one level up
    if (!empty($_SERVER['SCRIPT_FILENAME'])) {
        $candidatePaths[] = dirname(dirname($_SERVER['SCRIPT_FILENAME'])) . '/CHANGELOG.md';
    }

    foreach ($candidatePaths as $path) {
        // Normalize path (handles /../ and backslashes on Windows)
        $path = realpath($path) ?: $path;
        if (is_readable($path)) {
            $content = file_get_contents($path);
            if ($content && preg_match('/^##\s*\[(\d+\.\d+\.\d+)\]/m', $content, $m)) {
                $cached = $m[1];
                return $cached;
            }
        }
    }

    // Fallback: changelog is unreadable — return '0.0.0' so the problem is visible
    // (a hidden stale version is worse than an obviously wrong one)
    $cached = defined('APP_VERSION') ? APP_VERSION : '0.0.0';
    return $cached;
}
