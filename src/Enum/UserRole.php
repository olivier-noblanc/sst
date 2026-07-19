<?php

namespace App\Enum;

enum UserRole: string
{
    case Agent       = 'agent';
    case Superviseur = 'superviseur';
    case Chsct       = 'chsct';

    /** Libellé par défaut — PAS le libellé effectif, voir ConfigService::getRoleLabel() */
    public function defaultLabel(): string
    {
        return match ($this) {
            self::Agent       => 'Agent',
            self::Superviseur => 'Superviseur',
            self::Chsct       => 'Membre FS/CSA',
        };
    }

    public function canSeeAllSites(): bool
    {
        return match ($this) {
            self::Agent       => false,
            self::Superviseur => true,
            self::Chsct       => true,
        };
    }
}
