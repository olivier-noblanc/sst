<?php

/**
 * Report Agent Queries — Application SST DREETS BFC
 *
 * Functions for managing agents linked to a report (many-to-many).
 * Split from report_queries.php for readability (max 250 lines per file).
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

/**
 * Link agents to a report (adds to report_agents table).
 * Skips duplicates (UNIQUE constraint on report_uuid + user_id).
 * @param array<int> $userIds
 */
function linkAgentsToReport(PDO $pdo, string $reportUuid, array $userIds): void
{
    ReportRepository::instance()->linkAgents($reportUuid, array_values($userIds));
}

/**
 * Replace all linked agents for a report (delete + re-insert).
 * @param array<int> $userIds
 */
function replaceLinkedAgents(PDO $pdo, string $reportUuid, array $userIds): void
{
    ReportRepository::instance()->replaceLinkedAgents($reportUuid, array_values($userIds));
}
