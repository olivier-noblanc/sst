#!/usr/bin/env php
<?php
/**
 * Nuclear Reset — Purger tous les signalements (Application SST DREETS BFC)
 *
 * Supprime les données liées aux signalements uniquement :
 *   - reports            (les signalements)
 *   - report_responses   (les réponses des superviseurs)
 *   - report_sequence    (les compteurs de référence rsst-25-001, etc.)
 *   - audit_log          (le journal d'audit)
 *
 * CONSERVE :
 *   - users              (les comptes utilisateurs)
 *   - sites              (les unités régionales)
 *   - config_app         (la configuration de l'application)
 *   - notification_settings (les paramètres de notification email)
 *
 * Usage :  php nuclear-reset.php
 *
 * Le script demande confirmation avant de supprimer quoi que ce soit.
 * À exécuter en CLI uniquement (pas via navigateur).
 */

echo "\n";
echo "╔══════════════════════════════════════════════════╗\n";
echo "║     ☢  NUCLEAR RESET — Purge signalements       ║\n";
echo "╚══════════════════════════════════════════════════╝\n";
echo "\n";

$dbPath = __DIR__ . '/data/sst.db';

if (!file_exists($dbPath)) {
    echo "❌ Base de données introuvable : $dbPath\n";
    exit(1);
}

// Prevent web execution
if (php_sapi_name() !== 'cli') {
    echo "❌ Ce script doit être exécuté en ligne de commande uniquement.\n";
    exit(1);
}

// Require explicit confirmation via environment variable
if (!isset($_SERVER['SST_CONFIRM_RESET']) || $_SERVER['SST_CONFIRM_RESET'] !== 'yes') {
    echo "Erreur : définissez SST_CONFIRM_RESET=yes pour confirmer.\n";
    echo "Usage: SST_CONFIRM_RESET=yes php nuclear-reset.php\n";
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA busy_timeout = 30000;');

    // Count what will be deleted
    $stmt = $pdo->query('SELECT COUNT(*) FROM reports');
    assert($stmt !== false);
    $reportCount = (int) $stmt->fetchColumn();
    $stmt = $pdo->query('SELECT COUNT(*) FROM report_responses');
    assert($stmt !== false);
    $responseCount = (int) $stmt->fetchColumn();
    $stmt = $pdo->query('SELECT COUNT(*) FROM report_sequence');
    assert($stmt !== false);
    $sequenceCount = (int) $stmt->fetchColumn();
    $stmt = $pdo->query('SELECT COUNT(*) FROM audit_log');
    assert($stmt !== false);
    $auditCount = (int) $stmt->fetchColumn();

    // Count what will be kept
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    assert($stmt !== false);
    $userCount = (int) $stmt->fetchColumn();
    $stmt = $pdo->query('SELECT COUNT(*) FROM sites');
    assert($stmt !== false);
    $siteCount = (int) $stmt->fetchColumn();

    echo "SERONT SUPPRIMÉS :\n";
    echo "  • Signalements (reports)          : $reportCount\n";
    echo "  • Réponses (report_responses)     : $responseCount\n";
    echo "  • Séquences (report_sequence)     : $sequenceCount\n";
    echo "  • Journal d'audit (audit_log)     : $auditCount\n";
    echo "\n";
    echo "SERONT CONSERVÉS :\n";
    echo "  • Utilisateurs (users)            : $userCount\n";
    echo "  • Sites (sites)                   : $siteCount\n";
    echo "  • Configuration (config_app)      : conservée\n";
    echo "  • Notifications (notif_settings)  : conservée\n";
    echo "\n";

    if ($reportCount === 0 && $responseCount === 0 && $sequenceCount === 0 && $auditCount === 0) {
        echo "ℹ  Rien à supprimer — la base est déjà vide de signalements.\n";
        exit(0);
    }

    // Confirmation
    echo "⚠️  Tapez OUI pour confirmer la suppression : ";
    $input = trim((string) fgets(STDIN));

    if ($input !== 'OUI') {
        echo "\n Annulé.\n";
        exit(0);
    }

    // Delete in correct order (respect FK constraints)
    echo "\n";

    $pdo->exec('DELETE FROM report_responses');
    echo "  ✓ report_responses vidé\n";

    $pdo->exec('DELETE FROM reports');
    echo "  ✓ reports vidé\n";

    $pdo->exec('DELETE FROM report_sequence');
    echo "  ✓ report_sequence vidé\n";

    $pdo->exec('DELETE FROM audit_log');
    echo "  ✓ audit_log vidé\n";

    // Reset auto-increment counters
    $pdo->exec('DELETE FROM sqlite_sequence WHERE name IN ("reports", "report_responses", "report_sequence", "audit_log")');
    echo "  ✓ Compteurs auto-increment réinitialisés\n";

    // Optimize
    $pdo->exec('VACUUM');
    echo "  ✓ VACUUM (optimisation SQLite)\n";

    echo "\n✅ Nuclear reset terminé ! Tous les signalements ont été supprimés.\n";
    echo "   Les utilisateurs et la configuration sont intacts.\n\n";

} catch (Exception $e) {
    echo "\n❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
