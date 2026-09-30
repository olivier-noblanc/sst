<?php

use App\Enum\ReportField;
use App\Services\ConfigService;

/**
 * Configuration Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\ConfigService.
 */

function getConfigService(): ConfigService
{
    return getContainer()->get(ConfigService::class);
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
    return getConfigService()->getEnabledRegistries();
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

/**
 * Texte explicatif du niveau de confidentialité d'un registre.
 *
 * Renvoie le texte personnalisé par l'admin s'il est défini, sinon le texte
 * par défaut passé par l'appelant (fallback = phrase actuelle). Le résultat
 * doit toujours être échappé par l'appelant (e()).
 */
function confidentialityNote(string $registryCode, string $default): string
{
    $note = getConfigService()->getConfidentialityNote($registryCode);
    return $note !== '' ? $note : $default;
}

/**
 * Activation d'un champ métier du formulaire, configurable par registre
 * (clé `app_field_<field>_enabled_<code>`, défaut : activé).
 */
function isReportFieldEnabled(string $registryCode, ReportField $field): bool
{
    return getConfigService()->isReportFieldEnabled($registryCode, $field);
}

/**
 * Libellé effectif d'un champ métier : personnalisé par registre, sinon
 * libellé par défaut actuel. Le résultat doit toujours être échappé par
 * l'appelant (e() / $fmt->e()).
 */
function reportFieldLabel(string $registryCode, ReportField $field): string
{
    return getConfigService()->reportFieldLabel($registryCode, $field);
}
