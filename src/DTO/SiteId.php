<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Value object pour site_id — centralise la conversion 0 (sentinel côté
 * entrée : formulaires, commandes) / NULL (vérité DB : mode sans site) en
 * un seul endroit, documentée dans AGENTS.md.
 *
 * Câblé dans UpdateUserCommand, CreateUserCommand, CreateReportCommand
 * et les repositories (UserRepository, ReportRepository).
 */
final readonly class SiteId
{
    private function __construct(private ?int $value) {}

    /**
     * Depuis une entrée applicative (formulaire, commande) où 0 signifie
     * "aucun site sélectionné" — un <select> HTML ne soumet jamais null.
     */
    public static function fromInput(int $rawValue): self
    {
        return new self($rawValue > 0 ? $rawValue : null);
    }

    /**
     * Depuis une ligne de base où la colonne est réellement nullable —
     * ne jamais coercer un NULL lu en 0 ici (c'est le bug classique).
     */
    public static function fromDatabase(?int $value): self
    {
        return new self($value !== null && $value > 0 ? $value : null);
    }

    public static function none(): self
    {
        return new self(null);
    }

    public function isNone(): bool
    {
        return $this->value === null;
    }

    /** Toujours NULL ou un entier positif — jamais 0. Prêt pour un bind SQL. */
    public function toSql(): ?int
    {
        return $this->value;
    }

    /** Pour l'affichage/comparaison applicative (ex. site_id === user->siteId). */
    public function toNullableInt(): ?int
    {
        return $this->value;
    }
}
