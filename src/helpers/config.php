<?php

use App\Services\ConfigService;

/**
 * Configuration Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\ConfigService.
 */

function getConfig(string $cle, string $default = ''): string
{
    return ConfigService::getInstance()->get($cle, $default);
}

function updateConfig(PDO $pdo, string $cle, string $valeur): void
{
    ConfigService::getInstance()->set($cle, $valeur);
}

function clearConfigCache(): void
{
    ConfigService::getInstance()->clearCache();
}

function isRegistryEnabled(string $type): bool
{
    return (new ConfigService())->isRegistryEnabled($type);
}

function getEnabledRegistries(): array
{
    return (new ConfigService())->getEnabledRegistries();
}

function getRoleLabel(string $role): string
{
    return (new ConfigService())->getRoleLabel($role);
}

function getRoleLabels(): array
{
    return (new ConfigService())->getRoleLabels();
}

function getRoleLabelShort(string $role): string
{
    return (new ConfigService())->getRoleLabelShort($role);
}

function hasActiveSites(PDO $pdo): bool
{
    return (new ConfigService())->hasActiveSites();
}

function isNoSiteMode(PDO $pdo): bool
{
    return (new ConfigService())->isNoSiteMode();
}

function countActiveSites(PDO $pdo): int
{
    return (new ConfigService())->countActiveSites();
}

function getAppVersion(): string
{
    return (new ConfigService())->getAppVersion();
}
