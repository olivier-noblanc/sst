<?php

/**
 * Lazy Cron — Application SST DREETS BFC
 *
 * Pas de cron système. Les tâches de maintenance sont déclenchées
 * paresseusement (lazy) lors de la connexion d'un utilisateur.
 *
 * Fonctionnement :
 *   - Au login (IIS auto-auth ou formulaire dev), runLazyCron() est appelé.
 *   - Chaque tâche vérifie son dernier timestamp d'exécution dans config_app.
 *   - Si le délai minimum est écoulé, la tâche s'exécute.
 *   - Si le délai n'est pas écoulé, la tâche est ignorée (zéro I/O inutile).
 *   - Les erreurs sont silencieuses : le lazy cron ne doit JAMAIS bloquer l'appli.
 *
 * Tâches planifiées :
 *   1. check_delays   — Alerte superviseurs si signalements en retard
 *                        (remplace le cron recommandé dans check_delays.php)
 *   2. anonymize      — Anonymisation RGPD des signalements anciens
 *                        (remplace l'exécution manuelle de anonymize_old_reports.php)
 *   3. cleanup        — Purge des invitations agent expirées
 *                        (report_agent_invites, jamais nettoyée auparavant)
 *   4. session_gc     — Purge des sessions expirées (>24h)
 *                        (garantit un nettoyage quotidien, au-delà du GC probabiliste)
 *   5. audit_purge    — Purge des logs d'audit > 180 jours
 *   6. access_purge   — Purge des logs de consultation > 2 ans
 *
 * Les outils CLI (tools/check_delays.php, tools/anonymize_old_reports.php)
 * restent disponibles pour les dry-run et l'exécution manuelle.
 */

require_once __DIR__ . '/cron_anonymize.php';
require_once __DIR__ . '/cron_cleanup.php';

/**
 * Run all lazy cron tasks that are due.
 * Called once per login, never on every page load.
 *
 * @param PDO $pdo  Database connection
 */
function runLazyCron(PDO $pdo): void
{
    // Check delays: run every 24 hours minimum
    runLazyCronTask($pdo, 'check_delays', 24 * 3600, 'lazyCronCheckDelays');

    // Anonymize old reports: run every 7 days minimum
    runLazyCronTask($pdo, 'anonymize', 7 * 24 * 3600, 'lazyCronAnonymize');

    // Purge old agent invites: run every 7 days minimum
    runLazyCronTask($pdo, 'cleanup', 7 * 24 * 3600, 'lazyCronCleanup');

    // Purge expired sessions: run every 24 hours minimum
    runLazyCronTask($pdo, 'session_gc', 24 * 3600, 'lazyCronPurgeSessions');

    // Purge old audit log entries: run every 7 days minimum
    runLazyCronTask($pdo, 'audit_purge', 7 * 24 * 3600, 'lazyCronPurgeAuditLog');

    // Purge old access log entries: run every 7 days minimum
    runLazyCronTask($pdo, 'access_purge', 7 * 24 * 3600, 'lazyCronPurgeAccessLog');
}

/**
 * Run a single lazy cron task if its minimum interval has elapsed.
 *
 * @param PDO      $pdo        Database connection
 * @param string   $taskName   Task identifier (used as config key prefix)
 * @param int      $minInterval Minimum seconds between runs
 * @param callable $callback    The task function to execute
 */
