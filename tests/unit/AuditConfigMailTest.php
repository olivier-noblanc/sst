<?php
/**
 * Audit & Config Unit Tests — Delay Alert Email
 *
 * Tests from src/mail.php in the context of audit/config:
 * - buildDelayAlertEmail()
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SessionUser;

require_once __DIR__ . '/../../src/audit.php';
require_once __DIR__ . '/../../src/mail.php';

class AuditConfigMailTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM audit_log');
        $this->pdo->exec('DELETE FROM config_app');
        setUserSession(SessionUser::fromArray(['id' => 1, 'username' => 'testuser']));
    }

    protected function tearDown(): void
    {
        clearConfigCache();
    }

    public function testBuildDelayAlertEmailContainsSiteInfo(): void
    {
        updateConfig($this->pdo, 'app_nom_organisation', 'DREETS BFC');
        clearConfigCache();
        $siteData = [
            'site_code' => 'UR21', 'site_nom' => 'UR Côte-d\'Or',
            'reports' => [
                ['reference' => 'rsst-25-001', 'type' => 'rsst', 'objet' => 'Chute dans les escaliers',
                 'declarant_prenom' => 'Jean', 'declarant_nom' => 'Martin',
                 'created_at' => '2025-06-01 09:00:00'],
            ],
        ];
        $html = buildDelayAlertEmail($siteData, 7);
        $this->assertStringContainsString('UR21', $html);
        $this->assertStringContainsString('UR Côte-d', $html);
        $this->assertStringContainsString('7', $html);
        $this->assertStringContainsString('rsst-25-001', $html);
        $this->assertStringContainsString('Chute dans les escaliers', $html);
        $this->assertStringContainsString('Jean Martin', $html);
    }

    public function testBuildDelayAlertEmailWithMultipleReports(): void
    {
        updateConfig($this->pdo, 'app_nom_organisation', 'DREETS BFC');
        clearConfigCache();
        $siteData = [
            'site_code' => 'UR25', 'site_nom' => 'UR Doubs',
            'reports' => [
                ['reference' => 'rsst-25-001', 'type' => 'rsst', 'objet' => 'Objet 1',
                 'declarant_prenom' => 'A', 'declarant_nom' => 'B', 'created_at' => '2025-06-01'],
                ['reference' => 'dgi-25-003', 'type' => 'dgi', 'objet' => 'Objet 2',
                 'declarant_prenom' => 'C', 'declarant_nom' => 'D', 'created_at' => '2025-06-02'],
            ],
        ];
        $html = buildDelayAlertEmail($siteData, 5);
        $this->assertStringContainsString('rsst-25-001', $html);
        $this->assertStringContainsString('dgi-25-003', $html);
        $this->assertStringContainsString('Objet 1', $html);
        $this->assertStringContainsString('Objet 2', $html);
    }

    public function testBuildDelayAlertEmailEscapesHtml(): void
    {
        updateConfig($this->pdo, 'app_nom_organisation', 'DREETS BFC');
        clearConfigCache();
        $siteData = [
            'site_code' => 'UR21', 'site_nom' => '<script>alert(1)</script>',
            'reports' => [
                ['reference' => 'rsst-25-001', 'type' => 'rsst',
                 'objet' => '<img src=x onerror=alert(1)>',
                 'declarant_prenom' => 'Test', 'declarant_nom' => 'User',
                 'created_at' => '2025-06-01'],
            ],
        ];
        $html = buildDelayAlertEmail($siteData, 7);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }
}
