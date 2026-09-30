<?php

namespace App\Enum;

/**
 * Champs « métier » du formulaire de signalement dont le libellé — et, pour
 * Pôle et Service d'affectation, l'activation — sont configurables PAR REGISTRE.
 *
 * Les valeurs sont les identifiants techniques (noms de champs HTML / colonnes
 * SQL). Ils ne sont JAMAIS renommés : seul le libellé affiché est personnalisable.
 */
enum ReportField: string
{
    case Pole = 'pole';
    case ServiceAffectation = 'service_affectation';
    case Objet = 'objet';

    /**
     * Libellé par défaut actuel (rétrocompatible) du champ.
     */
    public function defaultLabel(): string
    {
        return match ($this) {
            self::Pole => 'Pôle',
            self::ServiceAffectation => 'Service d\'affectation',
            self::Objet => 'Objet',
        };
    }
}