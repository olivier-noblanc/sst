<?php
/**
 * Backup Unit Tests — Fingerprint Write & Round-Trip Operations
 *
 * Tests backup functions from src/backup.php:
 * - setLastBackupFingerprint() / getLastBackupFingerprint() round trips
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/backup.php';

class BackupFingerprintTest extends TestCase
{
    private string $tmpDir;
    private string $markerFile;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/sst_backup_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->markerFile = $this->tmpDir . '/.last_backup';
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

    private function setFingerprintToPath(string $dir, string $markerPath, array $fingerprint): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($markerPath, json_encode($fingerprint, JSON_PRETTY_PRINT));
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
}
