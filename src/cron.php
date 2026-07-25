<?php

use App\Repository\ReportRepository;
use App\Repository\AuditRepository;

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
 *   2. anonymize      — Anonymisation RGPD des signalements anciens
 *   3. cleanup        — Purge des invitations agent expirées
 *   4. session_gc     — Purge des sessions expirées (>24h)
 *   5. audit_purge    — Purge des logs d'audit > 180 jours
 *   6. access_purge   — Purge des logs de consultation > 2 ans
 */

require_once __DIR__ . '/cron_anonymize.php';
require_once __DIR__ . '/cron_cleanup.php';

function runLazyCron(PDO $pdo): void
{
    runLazyCronTask($pdo, 'check_delays', 24 * 3600, 'lazyCronCheckDelays');
    runLazyCronTask($pdo, 'anonymize', 7 * 24 * 3600, 'lazyCronAnonymize');
    runLazyCronTask($pdo, 'cleanup', 7 * 24 * 3600, 'lazyCronCleanup');
    runLazyCronTask($pdo, 'session_gc', 24 * 3600, 'lazyCronPurgeSessions');
    runLazyCronTask($pdo, 'audit_purge', 7 * 24 * 3600, 'lazyCronPurgeAuditLog');
    runLazyCronTask($pdo, 'access_purge', 7 * 24 * 3600, 'lazyCronPurgeAccessLog');
}

function runLazyCronTask(PDO $pdo, string $taskName, int $minInterval, callable $callback): void
{
    try {
        // Audit #41 — atomic compare-and-swap lock. Before this fix,
        // two concurrent logins could both read lastRun=old → both write
        // new timestamp → both execute the task (double email, double
        // anonymization, etc.). Now claimLazyCronLock does the read+write
        // inside a transaction, so only one caller acquires the lock.
        $configRepo = \App\Repository\ConfigRepository::instance();
        if (!$configRepo->claimLazyCronLock("last_lazy_cron_{$taskName}", $minInterval)) {
            return; // Another caller already claimed it
        }

        $callback($pdo);
    } catch (Exception $e) {
        error_log("[SST-CRON] Lazy cron task '{$taskName}' failed: " . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Task 1: Check Delayed Reports
// ─────────────────────────────────────────────────────────────────────────────

function lazyCronCheckDelays(PDO $pdo): void
{
    $alertDelayDays = (int) getConfigService()->get('app_alert_delay_days', '0');
    if ($alertDelayDays <= 0) {
        return;
    }

    $cutoffTs = strtotime("-{$alertDelayDays} days");
    if ($cutoffTs === false) {
        return;
    }
    $cutoffDate = gmdate('Y-m-d H:i:s', $cutoffTs);

    $overdueReports = ReportRepository::instance()->findOverdue($cutoffDate);
    if (empty($overdueReports)) {
        return;
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

    require_once __DIR__ . '/mail.php';

    $emailsSent = 0;
    $errors = 0;

    foreach ($bySite as $siteId => $siteData) {
        $recipients = getNotificationRecipients($pdo, $siteId);
        if (empty($recipients)) {
            continue;
        }

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

    $details = "Lazy cron — Vérification délai : {$alertDelayDays}j — " . count($overdueReports) . " signalement(s) en retard, {$emailsSent} e-mail(s) envoyé(s)";
    if ($errors > 0) {
        $details .= " — {$errors} erreur(s)";
    }

    AuditRepository::instance()->log(
        category: 'report',
        action: 'delay_check',
        details: $details,
        context: [
            'source'           => 'lazy_cron',
            'alert_delay_days' => $alertDelayDays,
            'overdue_count'    => count($overdueReports),
            'emails_sent'      => $emailsSent,
            'error_count'      => $errors,
        ],
    );
}
