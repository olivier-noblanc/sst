<?php

/**
 * Report Agent Queries — Application SST DREETS BFC
 *
 * getLinkedAgents() is still called from production (report display).
 * linkAgentsToReport() and replaceLinkedAgents() were removed as dead
 * code: no callers anywhere, prod or tests.
 *
 * All functions delegate to App\Repository\ReportRepository.
 */

use App\Repository\ReportRepository;

/**
 * Get all agents linked to a report.
 * @return array<int, array{id: int, nom: string, prenom: string, email: string}>
 */
function getLinkedAgents(PDO $pdo, string $reportUuid): array
{
    return ReportRepository::instance()->getLinkedAgents($reportUuid);
}
