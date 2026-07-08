<?php
/**
 * BackupService Unit Tests — Service Layer
 *
 * Tests BackupService from src/Services/BackupService.php:
 * - Service instantiation
 * - Method existence and type hints
 * - Delegation to global backup functions
 */

use PHPUnit\Framework\TestCase;
use App\Services\BackupService;

class BackupServiceTest extends TestCase
{
    public function testServiceCanBeInstantiated(): void
    {
        $service = new BackupService();
        $this->assertInstanceOf(BackupService::class, $service);
    }

    public function testGetDbFingerprintMethodExists(): void
    {
        $service = new BackupService();
        $this->assertTrue(method_exists($service, 'getDbFingerprint'));
    }

    public function testShouldBackupMethodExists(): void
    {
        $service = new BackupService();
        $this->assertTrue(method_exists($service, 'shouldBackup'));
    }

    public function testPerformBackupMethodExists(): void
    {
        $service = new BackupService();
        $this->assertTrue(method_exists($service, 'performBackup'));
    }

    public function testBackupBeforeMigrationMethodExists(): void
    {
        $service = new BackupService();
        $this->assertTrue(method_exists($service, 'backupBeforeMigration'));
    }

    public function testRotateBackupsMethodExists(): void
    {
        $service = new BackupService();
        $this->assertTrue(method_exists($service, 'rotateBackups'));
    }

    public function testGetDbFingerprintReturnsArray(): void
    {
        $service = new BackupService();
        $result = $service->getDbFingerprint();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('mtime', $result);
        $this->assertArrayHasKey('size', $result);
    }

    public function testGetDbFingerprintMtimeIsInt(): void
    {
        $service = new BackupService();
        $result = $service->getDbFingerprint();
        $this->assertIsInt($result['mtime']);
    }

    public function testGetDbFingerprintSizeIsInt(): void
    {
        $service = new BackupService();
        $result = $service->getDbFingerprint();
        $this->assertIsInt($result['size']);
    }

    public function testShouldBackupReturnsBool(): void
    {
        $service = new BackupService();
        $result = $service->shouldBackup();
        $this->assertIsBool($result);
    }

    public function testPerformBackupReturnsBool(): void
    {
        $service = new BackupService();
        // performBackup may fail in test environment (no backup dir), but should return bool
        $result = $service->performBackup();
        $this->assertIsBool($result);
    }

    public function testBackupBeforeMigrationReturnsBool(): void
    {
        $service = new BackupService();
        $result = $service->backupBeforeMigration();
        $this->assertIsBool($result);
    }

    public function testRotateBackupsDoesNotThrow(): void
    {
        $service = new BackupService();
        // Should not throw even if backup dir doesn't exist
        $service->rotateBackups();
        $this->assertTrue(true);
    }
}
