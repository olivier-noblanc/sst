<?php

/**
 * UUID helpers — Application SST DREETS BFC
 *
 * Pure utility functions for UUID v4 generation and validation.
 * No database dependency.
 */

/** Generate a UUID v4. */
function generateUuid(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
        . '-' . dechex((hexdec(substr($hex, 16, 2)) & 0x3F) | 0x80) . substr($hex, 18, 2)
        . '-' . substr($hex, 20, 12);
}

/** Validate UUID format (8-4-4-4-12 hex). Accepts all variants for legacy compatibility. */
function isValidUuid(string $uuid): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
}
