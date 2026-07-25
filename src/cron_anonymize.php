<?php

use App\Services\ConfigService;
use App\Repository\ReportRepository;
use App\Repository\AuditRepository;

/**
 * Lazy Cron — Anonymize Task — Application SST DREETS BFC
 *
 * RGPD anonymization of old reports.
 */

function lazyCronAnonymize(PDO $pdo): void
{
    $retentionYears = (int) getConfigService()->get('app_retention_years', '0');
    if ($retentionYears <= 0) {
        return;
    }

    $cutoffTs = strtotime("-{$retentionYears} years");
    if ($cutoffTs === false) {
        return;
    }
    $cutoffDate = date('Y-m-d', $cutoffTs);

    $reports = ReportRepository::instance()->findAnonymizable($cutoffDate);
    if (empty($reports)) {
        return;
    }

    $anonymized = 0;
    $errors = 0;

    $pdo->beginTransaction();

    try {
        foreach ($reports as $report) {
            try {
                if (ReportRepository::instance()->anonymize($report['uuid'])) {
                    $anonymized++;
                }
            } catch (Exception $e) {
                $errors++;
                error_log("[SST-CRON] anonymize: failed on {$report['reference']} — " . $e->getMessage());
            }
        }

        $details = "Lazy cron — Anonymisation de {$anonymized} signalement(s) de plus de {$retentionYears} an(s) (date de coupure : {$cutoffDate})";
        if ($errors > 0) {
            $details .= " — {$errors} erreur(s)";
        }

        AuditRepository::instance()->log(
            category: 'gdpr',
            action: 'anonymize',
            details: $details,
            context: [
                'source'           => 'lazy_cron',
                'retention_years'  => $retentionYears,
                'cutoff_date'      => $cutoffDate,
                'anonymized_count' => $anonymized,
                'error_count'      => $errors,
            ],
        );

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[SST-CRON] anonymize: transaction rolled back — ' . $e->getMessage());
    }
}
