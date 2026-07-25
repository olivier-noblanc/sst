<?php

namespace App\DTO;

/**
 * @deprecated Dead code — RegistryCardService builds arrays, not this DTO.
 */
class RegistryCard
{
    /** @phpstan-ignore shipmonk.deadMethod */
    public function __construct(
        /** @phpstan-ignore shipmonk.deadProperty.neverRead */
        public readonly string $type,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead */
        public readonly string $title,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead */
        public readonly string $subtitle,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead */
        public readonly string $desc,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead */
        public readonly int $count,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead */
        public readonly string $btnLabel,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead */
        public readonly string $btnUrl,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead */
        public readonly string $listUrl,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead */
        public readonly string $listLabel,
    ) {}
}
