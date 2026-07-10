<?php

use App\Services\FormattingService;

/**
 * Formatting Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\FormattingService.
 */

function e(?string $string): string
{
    return (new FormattingService())->e($string);
}

function formatDateFR(?string $date): string
{
    return (new FormattingService())->formatDateFR($date);
}

function formatDateTimeFR(?string $datetime): string
{
    return (new FormattingService())->formatDateTimeFR($datetime);
}

function generateReference(string $type, string $year2, int $seq): string
{
    return (new FormattingService())->generateReference($type, $year2, $seq);
}

function getNextSequence(PDO $pdo, string $type, int $year): int
{
    return (new FormattingService())->getNextSequence($pdo, $type, $year);
}

function getRegistryColor(string $type): string
{
    return (new FormattingService())->getRegistryColor($type);
}

function getEtatBadgeClass(string $etat): string
{
    return (new FormattingService())->getEtatBadgeClass($etat);
}

function getRegistryBadgeClass(string $type): string
{
    return (new FormattingService())->getRegistryBadgeClass($type);
}

function getRoleBadgeClass(string $role): string
{
    return (new FormattingService())->getRoleBadgeClass($role);
}

function getMimeType(string $filePath): string
{
    return (new FormattingService())->getMimeType($filePath);
}

function truncate(string $string, int $length = 50): string
{
    return (new FormattingService())->truncate($string, $length);
}

function todayISO(): string
{
    return (new FormattingService())->todayISO();
}

function nowTime(): string
{
    return (new FormattingService())->nowTime();
}

function renderBreadcrumb(array $items): string
{
    return (new FormattingService())->renderBreadcrumb($items);
}

function buildWordCloud(PDO $pdo, string $type, int $maxWords = 30): string
{
    return (new FormattingService())->buildWordCloud($pdo, $type, $maxWords);
}
