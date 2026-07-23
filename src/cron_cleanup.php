<?php

/**
 * Lazy Cron — Cleanup Task — Application SST DREETS BFC
 *
 * Purges expired sessions and old report_agent_invites rows — nothing did
 * this before, so those tables only ever grew. Split from cron.php to
 * keep file size under 250 lines, same convention as cron_anonymize.php.
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

// ─────────────────────────────────────────────────────────────────────────────
// Task 4: Purge Expired Sessions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Delete sessions older than gc_maxlifetime (24h).
 *
 * PHP's built-in garbage collector already does this probabilistically
 * (gc_probability=1 / gc_divisor=100 ≈ 1% chance per request), but on
 * a low-traffic app that can take hundreds of requests to fire. This
 * explicit call guarantees at least one cleanup per day via the lazy cron.
 *
 * @param PDO $pdo  Database connection
 */
function lazyCronPurgeSessions(PDO $pdo): void
{
    $maxLifetime = (int) ini_get('session.gc_maxlifetime');
    if ($maxLifetime <= 0) {
        $maxLifetime = 86400; // 24h default, same as SessionService
    }

    $cutoff = time() - $maxLifetime;
    $stmt = $pdo->prepare('DELETE FROM sessions WHERE last_accessed < :cutoff');
    $stmt->execute([':cutoff' => $cutoff]);
    $deleted = $stmt->rowCount();

    if ($deleted === 0) {
        return;
    }

    require_once __DIR__ . '/audit.php';
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, username, category, action, details, context, ip_address)
        VALUES (NULL, 'system', 'session', 'gc_purge', :details, :context, 'lazy-cron')
    ");
    $stmt->execute([
        ':details' => "Lazy cron — {$deleted} session(s) expirée(s) purgée(s)",
        ':context' => json_encode([
            'source'         => 'lazy_cron',
            'deleted'        => $deleted,
            'max_lifetime'   => $maxLifetime,
        ]),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Task 5: Purge Old Audit Log
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Delete audit_log entries older than 180 days.
 *
 * The audit log is an operational trail (logins, report edits, exports...).
 * It grows unboundedly with every action. 180 days is enough for
 * debugging and post-incident review; anything older has no operational
 * value and just bloats the database.
 *
 * @param PDO $pdo  Database connection
 */
function lazyCronPurgeAuditLog(PDO $pdo): void
{
    $retentionDays = 180;
    $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

    $stmt = $pdo->prepare('DELETE FROM audit_log WHERE created_at < :cutoff');
    $stmt->execute([':cutoff' => $cutoff]);
    $deleted = $stmt->rowCount();

    if ($deleted === 0) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, username, category, action, details, context, ip_address)
        VALUES (NULL, 'system', 'maintenance', 'audit_purge', :details, :context, 'lazy-cron')
    ");
    $stmt->execute([
        ':details' => "Lazy cron — {$deleted} ligne(s) d'audit purgée(s) (> {$retentionDays}j)",
        ':context' => json_encode([
            'source'          => 'lazy_cron',
            'deleted'         => $deleted,
            'retention_days'  => $retentionDays,
        ]),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Task 6: Purge Old Access Log
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Delete report_access_log entries older than 2 years.
 *
 * This is a legal consultation trail (Code du travail). Keep 2 years
 * for compliance; entries for deleted reports are already cascade-removed.
 *
 * @param PDO $pdo  Database connection
 */
function lazyCronPurgeAccessLog(PDO $pdo): void
{
    $retentionDays = 730; // 2 years
    $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

    $stmt = $pdo->prepare('DELETE FROM report_access_log WHERE accessed_at < :cutoff');
    $stmt->execute([':cutoff' => $cutoff]);
    $deleted = $stmt->rowCount();

    if ($deleted === 0) {
        return;
    }

    require_once __DIR__ . '/audit.php';
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, username, category, action, details, context, ip_address)
        VALUES (NULL, 'system', 'maintenance', 'access_purge', :details, :context, 'lazy-cron')
    ");
    $stmt->execute([
        ':details' => "Lazy cron — {$deleted} ligne(s) de consultation purgée(s) (> {$retentionDays}j)",
        ':context' => json_encode([
            'source'          => 'lazy_cron',
            'deleted'         => $deleted,
            'retention_days'  => $retentionDays,
        ]),
    ]);
}
