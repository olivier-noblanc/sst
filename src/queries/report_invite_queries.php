<?php

/**
 * Report Invite Queries — Application SST DREETS BFC
 *
 * Functions for managing agent invitation tokens (report_agent_invites).
 * Split from report_queries.php for readability (max 250 lines per file).
 *
 * All functions delegate to App\Repository\ReportRepository.
 */

use App\Repository\ReportRepository;

/**
 * Create an invitation for an agent to be linked to a report.
 * Returns the generated token.
 */
function createAgentInvite(PDO $pdo, string $reportUuid, string $email): string
{
    return ReportRepository::instance()->createAgentInvite($reportUuid, $email);
}

/**
 * Get an invitation by token.
 * @return array<string, mixed>|null
 */
function getAgentInviteByToken(PDO $pdo, string $token): ?array
{
    return ReportRepository::instance()->getAgentInviteByToken($token);
}

/**
 * Confirm an agent invitation. Links the agent to the report.
 * Returns true on success.
 */
function confirmAgentInvite(PDO $pdo, string $token, int $userId): bool
{
    return ReportRepository::instance()->confirmAgentInvite($token, $userId);
}

/**
 * Get pending (unconfirmed) invitations for a report.
 * @return list<array{email: string, created_at: string}>
 */
function getPendingInvites(PDO $pdo, string $reportUuid): array
{
    return ReportRepository::instance()->getPendingInvites($reportUuid);
}
