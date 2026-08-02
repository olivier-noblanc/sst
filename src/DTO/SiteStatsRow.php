<?php

namespace App\DTO;

class SiteStatsRow
{
    public function __construct(
        public readonly string $code,
        public readonly int $total,
        /** @var array<string, int> Dynamic per-registry counts, e.g. ['rsst' => 5, 'rami' => 3] */
        public readonly array $registryCounts,
    ) {}

    public function getCount(string $registryCode): int
    {
        return $this->registryCounts[$registryCode] ?? 0;
    }
}
