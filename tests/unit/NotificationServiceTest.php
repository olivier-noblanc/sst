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
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use App\Services\NotificationService;

class NotificationServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM notification_settings');
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

    /**
     * Audit #79 — this class (and every other notify* test above) only
     * checked method_exists()/ReflectionMethod, never actually called the
     * method. notifyReportAbandon/notifyReportReopen above DO call the real
     * function — but that only worked because some Mail*Test.php file
     * (alphabetically before this one — MailHelperTest, AuditConfigMailTest,
     * etc.) already did `require_once mail.php` first, in the same shared
     * PHPUnit process (see tests/bootstrap.php: one static $db, one PHP
     * process for the whole suite). NotificationService itself never loaded
     * its own dependency, so in a real request that instantiates it without
     * any of those other files ever having run, every notify* call fatals
     * with "Call to undefined function App\Services\notifyNewReport()" —
     * exactly what happened in production. These 3 close the gap by
     * asserting the real call succeeds, independent of suite ordering.
     *
     * testNotifyNewReportReturnsEarlyForUnknownUuid below additionally runs
     * in its own process (#[RunInSeparateProcess]) — the only way to
     * actually prove NotificationService loads its own dependency, since
     * every other test in this method-in-a-shared-process suite would pass
     * regardless, as long as ANY alphabetically-earlier test file happens to
     * require mail.php first.
     */
    #[RunInSeparateProcess]
    public function testNotifyNewReportReturnsEarlyForUnknownUuid(): void
    {
        $service = new NotificationService($this->pdo);
        $service->notifyNewReport('nonexistent-uuid', 'rsst', 1);
        $this->assertTrue(true); // No exception = pass
    }

    public function testNotifyReportResponseReturnsEarlyForUnknownUuid(): void
    {
        $service = new NotificationService($this->pdo);
        $service->notifyReportResponse('nonexistent-uuid', 1);
        $this->assertTrue(true); // No exception = pass
    }

    public function testNotifyRoleChangeReturnsEarlyForUnknownUserId(): void
    {
        $service = new NotificationService($this->pdo);
        $service->notifyRoleChange(999999, 'agent', 'superviseur');
        $this->assertTrue(true); // No exception = pass
    }

    /**
     * Oracle — INVARIANT déclarant sans email : users.email est légalement
     * nullable (?string en création/édition, validation permissive). Les
     * agents rattachés doivent recevoir leur notification MÊME si le
     * déclarant n'a pas d'email — l'ancien return précoce les privait de
     * tout message. Le helper pur est testé sans SMTP.
     */
    public function testBuildResponseNotificationTargetsKeepsLinkedAgentsWhenDeclarantHasNoEmail(): void
    {
        $targets = buildResponseNotificationTargets(
            ['email' => null],
            [
                ['email' => 'agent.rattache@dreets-bfc.gouv.fr', 'prenom' => 'Al'],
                ['email' => 'autre.rattache@dreets-bfc.gouv.fr', 'prenom' => 'Bob'],
            ]
        );

        $emails = array_column($targets, 'email');
        $this->assertContains('agent.rattache@dreets-bfc.gouv.fr', $emails, 'Les rattachés reçoivent leur notification même sans email déclarant');
        $this->assertContains('autre.rattache@dreets-bfc.gouv.fr', $emails);
        $this->assertNotContains('declarant', array_column($targets, 'role'), 'Aucun envoi au déclarant sans email');
    }

    public function testBuildResponseNotificationTargetsDeduplicatesAgainstDeclarant(): void
    {
        $targets = buildResponseNotificationTargets(
            ['email' => 'declarant@dreets-bfc.gouv.fr'],
            [
                ['email' => 'DECLARANT@dreets-bfc.gouv.fr', 'prenom' => 'Self'],
                ['email' => 'agent.rattache@dreets-bfc.gouv.fr', 'prenom' => 'Al'],
            ]
        );

        $roles = array_column($targets, 'role');
        $this->assertSame(['declarant', 'linked'], $roles, 'Le déclarant reçoit son e-mail ; le rattaché au même email (casse différente) est dédupliqué');
    }

    public function testBuildResponseNotificationTargetsDeclarantOnlyWithoutAgents(): void
    {
        $targets = buildResponseNotificationTargets(['email' => 'declarant@dreets-bfc.gouv.fr'], []);
        $this->assertSame([['email' => 'declarant@dreets-bfc.gouv.fr', 'role' => 'declarant']], $targets);
    }
}
