<?php
/**
 * Mail Helper Unit Tests — Application SST DREETS BFC
 *
 * Tests mail functions from src/mail.php:
 * - getNotificationRecipients()
 * - getBaseUrl()
 * - buildEmailBody()
 * - buildDelayAlertEmail()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/mail.php';

class MailHelperTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        // Clean tables
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');
        clearConfigCache();
    }

    // ─── getNotificationRecipients ──────────────────────────────────────────

    public function testGetNotificationRecipientsWithNoSettings(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');
        $result = getNotificationRecipients($this->pdo, $siteId);
        $this->assertEquals([], $result);
    }

    public function testGetNotificationRecipientsPerSiteOnly(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');

        $stmt = $this->pdo->prepare(
            "INSERT INTO notification_settings (site_id, type, registry, email) VALUES (:site_id, 'site', 'rsst', :email)"
        );
        $stmt->execute([':site_id' => $siteId, ':email' => 'site1@test.gouv.fr']);

        $result = getNotificationRecipients($this->pdo, $siteId);
        $this->assertEquals(['site1@test.gouv.fr'], $result);
    }

    public function testGetNotificationRecipientsGlobalOnly(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');

        $stmt = $this->pdo->prepare(
            "INSERT INTO notification_settings (site_id, type, registry, email) VALUES (NULL, 'global', 'rsst', :email)"
        );
        $stmt->execute([':email' => 'global@test.gouv.fr']);

        $result = getNotificationRecipients($this->pdo, $siteId);
        $this->assertEquals(['global@test.gouv.fr'], $result);
    }

    public function testGetNotificationRecipientsPerSiteAndGlobal(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');

        $stmt = $this->pdo->prepare(
            "INSERT INTO notification_settings (site_id, type, registry, email) VALUES (:site_id, 'site', 'rsst', :email)"
        );
        $stmt->execute([':site_id' => $siteId, ':email' => 'site1@test.gouv.fr']);

        $stmt = $this->pdo->prepare(
            "INSERT INTO notification_settings (site_id, type, registry, email) VALUES (NULL, 'global', 'rsst', :email)"
        );
        $stmt->execute([':email' => 'global@test.gouv.fr']);

        $result = getNotificationRecipients($this->pdo, $siteId);
        $this->assertCount(2, $result);
        $this->assertContains('site1@test.gouv.fr', $result);
        $this->assertContains('global@test.gouv.fr', $result);
    }

    public function testGetNotificationRecipientsDeduplicatesEmails(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');

        // Same email in both site and global
        $stmt = $this->pdo->prepare(
            "INSERT INTO notification_settings (site_id, type, registry, email) VALUES (:site_id, 'site', 'rsst', :email)"
        );
        $stmt->execute([':site_id' => $siteId, ':email' => 'shared@test.gouv.fr']);

        $stmt = $this->pdo->prepare(
            "INSERT INTO notification_settings (site_id, type, registry, email) VALUES (NULL, 'global', 'rsst', :email)"
        );
        $stmt->execute([':email' => 'shared@test.gouv.fr']);

        $result = getNotificationRecipients($this->pdo, $siteId);
        $this->assertCount(1, $result);
        $this->assertEquals(['shared@test.gouv.fr'], $result);
    }

    public function testGetNotificationRecipientsOnlyReturnsMatchingSite(): void
    {
        $site1 = createSite($this->pdo, 'UR21', 'UR Test 1', 'Test1');
        $site2 = createSite($this->pdo, 'UR58', 'UR Test 2', 'Test2');

        $stmt = $this->pdo->prepare(
            "INSERT INTO notification_settings (site_id, type, registry, email) VALUES (:site_id, 'site', 'rsst', :email)"
        );
        $stmt->execute([':site_id' => $site1, ':email' => 'site1@test.gouv.fr']);
        $stmt->execute([':site_id' => $site2, ':email' => 'site2@test.gouv.fr']);

        $result = getNotificationRecipients($this->pdo, $site1);
        $this->assertEquals(['site1@test.gouv.fr'], $result);
    }

    public function testGetNotificationRecipientsMultipleSiteEmails(): void
    {
        $siteId = createSite($this->pdo, 'UR21', 'UR Test', 'Test');

        $stmt = $this->pdo->prepare(
            "INSERT INTO notification_settings (site_id, type, registry, email) VALUES (:site_id, 'site', 'rsst', :email)"
        );
        $stmt->execute([':site_id' => $siteId, ':email' => 'email1@test.gouv.fr']);
        $stmt->execute([':site_id' => $siteId, ':email' => 'email2@test.gouv.fr']);

        $result = getNotificationRecipients($this->pdo, $siteId);
        $this->assertCount(2, $result);
    }

    // ─── getBaseUrl ─────────────────────────────────────────────────────────

    public function testGetBaseUrlWithHttp(): void
    {
        $_SERVER['HTTPS'] = '';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertEquals('http://example.com', getBaseUrl());
    }

    public function testGetBaseUrlWithHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertEquals('https://example.com', getBaseUrl());
    }

    public function testGetBaseUrlWithHttpsOff(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertEquals('http://example.com', getBaseUrl());
    }

    public function testGetBaseUrlWithPort(): void
    {
        $_SERVER['HTTPS'] = '';
        $_SERVER['HTTP_HOST'] = 'localhost:8080';
        $this->assertEquals('http://localhost:8080', getBaseUrl());
    }

    public function testGetBaseUrlWithMissingHost(): void
    {
        $_SERVER['HTTPS'] = '';
        unset($_SERVER['HTTP_HOST']);
        $this->assertEquals('http://localhost', getBaseUrl());
    }

    // ─── buildEmailBody ─────────────────────────────────────────────────────

    public function testBuildEmailBodyContainsHtmlTags(): void
    {
        $body = buildEmailBody('Test Title', '<p>Content</p>');
        $this->assertStringStartsWith('<html><body>', $body);
        $this->assertStringEndsWith('</body></html>', $body);
    }

    public function testBuildEmailBodyContainsTitle(): void
    {
        $body = buildEmailBody('Nouveau signalement', '<p>Content</p>');
        $this->assertStringContainsString('<h2>Nouveau signalement</h2>', $body);
    }

    public function testBuildEmailBodyContainsContent(): void
    {
        $body = buildEmailBody('Title', '<p>Some important content</p>');
        $this->assertStringContainsString('<p>Some important content</p>', $body);
    }

    public function testBuildEmailBodyContainsFooter(): void
    {
        $body = buildEmailBody('Title', '<p>Content</p>');
        $this->assertStringContainsString('Cet e-mail a été envoyé automatiquement', $body);
        $this->assertStringContainsString('Ne pas répondre directement à ce message', $body);
    }

    public function testBuildEmailBodyWithCustomSiteName(): void
    {
        $body = buildEmailBody('Title', '<p>Content</p>', 'Mon Organisation');
        $this->assertStringContainsString('Mon Organisation', $body);
    }

    public function testBuildEmailBodyWithConfigSiteName(): void
    {
        updateConfig($this->pdo, 'app_nom_organisation', 'Test Org');
        clearConfigCache();

        $body = buildEmailBody('Title', '<p>Content</p>');
        $this->assertStringContainsString('Test Org', $body);
    }

    public function testBuildEmailBodyEscapesTitle(): void
    {
        $body = buildEmailBody('<script>alert(1)</script>', '<p>Content</p>');
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
    }

    public function testBuildEmailBodyContainsHorizontalRule(): void
    {
        $body = buildEmailBody('Title', '<p>Content</p>');
        $this->assertStringContainsString('<hr', $body);
    }

    // ─── buildDelayAlertEmail ───────────────────────────────────────────────

    public function testBuildDelayAlertEmailContainsHtmlTags(): void
    {
        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => 'Dijon',
            'reports' => [],
        ];
        $body = buildDelayAlertEmail($siteData, 5);

        $this->assertStringStartsWith('<html><body>', $body);
        $this->assertStringEndsWith('</body></html>', $body);
    }

    public function testBuildDelayAlertEmailContainsAlertTitle(): void
    {
        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => 'Dijon',
            'reports' => [],
        ];
        $body = buildDelayAlertEmail($siteData, 5);

        $this->assertStringContainsString('Alerte de délai de traitement', $body);
    }

    public function testBuildDelayAlertEmailContainsDelayDays(): void
    {
        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => 'Dijon',
            'reports' => [],
        ];
        $body = buildDelayAlertEmail($siteData, 7);

        $this->assertStringContainsString('7 jour(s)', $body);
    }

    public function testBuildDelayAlertEmailContainsSiteInfo(): void
    {
        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => 'Dijon',
            'reports' => [],
        ];
        $body = buildDelayAlertEmail($siteData, 5);

        $this->assertStringContainsString('UR21', $body);
        $this->assertStringContainsString('Dijon', $body);
    }

    public function testBuildDelayAlertEmailContainsReportTable(): void
    {
        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => 'Dijon',
            'reports' => [
                [
                    'reference' => 'rsst-25-001',
                    'type' => 'rsst',
                    'objet' => 'Test objet',
                    'declarant_prenom' => 'Jean',
                    'declarant_nom' => 'Martin',
                    'created_at' => '2025-01-15 10:00:00',
                ],
            ],
        ];
        $body = buildDelayAlertEmail($siteData, 5);

        $this->assertStringContainsString('rsst-25-001', $body);
        $this->assertStringContainsString('RSST', $body);
        $this->assertStringContainsString('Test objet', $body);
        $this->assertStringContainsString('Jean Martin', $body);
        $this->assertStringContainsString('2025-01-15 10:00:00', $body);
    }

    public function testBuildDelayAlertEmailWithMultipleReports(): void
    {
        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => 'Dijon',
            'reports' => [
                [
                    'reference' => 'rsst-25-001',
                    'type' => 'rsst',
                    'objet' => 'First report',
                    'declarant_prenom' => 'Jean',
                    'declarant_nom' => 'Martin',
                    'created_at' => '2025-01-15 10:00:00',
                ],
                [
                    'reference' => 'rami-25-002',
                    'type' => 'rami',
                    'objet' => 'Second report',
                    'declarant_prenom' => 'Marie',
                    'declarant_nom' => 'Dupont',
                    'created_at' => '2025-01-16 11:00:00',
                ],
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
        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => 'Dijon',
            'reports' => [],
        ];
        $body = buildDelayAlertEmail($siteData, 5);

        $this->assertStringContainsString('Réf.', $body);
        $this->assertStringContainsString('Registre', $body);
        $this->assertStringContainsString('Objet', $body);
        $this->assertStringContainsString('Déclarant', $body);
        $this->assertStringContainsString('Créé le', $body);
    }

    public function testBuildDelayAlertEmailContainsFooter(): void
    {
        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => 'Dijon',
            'reports' => [],
        ];
        $body = buildDelayAlertEmail($siteData, 5);

        $this->assertStringContainsString('Cet e-mail a été envoyé automatiquement', $body);
    }

    public function testBuildDelayAlertEmailWithEmptyReports(): void
    {
        $siteData = [
            'site_code' => 'UR21',
            'site_nom' => 'Dijon',
            'reports' => [],
        ];
        $body = buildDelayAlertEmail($siteData, 3);

        // Should still have the table structure with headers but no data rows
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
                [
                    'reference' => 'rsst-25-001',
                    'type' => 'rsst',
                    'objet' => '<b>bold</b>',
                    'declarant_prenom' => 'Jean',
                    'declarant_nom' => 'Martin',
                    'created_at' => '2025-01-15',
                ],
            ],
        ];
        $body = buildDelayAlertEmail($siteData, 5);

        // Site code/nom is escaped via htmlspecialchars
        $this->assertStringContainsString('&lt;script&gt;Dijon&lt;/script&gt;', $body);
        // Report fields are escaped via htmlspecialchars
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $body);
    }
}
