<?php

namespace App\DTO;

class RamiStats
{
    /**
     * @param list<array{nature_auteur: string, count: int}> $byNatureAuteur
     * @param list<array{type_acte: string, count: int}> $byTypeActe
     */
    public function __construct(
        public readonly array $byNatureAuteur,
        public readonly array $byTypeActe,
    ) {}

    public function hasData(): bool
    {
        return !empty($this->byNatureAuteur) || !empty($this->byTypeActe);
    }
}
