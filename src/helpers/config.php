<?php

use App\Services\ConfigService;

/**
 * Configuration Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\ConfigService.
 */

function getConfigService(): ConfigService
{
    if (function_exists('getContainer') && getContainer()->has(ConfigService::class)) {
        return getContainer()->get(ConfigService::class);
    }
    return ConfigService::getInstance();
}

function getConfig(string $cle, string $default = ''): string
{
    return getConfigService()->get($cle, $default);
}

function updateConfig(PDO $pdo, string $cle, string $valeur): void
{
    getConfigService()->set($cle, $valeur);
}

function clearConfigCache(): void
{
    getConfigService()->clearCache();
}

function isRegistryEnabled(string $type): bool
{
    return getConfigService()->isRegistryEnabled($type);
}

/**
 * @return list<string>
 */
function getEnabledRegistries(): array
{
    return array_values(getConfigService()->getEnabledRegistries());
}

function getRoleLabel(string $role): string
{
    return getConfigService()->getRoleLabel($role);
}

/**
 * @return array<string, string>
 */
function getRoleLabels(): array
{
    return getConfigService()->getRoleLabels();
}

function getRoleLabelShort(string $role): string
{
    return getConfigService()->getRoleLabelShort($role);
}

function hasActiveSites(PDO $pdo): bool
{
    return getConfigService()->hasActiveSites();
}

function isNoSiteMode(PDO $pdo): bool
{
    return getConfigService()->isNoSiteMode();
}

function countActiveSites(PDO $pdo): int
{
    return getConfigService()->countActiveSites();
}

function getAppVersion(): string
{
    return getConfigService()->getAppVersion();
}
