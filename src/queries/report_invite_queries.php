<?php

/**
 * Report Invite Queries — Application SST DREETS BFC
 *
 * Functions for managing agent invitation tokens (report_agent_invites).
 * Split from report_queries.php for readability (max 250 lines per file).
 */

// ============================================================
// Agent invitations (report_agent_invites)
// ============================================================

/**
 * Create an invitation for an agent to be linked to a report.
 * Returns the generated token.
 */
function createAgentInvite(PDO $pdo, string $reportUuid, string $email): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('
        INSERT INTO report_agent_invites (report_uuid, email, token)
        VALUES (:uuid, :email, :token)
    ');
    $stmt->execute([':uuid' => $reportUuid, ':email' => $email, ':token' => $token]);
    return $token;
}

/**
 * Get an invitation by token.
 * @return array|null
 */
function getAgentInviteByToken(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare('
        SELECT * FROM report_agent_invites WHERE token = ? AND confirmed = 0
    ');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Confirm an agent invitation. Links the agent to the report.
 * Returns true on success.
 */
function confirmAgentInvite(PDO $pdo, string $token, int $userId): bool
{
    $invite = getAgentInviteByToken($pdo, $token);
    if (!$invite) {
        return false;
    }
    // Mark invite as confirmed
    $stmt = $pdo->prepare("
        UPDATE report_agent_invites
        SET confirmed = 1, confirmed_at = datetime('now')
        WHERE token = ?
    ");
    $stmt->execute([$token]);
    // Link agent to report
    linkAgentsToReport($pdo, $invite['report_uuid'], [$userId]);
    return true;
}

/**
 * Get pending (unconfirmed) invitations for a report.
 * @return array
 */
function getPendingInvites(PDO $pdo, string $reportUuid): array
{
    $stmt = $pdo->prepare('
        SELECT email, created_at FROM report_agent_invites
        WHERE report_uuid = ? AND confirmed = 0
        ORDER BY created_at
    ');
    $stmt->execute([$reportUuid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
