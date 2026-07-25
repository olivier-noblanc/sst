<?php

namespace App\DTO;

class ReportStateCounts
{
    public function __construct(
        /** @phpstan-ignore shipmonk.deadProperty.neverRead (consumed by tests) */
        public readonly int $nouveau,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead (consumed by tests) */
        public readonly int $enCours,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead (consumed by tests) */
        public readonly int $traite,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead (consumed by tests) */
        public readonly int $reouvert,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead (consumed by tests) */
        public readonly int $total,
    ) {}
}
