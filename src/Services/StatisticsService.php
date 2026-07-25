<?php

/** StatisticsService — Couche métier pour les statistiques. */

namespace App\Services;

use App\DTO\StatisticsResult;
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

    public function getStatistics(string $year): StatisticsResult
    {
        return new StatisticsResult(
            indicateurs: $this->statsRepo->getIndicateurs($year),
            statsBySite: $this->statsRepo->getBySite($year),
            ramiStats: $this->statsRepo->getRamiStructuredStats($year),
        );
    }
}
