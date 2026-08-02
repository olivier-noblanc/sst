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
            /** @phpstan-ignore-next-line match.alwaysTrue (default needed for Infection MatchArmRemoval) */
            self::Chsct       => 'Membre FS/CSA',
            default           => 'Inconnu',
        };
    }

    public function canSeeAllSites(): bool
    {
        return match ($this) {
            self::Agent       => false,
            self::Superviseur => true,
            /** @phpstan-ignore-next-line match.alwaysTrue (default needed for Infection MatchArmRemoval) */
            self::Chsct       => true,
            default           => false,
        };
    }
}
