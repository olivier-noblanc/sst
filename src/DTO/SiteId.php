<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Value object pour site_id — centralise la conversion 0 (sentinel côté
 * entrée : formulaires, commandes) / NULL (vérité DB : mode sans site) en
 * un seul endroit, documentée maintenant dans AGENTS.md.
 *
 * Avant ce jour, cette conversion vivait uniquement dans
 * UserRepository::update() (`!empty($data['site_id']) ? ... : null`) — tout
 * nouveau repository/handler qui écrit site_id pouvait l'oublier sans
 * qu'aucun outil ne le signale. Un futur mécanisme de détection (règle
 * PHPStan étendant NoSqlOutsideRepositoryRule) peut s'appuyer sur ce type
 * pour repérer le SQL brut qui contourne ce point de passage — mais la
 * vraie garde-fou est la contrainte CHECK (site_id IS NULL OR site_id > 0)
 * ajoutée sur users/reports/notification_settings dans schema.sql : elle
 * rend un 0 littéral impossible à persister, quel que soit ce qui l'a écrit.
 *
 * Note d'intégration : cette classe existe et est prête à l'emploi, mais
 * n'est pas encore câblée dans UpdateUserCommand/CreateReportCommand/
 * ReportData/les repositories — remplacer leurs `int $siteId`/`?int $siteId`
 * par `SiteId $siteId` est un refactor à part, plus large, à faire
 * délibérément plutôt que dans la foulée de ce commit.
 */
final class SiteId
{
    private function __construct(private readonly ?int $value)
    {
    }

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
