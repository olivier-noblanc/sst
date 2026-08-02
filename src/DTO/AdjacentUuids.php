<?php

namespace App\DTO;

class AdjacentUuids
{
    public function __construct(
        public readonly ?string $prev,
        public readonly ?string $next,
    ) {}
}