function runLazyCronTask(PDO $pdo, string $taskName, int $minInterval, callable $callback): void
{
    try {
        $lastRun = getConfig("last_lazy_cron_{$taskName}", '');
        $now = time();

        if (!empty($lastRun)) {
            $lastTs = strtotime($lastRun);
            if ($lastTs !== false && ($now - $lastTs) < $minInterval) {
                // Not due yet — skip
                return;
            }
        }

        // Mark as running immediately (prevents concurrent runs from another login)
        updateConfig($pdo, "last_lazy_cron_{$taskName}", date('Y-m-d H:i:s', $now));

        // Execute the task
        $callback($pdo);
    } catch (Exception $e) {
        // Lazy cron failures must NEVER break the application
        error_log("[SST-CRON] Lazy cron task '{$taskName}' failed: " . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Task 1: Check Delayed Reports
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check for reports that have been in "nouveau" state for too long
 * and send email alerts to supervisors.
 *
 * This is the web equivalent of tools/check_delays.php.
 * Runs silently — no output, errors are logged only.
 *
 * @param PDO $pdo  Database connection
 */
function lazyCronCheckDelays(PDO $pdo): void
{
    $alertDelayDays = (int) getConfig('app_alert_delay_days', '0');

    // If alert delay is disabled, skip entirely
    if ($alertDelayDays <= 0) {
        return;
    }

    // Find overdue reports
    $cutoffTs = strtotime("-{$alertDelayDays} days");
    if ($cutoffTs === false) {
        return;
    }
    $cutoffDate = gmdate('Y-m-d H:i:s', $cutoffTs);

    $sql = "SELECT r.uuid, r.reference, r.type, r.objet, r.created_at,
                   r.site_id, s.code as site_code, s.nom as site_nom,
                   d.nom as declarant_nom, d.prenom as declarant_prenom
            FROM reports r
            LEFT JOIN sites s ON r.site_id = s.id
            LEFT JOIN users d ON r.declarant_id = d.id
            WHERE r.etat = '" . ETAT_NOUVEAU . "'
              AND r.created_at < :cutoff_date
            ORDER BY r.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cutoff_date' => $cutoffDate]);
    $overdueReports = $stmt->fetchAll();

    if (empty($overdueReports)) {
        return; // Nothing to report
    }

    // Group by site for notification
    $bySite = [];
    foreach ($overdueReports as $report) {
        /** @var int */
        $siteId = $report['site_id'] ?? 0;
        if (!isset($bySite[$siteId])) {
            $bySite[$siteId] = [
                'site_code' => $report['site_code'],
                'site_nom'  => $report['site_nom'],
                'reports'   => [],
            ];
        }
        $bySite[$siteId]['reports'][] = $report;
    }

    // Send email alerts
    require_once __DIR__ . '/mail.php';

    $emailsSent = 0;
    $errors = 0;

    foreach ($bySite as $siteId => $siteData) {
        $recipients = getNotificationRecipients($pdo, $siteId);

        if (empty($recipients)) {
            continue;
        }

        $appName = getConfig('app_nom_organisation', 'DREETS BFC');
        $subject = "Alerte : {$siteData['site_code']} — " . count($siteData['reports']) . " signalement(s) en attente depuis plus de {$alertDelayDays}j";

        $body = buildDelayAlertEmail($siteData, $alertDelayDays);

        foreach ($recipients as $email) {
            try {
                $result = sendMail($email, $subject, $body);
                if ($result) {
                    $emailsSent++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                $errors++;
                error_log("[SST-CRON] check_delays: failed to send to $email — " . $e->getMessage());
            }
        }
    }

    // Log to audit_log
    require_once __DIR__ . '/audit.php';
    $details = "Lazy cron — Vérification délai : {$alertDelayDays}j — " . count($overdueReports) . " signalement(s) en retard, {$emailsSent} e-mail(s) envoyé(s)";
    if ($errors > 0) {
        $details .= " — {$errors} erreur(s)";
    }

    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, username, category, action, details, context, ip_address)
        VALUES (NULL, 'system', 'report', 'delay_check', :details, :context, 'lazy-cron')
    ");
    $stmt->execute([
        ':details' => $details,
        ':context' => json_encode([
            'source'           => 'lazy_cron',
            'alert_delay_days' => $alertDelayDays,
            'overdue_count'    => count($overdueReports),
            'emails_sent'      => $emailsSent,
            'error_count'      => $errors,
        ]),
    ]);
}
