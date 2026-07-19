<?php

namespace App\Enum;

enum ReportState: string
{
    case Nouveau   = 'nouveau';
    case EnCours   = 'en_cours';
    case Traite    = 'traite';
    case Reouvert  = 'reouvert';
    case Abandonne = 'abandonne';

    public function label(): string
    {
        return match ($this) {
            self::Nouveau   => 'Nouveau',
            self::EnCours   => 'En cours',
            self::Traite    => 'Traité',
            self::Reouvert  => 'Réouvert',
            self::Abandonne => 'Abandonné',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Nouveau   => 'badge--nouveau',
            self::EnCours   => 'badge--en-cours',
            self::Traite    => 'badge--traite',
            self::Reouvert  => 'badge--reouvert',
            self::Abandonne => 'badge--abandonne',
        };
    }

    /** @return array{int,int,int} RGB, pour le PDF (report_print_helpers.php) */
    public function pdfColor(): array
    {
        return match ($this) {
            self::Nouveau   => [46, 92, 138],
            self::EnCours   => [230, 126, 34],
            self::Traite    => [39, 174, 96],
            self::Reouvert  => [142, 68, 173],
            self::Abandonne => [149, 165, 166],
        };
    }
}
