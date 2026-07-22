<?php

/** ConfigService — Configuration read/write, cache management, version detection. */

namespace App\Services;

use App\Enum\UserRole;
use Exception;

class ConfigService
{
    private static ?self $instance = null;
    /** @var array<string, string> */
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
            $value = ($result !== false && $result !== null && $result !== '') ? (string) $result : $default;
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
            return $this->get('app_registry_rami_enabled', '0') === '1';
        }
        if ($type === TYPE_DGI) {
            return $this->get('app_registry_dgi_enabled', '0') === '1';
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
        return UserRole::tryFrom($role)?->defaultLabel() ?? ucfirst($role);
    }

    /**
     * Get all role labels (customized or default).
     * @return array<string, string>
     */
    public function getRoleLabels(): array
    {
        return array_combine(
            array_map(fn($c) => $c->value, UserRole::cases()),
            array_map(fn($c) => $this->getRoleLabel($c->value), UserRole::cases())
        );
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
        if ($stmt === false) {
            return false;
        }
        $count = (int) $stmt->fetchColumn();
        return $count > 0;
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
            if ($stmt === false) {
                $cache = true;
            } else {
                $cache = ((int) $stmt->fetchColumn()) === 0;
            }
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
        if ($stmt === false) {
            return 0;
        }
        return (int) $stmt->fetchColumn();
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
            /** @var string */
            $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
            $candidatePaths[] = rtrim($docRoot, '/\\') . '/../CHANGELOG.md';
        }
        if (!empty($_SERVER['SCRIPT_FILENAME'])) {
            /** @var string */
            $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
            $candidatePaths[] = dirname($scriptFilename, 2) . '/CHANGELOG.md';
        }

        foreach ($candidatePaths as $path) {
            $resolved = realpath($path);
            $path = $resolved !== false ? $resolved : $path;
            if (is_readable($path)) {
                $content = file_get_contents($path);
                if (($content !== false && $content !== '') && preg_match('/^##\s*\[(\d+\.\d+\.\d+)\]/m', $content, $m) === 1) {
                    $cached = $m[1];
                    return $cached;
                }
            }
        }

        $cached = defined('APP_VERSION') ? APP_VERSION : '0.0.0';
        return $cached;
    }
}
