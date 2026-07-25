<?php

namespace App\DTO;

class PaginatedReports
{
    /**
     * @param list<ReportListItem> $reports
     */
    public function __construct(
        public readonly array $reports,
        public readonly int $total,
    ) {}
}
