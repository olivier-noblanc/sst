<?php

use App\DTO\ReportData;
use App\DTO\SessionUser;
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

function canAccessReport(ReportData $report, SessionUser $user, ?string $forcedVisibility = null): bool
{
    return getAccessService()->canAccessReport($report, $user, $forcedVisibility);
}

function logConfidentialReportAccess(PDO $pdo, ReportData $report, SessionUser $user): void
{
    getAccessService()->logConfidentialReportAccess($pdo, $report, $user);
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

function canEditReport(ReportData $report, int $userId): bool
{
    return getAccessService()->canEditReport($report, $userId);
}

function canRespondToReport(ReportData $report, string $role): bool
{
    return getAccessService()->canRespondToReport($report, $role);
}
