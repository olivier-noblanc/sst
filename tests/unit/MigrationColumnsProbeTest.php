<?php

/**
 * Migration columns — reports.type CHECK detection & idempotence (B3/B4)
 *
 * B3 — the reports.type registry constraint must be detected read-only: no
 * fixed-UUID INSERT probe and no broad catch (Exception) that reads any
 * unrelated error as "constraint present".
 * B4 — migrateColumns() must be a no-op (zero writes) on an already-migrated
 * database, so the backup fingerprint stays stable and backups skip when the
 * base is unchanged.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/migration_columns.php';
require_once __DIR__ . '/../../src/migration_tables.php';
// migrateColumns() -> backupBeforeMigration() is required by the rebuild path
// (not exercised below, but the source of truth must stay loadable).
require_once __DIR__ . '/../../src/backup.php';

class MigrationColumnsProbeTest extends TestCase
{
    private string $tmpDir = '';
    private string $dbPath = '';

    protected function tearDown(): void
    {
        if ($this->tmpDir !== '' && is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tmpDir);
        }
        $this->tmpDir = '';
        $this->dbPath = '';
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    private function memoryPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    private function createFileDb(): PDO
    {
        $this->tmpDir = sys_get_temp_dir() . '/sst_migration_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->dbPath = $this->tmpDir . '/sst.db';

        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');

        $schema = file_get_contents(__DIR__ . '/../../schema.sql');
        self::assertIsString($schema);
        $pdo->exec($schema);
        migrateTables($pdo);

        return $pdo;
    }

    /**
     * Mirrors src/backup.php getDbFingerprint() for an arbitrary path (the
     * production constant DB_PATH cannot be redirected in a unit test).
     *
     * @return array{mtime: int, size: int}
     */
    private function fingerprint(string $dbPath): array
    {
        clearstatcache(true, $dbPath);
        if (!file_exists($dbPath)) {
            return ['mtime' => 0, 'size' => 0];
        }
        $mtime = (int) filemtime($dbPath);
        $size = (int) filesize($dbPath);

        $walPath = $dbPath . '-wal';
        if (file_exists($walPath)) {
            $mtime = max($mtime, (int) filemtime($walPath));
            $size += (int) filesize($walPath);
        }

        return ['mtime' => $mtime, 'size' => $size];
    }

    // ─── B3 — read-only constraint detection ────────────────────────────────

    public function testDetectsLegacyTypeRegistryCheckConstraint(): void
    {
        $pdo = $this->memoryPdo();
        $pdo->exec("CREATE TABLE reports (
            uuid TEXT PRIMARY KEY,
            reference TEXT NOT NULL UNIQUE,
            type TEXT NOT NULL,
            etat TEXT NOT NULL,
            CHECK (type IN ('rsst','rami','dgi'))
        )");

        $this->assertTrue(reportsTableHasTypeCheckConstraint($pdo));
    }

    public function testDoesNotDetectOtherChecksOnReports(): void
    {
        $pdo = $this->memoryPdo();
        // A CHECK on another column and a non-registry type check: neither is the
        // hardcoded registry constraint the migration must remove.
        $pdo->exec("CREATE TABLE reports (
            uuid TEXT PRIMARY KEY,
            type TEXT NOT NULL,
            etat TEXT NOT NULL CHECK (etat IN ('nouveau','en_cours')),
            CHECK (type <> '')
        )");

        $this->assertFalse(reportsTableHasTypeCheckConstraint($pdo));
    }

    public function testReturnsFalseWhenNoTypeCheckPresent(): void
    {
        $pdo = $this->memoryPdo();
        $pdo->exec('CREATE TABLE reports (uuid TEXT PRIMARY KEY, type TEXT NOT NULL, etat TEXT NOT NULL)');

        $this->assertFalse(reportsTableHasTypeCheckConstraint($pdo));
    }

    public function testCrashesHardWhenReportsTableIsMissing(): void
    {
        $pdo = $this->memoryPdo();

        $this->expectException(RuntimeException::class);
        reportsTableHasTypeCheckConstraint($pdo);
    }

    // ─── B4 — idempotence / no fingerprint perturbation ─────────────────────

    public function testMigrateColumnsWritesNothingOnAlreadyMigratedDatabase(): void
    {
        $pdo = $this->createFileDb();

        // First pass: bring the DB to a steady state (the registry policy
        // backfill may legitimately write once), then reset the WAL so any write
        // from the second pass shows up as fingerprint growth.
        migrateColumns($pdo);
        $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        $before = $this->fingerprint($this->dbPath);

        migrateColumns($pdo);

        $after = $this->fingerprint($this->dbPath);
        $this->assertSame(
            $before['size'],
            $after['size'],
            'migrateColumns() doit être un no-op sur une base déjà migrée (aucune écriture, fingerprint stable)'
        );
    }

    public function testMigrateColumnsLeavesNoProbeArtifact(): void
    {
        $pdo = $this->createFileDb();
        migrateColumns($pdo);
        migrateColumns($pdo);

        $this->assertSame(
            0,
            (int) $pdo->query("SELECT COUNT(*) FROM reports WHERE reference = 'test-check-removal'")->fetchColumn(),
            'La détection B3 ne doit plus insérer de ligne de référence fixe'
        );
        $this->assertSame(
            0,
            (int) $pdo->query("SELECT COUNT(*) FROM reports WHERE uuid = '00000000-0000-0000-0000-000000000000'")->fetchColumn(),
            'La détection B3 ne doit plus insérer d\'UUID fixe'
        );
    }
}
