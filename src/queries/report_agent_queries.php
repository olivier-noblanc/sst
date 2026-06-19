<?php

/**
 * Report Agent Queries — Application SST DREETS BFC
 *
 * Functions for managing agents linked to a report (many-to-many).
 * Split from report_queries.php for readability (max 250 lines per file).
 */

// ============================================================
// Linked agents (report_agents)
// ============================================================

/**
 * Get all agents linked to a report.
 * @return array<int, array{id: int, nom: string, prenom: string, email: string}>
 */
function getLinkedAgents(PDO $pdo, string $reportUuid): array
{
    $stmt = $pdo->prepare("
        SELECT u.id, u.nom, u.prenom, u.email
        FROM report_agents ra
        JOIN users u ON u.id = ra.user_id
        WHERE ra.report_uuid = ?
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute([$reportUuid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Link agents to a report (adds to report_agents table).
 * Skips duplicates (UNIQUE constraint on report_uuid + user_id).
 * @param array<int> $userIds
 */
function linkAgentsToReport(PDO $pdo, string $reportUuid, array $userIds): void
{
    if (empty($userIds)) {
        return;
    }
    $stmt = $pdo->prepare("
        INSERT OR IGNORE INTO report_agents (report_uuid, user_id)
        VALUES (:uuid, :user_id)
    ");
    foreach ($userIds as $uid) {
        $uid = (int) $uid;
        if ($uid > 0) {
            $stmt->execute([':uuid' => $reportUuid, ':user_id' => $uid]);
        }
    }
}

/**
 * Replace all linked agents for a report (delete + re-insert).
 * @param array<int> $userIds
 */
function replaceLinkedAgents(PDO $pdo, string $reportUuid, array $userIds): void
{
    $pdo->prepare("DELETE FROM report_agents WHERE report_uuid = ?")->execute([$reportUuid]);
    linkAgentsToReport($pdo, $reportUuid, $userIds);
}
