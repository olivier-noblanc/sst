<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Audit #85 — remplace la string libre ('ok'/'concurrent'/'error') que
 * ReportRepository::respondToReport() retournait. Une string libre pour un
 * résultat fini est un enum déguisé sans les garanties d'un enum : rien
 * n'empêchait ReportService::respond() de comparer à 'success' (qui n'a
 * jamais existé) au lieu de 'ok' — l'event report.responded (et toute
 * notification qui en dépend) ne se déclenchait donc jamais, silencieusement,
 * sans qu'aucun outil ne le voie. Avec un enum, ce genre de faute de frappe
 * ne compile simplement pas.
 */
enum RespondStatus: string
{
    case Ok = 'ok';
    case Concurrent = 'concurrent';
    case Error = 'error';
}
