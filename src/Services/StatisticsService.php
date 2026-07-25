<?php

/** StatisticsService — Couche métier pour les statistiques. */

namespace App\Services;

use App\Repository\StatsRepository;

class StatisticsService
{
    public function __construct(
        private readonly StatsRepository $statsRepo,
    ) {}

    /**
     * Get available years for statistics filtering.
     *
     * @return list<string>
     */
    public function getAvailableYears(): array
    {
        $years = $this->statsRepo->getAvailableYears();
        if (empty($years)) {
            return [date('Y')];
        }
        return $years;
    }

    /**
     * Get all statistics for a given year.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(string $year): array
    {
        return [
            'indicateurs' => $this->statsRepo->getIndicateurs($year),
            'statsBySite' => $this->statsRepo->getBySite($year),
            'ramiStats' => $this->statsRepo->getRamiStructuredStats($year),
        ];
    }
}
