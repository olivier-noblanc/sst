<?php

use App\Services\FormattingService;

/**
 * Formatting Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\FormattingService.
 */

function getFormattingService(): FormattingService
{
    if (function_exists('getContainer') && getContainer()->has(FormattingService::class)) {
        return getContainer()->get(FormattingService::class);
    }
    return new FormattingService();
}

function e(?string $string): string
{
    return getFormattingService()->e($string);
}

function formatDateFR(?string $date): string
{
    return getFormattingService()->formatDateFR($date);
}

function formatDateTimeFR(?string $datetime): string
{
    return getFormattingService()->formatDateTimeFR($datetime);
}

function generateReference(string $type, string $year2, int $seq): string
{
    return getFormattingService()->generateReference($type, $year2, $seq);
}

function getNextSequence(PDO $pdo, string $type, int $year): int
{
    return getFormattingService()->getNextSequence($pdo, $type, $year);
}

function getRegistryColor(string $type): string
{
    return getFormattingService()->getRegistryColor($type);
}

function getEtatBadgeClass(string $etat): string
{
    return getFormattingService()->getEtatBadgeClass($etat);
}

function getRegistryBadgeClass(string $type): string
{
    return getFormattingService()->getRegistryBadgeClass($type);
}

function getRoleBadgeClass(string $role): string
{
    return getFormattingService()->getRoleBadgeClass($role);
}

function getMimeType(string $filePath): string
{
    return getFormattingService()->getMimeType($filePath);
}

function truncate(string $string, int $length = 50): string
{
    return getFormattingService()->truncate($string, $length);
}

function todayISO(): string
{
    return getFormattingService()->todayISO();
}

function nowTime(): string
{
    return getFormattingService()->nowTime();
}

/**
 * @param list<array{label: string, url?: string}> $items
 */
function renderBreadcrumb(array $items): string
{
    return getFormattingService()->renderBreadcrumb($items);
}

function buildWordCloud(PDO $pdo, string $type, int $maxWords = 30): string
{
    return getFormattingService()->buildWordCloud($pdo, $type, $maxWords);
}
