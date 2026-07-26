<?php

/**
 * RegistryPolicy — Centralise la logique métier spécifique aux registres.
 *
 * Modular-audit P2.1 — Avant ce fix, la logique spécifique RAMI (validation
 * pour_compte) et DGI (warning panel L4131-2, label "Lieu / Mesures de protection")
 * était éclatée sur 8+ fichiers avec des `=== ReportType::Rami->value` et
 * `=== ReportType::Dgi->value` hardcodés.
 *
 * Désormais, cette logique est configurable via la table `registries` :
 * - `requires_pour_compte` (INTEGER, default 0) — pour_compte_nom/prenom requis
 * - `has_dgi_warning` (INTEGER, default 0) — affiche le panneau L4131-2
 * - `lieu_label_override` (TEXT, nullable) — override du label "Lieu"
 *
 * Pour les DB pré-migration, on garde le comportement historique (RAMI →
 * pour_compte, DGI → warning + lieu label override).
 */

namespace App\Services;

use App\Repository\RegistryRepository;
use App\Enum\ReportType;

class RegistryPolicy
{
    /**
     * Whether this registry requires the "pour le compte de" sub-fields
     * (pour_compte_nom, pour_compte_prenom). Historically RAMI-only.
     */
    public function requiresPourCompte(string $type): bool
    {
        // For backwards compat: RAMI always requires pour_compte (hardcoded
        // historical behavior, would be too disruptive to migrate to DB).
        if ($type === ReportType::Rami->value) {
            return true;
        }
        // For custom registries: check the registries.requires_pour_compte column
        // (added via migration_columns.php). Default 0 (no pour_compte).
        return $this->getRegistryBoolFlag($type, 'requires_pour_compte');
    }

    /**
     * Whether this registry shows the DGI warning panel (L4131-2 Code du travail).
     * Historically DGI-only.
     */
    public function hasDgiWarningPanel(string $type): bool
    {
        if ($type === ReportType::Dgi->value) {
            return true;
        }
        return $this->getRegistryBoolFlag($type, 'has_dgi_warning');
    }

    /**
     * The label to use for the "lieu" field. DGI uses "Lieu / Mesures de protection".
     * Custom registries can override via registries.lieu_label_override.
     */
    public function getLieuLabel(string $type): string
    {
        if ($type === \App\Enum\ReportType::Dgi->value) {
            return 'Lieu / Mesures de protection';
        }
        $override = $this->getRegistryStringFlag($type, 'lieu_label_override');
        return $override !== '' ? $override : 'Lieu';
    }

    /**
     * Get a boolean flag from the registries table (with safe fallback).
     */
    private function getRegistryBoolFlag(string $type, string $column): bool
    {
        try {
            $registry = RegistryRepository::instance()->findByCode($type);
            if ($registry !== null && isset($registry[$column])) {
                return (int) $registry[$column] === 1;
            }
        } catch (\Throwable $e) {
            // Pre-migration (column missing) — fail safe (false)
        }
        return false;
    }

    /**
     * Get a string flag from the registries table (with safe fallback).
     */
    private function getRegistryStringFlag(string $type, string $column): string
    {
        try {
            $registry = RegistryRepository::instance()->findByCode($type);
            if ($registry !== null && isset($registry[$column])) {
                $value = $registry[$column];
                return is_string($value) ? $value : '';
            }
        } catch (\Throwable $e) {
            // Pre-migration (column missing) — fail safe ('')
        }
        return '';
    }
}
