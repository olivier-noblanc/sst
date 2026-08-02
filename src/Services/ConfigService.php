<?php

/** ConfigService — Configuration read/write, cache management, version detection. */

namespace App\Services;

use App\Enum\UserRole;
use App\Repository\ConfigRepository;
use App\Repository\RegistryRepository;
use App\Repository\SiteRepository;

class ConfigService
{
    /** @var array<string, string> */
    private array $cache = [];
    private bool $cacheCleared = false;

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
        $value = ConfigRepository::instance()->get($cle);
        $value = ($value !== null && $value !== '') ? $value : $default;
        $this->cache[$cle] = $value;
        return $value;
    }

    /**
     * Update (or insert) a configuration value in the config_app table.
     */
    public function set(string $cle, string $valeur): void
    {
        ConfigRepository::instance()->set($cle, $valeur);
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
     * Check if a registry type is enabled.
     * Reads from the registries table instead of hardcoded constants.
     */
    public function isRegistryEnabled(string $type): bool
    {
        $reg = RegistryRepository::instance()->findByCode($type);
        return $reg !== null && (int) $reg['is_enabled'] === 1;
    }

    /**
     * Get the list of enabled registry codes.
     * @return list<string>
     */
    public function getEnabledRegistries(): array
    {
        return array_column(RegistryRepository::instance()->findEnabled(), 'code');
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
        return SiteRepository::instance()->countActiveSites() > 0;
    }

    /**
     * Check if the application is in "no-site" mode (zero active sites).
     */
    public function isNoSiteMode(): bool
    {
        static $cache = null;
        if ($cache === null || !empty($GLOBALS['_config_cache_cleared'])) {
            $GLOBALS['_config_cache_cleared'] = false;
            $cache = SiteRepository::instance()->countActiveSites() === 0;
        }
        return $cache;
    }

    /**
     * Get the count of active sites.
     */
    public function countActiveSites(): int
    {
        return SiteRepository::instance()->countActiveSites();
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
            $docRoot = $_SERVER['DOCUMENT_ROOT'];
            $candidatePaths[] = rtrim($docRoot, '/\\') . '/../CHANGELOG.md';
        }
        if (!empty($_SERVER['SCRIPT_FILENAME'])) {
            /** @var string */
            $scriptFilename = $_SERVER['SCRIPT_FILENAME'];
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
