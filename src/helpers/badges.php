<?php

/**
 * Badge CSS class helpers — Application SST DREETS BFC
 *
 * Extracted from formatting.php for single-responsibility clarity.
 */

/**
 * Get the registry color CSS variable name.
 */
function getRegistryColor(string $type): string
{
    return match ($type) {
        'rsst' => 'var(--rsst-color)',
        'rami' => 'var(--rami-color)',
        'dgi'  => 'var(--dgi-color)',
        default => 'var(--color-primary)',
    };
}

/**
 * Get the badge CSS class for a report state.
 */
function getEtatBadgeClass(string $etat): string
{
    return match ($etat) {
        'nouveau'    => 'badge--nouveau',
        'en_cours'   => 'badge--en-cours',
        'traite'     => 'badge--traite',
        'abandonne'  => 'badge--abandonne',
        'reouvert'   => 'badge--reouvert',
        default      => '',
    };
}

/**
 * Get the badge CSS class for a registry type.
 */
function getRegistryBadgeClass(string $type): string
{
    return match ($type) {
        'rsst' => 'badge--rsst',
        'rami' => 'badge--rami',
        'dgi'  => 'badge--dgi',
        default => '',
    };
}

/**
 * Get the badge CSS class for a user role.
 */
function getRoleBadgeClass(string $role): string
{
    return match ($role) {
        'agent'       => 'badge--agent',
        'superviseur' => 'badge--superviseur',
        'chsct'       => 'badge--chsct',
        default       => '',
    };
}
