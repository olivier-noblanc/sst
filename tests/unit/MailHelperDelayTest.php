<?php
/**
 * Mail Helper Unit Tests — Delay Alert Email
 *
 * Tests mail functions from src/mail.php:
 * - buildDelayAlertEmail()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/mail.php';

class MailHelperDelayTest extends TestCase
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
        $this->pdo->exec('DELETE FROM config_app');
        clearConfigCache();
    }

    // ─── buildDelayAlertEmail ───────────────────────────────────────────────

    public function testBuildDelayAlertEmailContainsHtmlTags(): void
    {
        $siteData = ['site_code' => 'UR21', 'site_nom' => 'Dijon', 'reports' => []];
        $body = buildDelayAlertEmail($siteData, 5);
        $this->assertStringStartsWith('<html><body>', $body);
        $this->assertStringEndsWith('</body></html>', $body);
    }

    public function testBuildDelayAlertEmailContainsAlertTitle(): void
    {
        $siteData = ['site_code' => 'UR21', 'site_nom' => 'Dijon', 'reports' => []];
        $body = buildDelayAlertEmail($siteData, 5);
        $this->assertStringContainsString('Alerte de délai de traitement', $body);
    }

    public function testBuildDelayAlertEmailContainsDelayDays(): void
    {
        $siteData = ['site_code' => 'UR21', 'site_nom' => 'Dijon', 'reports' => []];
        $body = buildDelayAlertEmail($siteData, 7);
        $this->assertStringContainsString('7 jour(s)', $body);
    }

    public function testBuildDelayAlertEmailContainsSiteInfo(): void
    {
        $siteData = ['site_code' => 'UR21', 'site_nom' => 'Dijon', 'reports' => []];
        $body = buildDelayAlertEmail($siteData, 5);
        $this->assertStringContainsString('UR21', $body);
        $this->assertStringContainsString('Dijon', $body);
    }

    public function testBuildDelayAlertEmailContainsReportTable(): void
    {
        $siteData = [
            'site_code' => 'UR21', 'site_nom' => 'Dijon',
            'reports' => [
                ['reference' => 'rsst-25-001', 'type' => 'rsst', 'objet' => 'Test objet',
                 'declarant_prenom' => 'Jean', 'declarant_nom' => 'Martin',
                 'created_at' => '2025-01-15 10:00:00'],
            ],
        ];
        $body = buildDelayAlertEmail($siteData, 5);
        $this->assertStringContainsString('rsst-25-001', $body);
        $this->assertStringContainsString('RSST', $body);
        $this->assertStringContainsString('Test objet', $body);
        $this->assertStringContainsString('Jean Martin', $body);
        $this->assertStringContainsString('15/01/2025 à 11:00', $body);
    }

    public function testBuildDelayAlertEmailWithMultipleReports(): void
    {
        $siteData = [
            'site_code' => 'UR21', 'site_nom' => 'Dijon',
            'reports' => [
                ['reference' => 'rsst-25-001', 'type' => 'rsst', 'objet' => 'First report',
                 'declarant_prenom' => 'Jean', 'declarant_nom' => 'Martin',
                 'created_at' => '2025-01-15 10:00:00'],
                ['reference' => 'rami-25-002', 'type' => 'rami', 'objet' => 'Second report',
                 'declarant_prenom' => 'Marie', 'declarant_nom' => 'Dupont',
                 'created_at' => '2025-01-16 11:00:00'],
            ],
        ];
        $body = buildDelayAlertEmail($siteData, 5);
        $this->assertStringContainsString('rsst-25-001', $body);
        $this->assertStringContainsString('rami-25-002', $body);
        $this->assertStringContainsString('RAMI', $body);
        $this->assertStringContainsString('First report', $body);
        $this->assertStringContainsString('Second report', $body);
    }

    public function testBuildDelayAlertEmailContainsTableHeaders(): void
    {
        $siteData = ['site_code' => 'UR21', 'site_nom' => 'Dijon', 'reports' => []];
        $body = buildDelayAlertEmail($siteData, 5);
        $this->assertStringContainsString('Réf.', $body);
        $this->assertStringContainsString('Registre', $body);
        $this->assertStringContainsString('Objet', $body);
        $this->assertStringContainsString('Déclarant', $body);
        $this->assertStringContainsString('Créé le', $body);
    }

    public function testBuildDelayAlertEmailContainsFooter(): void
    {
        $siteData = ['site_code' => 'UR21', 'site_nom' => 'Dijon', 'reports' => []];
        $body = buildDelayAlertEmail($siteData, 5);
        $this->assertStringContainsString('Cet e-mail a été envoyé automatiquement', $body);
    }

    public function testBuildDelayAlertEmailWithEmptyReports(): void
    {
        $siteData = ['site_code' => 'UR21', 'site_nom' => 'Dijon', 'reports' => []];
        $body = buildDelayAlertEmail($siteData, 3);
        $this->assertStringContainsString('<table', $body);
        $this->assertStringContainsString('</table>', $body);
        $this->assertStringContainsString('3 jour(s)', $body);
    }

    public function testBuildDelayAlertEmailEscapesContent(): void
    {
        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => '<script>Dijon</script>',
            'reports' => [
                ['reference' => 'rsst-25-001', 'type' => 'rsst',
                 'objet' => '<b>bold</b>', 'declarant_prenom' => 'Jean',
                 'declarant_nom' => 'Martin', 'created_at' => '2025-01-15'],
            ],
        ];
        $body = buildDelayAlertEmail($siteData, 5);
        $this->assertStringContainsString('&lt;script&gt;Dijon&lt;/script&gt;', $body);
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $body);
    }
}
