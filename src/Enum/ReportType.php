<?php

namespace App\Enum;

enum ReportType: string
{
    case Rsst = 'rsst';
    case Rami = 'rami';
    case Dgi  = 'dgi';

    /**
     * Modular-audit P3.1 — Safe factory that returns null for custom registry codes.
     *
     * ReportType::from() throws ValueError on unknown values (e.g. custom registry
     * codes like 'violences', 'harassment'). This method returns null instead,
     * allowing callers to gracefully fall back to DB-driven configuration.
     *
     * @deprecated Use tryFrom() directly in new code — this method is kept for
     *             backwards compatibility and semantic clarity.
     */
    public static function fromCode(string $code): ?self
    {
        return self::tryFrom($code);
    }

    public function label(): string
    {
        return match ($this) {
            self::Rsst => 'Santé et Sécurité au Travail',
            self::Rami => 'Agressions, Menaces et Incivilités',
            /** @phpstan-ignore-next-line match.alwaysTrue (default needed for Infection MatchArmRemoval) */
            self::Dgi  => 'Danger Grave et Imminent',
            default    => 'Inconnu',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Rsst => 'RSST',
            self::Rami => 'RAMI',
            /** @phpstan-ignore-next-line match.alwaysTrue (default needed for Infection MatchArmRemoval) */
            self::Dgi  => 'DGI',
            default    => '',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Rsst => 'badge--rsst',
            self::Rami => 'badge--rami',
            /** @phpstan-ignore-next-line match.alwaysTrue (default needed for Infection MatchArmRemoval) */
            self::Dgi  => 'badge--dgi',
            default    => '',
        };
    }

    /** @return array{int,int,int} RGB, pour le PDF */
    public function pdfColor(): array
    {
        return match ($this) {
            self::Rsst => [46, 92, 138],
            self::Rami => [108, 108, 108],
            /** @phpstan-ignore-next-line match.alwaysTrue (default needed for Infection MatchArmRemoval) */
            self::Dgi  => [178, 34, 34],
            default    => [0, 0, 0],
        };
    }

    public function legalNote(): string
    {
        return match ($this) {
            self::Rsst => 'Décret n° 82-453 art. 3-2 : registre consultable par tout agent. La transparence est recommandée.',
            self::Rami => 'Données sensibles (art. 9 RGPD) : le mode confidentiel ou choix de l\'agent est recommandé.',
            /** @phpstan-ignore-next-line match.alwaysTrue (default needed for Infection MatchArmRemoval) */
            self::Dgi  => 'Articles L4131-1 et D4132-1 du Code du travail : le formalisme du registre spécial peut justifier un mode restrictif.',
            default    => '',
        };
    }
}
