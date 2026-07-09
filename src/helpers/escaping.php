<?php

/**
 * HTML escaping functions — Application SST DREETS BFC
 *
 * Extracted from formatting.php for single-responsibility clarity.
 */

/**
 * Escape HTML special characters. Use for ALL output.
 *
 * @param string|null $string  The string to escape
 * @return string
 */
function e(?string $string): string
{
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format an ISO date to French format (d/m/Y).
 *
 * @param string|null $date  ISO date string (YYYY-MM-DD)
 * @return string
 */
function formatDateFR(?string $date): string
{
    if (empty($date)) {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if ($dt === false) {
        // Try full datetime format
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $date);
    }
    return $dt !== false ? $dt->format('d/m/Y') : e($date);
}

/**
 * Format an ISO datetime to French format (d/m/Y à H:i).
 *
 * @param string|null $datetime  ISO datetime string
 * @return string
 */
function formatDateTimeFR(?string $datetime): string
{
    if (empty($datetime)) {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
    if ($dt === false) {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $datetime);
    }
    return $dt !== false ? $dt->format('d/m/Y \à H:i') : e($datetime);
}
