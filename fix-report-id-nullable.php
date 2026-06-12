#!/usr/bin/env php
<?php
/**
 * One-time migration script — Make report_id nullable in report_responses
 *
 * Run this ONCE from the command line on the IIS server:
 *   php fix-report-id-nullable.php
 *
 * This cannot run via web (IIS concurrent requests → "database table is locked").
 * CLI guarantees exclusive SQLite access.
 *
 * After running this script, you can delete it.
 */

echo "=== Migration: report_responses.report_id → nullable ===\n\n";

$dbPath = __DIR__ . '/data/sst.db';

if (!file_exists($dbPath)) {
    echo "ERROR: Database not found at $dbPath\n";
    exit(1);
}

if (!is_writable($dbPath)) {
    echo "ERROR: Database is not writable. Check permissions.\n";
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::MODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=30000');

    // Check if migration is needed
    $cols = $pdo->query("PRAGMA table_info(report_responses)")->fetchAll(PDO::FETCH_ASSOC);
    $reportIdNotNull = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'report_id' && (int)$col['notnull'] === 1) {
            $reportIdNotNull = true;
        }
    }

    if (!$reportIdNotNull) {
        echo "OK: report_id is already nullable. No migration needed.\n";
        exit(0);
    }

    echo "report_id is NOT NULL — running migration...\n\n";

    // Step 1: Clean up any leftover _new table
    $newExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='report_responses_new'")->fetch();
    if ($newExists) {
        echo "  - Dropping orphaned report_responses_new table...\n";
        $pdo->exec('DROP TABLE report_responses_new');
    }

    // Step 2: Count existing rows
    $count = $pdo->query('SELECT COUNT(*) FROM report_responses')->fetchColumn();
    echo "  - Existing rows in report_responses: $count\n";

    // Step 3: Create new table with report_id nullable
    echo "  - Creating report_responses_new with report_id nullable...\n";
    $pdo->exec('CREATE TABLE report_responses_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        report_uuid TEXT,
        report_id INTEGER,
        user_id INTEGER NOT NULL,
        reponse TEXT NOT NULL,
        nouvel_etat TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
        FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // Step 4: Copy data
    echo "  - Copying data...\n";
    $pdo->exec('INSERT INTO report_responses_new (id, report_uuid, report_id, user_id, reponse, nouvel_etat, created_at)
        SELECT id, report_uuid, report_id, user_id, reponse, nouvel_etat, created_at FROM report_responses');

    // Step 5: Verify row count
    $newCount = $pdo->query('SELECT COUNT(*) FROM report_responses_new')->fetchColumn();
    if ($newCount != $count) {
        echo "ERROR: Row count mismatch! Original: $count, New: $newCount. Aborting.\n";
        $pdo->exec('DROP TABLE report_responses_new');
        exit(1);
    }
    echo "  - Rows copied: $newCount ✓\n";

    // Step 6: Swap tables
    echo "  - Swapping tables...\n";
    $pdo->exec('DROP TABLE report_responses');
    $pdo->exec('ALTER TABLE report_responses_new RENAME TO report_responses');

    // Step 7: Recreate indexes
    echo "  - Recreating indexes...\n";
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_report_responses_report_uuid ON report_responses(report_uuid)');

    echo "\n✅ Migration complete! report_id is now nullable.\n";
    echo "   You can delete this script now.\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    // Try to clean up
    try { $pdo->exec('DROP TABLE IF EXISTS report_responses_new'); } catch (Exception $e2) {}
    exit(1);
}
