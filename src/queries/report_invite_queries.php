<?php

/**
 * Report Invite Queries — Application SST DREETS BFC
 *
 * createAgentInvite() is still called from production
 * (src/mail_notifications.php). getAgentInviteByToken(),
 * confirmAgentInvite() and getPendingInvites() were removed as dead
 * code: no callers anywhere, prod or tests (the invite-confirmation
 * flow appears to call App\Repository\ReportRepository directly where
 * it's actually wired up, not through these wrappers).
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
