<?php
/**
 * Seed Script — Application SST DREETS BFC
 * 
 * Populates the database with comprehensive test users and sample reports.
 * Run from CLI: /home/z/my-project/php-bin seed.php
 */

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/queries/report_queries.php';
require_once __DIR__ . '/src/queries/user_queries.php';
require_once __DIR__ . '/src/queries/site_queries.php';

echo "=== SST DREETS BFC — Seed Script ===\n\n";

// Remove existing database to start fresh
if (file_exists(DB_PATH)) {
    unlink(DB_PATH);
    echo "Removed existing database.\n";
}

// Initialize database (creates schema + default data)
$pdo = getDB();
echo "Database initialized with schema.\n";

// Load seed data from separate files
require __DIR__ . '/seed/_registries.php';
require __DIR__ . '/seed/_sites.php';
require __DIR__ . '/seed/_users.php';
require __DIR__ . '/seed/_reports.php';
require __DIR__ . '/seed/_notifications.php';

// ================================================================
// FINAL SUMMARY
// ================================================================
$totalUsers = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalReports = $pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
$totalSites = $pdo->query('SELECT COUNT(*) FROM sites')->fetchColumn();
$totalResponses = $pdo->query('SELECT COUNT(*) FROM report_responses')->fetchColumn();

echo "\n=== Seed Complete ===\n";
echo "Sites: $totalSites\n";
echo "Users: $totalUsers\n";
echo "Reports: $totalReports\n";
echo "Responses: $totalResponses\n";

// Report breakdown
foreach (['rsst', 'rami', 'dgi'] as $type) {
    $stmt = $pdo->prepare("SELECT etat, COUNT(*) as cnt FROM reports WHERE type = :type GROUP BY etat");
    $stmt->execute([':type' => $type]);
    $breakdown = [];
    foreach ($stmt->fetchAll() as $row) {
        $breakdown[] = ETAT_LABELS[$row['etat']] . ': ' . $row['cnt'];
    }
    echo "  $type: " . implode(', ', $breakdown) . "\n";
}

// User breakdown
foreach (['superviseur', 'agent', 'chsct'] as $role) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = :role AND is_active = 1");
    $stmt->execute([':role' => $role]);
    echo "  $role: " . $stmt->fetchColumn() . "\n";
}

echo "Database: " . DB_PATH . "\n";
