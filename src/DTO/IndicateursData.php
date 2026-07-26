<?php

namespace App\DTO;

class IndicateursData
{
    public function __construct(
        public readonly int $totalReports,
        public readonly int $totalNouveau,
        public readonly int $totalEnCours,
        public readonly int $totalTraite,
        // Audit #67 — totalAbandonne et totalReouvert étaient calculés en SQL
        // mais jamais exposés dans le DTO. Ajoutés pour que la couche présentation
        // puisse les afficher (ex: page statistiques, indicateurs).
        public readonly int $totalAbandonne = 0,
        public readonly int $totalReouvert = 0,
        /** @var array<string, int> Dynamic per-registry totals, e.g. ['total_rsst' => 5, 'total_rami' => 3] */
        public readonly array $registryTotals = [],
    ) {}

    public function getRegistryTotal(string $registryCode): int
    {
        return $this->registryTotals['total_' . str_replace('-', '_', $registryCode)] ?? 0;
    }
}
