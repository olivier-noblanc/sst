<?php

/**
 * CronService — Gère les tâches cron paresseuses (lazy cron).
 *
 * Encapsule la logique de verrouillage atomique et d'exécution des tâches
 * de maintenance déclenchées au login.
 */

namespace App\Services;

use App\Repository\ConfigRepository;
use App\Repository\AuditRepository;
use App\Repository\ReportRepository;
use App\Repository\SessionRepository;
use PDO;
use Throwable;

class CronService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ConfigRepository $configRepo,
        private readonly ReportRepository $reportRepo,
        private readonly AuditRepository $auditRepo,
        private readonly SessionRepository $sessionRepo
    ) {}

    /**
     * Exécute toutes les tâches cron lazy.
     */
    public function runLazyCron(): void
    {
        $this->runLazyCronTask('check_delays', 24 * 3600, fn() => $this->checkDelays());
        $this->runLazyCronTask('anonymize', 7 * 24 * 3600, fn() => $this->anonymize());
        $this->runLazyCronTask('cleanup', 7 * 24 * 3600, fn() => $this->cleanup());
        $this->runLazyCronTask('session_gc', 24 * 3600, fn() => $this->purgeSessions());
        $this->runLazyCronTask('audit_purge', 7 * 24 * 3600, fn() => $this->purgeAuditLog());
        $this->runLazyCronTask('access_purge', 7 * 24 * 3600, fn() => $this->purgeAccessLog());
    }

    /**
     * Exécute une tâche cron avec verrouillage atomique.
     *
     * @param string   $taskName    Nom de la tâche (clé config)
     * @param int      $minInterval Délai minimum entre exécutions (secondes)
     * @param callable $callback    Fonction à exécuter si le verrou est acquis
     */
    private function runLazyCronTask(string $taskName, int $minInterval, callable $callback): void
    {
        try {
            if (!$this->configRepo->claimLazyCronLock("last_lazy_cron_{$taskName}", $minInterval)) {
                return; // Another caller already claimed it
            }

            $callback();
        } catch (Throwable $e) {
            // @silent-ok: lazy-cron dispatcher — one task failing must not stop the others
            error_log("[SST-CRON] Lazy cron task '{$taskName}' failed: " . $e->getMessage());
        }
    }

    /**
     * Tâche 1 : Vérifie les signalements en retard et alerte les superviseurs.
     */
    private function checkDelays(): void
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

        $overdueReports = $this->reportRepo->findOverdue($cutoffDate);
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

        require_once __DIR__ . '/../mail.php';

        $emailsSent = 0;
        $errors = 0;

        foreach ($bySite as $siteId => $siteData) {
            $recipients = getNotificationRecipients($this->pdo, $siteId);
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
                } catch (Throwable $e) {
                    // @silent-ok: per-recipient email failure in a batch
                    $errors++;
                    error_log("[SST-CRON] check_delays: failed to send to $email — " . $e->getMessage());
                }
            }
        }

        $details = "Lazy cron — Vérification délai : {$alertDelayDays}j — " . count($overdueReports) . " signalement(s) en retard, {$emailsSent} e-mail(s) envoyé(s)";
        if ($errors > 0) {
            $details .= " — {$errors} erreur(s)";
        }

        $this->auditRepo->log(
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

    /**
     * Tâche 2 : Anonymisation RGPD des signalements anciens.
     */
    private function anonymize(): void
    {
        require_once __DIR__ . '/cron_anonymize.php';
        lazyCronAnonymize($this->pdo);
    }

    /**
     * Tâche 3 : Purge des invitations agent expirées.
     */
    private function cleanup(): void
    {
        require_once __DIR__ . '/cron_cleanup.php';
        lazyCronCleanup($this->pdo);
    }

    /**
     * Tâche 4 : Purge des sessions expirées (>24h).
     */
    private function purgeSessions(): void
    {
        $count = $this->sessionRepo->purgeExpired(24 * 3600);

        if ($count > 0) {
            $this->auditRepo->log(
                category: 'session',
                action: 'gc',
                details: "Lazy cron — Purge sessions : {$count} session(s) supprimée(s)",
                context: ['source' => 'lazy_cron', 'sessions_deleted' => $count],
            );
        }
    }

    /**
     * Tâche 5 : Purge des logs d'audit > 180 jours.
     */
    private function purgeAuditLog(): void
    {
        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-180 days'));
        $count = $this->auditRepo->purgeOlderThan($cutoff);

        if ($count > 0) {
            $this->auditRepo->log(
                category: 'audit',
                action: 'purge',
                details: "Lazy cron — Purge audit : {$count} entrée(s) supprimée(s)",
                context: ['source' => 'lazy_cron', 'audit_entries_deleted' => $count],
            );
        }
    }

    /**
     * Tâche 6 : Purge des logs de consultation > 2 ans.
     */
    private function purgeAccessLog(): void
    {
        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-2 years'));
        $count = $this->auditRepo->purgeAccessLogOlderThan($cutoff);

        if ($count > 0) {
            $this->auditRepo->log(
                category: 'access',
                action: 'purge',
                details: "Lazy cron — Purge accès : {$count} entrée(s) supprimée(s)",
                context: ['source' => 'lazy_cron', 'access_entries_deleted' => $count],
            );
        }
    }
}
