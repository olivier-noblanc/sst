<?php
/**
 * Anonymize Old Reports — Application SST DREETS BFC
 *
 * CLI script to anonymize reports that have been in a final state (traité/abandonné)
 * for longer than the configured retention period.
 *
 * This script reads app_retention_years from config_app:
 *   - If 0 or empty: disabled, no anonymization is performed.
 *   - If > 0: reports in état 'traite' or 'abandonne' whose date_evenement
 *     is older than app_retention_years years will have their personal data
 *     anonymized (declarant_nom, declarant_prenom → "Anonymisé").
 *
 * NOTE: This task is also executed automatically via lazy cron at user login
 * (see src/cron.php, every 7 days minimum). This CLI script remains available
 * for manual execution, dry-run testing, and interactive confirmation.
 * No system cron is needed.
 *
 * Usage:
 *   php tools/anonymize_old_reports.php          # Normal execution
 *   php tools/anonymize_old_reports.php --dry-run # Preview without modifying
 *
 * IMPORTANT: This script must be run from the project root directory:
 *   cd C:\inetpub\sst
 *   php tools\anonymize_old_reports.php
 *
 * The retention period MUST be validated by the DPO before enabling.
 * See DEPLOY.md for configuration instructions.
 */

// Only run from CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Ce script ne peut être exécuté qu\'en ligne de commande (CLI).');
}

echo "\n=== Anonymisation des signalements anciens — SST DREETS BFC ===\n\n";

$dryRun = in_array('--dry-run', $argv ?? []);

if ($dryRun) {
    echo "[MODE DRY-RUN] Aucune modification ne sera effectuée.\n\n";
}

// Determine project root (parent of tools/ directory)
$projectRoot = dirname(__DIR__);

// Load dependencies (same as the web application bootstrap)
require_once $projectRoot . '/src/config.php';
require_once $projectRoot . '/src/helpers.php';
require_once $projectRoot . '/src/database.php';

try {
    $pdo = getDB();
} catch (Exception $e) {
    echo "ERREUR : Impossible de se connecter à la base de données.\n";
    echo "Détail : " . $e->getMessage() . "\n";
    exit(1);
}

// Read retention period from config
$retentionYears = (int) getConfig('app_retention_years', '0');

if ($retentionYears <= 0) {
    echo "INFO : La conservation illimitée est activée (app_retention_years = 0).\n";
    echo "       Aucune anonymisation ne sera effectuée.\n";
    echo "       Pour activer, définissez app_retention_years > 0 dans les paramètres de l'application,\n";
    echo "       après validation du DPO.\n\n";
    exit(0);
}

// Calculate cutoff date
$cutoffDate = date('Y-m-d', strtotime("-{$retentionYears} years"));
echo "Période de conservation : {$retentionYears} an(s)\n";
echo "Date de coupure : les signalements traités/abandonnés avant le {$cutoffDate} seront anonymisés.\n\n";

// Find eligible reports
$sql = "SELECT uuid, reference, type, declarant_nom, declarant_prenom, date_evenement, etat
        FROM reports
        WHERE etat IN ('traite', 'abandonne')
          AND date_evenement < :cutoff_date
          AND declarant_nom != 'Anonymisé'";

$stmt = $pdo->prepare($sql);
$stmt->execute([':cutoff_date' => $cutoffDate]);
$reports = $stmt->fetchAll();

if (empty($reports)) {
    echo "INFO : Aucun signalement éligible à l'anonymisation.\n\n";
    exit(0);
}

echo count($reports) . " signalement(s) éligible(s) à l'anonymisation :\n";
echo str_repeat('-', 80) . "\n";
printf("%-15s %-12s %-20s %-12s %s\n", 'Référence', 'Type', 'Déclarant', 'État', 'Date événement');
echo str_repeat('-', 80) . "\n";

foreach ($reports as $report) {
    $name = $report['declarant_prenom'] . ' ' . $report['declarant_nom'];
    printf("%-15s %-12s %-20s %-12s %s\n",
        $report['reference'],
        strtoupper($report['type']),
        $name,
        $report['etat'],
        $report['date_evenement']
    );
}

echo str_repeat('-', 80) . "\n\n";

if ($dryRun) {
    echo "[DRY-RUN] " . count($reports) . " signalement(s) seraient anonymisés.\n";
    echo "Exécutez sans --dry-run pour appliquer les modifications.\n\n";
    exit(0);
}

// Confirm before proceeding
echo "Voulez-vous procéder à l'anonymisation de ces " . count($reports) . " signalement(s) ?\n";
echo "Tapez OUI pour confirmer : ";
$handle = fopen('php://stdin', 'r');
$confirm = trim(fgets($handle));
fclose($handle);

if ($confirm !== 'OUI') {
    echo "Anonymisation annulée.\n\n";
    exit(0);
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
          AND etat IN ('traite', 'abandonne')
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
            echo "ERREUR sur {$report['reference']} : " . $e->getMessage() . "\n";
        }
    }

    // Log the anonymization in audit_log
    require_once $projectRoot . '/src/audit.php';
    $details = "Anonymisation de {$anonymized} signalement(s) de plus de {$retentionYears} an(s) (date de coupure : {$cutoffDate})";
    if ($errors > 0) {
        $details .= " — {$errors} erreur(s)";
    }

    // Insert audit log directly (we're in CLI, no session)
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, username, category, action, details, context, ip_address)
        VALUES (NULL, 'system', 'gdpr', 'anonymize', :details, :context, 'cli')
    ");
    $stmt->execute([
        ':details' => $details,
        ':context' => json_encode([
            'retention_years' => $retentionYears,
            'cutoff_date' => $cutoffDate,
            'anonymized_count' => $anonymized,
            'error_count' => $errors,
        ]),
    ]);

    $pdo->commit();

    echo "\nRÉSULTAT : {$anonymized} signalement(s) anonymisé(s) avec succès.\n";
    if ($errors > 0) {
        echo "ATTENTION : {$errors} erreur(s) rencontrée(s). Consultez les logs ci-dessus.\n";
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo "\nERREUR FATALE : " . $e->getMessage() . "\n";
    echo "Aucune modification n'a été appliquée (rollback).\n";
    exit(1);
}

echo "\nOpération terminée.\n\n";
