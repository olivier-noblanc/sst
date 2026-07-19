<?php
/**
 * Check Delayed Reports — Application SST DREETS BFC
 *
 * CLI script to detect reports that have been in "nouveau" state
 * for longer than the configured delay (app_alert_delay_days).
 * Sends an email alert to supervisors of the affected sites.
 *
 * NOTE: This task is also executed automatically via lazy cron at user login
 * (see src/cron.php). This CLI script remains available for manual execution
 * and dry-run testing. No system cron is needed.
 *
 * Usage:
 *   php tools/check_delays.php          # Normal execution
 *   php tools/check_delays.php --dry-run # Preview without sending emails
 *
 * IMPORTANT: This script must be run from the project root directory:
 *   cd C:\inetpub\sst
 *   php tools\check_delays.php
 */

// Only run from CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Ce script ne peut être exécuté qu'en ligne de commande (CLI).\n");
}

echo "\n=== Vérification des délais de signalement — SST DREETS BFC ===\n\n";

$dryRun = in_array('--dry-run', $argv ?? []);

if ($dryRun) {
    echo "[MODE DRY-RUN] Aucun e-mail ne sera envoyé.\n\n";
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

// Read alert delay from config
$alertDelayDays = (int) getConfig('app_alert_delay_days', '0');

if ($alertDelayDays <= 0) {
    echo "INFO : L'alerte délai est désactivée (app_alert_delay_days = 0).\n";
    echo "       Pour activer, définissez app_alert_delay_days > 0 dans les paramètres.\n\n";
    exit(0);
}

echo "Délai d'alerte configuré : {$alertDelayDays} jour(s)\n";
echo "Recherche des signalements en état « Nouveau » depuis plus de {$alertDelayDays} jour(s)...\n\n";

// Find overdue reports
$cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$alertDelayDays} days"));

$sql = "SELECT r.uuid, r.reference, r.type, r.objet, r.created_at,
               r.site_id, s.code as site_code, s.nom as site_nom,
               d.nom as declarant_nom, d.prenom as declarant_prenom
        FROM reports r
        LEFT JOIN sites s ON r.site_id = s.id
        LEFT JOIN users d ON r.declarant_id = d.id
        WHERE r.etat = 'nouveau'
          AND r.created_at < :cutoff_date
        ORDER BY r.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':cutoff_date' => $cutoffDate]);
$overdueReports = $stmt->fetchAll();

if (empty($overdueReports)) {
    echo "INFO : Aucun signalement en retard de traitement.\n\n";
    exit(0);
}

echo count($overdueReports) . " signalement(s) en retard de traitement :\n";
echo str_repeat('-', 100) . "\n";
printf("%-15s %-12s %-10s %-30s %-12s %s\n", 'Référence', 'Type', 'Site', 'Objet', 'Déclarant', 'Créé le');
echo str_repeat('-', 100) . "\n";

// Group by site for notification
$bySite = [];
foreach ($overdueReports as $report) {
    $siteId = (int) $report['site_id'];
    if (!isset($bySite[$siteId])) {
        $bySite[$siteId] = [
            'site_code' => $report['site_code'],
            'site_nom' => $report['site_nom'],
            'reports' => [],
        ];
    }
    $bySite[$siteId]['reports'][] = $report;

    printf("%-15s %-12s %-10s %-30s %-12s %s\n",
        $report['reference'],
        strtoupper($report['type']),
        $report['site_code'],
        mb_strimwidth($report['objet'], 0, 30, '...'),
        $report['declarant_prenom'] . ' ' . $report['declarant_nom'],
        $report['created_at']
    );
}

echo str_repeat('-', 100) . "\n\n";

if ($dryRun) {
    echo "[DRY-RUN] " . count($overdueReports) . " signalement(s) seraient signalés par e-mail.\n";
    echo "Exécutez sans --dry-run pour envoyer les alertes.\n\n";
    exit(0);
}

// Send email alerts
require_once $projectRoot . '/src/mail.php';

$emailsSent = 0;
$errors = 0;

foreach ($bySite as $siteId => $siteData) {
    // Get notification recipients for this site
    $recipients = getNotificationRecipients($pdo, $siteId);

    if (empty($recipients)) {
        echo "ATTENTION : Aucun destinataire configuré pour le site {$siteData['site_code']}.\n";
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
                echo "E-mail envoyé à : $email (site {$siteData['site_code']})\n";
            } else {
                $errors++;
                echo "ERREUR : échec d'envoi à $email\n";
            }
        } catch (Exception $e) {
            $errors++;
            echo "ERREUR : " . $e->getMessage() . "\n";
        }
    }
}

// Log the check in audit_log
require_once $projectRoot . '/src/audit.php';
$details = "Vérification délai : {$alertDelayDays}j — " . count($overdueReports) . " signalement(s) en retard, {$emailsSent} e-mail(s) envoyé(s)";
if ($errors > 0) {
    $details .= " — {$errors} erreur(s)";
}

$stmt = $pdo->prepare("
    INSERT INTO audit_log (user_id, username, category, action, details, context, ip_address)
    VALUES (NULL, 'system', 'report', 'delay_check', :details, :context, 'cli')
");
$stmt->execute([
    ':details' => $details,
    ':context' => json_encode([
        'alert_delay_days' => $alertDelayDays,
        'overdue_count' => count($overdueReports),
        'emails_sent' => $emailsSent,
        'error_count' => $errors,
    ]),
]);

echo "\nRÉSULTAT : {$emailsSent} e-mail(s) envoyé(s), {$errors} erreur(s).\n";
echo "Opération terminée.\n\n";
