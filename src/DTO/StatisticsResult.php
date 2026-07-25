<?php

namespace App\DTO;

class StatisticsResult
{
    public function __construct(
        public readonly IndicateursData $indicateurs,
        /** @var list<SiteStatsRow> */
        public readonly array $statsBySite,
        public readonly RamiStats $ramiStats,
    ) {}
}
