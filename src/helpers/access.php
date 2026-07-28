<?php

use App\DTO\ReportData;
use App\Services\AccessService;

/**
 * Access Control Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\AccessService.
 */

function getAccessService(): AccessService
{
    if (function_exists('getContainer') && getContainer()->has(AccessService::class)) {
        return getContainer()->get(AccessService::class);
    }
    return new AccessService();
}

/**
 * @param UserArray $user
 */
function canAccessReport(ReportData $report, array $user, ?string $forcedVisibility = null): bool
{
    return getAccessService()->canAccessReport($report->toArray(), $user, $forcedVisibility);
}

/**
 * @param UserArray $user
 */
function logConfidentialReportAccess(PDO $pdo, ReportData $report, array $user): void
{
    getAccessService()->logConfidentialReportAccess($pdo, $report->toArray(), $user);
}

function canSeeAllSites(): bool
{
    return getAccessService()->canSeeAllSites();
}

function normalizeVisibilityValue(string $value): string
{
    return getAccessService()->normalizeVisibilityValue($value);
}

function getReportVisibilityMode(?string $type = null): string
{
    return getAccessService()->getReportVisibilityMode($type);
}

function getReportVisibility(?string $type = null): string
{
    return getAccessService()->getReportVisibility($type);
}

function reportVisibilityIsConfidential(?string $type = null): bool
{
    return getAccessService()->reportVisibilityIsConfidential($type);
}

function reportVisibilityIsAgentChoice(?string $type = null): bool
{
    return getAccessService()->reportVisibilityIsAgentChoice($type);
}

function reportVisibilityIsPublic(?string $type = null): bool
{
    return getAccessService()->reportVisibilityIsPublic($type);
}

/**
 * @param array<string, mixed> $report
 */
function canEditReport(array $report, int $userId): bool
{
    return getAccessService()->canEditReport($report, $userId);
}

/**
 * @param array<string, mixed> $report
 */
function canRespondToReport(array $report, string $role): bool
{
    return getAccessService()->canRespondToReport($report, $role);
}
