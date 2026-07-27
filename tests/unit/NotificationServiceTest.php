<?php
/**
 * NotificationService Unit Tests — Service Layer
 *
 * Tests NotificationService from src/Services/NotificationService.php:
 * - Service instantiation
 * - Method existence and type hints
 * - Wrapper delegation (when possible without full mail setup)
 */

use PHPUnit\Framework\TestCase;
use App\Services\NotificationService;

class NotificationServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
    }

    public function testServiceCanBeInstantiated(): void
    {
        $service = new NotificationService($this->pdo);
        $this->assertInstanceOf(NotificationService::class, $service);
    }

    public function testNotifyNewReportMethodExists(): void
    {
        $service = new NotificationService($this->pdo);
        $this->assertTrue(method_exists($service, 'notifyNewReport'));
    }

    public function testNotifyReportResponseMethodExists(): void
    {
        $service = new NotificationService($this->pdo);
        $this->assertTrue(method_exists($service, 'notifyReportResponse'));
    }

    public function testNotifyReportAbandonMethodExists(): void
    {
        $service = new NotificationService($this->pdo);
        $this->assertTrue(method_exists($service, 'notifyReportAbandon'));
    }

    public function testNotifyReportReopenMethodExists(): void
    {
        $service = new NotificationService($this->pdo);
        $this->assertTrue(method_exists($service, 'notifyReportReopen'));
    }

    public function testNotifyRoleChangeMethodExists(): void
    {
        $service = new NotificationService($this->pdo);
        $this->assertTrue(method_exists($service, 'notifyRoleChange'));
    }

    public function testSendDelayNotificationsMethodExists(): void
    {
        $service = new NotificationService($this->pdo);
        $this->assertTrue(method_exists($service, 'sendDelayNotifications'));
    }

    public function testNotifyNewReportAcceptsCorrectParameters(): void
    {
        $service = new NotificationService($this->pdo);
        $ref = new ReflectionMethod($service, 'notifyNewReport');
        $params = $ref->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('reportUuid', $params[0]->getName());
        $this->assertEquals('type', $params[1]->getName());
        $this->assertEquals('siteId', $params[2]->getName());
    }

    public function testNotifyReportResponseAcceptsCorrectParameters(): void
    {
        $service = new NotificationService($this->pdo);
        $ref = new ReflectionMethod($service, 'notifyReportResponse');
        $params = $ref->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('reportUuid', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());
    }

    public function testNotifyRoleChangeAcceptsCorrectParameters(): void
    {
        $service = new NotificationService($this->pdo);
        $ref = new ReflectionMethod($service, 'notifyRoleChange');
        $params = $ref->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('oldRole', $params[1]->getName());
        $this->assertEquals('newRole', $params[2]->getName());
    }

    public function testNotifyReportAbandonReturnsEarlyForUnknownUuid(): void
    {
        $service = new NotificationService($this->pdo);
        // Should not throw — method returns early when report not found
        $service->notifyReportAbandon('nonexistent-uuid', 1);
        $this->assertTrue(true); // No exception = pass
    }

    public function testNotifyReportReopenReturnsEarlyForUnknownUuid(): void
    {
        $service = new NotificationService($this->pdo);
        // Should not throw — method returns early when report not found
        $service->notifyReportReopen('nonexistent-uuid', 1);
        $this->assertTrue(true); // No exception = pass
    }
}
