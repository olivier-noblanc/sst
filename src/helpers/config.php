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
function getConfig(string $cle, string $default = ''): string
{
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
function updateConfig(PDO $pdo, string $cle, string $valeur): void
{
    $stmt = $pdo->prepare('INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) 
        VALUES (:cle, :valeur, "", "", "", 1)
        ON CONFLICT(cle) DO UPDATE SET valeur = :valeur2, updated_at = datetime("now")');
    $stmt->execute([':cle' => $cle, ':valeur' => $valeur, ':valeur2' => $valeur]);
    clearConfigCache();
}

/**
 * Clear the static cache used by getConfig().
 */
function clearConfigCache(): void
{
    $GLOBALS['_config_cache_cleared'] = true;
}

/**
 * Check if a registry type is enabled (RAMI or DGI).
 * RSST is always enabled.
 */
function isRegistryEnabled(string $type): bool
{
    if ($type === TYPE_RSST) {
        return true; // RSST is always active
    }
    if ($type === TYPE_RAMI) {
        return getConfig('app_registry_rami_enabled', REGISTRY_RAMI_ENABLED_DEFAULT ? '1' : '0') === '1';
    }
    if ($type === TYPE_DGI) {
        return getConfig('app_registry_dgi_enabled', REGISTRY_DGI_ENABLED_DEFAULT ? '1' : '0') === '1';
    }
    return false;
}

/**
 * Get the list of enabled registry types.
 * @return string[]
 */
function getEnabledRegistries(): array
{
    $types = [TYPE_RSST]; // RSST always enabled
    if (isRegistryEnabled(TYPE_RAMI)) {
        $types[] = TYPE_RAMI;
    }
    if (isRegistryEnabled(TYPE_DGI)) {
        $types[] = TYPE_DGI;
    }
    return $types;
}

/**
 * Get the customizable label for a role.
 * Uses DB config if set, otherwise falls back to ROLE_LABELS_DEFAULT.
 */
function getRoleLabel(string $role): string
{
    $dbKey = 'app_role_label_' . $role;
    $dbValue = getConfig($dbKey, '');
    if ($dbValue !== '') {
        return $dbValue;
    }
    return ROLE_LABELS_DEFAULT[$role] ?? ucfirst($role);
}

/**
 * Get all role labels (customized or default).
 * @return array<string, string>
 */
function getRoleLabels(): array
{
    return [
        'agent'       => getRoleLabel('agent'),
        'superviseur' => getRoleLabel('superviseur'),
        'chsct'       => getRoleLabel('chsct'),
    ];
}

/**
 * Get the short/customary name for a role (without "Membre" prefix).
 * E.g. getRoleLabel('chsct') = "Membre FS/CSA" → getRoleLabelShort('chsct') = "FS/CSA".
 */
function getRoleLabelShort(string $role): string
{
    $label = getRoleLabel($role);
    // Strip common prefix: "Membre FS/CSA" → "FS/CSA", "Agent" → "Agent"
    $prefixes = ['Membre ', 'membre '];
    foreach ($prefixes as $prefix) {
        if (stripos($label, $prefix) === 0) {
            return substr($label, strlen($prefix));
        }
    }
    return $label;
}

/**
 * Check if there are any active sites in the system.
 * Returns true if at least one site is active (excluding the default RSST row).
 */
function hasActiveSites(PDO $pdo): bool
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM sites WHERE is_active = 1');
    return ($stmt->fetchColumn() ?: 0) > 0;
}

/**
 * Check if the application is in "no-site" mode (zero active sites).
 * When true, site-related fields should be hidden from forms, tables, and filters.
 */
function isNoSiteMode(PDO $pdo): bool
{
    static $cache = null;
    if ($cache === null) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM sites WHERE is_active = 1');
        $cache = (($stmt->fetchColumn() ?: 0) === 0);
    }
    return $cache;
}

/**
 * Get the count of active sites.
 */
function countActiveSites(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM sites WHERE is_active = 1');
    return (int) ($stmt->fetchColumn() ?: 0);
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
function getAppVersion(): string
{
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
