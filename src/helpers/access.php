<?php

use App\Services\AccessService;

/**
 * Access Control Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\AccessService.
 */

function canAccessReport(array $report, array $user, ?string $forcedVisibility = null): bool
{
    return (new AccessService())->canAccessReport($report, $user, $forcedVisibility);
}

function logConfidentialReportAccess(PDO $pdo, array $report, array $user): void
{
    (new AccessService())->logConfidentialReportAccess($pdo, $report, $user);
}

function canSeeAllSites(): bool
{
    return (new AccessService())->canSeeAllSites();
}

function normalizeVisibilityValue(string $value): string
{
    return (new AccessService())->normalizeVisibilityValue($value);
}

function getReportVisibilityMode(?string $type = null): string
{
    return (new AccessService())->getReportVisibilityMode($type);
}

function getReportVisibility(?string $type = null): string
{
    return (new AccessService())->getReportVisibility($type);
}

function reportVisibilityIsConfidential(?string $type = null): bool
{
    return (new AccessService())->reportVisibilityIsConfidential($type);
}

function reportVisibilityIsAgentChoice(?string $type = null): bool
{
    return (new AccessService())->reportVisibilityIsAgentChoice($type);
}

function reportVisibilityIsPublic(?string $type = null): bool
{
    return (new AccessService())->reportVisibilityIsPublic($type);
}

function canEditReport(array $report, int $userId): bool
{
    return (new AccessService())->canEditReport($report, $userId);
}

function canRespondToReport(array $report, string $role): bool
{
    return (new AccessService())->canRespondToReport($report, $role);
}
