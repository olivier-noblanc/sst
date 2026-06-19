<?php

/**
 * Lazy Cron — Anonymize Task — Application SST DREETS BFC
 *
 * RGPD anonymization of old reports.
 * Split from cron.php to keep file size under 250 lines.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Task 2: Anonymize Old Reports (RGPD)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Anonymize reports that have been in a final state (traité/abandonné)
 * for longer than the configured retention period.
 *
 * This is the web equivalent of tools/anonymize_old_reports.php.
 * Unlike the CLI version, there is no interactive confirmation —
 * anonymization proceeds automatically when the retention period is met.
 *
 * The retention period MUST be validated by the DPO before enabling
 * (app_retention_years > 0).
 *
 * @param PDO $pdo  Database connection
 */
function lazyCronAnonymize(PDO $pdo): void
{
    $retentionYears = (int) getConfig('app_retention_years', '0');

    // If retention is disabled (0 = unlimited), skip entirely
    if ($retentionYears <= 0) {
        return;
    }

    // Calculate cutoff date
    $cutoffDate = date('Y-m-d', strtotime("-{$retentionYears} years"));

    // Find eligible reports
    $sql = "SELECT uuid, reference, type, declarant_nom, declarant_prenom, date_evenement, etat
            FROM reports
            WHERE etat IN ('" . ETAT_TRAITE . "', '" . ETAT_ABANDONNE . "')
              AND date_evenement < :cutoff_date
              AND declarant_nom != 'Anonymisé'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cutoff_date' => $cutoffDate]);
    $reports = $stmt->fetchAll();

    if (empty($reports)) {
        return; // Nothing to anonymize
    }

    // Perform anonymization
    $anonymized = 0;
    $errors = 0;

    $pdo->beginTransaction();

    try {
        $updateStmt = $pdo->prepare("
            UPDATE reports
            SET declarant_nom = 'Anonymisé',
                declarant_prenom = 'Anonymé',
                pour_compte_nom = NULL,
                pour_compte_prenom = NULL,
                updated_at = datetime('now')
            WHERE uuid = :uuid
              AND etat IN ('" . ETAT_TRAITE . "', '" . ETAT_ABANDONNE . "')
              AND declarant_nom != 'Anonymisé'
        ");

        foreach ($reports as $report) {
            try {
                $updateStmt->execute([':uuid' => $report['uuid']]);
                if ($updateStmt->rowCount() > 0) {
                    $anonymized++;
                }
            } catch (Exception $e) {
                $errors++;
                error_log("[SST-CRON] anonymize: failed on {$report['reference']} — " . $e->getMessage());
            }
        }

        // Log to audit_log
        require_once __DIR__ . '/audit.php';
        $details = "Lazy cron — Anonymisation de {$anonymized} signalement(s) de plus de {$retentionYears} an(s) (date de coupure : {$cutoffDate})";
        if ($errors > 0) {
            $details .= " — {$errors} erreur(s)";
        }

        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, username, category, action, details, context, ip_address)
            VALUES (NULL, 'system', 'gdpr', 'anonymize', :details, :context, 'lazy-cron')
        ");
        $stmt->execute([
            ':details' => $details,
            ':context' => json_encode([
                'source'           => 'lazy_cron',
                'retention_years'  => $retentionYears,
                'cutoff_date'      => $cutoffDate,
                'anonymized_count' => $anonymized,
                'error_count'      => $errors,
            ]),
        ]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[SST-CRON] anonymize: transaction rolled back — ' . $e->getMessage());
    }
}
