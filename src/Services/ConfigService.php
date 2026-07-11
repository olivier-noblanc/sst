<?php
/** ConfigService — Configuration read/write, cache management, version detection. */

namespace App\Services;

use Exception;

class ConfigService
{
    private static ?self $instance = null;
    private array $cache = [];
    private bool $cacheCleared = false;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset singleton (used by container to share instance).
     */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Get a configuration value from the config_app table.
     */
    public function get(string $cle, string $default = ''): string
    {
        if ($this->cacheCleared) {
            $this->cache = [];
            $this->cacheCleared = false;
        }
        if (isset($this->cache[$cle])) {
            return $this->cache[$cle];
        }
        try {
            $pdo = \getDB();
            $stmt = $pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
            $stmt->execute([':cle' => $cle]);
            $result = $stmt->fetchColumn();
            $value = ($result !== false && $result !== null) ? (string) $result : $default;
        } catch (Exception) {
            $value = $default;
        }
        $this->cache[$cle] = $value;
        return $value;
    }

    /**
     * Update (or insert) a configuration value in the config_app table.
     */
    public function set(string $cle, string $valeur): void
    {
        $pdo = \getDB();
        $stmt = $pdo->prepare('INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) 
            VALUES (:cle, :valeur, "", "", "", 1)
            ON CONFLICT(cle) DO UPDATE SET valeur = :valeur2, updated_at = datetime("now")');
        $stmt->execute([':cle' => $cle, ':valeur' => $valeur, ':valeur2' => $valeur]);
        $this->clearCache();
    }

    /**
     * Clear the config cache.
     */
    public function clearCache(): void
    {
        $this->cacheCleared = true;
        $GLOBALS['_config_cache_cleared'] = true;
    }

    /**
     * Check if a registry type is enabled (RAMI or DGI). RSST is always enabled.
     */
    public function isRegistryEnabled(string $type): bool
    {
        if ($type === TYPE_RSST) {
            return true;
        }
        if ($type === TYPE_RAMI) {
            return $this->get('app_registry_rami_enabled', REGISTRY_RAMI_ENABLED_DEFAULT ? '1' : '0') === '1';
        }
        if ($type === TYPE_DGI) {
            return $this->get('app_registry_dgi_enabled', REGISTRY_DGI_ENABLED_DEFAULT ? '1' : '0') === '1';
        }
        return false;
    }

    /**
     * Get the list of enabled registry types.
     * @return string[]
     */
    public function getEnabledRegistries(): array
    {
        $types = [TYPE_RSST];
        if ($this->isRegistryEnabled(TYPE_RAMI)) {
            $types[] = TYPE_RAMI;
        }
        if ($this->isRegistryEnabled(TYPE_DGI)) {
            $types[] = TYPE_DGI;
        }
        return $types;
    }

    /**
     * Get the customizable label for a role.
     */
    public function getRoleLabel(string $role): string
    {
        $dbKey = 'app_role_label_' . $role;
        $dbValue = $this->get($dbKey, '');
        if ($dbValue !== '') {
            return $dbValue;
        }
        return ROLE_LABELS_DEFAULT[$role] ?? ucfirst($role);
    }

    /**
     * Get all role labels (customized or default).
     * @return array<string, string>
     */
    public function getRoleLabels(): array
    {
        return [
            'agent'       => $this->getRoleLabel('agent'),
            'superviseur' => $this->getRoleLabel('superviseur'),
            'chsct'       => $this->getRoleLabel('chsct'),
        ];
    }

    /**
     * Get the short/customary name for a role (without "Membre" prefix).
     */
    public function getRoleLabelShort(string $role): string
    {
        $label = $this->getRoleLabel($role);
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
     */
    public function hasActiveSites(): bool
    {
        $pdo = \getDB();
        $stmt = $pdo->query('SELECT COUNT(*) FROM sites WHERE is_active = 1');
        return ($stmt->fetchColumn() ?: 0) > 0;
    }

    /**
     * Check if the application is in "no-site" mode (zero active sites).
     */
    public function isNoSiteMode(): bool
    {
        static $cache = null;
        if ($cache === null || !empty($GLOBALS['_config_cache_cleared'])) {
            $GLOBALS['_config_cache_cleared'] = false;
            $pdo = \getDB();
            $stmt = $pdo->query('SELECT COUNT(*) FROM sites WHERE is_active = 1');
            $cache = (($stmt->fetchColumn() ?: 0) === 0);
        }
        return $cache;
    }

    /**
     * Get the count of active sites.
     */
    public function countActiveSites(): int
    {
        $pdo = \getDB();
        $stmt = $pdo->query('SELECT COUNT(*) FROM sites WHERE is_active = 1');
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Get the application version from CHANGELOG.md.
     */
    public function getAppVersion(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $candidatePaths = [];

        if (defined('CHANGELOG_PATH')) {
            $candidatePaths[] = CHANGELOG_PATH;
        }
        $candidatePaths[] = dirname(__DIR__, 2) . '/CHANGELOG.md';
        $candidatePaths[] = dirname(__DIR__) . '/CHANGELOG.md';
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $candidatePaths[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') . '/../CHANGELOG.md';
        }
        if (!empty($_SERVER['SCRIPT_FILENAME'])) {
            $candidatePaths[] = dirname((string) $_SERVER['SCRIPT_FILENAME'], 2) . '/CHANGELOG.md';
        }

        foreach ($candidatePaths as $path) {
            $path = realpath($path) ?: $path;
            if (is_readable($path)) {
                $content = file_get_contents($path);
                if ($content && preg_match('/^##\s*\[(\d+\.\d+\.\d+)\]/m', $content, $m)) {
                    $cached = $m[1];
                    return $cached;
                }
            }
        }

        $cached = defined('APP_VERSION') ? APP_VERSION : '0.0.0';
        return $cached;
    }
}
