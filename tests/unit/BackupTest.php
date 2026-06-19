<?php
/**
 * Backup Unit Tests — DB Fingerprint & Marker Read Operations
 *
 * Tests backup functions from src/backup.php:
 * - getDbFingerprint()
 * - getLastBackupFingerprint()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/backup.php';

class BackupTest extends TestCase
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
}
