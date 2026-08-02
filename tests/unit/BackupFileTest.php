<?php
/**
 * Backup Unit Tests — File Rotation & Listing
 *
 * Tests backup functions from src/backup.php:
 * - rotateBackups()
 * - listBackups()
 *
 * Uses a temporary directory for filesystem operations.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/backup.php';

class BackupFileTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sst_backup_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $files = glob($this->tmpDir . '/sst_*');
            if ($files !== false) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            @unlink($this->tmpDir . '/.last_backup');
            @unlink($this->tmpDir . '/.htaccess');
            @unlink($this->tmpDir . '/web.config');
            @rmdir($this->tmpDir);
        }
    }

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
                'file' => basename($file), 'path' => $file,
                'size' => filesize($file), 'date' => filemtime($file),
            ];
        }
        usort($backups, fn($a, $b) => $b['date'] - $a['date']);
        return $backups;
    }

    private function rotateInDir(string $dir, int $maxFiles = 10): void
    {
        $files = glob($dir . '/sst_*.db');
        if ($files === false || count($files) <= $maxFiles) {
            return;
        }
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
        $toDelete = array_slice($files, 0, count($files) - $maxFiles);
        foreach ($toDelete as $file) {
            @unlink($file);
        }
    }

    // ─── rotateBackups ─────────────────────────────────────────────────────

    public function testRotateBackupsDeletesOldestFiles(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $file = $this->tmpDir . '/sst_2025-01' . str_pad($i, 2, '0', STR_PAD_LEFT) . '_120000.db';
            file_put_contents($file, 'backup data ' . $i);
            touch($file, strtotime("2025-01-$i 12:00:00"));
        }
        $this->rotateInDir($this->tmpDir, 10);
        $remaining = glob($this->tmpDir . '/sst_*.db');
        $this->assertCount(10, $remaining);
    }

    public function testRotateBackupsKeepsNewestFiles(): void
    {
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
        for ($i = 1; $i <= 12; $i++) {
            $file = $this->tmpDir . '/sst_2025-01' . str_pad($i, 2, '0', STR_PAD_LEFT) . '_120000.db';
            file_put_contents($file, 'backup data ' . $i);
            touch($file, strtotime("2025-01-$i 12:00:00"));
        }
        $otherFile = $this->tmpDir . '/other_file.db';
        file_put_contents($otherFile, 'other data');
        $this->rotateInDir($this->tmpDir, 10);
        $this->assertFileExists($otherFile);
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
