<?php

/**
 * Lazy Cron — Cleanup Task — Application SST DREETS BFC
 *
 * Purges old report_agent_invites rows — nothing did this before, so the
 * table only ever grew (one row per invite sent, forever). Split from
 * cron.php to keep file size under 250 lines, same convention as
 * cron_anonymize.php.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Task 3: Purge Old Agent Invites
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Delete report_agent_invites rows that no longer serve any purpose:
 *   - confirmed ones older than 90 days — once confirmed, the actual link
 *     lives in report_agents; the invite row (and its token) has nothing
 *     left to do.
 *   - unconfirmed ones older than 30 days — nobody clicked the link in a
 *     month, the invite is abandoned. The token is also a live secret
 *     while unconfirmed (see agent_confirm), so there's a security
 *     argument for not keeping stale ones around indefinitely either.
 *
 * @param PDO $pdo  Database connection
 */
function lazyCronCleanup(PDO $pdo): void
{
    $confirmedCutoff = gmdate('Y-m-d H:i:s', strtotime('-90 days'));
    $unconfirmedCutoff = gmdate('Y-m-d H:i:s', strtotime('-30 days'));

    $stmt = $pdo->prepare("
        DELETE FROM report_agent_invites
        WHERE (confirmed = 1 AND confirmed_at IS NOT NULL AND confirmed_at < :confirmed_cutoff)
           OR (confirmed = 0 AND created_at < :unconfirmed_cutoff)
    ");
    $stmt->execute([
        ':confirmed_cutoff' => $confirmedCutoff,
        ':unconfirmed_cutoff' => $unconfirmedCutoff,
    ]);
    $deleted = $stmt->rowCount();

    if ($deleted === 0) {
        return; // Nothing to clean up — no audit noise for a no-op
    }

    require_once __DIR__ . '/audit.php';
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, username, category, action, details, context, ip_address)
        VALUES (NULL, 'system', 'report', 'cleanup', :details, :context, 'lazy-cron')
    ");
    $stmt->execute([
        ':details' => "Lazy cron — {$deleted} invitation(s) agent expirée(s) purgée(s)",
        ':context' => json_encode([
            'source'  => 'lazy_cron',
            'deleted' => $deleted,
        ]),
    ]);
}
