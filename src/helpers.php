<?php
/**
 * Helper Functions — Application SST DREETS BFC
 * 
 * Utility functions used throughout the application.
 */

/**
 * Centralize access control for a report.
 * Combines role, site, visibility mode and confidentiality checks.
 *
 * @param array $report  Row from `reports` (site_id, declarant_id, is_confidential, type)
 * @param array $user    $_SESSION['user'] (id, site_id, role)
 * @return bool
 */
function canAccessReport(array $report, array $user, ?string $forcedVisibility = null): bool {
    // Superviseur/CSA/CHSCT can always see everything
    if (in_array($user['role'], ['superviseur', 'chsct'], true)) {
        return true;
    }

    // Agent can never see reports from other sites
    if ((int) $report['site_id'] !== (int) $user['site_id']) {
        return false;
    }

    // Use forced visibility mode (for tests) or read from config
    $visibility = $forcedVisibility ?? getReportVisibilityMode($report['type'] ?? null);

    if ($visibility === 'confidential' && (int) $report['declarant_id'] !== (int) $user['id']) {
        // In confidential mode, agent can ONLY see their own reports
        return false;
    }

    if ($visibility === 'agent_choice' && (int) $report['is_confidential'] === 1 && (int) $report['declarant_id'] !== (int) $user['id']) {
        // In agent_choice mode, agent cannot see other agents' confidential reports
        return false;
    }

    return true;
}

/**
 * Log access to a confidential report by supervisor/CSA/CHSCT.
 * Only logs when a superviseur/chsct consults a report with is_confidential=1
 * that they did not file themselves.
 *
 * @param PDO   $pdo     Database connection
 * @param array $report  Row from `reports`
 * @param array $user    $_SESSION['user']
 */
