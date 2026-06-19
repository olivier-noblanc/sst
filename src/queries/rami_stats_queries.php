<?php

/**
 * RAMI Statistics Queries — Application SST DREETS BFC
 *
 * RAMI-specific statistics queries.
 * Split from stats_queries.php to keep file size under 250 lines.
 */

/**
 * Get RAMI statistics by nature_auteur and type_acte.
 * Returns counts grouped by nature_auteur and type_acte for RAMI reports.
 *
 * @param PDO    $pdo     Database connection
 * @param string $year    Year filter
 * @return array{by_nature_auteur: array<int, mixed>, by_type_acte: array<int, mixed>}  ['by_nature_auteur' => [...], 'by_type_acte' => [...]]
 */
function getRamiStructuredStats(PDO $pdo, string $year = ''): array
{
    $params = [];
    $yearFilter = '';
    if (!empty($year)) {
        $yearFilter = ' AND created_at >= :year_start AND created_at < :year_next';
        $params[':year_start'] = $year . '-01-01 00:00:00';
        $params[':year_next'] = ((int)$year + 1) . '-01-01 00:00:00';
    }

    // By nature_auteur
    $sqlNature = "SELECT nature_auteur, COUNT(*) as count
        FROM reports
        WHERE type = 'rami' AND nature_auteur IS NOT NULL AND nature_auteur != ''{$yearFilter}
        GROUP BY nature_auteur
        ORDER BY count DESC";
    $stmt = $pdo->prepare($sqlNature);
    $stmt->execute($params);
    $byNature = $stmt->fetchAll();

    // By type_acte
    $sqlType = "SELECT type_acte, COUNT(*) as count
        FROM reports
        WHERE type = 'rami' AND type_acte IS NOT NULL AND type_acte != ''{$yearFilter}
        GROUP BY type_acte
        ORDER BY count DESC";
    $stmt = $pdo->prepare($sqlType);
    $stmt->execute($params);
    $byType = $stmt->fetchAll();

    return ['by_nature_auteur' => $byNature, 'by_type_acte' => $byType];
}
