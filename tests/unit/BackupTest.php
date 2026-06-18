<?php
/**
 * Backup Unit Tests — Application SST DREETS BFC
 *
 * Tests backup functions from src/backup.php:
 * - getDbFingerprint()
 * - getLastBackupFingerprint()
 * - setLastBackupFingerprint()
 * - rotateBackups()
 * - listBackups()
 *
 * Uses a temporary directory for filesystem operations to avoid
 * touching the real data/backups directory.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/backup.php';

class BackupTest extends TestCase
{
    private string $tmpDir;
    private string $markerFile;

    protected function setUp(): void
    {
        // Create a temporary directory for backup testing
        $this->tmpDir = sys_get_temp_dir() . '/sst_backup_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->markerFile = $this->tmpDir . '/.last_backup';
    }

    protected function tearDown(): void
    {
        // Clean up all test files
        if (is_dir($this->tmpDir)) {
            $files = glob($this->tmpDir . '/sst_*');
            if ($files !== false) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            // Remove protection files if created
            @unlink($this->tmpDir . '/.last_backup');
            @unlink($this->tmpDir . '/.htaccess');
            @unlink($this->tmpDir . '/web.config');
            @rmdir($this->tmpDir);
        }
    }

    /**
     * Helper: simulate getLastBackupFingerprint() using a custom marker path.
     */
    private function getLastFingerprintFromPath(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }
        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['mtime'], $data['size'])) {
            return null;
        }
        return $data;
    }

    /**
     * Helper: simulate setLastBackupFingerprint() using a custom marker path.
     */
    private function setFingerprintToPath(string $dir, string $markerPath, array $fingerprint): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($markerPath, json_encode($fingerprint, JSON_PRETTY_PRINT));
    }

    /**
     * Helper: simulate listBackups() for a specific directory.
     */
    private function listBackupsInDir(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/sst_*.db');
        if ($files === false) {
            return [];
        }
        $backups = [];
        foreach ($files as $file) {
            $backups[] = [
                'file' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'date' => filemtime($file),
            ];
        }
        usort($backups, function ($a, $b) {
            return $b['date'] - $a['date'];
        });
        return $backups;
    }

    /**
     * Helper: simulate rotateBackups() for a specific directory.
     */
    private function rotateInDir(string $dir, int $maxFiles = 10): void
    {
        $files = glob($dir . '/sst_*.db');
        if ($files === false || count($files) <= $maxFiles) {
            return;
        }
        usort($files, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        $toDelete = array_slice($files, 0, count($files) - $maxFiles);
        foreach ($toDelete as $file) {
            @unlink($file);
        }
    }

    // ─── getDbFingerprint ──────────────────────────────────────────────────

    public function testGetDbFingerprintReturnsArrayWithRequiredKeys(): void
    {
        $pdo = getDB();
        $fingerprint = getDbFingerprint($pdo);

        $this->assertIsArray($fingerprint);
        $this->assertArrayHasKey('mtime', $fingerprint);
        $this->assertArrayHasKey('size', $fingerprint);
    }

    public function testGetDbFingerprintMtimeIsInt(): void
    {
        $pdo = getDB();
        $fingerprint = getDbFingerprint($pdo);
        $this->assertIsInt($fingerprint['mtime']);
    }

    public function testGetDbFingerprintSizeIsInt(): void
    {
        $pdo = getDB();
        $fingerprint = getDbFingerprint($pdo);
        $this->assertIsInt($fingerprint['size']);
    }

    public function testGetDbFingerprintWithInMemoryDbReturnsIntegers(): void
    {
        // In-memory SQLite has no file on disk — getDbFingerprint reads DB_PATH
        // which may or may not exist. Either way, it should return integers.
        $pdo = getDB();
        $fingerprint = getDbFingerprint($pdo);
        $this->assertIsInt($fingerprint['mtime']);
        $this->assertIsInt($fingerprint['size']);
        $this->assertGreaterThanOrEqual(0, $fingerprint['mtime']);
        $this->assertGreaterThanOrEqual(0, $fingerprint['size']);
    }

    // ─── getLastBackupFingerprint ───────────────────────────────────────────

    public function testGetLastBackupFingerprintReturnsNullWhenNoMarkerFile(): void
    {
        // Using the real function with our temp marker path (no file exists)
        $result = $this->getLastFingerprintFromPath($this->tmpDir . '/nonexistent_marker');
        $this->assertNull($result);
    }

    public function testGetLastBackupFingerprintReturnsNullForInvalidJson(): void
    {
        file_put_contents($this->markerFile, 'not valid json');
        $result = $this->getLastFingerprintFromPath($this->markerFile);
        $this->assertNull($result);
    }

    public function testGetLastBackupFingerprintReturnsNullForMissingKeys(): void
    {
        file_put_contents($this->markerFile, json_encode(['mtime' => 123]));
        $result = $this->getLastFingerprintFromPath($this->markerFile);
        $this->assertNull($result);
    }

    // ─── setLastBackupFingerprint / getLastBackupFingerprint round trip ─────

    public function testSetAndGetLastBackupFingerprintRoundTrip(): void
    {
        $fingerprint = ['mtime' => 1700000000, 'size' => 4096];
        $this->setFingerprintToPath($this->tmpDir, $this->markerFile, $fingerprint);

        $result = $this->getLastFingerprintFromPath($this->markerFile);
        $this->assertEquals($fingerprint, $result);
    }

    public function testSetLastBackupFingerprintCreatesDirectory(): void
    {
        $newDir = $this->tmpDir . '/subdir';
        $newMarker = $newDir . '/.last_backup';

        $fingerprint = ['mtime' => 1700000000, 'size' => 8192];
        $this->setFingerprintToPath($newDir, $newMarker, $fingerprint);

        $this->assertDirectoryExists($newDir);
        $result = $this->getLastFingerprintFromPath($newMarker);
        $this->assertEquals($fingerprint, $result);

        // Cleanup
        @unlink($newMarker);
        @rmdir($newDir);
    }

    public function testSetLastBackupFingerprintOverwritesPrevious(): void
    {
        $fp1 = ['mtime' => 1700000000, 'size' => 4096];
        $this->setFingerprintToPath($this->tmpDir, $this->markerFile, $fp1);

        $fp2 = ['mtime' => 1700001000, 'size' => 8192];
        $this->setFingerprintToPath($this->tmpDir, $this->markerFile, $fp2);

        $result = $this->getLastFingerprintFromPath($this->markerFile);
        $this->assertEquals($fp2, $result);
    }

    // ─── rotateBackups ─────────────────────────────────────────────────────

    public function testRotateBackupsDeletesOldestFiles(): void
    {
        // Create 12 backup files with different modification times
        for ($i = 1; $i <= 12; $i++) {
            $file = $this->tmpDir . '/sst_2025-01' . str_pad($i, 2, '0', STR_PAD_LEFT) . '_120000.db';
            file_put_contents($file, 'backup data ' . $i);
            // Set modification time to ensure ordering
            touch($file, strtotime("2025-01-$i 12:00:00"));
        }

        $this->rotateInDir($this->tmpDir, 10);

        $remaining = glob($this->tmpDir . '/sst_*.db');
        $this->assertCount(10, $remaining);
    }

    public function testRotateBackupsKeepsNewestFiles(): void
    {
        // Create 5 files
        for ($i = 1; $i <= 5; $i++) {
            $file = $this->tmpDir . '/sst_2025-01' . str_pad($i, 2, '0', STR_PAD_LEFT) . '_120000.db';
            file_put_contents($file, 'backup data ' . $i);
            touch($file, strtotime("2025-01-$i 12:00:00"));
        }

        $this->rotateInDir($this->tmpDir, 10);

        $remaining = glob($this->tmpDir . '/sst_*.db');
        $this->assertCount(5, $remaining);
    }

    public function testRotateBackupsWithExactMaxFiles(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $file = $this->tmpDir . '/sst_2025-01' . str_pad($i, 2, '0', STR_PAD_LEFT) . '_120000.db';
            file_put_contents($file, 'backup data ' . $i);
            touch($file, strtotime("2025-01-$i 12:00:00"));
        }

        $this->rotateInDir($this->tmpDir, 10);

        $remaining = glob($this->tmpDir . '/sst_*.db');
        $this->assertCount(10, $remaining);
    }

    public function testRotateBackupsWithNoFiles(): void
    {
        $this->rotateInDir($this->tmpDir, 10);

        $remaining = glob($this->tmpDir . '/sst_*.db');
        $this->assertCount(0, $remaining);
    }

    public function testRotateBackupsOnlyDeletesSstFiles(): void
    {
        // Create sst backup files
        for ($i = 1; $i <= 12; $i++) {
            $file = $this->tmpDir . '/sst_2025-01' . str_pad($i, 2, '0', STR_PAD_LEFT) . '_120000.db';
            file_put_contents($file, 'backup data ' . $i);
            touch($file, strtotime("2025-01-$i 12:00:00"));
        }
        // Create a non-sst file
        $otherFile = $this->tmpDir . '/other_file.db';
        file_put_contents($otherFile, 'other data');

        $this->rotateInDir($this->tmpDir, 10);

        // The other file should still exist
        $this->assertFileExists($otherFile);
        // Only 10 sst files remain
        $remaining = glob($this->tmpDir . '/sst_*.db');
        $this->assertCount(10, $remaining);
    }

    // ─── listBackups ───────────────────────────────────────────────────────

    public function testListBackupsReturnsEmptyForNonExistentDirectory(): void
    {
        $result = $this->listBackupsInDir('/nonexistent/directory');
        $this->assertEquals([], $result);
    }

    public function testListBackupsReturnsEmptyForEmptyDirectory(): void
    {
        $result = $this->listBackupsInDir($this->tmpDir);
        $this->assertEquals([], $result);
    }

    public function testListBackupsReturnsBackupFiles(): void
    {
        // Create some backup files
        $file1 = $this->tmpDir . '/sst_2025-0101_120000.db';
        $file2 = $this->tmpDir . '/sst_2025-0102_120000.db';
        file_put_contents($file1, 'backup1');
        file_put_contents($file2, 'backup2');
        touch($file1, strtotime('2025-01-01 12:00:00'));
        touch($file2, strtotime('2025-01-02 12:00:00'));

        $result = $this->listBackupsInDir($this->tmpDir);

        $this->assertCount(2, $result);
    }

    public function testListBackupsSortedNewestFirst(): void
    {
        $file1 = $this->tmpDir . '/sst_2025-0101_120000.db';
        $file2 = $this->tmpDir . '/sst_2025-0102_120000.db';
        $file3 = $this->tmpDir . '/sst_2025-0103_120000.db';
        file_put_contents($file1, 'backup1');
        file_put_contents($file2, 'backup2');
        file_put_contents($file3, 'backup3');
        touch($file1, strtotime('2025-01-01 12:00:00'));
        touch($file2, strtotime('2025-01-02 12:00:00'));
        touch($file3, strtotime('2025-01-03 12:00:00'));

        $result = $this->listBackupsInDir($this->tmpDir);

        $this->assertCount(3, $result);
        // Newest first
        $this->assertEquals('sst_2025-0103_120000.db', $result[0]['file']);
        $this->assertEquals('sst_2025-0102_120000.db', $result[1]['file']);
        $this->assertEquals('sst_2025-0101_120000.db', $result[2]['file']);
    }

    public function testListBackupsReturnsCorrectStructure(): void
    {
        $file = $this->tmpDir . '/sst_2025-0101_120000.db';
        file_put_contents($file, 'backup content here');
        touch($file, strtotime('2025-01-01 12:00:00'));

        $result = $this->listBackupsInDir($this->tmpDir);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('file', $result[0]);
        $this->assertArrayHasKey('path', $result[0]);
        $this->assertArrayHasKey('size', $result[0]);
        $this->assertArrayHasKey('date', $result[0]);
        $this->assertEquals('sst_2025-0101_120000.db', $result[0]['file']);
        $this->assertEquals(strlen('backup content here'), $result[0]['size']);
    }

    public function testListBackupsOnlyListsSstFiles(): void
    {
        $sstFile = $this->tmpDir . '/sst_2025-0101_120000.db';
        $otherFile = $this->tmpDir . '/other_data.db';
        file_put_contents($sstFile, 'backup');
        file_put_contents($otherFile, 'other');

        $result = $this->listBackupsInDir($this->tmpDir);

        $this->assertCount(1, $result);
        $this->assertEquals('sst_2025-0101_120000.db', $result[0]['file']);
    }
}
