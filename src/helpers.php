<?php
/**
 * Helper Functions — Application SST DREETS BFC
 * 
 * Utility functions used throughout the application.
 */

/**
 * Escape HTML special characters. Use for ALL output.
 * 
 * @param string|null $string  The string to escape
 * @return string
 */
function e(?string $string): string {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * HTTP redirect and exit.
 * Also sets a global variable for CLI/proxy mode where header() is a no-op.
 * 
 * @param string $url  The URL to redirect to
 */
function redirect(string $url): void {
    // Store redirect info for CLI/proxy mode
    $GLOBALS['_PHP_REDIRECT'] = $url;
    header('Location: ' . $url);
    exit;
}

/**
 * Set a cookie (works in both web and CLI/proxy mode).
 */
function setCookieSafe(string $name, string $value = '', int $expires = 0, string $path = '/', bool $httpOnly = true, string $sameSite = 'Lax'): void {
    $cookieStr = $name . '=' . urlencode($value);
    if ($expires > 0) $cookieStr .= '; expires=' . gmdate('D, d M Y H:i:s T', $expires);
    if ($path) $cookieStr .= '; path=' . $path;
    if ($sameSite) $cookieStr .= '; SameSite=' . $sameSite;
    if ($httpOnly) $cookieStr .= '; HttpOnly';
    
    $GLOBALS['_PHP_COOKIES'][] = $cookieStr;
    header('Set-Cookie: ' . $cookieStr);
}

/**
 * Format an ISO date to French format (d/m/Y).
 * 
 * @param string|null $date  ISO date string (YYYY-MM-DD)
 * @return string
 */
function formatDateFR(?string $date): string {
    if (empty($date)) {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if ($dt === false) {
        // Try full datetime format
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $date);
    }
    return $dt !== false ? $dt->format('d/m/Y') : e($date);
}

/**
 * Format an ISO datetime to French format (d/m/Y à H:i).
 * 
 * @param string|null $datetime  ISO datetime string
 * @return string
 */
function formatDateTimeFR(?string $datetime): string {
    if (empty($datetime)) {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
    if ($dt === false) {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $datetime);
    }
    return $dt !== false ? $dt->format('d/m/Y \à H:i') : e($datetime);
}

/**
 * Generate a report reference string.
 * Format: {type}-{YY}-{NNN}
 * 
 * @param string $type    Registry type: 'rsst', 'rami', 'dgi'
 * @param string $year2   2-digit year, e.g. '25'
 * @param int    $seq     Sequence number
 * @return string
 */
function generateReference(string $type, string $year2, int $seq): string {
    return $type . '-' . $year2 . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

/**
 * Get the next sequence number for a report reference.
 * Uses atomic UPSERT on the report_sequence table.
 * 
 * @param PDO    $pdo   Database connection
 * @param string $type  Registry type
 * @param int    $year  Full year, e.g. 2025
 * @return int
 */
function getNextSequence(PDO $pdo, string $type, int $year): int {
    // Try to insert, or increment if exists
    $stmt = $pdo->prepare("
        INSERT INTO report_sequence (type, year, last_sequence)
        VALUES (:type, :year, 1)
        ON CONFLICT(type, year) DO UPDATE SET last_sequence = last_sequence + 1
    ");
    $stmt->execute([':type' => $type, ':year' => $year]);

    // Read the new value
    $stmt = $pdo->prepare("
        SELECT last_sequence FROM report_sequence WHERE type = :type AND year = :year
    ");
    $stmt->execute([':type' => $type, ':year' => $year]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get the registry color CSS variable name.
 * 
 * @param string $type  Registry type
 * @return string
 */
function getRegistryColor(string $type): string {
    return match ($type) {
        'rsst' => 'var(--rsst-color)',
        'rami' => 'var(--rami-color)',
        'dgi'  => 'var(--dgi-color)',
        default => 'var(--color-primary)',
    };
}

/**
 * Get the badge CSS class for a report state.
 * 
 * @param string $etat  Report state
 * @return string
 */
function getEtatBadgeClass(string $etat): string {
    return match ($etat) {
        'nouveau'    => 'badge--nouveau',
        'en_cours'   => 'badge--en-cours',
        'traite'     => 'badge--traite',
        'abandonne'  => 'badge--abandonne',
        default      => '',
    };
}

/**
 * Get the badge CSS class for a registry type.
 * 
 * @param string $type  Registry type
 * @return string
 */
function getRegistryBadgeClass(string $type): string {
    return match ($type) {
        'rsst' => 'badge--rsst',
        'rami' => 'badge--rami',
        'dgi'  => 'badge--dgi',
        default => '',
    };
}

/**
 * Get the badge CSS class for a user role.
 * 
 * @param string $role  User role
 * @return string
 */
function getRoleBadgeClass(string $role): string {
    return match ($role) {
        'agent'       => 'badge--agent',
        'superviseur' => 'badge--superviseur',
        'chsct'       => 'badge--chsct',
        default       => '',
    };
}

/**
 * Check if the current user can see all sites.
 * Superviseurs and CHSCT members always see all sites.
 * Agents never see all sites (they are restricted to their own site).
 * 
 * @return bool
 */
function canSeeAllSites(): bool {
    if (!isset($_SESSION['user']['role'])) {
        return false;
    }
    return in_array($_SESSION['user']['role'], ['superviseur', 'chsct']);
}

/**
 * Get the raw report visibility mode from config (role-agnostic).
 * Returns one of: 'confidential', 'agent_choice', 'public'
 * This is the actual setting value, regardless of the current user's role.
 * Used for form display (creation/edit) and handler logic.
 * 
 * @return string
 */
function getReportVisibilityMode(): string {
    $value = getConfig('app_report_visibility', 'agent_choice');
    // Backward compatibility: migrate old values
    if ($value === '0') return 'public';
    if ($value === '1') return 'confidential';
    if ($value === 'site') return 'public';
    if ($value === 'own') return 'confidential';
    // Old 2-mode values
    if ($value === 'confidential') return 'confidential';
    // Valid 3-mode values
    if (in_array($value, ['confidential', 'agent_choice', 'public'])) {
        return $value;
    }
    return 'agent_choice'; // Default fallback
}

/**
 * Get the report visibility for the current user (for reading/filtering).
 * Returns one of: 'confidential', 'agent_choice', 'public', 'all'
 * 
 * - 'all'          : Non-agent roles see everything (superviseur, chsct, admin)
 * - 'confidential' : Agent sees ONLY their own reports (most restrictive)
 * - 'agent_choice' : Agent sees public reports from their site + their own reports (even confidential).
 *                    Agent can choose per-report visibility, defaulting to confidential.
 * - 'public'       : Agent sees all reports from their own site
 * 
 * Non-agent roles always get 'all'.
 * 
 * @return string
 */
function getReportVisibility(): string {
    if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'agent') {
        return 'all';
    }
    return getReportVisibilityMode();
}

/**
 * Get the agent visibility mode.
 * Backward-compatible alias for getReportVisibility().
 * 
 * @return string
 * @deprecated Use getReportVisibility() instead
 */
function getAgentVisibility(): string {
    return getReportVisibility();
}

/**
 * Check if the report visibility mode is 'confidential' (most restrictive).
 * When true, agents see ONLY their own reports — nothing from other agents.
 * 
 * @return bool
 */
function reportVisibilityIsConfidential(): bool {
    return getReportVisibilityMode() === 'confidential';
}

/**
 * Check if the report visibility mode is 'agent_choice'.
 * When true, agents can choose per-report visibility, defaulting to confidential.
 * They see public reports from their site + their own reports (even confidential).
 * 
 * @return bool
 */
function reportVisibilityIsAgentChoice(): bool {
    return getReportVisibilityMode() === 'agent_choice';
}

/**
 * Check if the report visibility mode is 'public'.
 * When true, agents see all reports from their site.
 * 
 * @return bool
 */
function reportVisibilityIsPublic(): bool {
    return getReportVisibilityMode() === 'public';
}

/**
 * Check if the agent visibility mode is 'confidential' (old name).
 * @deprecated Use reportVisibilityIsConfidential() or reportVisibilityIsAgentChoice() instead
 */
function agentVisibilityIsConfidential(): bool {
    return in_array(getReportVisibilityMode(), ['confidential', 'agent_choice']);
}

/**
 * Check if the agent visibility mode is 'public' (old name).
 * @deprecated Use reportVisibilityIsPublic() instead
 */
function agentVisibilityIsPublic(): bool {
    return getReportVisibilityMode() === 'public';
}

/**
 * Detect the MIME type of a file using the fileinfo extension.
 *
 * Requires the PHP fileinfo extension to be enabled.
 * If the extension is not available, throws a clear error message.
 *
 * @param string $filePath  Absolute path to the file
 * @return string  MIME type (e.g. 'image/jpeg')
 * @throws \RuntimeException  If the fileinfo extension is not available
 */
function getMimeType(string $filePath): string {
    if (!class_exists('finfo')) {
        throw new \RuntimeException(
            'L\'extension PHP "fileinfo" est requise pour le téléchargement de pièces jointes. ' .
            'Veuillez l\'activer dans php.ini : extension=fileinfo, puis redémarrer le serveur web.'
        );
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($filePath);
    if ($mime === false) {
        throw new \RuntimeException('Impossible de déterminer le type du fichier.');
    }
    return $mime;
}

/**
 * Truncate a string to a given length with ellipsis.
 * 
 * @param string $string   The string to truncate
 * @param int    $length   Max length
 * @return string
 */
function truncate(string $string, int $length = 50): string {
    if (mb_strlen($string, 'UTF-8') > $length) {
        return mb_substr($string, 0, $length, 'UTF-8') . '…';
    }
    return $string;
}

/**
 * Get a configuration value from the config_app table.
 * 
 * @param string $cle     Configuration key
 * @param string $default Default value if key not found
 * @return string
 */
function getConfig(string $cle, string $default = ''): string {
    static $cache = [];
    // Check if cache was cleared
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
 * 
 * @param PDO    $pdo     Database connection
 * @param string $cle     Configuration key
 * @param string $valeur  New value
 */
function updateConfig(PDO $pdo, string $cle, string $valeur): void {
    $stmt = $pdo->prepare('INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) 
        VALUES (:cle, :valeur, "", "", "", 1)
        ON CONFLICT(cle) DO UPDATE SET valeur = :valeur2, updated_at = datetime("now")');
    $stmt->execute([':cle' => $cle, ':valeur' => $valeur, ':valeur2' => $valeur]);

    // Invalidate the static cache so getConfig() picks up the new value
    clearConfigCache();
}

/**
 * Clear the static cache used by getConfig().
 * Call this after updating config values to ensure fresh reads.
 */
function clearConfigCache(): void {
    // getConfig() uses a static $cache variable.
    // We cannot directly clear it from outside, so we call getConfig
    // with a special trick: we rely on the static scope.
    // Instead, we use a reference approach by calling a closure bound to the function.
    // The simplest approach: we call getConfig with a marker that resets the cache.
    // However, since PHP static variables are per-function, we need a different approach.
    // We'll use a global flag that getConfig checks.
    $GLOBALS['_config_cache_cleared'] = true;
}

/**
 * Build a URL for a static asset (CSS, JS, images).
 *
 * @param string $path  Asset path relative to public/ (e.g. 'css/style.css')
 * @return string
 */
function assetUrl(string $path): string {
    return $path;
}

/**
 * Build an internal application URL.
 * On IIS: index.php?page=...
 * Via Caddy gateway (dev): index.php?XTransformPort=...&page=...
 *
 * @param string $page   Page name (e.g. 'home', 'report_view')
 * @param array  $params Additional query parameters (e.g. ['id' => 5, 'type' => 'rsst'])
 * @return string
 */
function url(string $page, array $params = []): string {
    $queryParams = [];
    
    // Preserve XTransformPort if present (dev gateway mode)
    if (isset($_GET['XTransformPort'])) {
        $queryParams['XTransformPort'] = $_GET['XTransformPort'];
    }
    
    $queryParams['page'] = $page;
    foreach ($params as $key => $value) {
        $queryParams[$key] = $value;
    }
    
    return 'index.php?' . http_build_query($queryParams);
}

/**
 * Get today's date in ISO format (Y-m-d).
 * 
 * @return string
 */
function todayISO(): string {
    return date('Y-m-d');
}

/**
 * Get current time in HH:MM format.
 * 
 * @return string
 */
function nowTime(): string {
    return date('H:i');
}
