<?php

namespace App\DTO;

class SynthesisRow
{
    public function __construct(
        public readonly int $siteId,
        public readonly string $type,
        public readonly int $nouveau,
        public readonly int $enCours,
        public readonly int $traite,
        public readonly int $abandonne,
        public readonly int $reouvert,
        public readonly int $total,
    ) {}
}