function logConfidentialReportAccess(PDO $pdo, array $report, array $user): void {
    if ((int) $report['is_confidential'] !== 1) {
        return;
    }
    if (!in_array($user['role'], ['superviseur', 'chsct'], true)) {
        return;
    }
    if ((int) $report['declarant_id'] === (int) $user['id']) {
        return;
    }
    try {
        $stmt = $pdo->prepare("
            INSERT INTO report_access_log (report_uuid, user_id, role)
            VALUES (:report_uuid, :user_id, :role)
        ");
        $stmt->execute([
            ':report_uuid' => $report['uuid'],
            ':user_id'     => (int) $user['id'],
            ':role'        => $user['role'],
        ]);
    } catch (Exception $e) {
        // Logging must NEVER break the application
        error_log('[SST-ACCESS-LOG] Failed to log report access: ' . $e->getMessage());
    }
}

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
    $stmt = $pdo->prepare("
        INSERT INTO report_sequence (type, year, last_sequence)
        VALUES (:type, :year, 1)
        ON CONFLICT(type, year) DO UPDATE SET last_sequence = last_sequence + 1
    ");
    $stmt->execute([':type' => $type, ':year' => $year]);

    $stmt = $pdo->prepare("
        SELECT last_sequence FROM report_sequence WHERE type = :type AND year = :year
    ");
    $stmt->execute([':type' => $type, ':year' => $year]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get the registry color CSS variable name.
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
 */
function canSeeAllSites(): bool {
    if (!isset($_SESSION['user']['role'])) {
        return false;
    }
    return in_array($_SESSION['user']['role'], ['superviseur', 'chsct']);
}

/**
 * Normalize a raw config value into a valid visibility mode.
 */
function normalizeVisibilityValue(string $value): string {
    if ($value === '0' || $value === 'site') return 'public';
    if ($value === '1' || $value === 'own') return 'confidential';
    if (in_array($value, ['confidential', 'agent_choice', 'public'])) {
        return $value;
    }
    return 'agent_choice';
}

/**
 * Get the raw report visibility mode from config (role-agnostic).
 * Supports per-registry keys (app_report_visibility_rsst/rami/dgi)
 * with fallback to the global key.
 *
 * @param string|null $type  Registry type ('rsst', 'rami', 'dgi') or null for global
 */
function getReportVisibilityMode(?string $type = null): string {
    if ($type !== null) {
        $key = 'app_report_visibility_' . $type;
        $value = getConfig($key, '');
        if ($value !== '') {
            return normalizeVisibilityValue($value);
        }
        // Fallback to global key if per-registry key is empty/not set
    }
    $value = getConfig('app_report_visibility', 'agent_choice');
    return normalizeVisibilityValue($value);
}

/**
 * Get the report visibility for the current user (for reading/filtering).
 *
 * @param string|null $type  Registry type ('rsst', 'rami', 'dgi') or null for global
 */
function getReportVisibility(?string $type = null): string {
    if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'agent') {
        return 'all';
    }
    return getReportVisibilityMode($type);
}

/** @deprecated Use getReportVisibility() instead */
function getAgentVisibility(): string {
    return getReportVisibility();
}

function reportVisibilityIsConfidential(?string $type = null): bool {
    return getReportVisibilityMode($type) === 'confidential';
}

function reportVisibilityIsAgentChoice(?string $type = null): bool {
    return getReportVisibilityMode($type) === 'agent_choice';
}

function reportVisibilityIsPublic(?string $type = null): bool {
    return getReportVisibilityMode($type) === 'public';
}

/** @deprecated */
function agentVisibilityIsConfidential(): bool {
    return in_array(getReportVisibilityMode(), ['confidential', 'agent_choice']);
}

/** @deprecated */
function agentVisibilityIsPublic(): bool {
    return getReportVisibilityMode() === 'public';
}

/**
 * Detect the MIME type of a file using the fileinfo extension.
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
 */
function truncate(string $string, int $length = 50): string {
    if (mb_strlen($string, 'UTF-8') > $length) {
        return mb_substr($string, 0, $length, 'UTF-8') . '…';
    }
    return $string;
}

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
 * Get the application version from the database (config_app table).
 * Falls back to the APP_VERSION constant if the DB is unreachable or the key is empty.
 * This is the canonical way to read the version — APP_VERSION is only a fallback.
 */
function getAppVersion(): string {
    $dbVersion = getConfig('app_version', '');
    if ($dbVersion !== '') {
        return $dbVersion;
    }
    return defined('APP_VERSION') ? APP_VERSION : '0';
}

/**
 * Build a URL for a static asset (CSS, JS, images, fonts).
 *
 * DEPRECATED for CSS/favicons: these are now inlined via inlineCss() and inlineDataUri().
 * assetUrl() is kept for rare cases (attachment downloads, exports, etc.)
 * that still need a separate HTTP request.
 *
 * @param string $path  Asset path relative to public/ (e.g. 'css/style.css')
 * @return string
 */
function assetUrl(string $path): string {
    $version = getAppVersion();
    return 'asset.php?f=' . urlencode($path) . '&v=' . urlencode($version);
}

/**
 * Inline a CSS file directly into HTML as a <style> tag.
 *
 * Reads the CSS file from public/ and returns a <style> element.
 * Eliminates a separate HTTP request, avoids webhint content-type false positives,
 * and removes all IIS dependency for serving static CSS.
 *
 * Since all HTML pages use Cache-Control: no-cache, the browser revalidates
 * on every page load anyway — a separate cached CSS file provides no benefit.
 *
 * Gzip compression (ob_gzhandler) compresses the inline CSS efficiently.
 *
 * @param string $path  CSS path relative to public/ (e.g. 'css/style.css')
 * @return string  HTML <style> tag with CSS content
 */
function inlineCss(string $path): string {
    $filePath = __DIR__ . '/../public/' . $path;
    if (!file_exists($filePath)) {
        return '<style>/* CSS not found: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . ' */</style>';
    }
    $css = file_get_contents($filePath);
    return '<style>' . $css . '</style>';
}

/**
 * Generate a data URI for a binary file (favicon, logo, etc.).
 *
 * Reads the file and returns a data: URI string suitable for use in
 * <link rel="icon" href="..."> or <img src="...">.
 *
 * Eliminates separate HTTP requests for small static assets,
 * avoids webhint content-type/cache-control issues entirely.
 *
 * @param string $path  File path relative to public/ (e.g. 'favicon.ico')
 * @return string  data URI (e.g. 'data:image/png;base64,...')
 */
function inlineDataUri(string $path): string {
    $filePath = __DIR__ . '/../public/' . $path;
    if (!file_exists($filePath)) {
        return '';
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mimeTypes = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'webp' => 'image/webp',
    ];

    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
    $data = base64_encode(file_get_contents($filePath));

    return 'data:' . $mime . ';base64,' . $data;
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
 */
function todayISO(): string {
    return date('Y-m-d');
}

/**
 * Get current time in HH:MM format.
 */
function nowTime(): string {
    return date('H:i');
}

/**
 * Encrypt a value with AES-256-CBC using SST_SECRET_KEY env var.
 * Returns "enc:base64(iv + ciphertext)". Returns plain value unchanged if encryption unavailable.
 *
 * @param string $plaintext  The value to encrypt
 * @return string            Encrypted value with "enc:" prefix, or plain value on failure
 */
function encryptConfigValue(string $plaintext): string {
    if ($plaintext === '') {
        return '';
    }
    $key = getenv('SST_SECRET_KEY');
    if ($key === false || strlen($key) < 32) {
        error_log('[SST-CRYPTO] SST_SECRET_KEY missing or too short — cannot encrypt. Set a 32+ character key in IIS environment variables.');
        return $plaintext;
    }
    $key = substr($key, 0, 32); // AES-256 requires exactly 32 bytes
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        error_log('[SST-CRYPTO] openssl_encrypt failed — returning plaintext.');
        return $plaintext;
    }
    return 'enc:' . base64_encode($iv . $ciphertext);
}

/**
 * Decrypt a value encrypted by encryptConfigValue().
 * Detects the "enc:" prefix. Returns plain value unchanged if not encrypted.
 *
 * @param string $value  The value to decrypt (may be "enc:..." or plain text)
 * @return string        Decrypted value, or original value if not encrypted / on failure
 */
function decryptConfigValue(string $value): string {
    if ($value === '' || !str_starts_with($value, 'enc:')) {
        return $value;
    }
    $key = getenv('SST_SECRET_KEY');
    if ($key === false || strlen($key) < 32) {
        error_log('[SST-CRYPTO] SST_SECRET_KEY missing or too short — cannot decrypt encrypted value.');
        return $value; // Return encrypted blob as-is (can't decrypt)
    }
    $key = substr($key, 0, 32);
    $decoded = base64_decode(substr($value, 4), true);
    if ($decoded === false || strlen($decoded) < 17) {
        error_log('[SST-CRYPTO] Invalid encrypted value — base64 decode failed or too short.');
        return $value;
    }
    $iv = substr($decoded, 0, 16);
    $ciphertext = substr($decoded, 16);
    $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        error_log('[SST-CRYPTO] openssl_decrypt failed — wrong key or corrupted data.');
        return $value;
    }
    return $decrypted;
}
