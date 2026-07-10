<?php

/**
 * RAMI Statistics Queries — Application SST DREETS BFC
 *
 * RAMI-specific statistics queries.
 * Split from stats_queries.php to keep file size under 250 lines.
 *
 * All functions delegate to App\Repository\StatsRepository.
 */

use App\Repository\StatsRepository;

/**
 * Get RAMI statistics by nature_auteur and type_acte.
 * Returns counts grouped by nature_auteur and type_acte for RAMI reports.
 *
 * @param PDO    $pdo     Database connection
 * @param string $year    Year filter
 * @return array{by_nature_auteur: array<int, mixed>, by_type_acte: array<int, mixed>}
 */
function getRamiStructuredStats(PDO $pdo, string $year = ''): array
{
    return StatsRepository::instance()->getRamiStructuredStats($year);
}
