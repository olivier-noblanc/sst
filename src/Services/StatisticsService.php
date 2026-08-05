<?php

/** StatisticsService — Couche métier pour les statistiques. */

namespace App\Services;

use App\DTO\StatisticsResult;
use App\Enum\ReportType;
use App\Repository\StatsRepository;

class StatisticsService
{
    public function __construct(
        private readonly StatsRepository $statsRepo,
    ) {}

    // Bug #66 — Cache statistics within a single request to avoid 3 SQL
    // queries per page load. The /statistics page calls getStatistics() once,
    // but if other components also need stats, they share the cache.
    /** @var array<string, StatisticsResult> */
    private array $cache = [];

    /**
     * Get available years for statistics filtering.
     *
     * @return list<string>
     */
    public function getAvailableYears(): array
    {
        static $yearsCache = null;
        if ($yearsCache === null) {
            $yearsCache = $this->statsRepo->getAvailableYears();
            if (empty($yearsCache)) {
                $yearsCache = [date('Y')];
            }
        }
        return $yearsCache;
    }

    public function getStatistics(string $year): StatisticsResult
    {
        if (isset($this->cache[$year])) {
            return $this->cache[$year];
        }
        $result = new StatisticsResult(
            indicateurs: $this->statsRepo->getIndicateurs($year),
            statsBySite: $this->statsRepo->getBySite($year),
            ramiStats: $this->statsRepo->getStructuredStatsForRegistry(ReportType::Rami->value, $year),
        );
        $this->cache[$year] = $result;
        return $result;
    }
}
