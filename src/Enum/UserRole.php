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
            /** @phpstan-ignore-next-line match.alwaysTrue (default for Infection MatchArmRemoval) */
            default           => 'Inconnu',
        };
    }

    public function canSeeAllSites(): bool
    {
        return match ($this) {
            self::Agent       => false,
            self::Superviseur => true,
            self::Chsct       => true,
            /** @phpstan-ignore-next-line match.alwaysTrue (default for Infection MatchArmRemoval) */
            default           => false,
        };
    }
}
