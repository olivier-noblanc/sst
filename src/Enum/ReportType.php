<?php

namespace App\Enum;

enum ReportType: string
{
    case Rsst = 'rsst';
    case Rami = 'rami';
    case Dgi  = 'dgi';

    public function label(): string
    {
        return match ($this) {
            self::Rsst => 'Registre de Santé et de Sécurité au Travail',
            self::Rami => 'Registre des Actes d\'Agressions, de Menaces et d\'Incivilités',
            self::Dgi  => 'Registre de signalement d\'un Danger Grave et Imminent',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Rsst => 'RSST',
            self::Rami => 'RAMI',
            self::Dgi  => 'DGI',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Rsst => 'badge--rsst',
            self::Rami => 'badge--rami',
            self::Dgi  => 'badge--dgi',
        };
    }

    /** @return array{int,int,int} RGB, pour le PDF */
    public function pdfColor(): array
    {
        return match ($this) {
            self::Rsst => [46, 92, 138],
            self::Rami => [108, 108, 108],
            self::Dgi  => [178, 34, 34],
        };
    }

    public function legalNote(): string
    {
        return match ($this) {
            self::Rsst => 'Décret n° 82-453 art. 3-2 : registre consultable par tout agent. La transparence est recommandée.',
            self::Rami => 'Données sensibles (art. 9 RGPD) : le mode confidentiel ou choix de l\'agent est recommandé.',
            self::Dgi  => 'Articles L4131-1 et D4132-1 du Code du travail : le formalisme du registre spécial peut justifier un mode restrictif.',
        };
    }
}
