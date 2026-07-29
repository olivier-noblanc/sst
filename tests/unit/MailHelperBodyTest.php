<?php
/**
 * Mail Helper Unit Tests — Email Body
 *
 * Tests mail functions from src/mail/email_renderer.php:
 * - renderEmailBody()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class MailHelperBodyTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        // Clean tables
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');
        clearConfigCache();
    }

    // ─── renderEmailBody ─────────────────────────────────────────────────────

    public function testRenderEmailBodyContainsHtmlTags(): void
    {
        $body = renderEmailBody('Test Title', '<p>Content</p>');
        $this->assertStringStartsWith('<html><body', $body);
        $this->assertStringEndsWith('</body></html>', $body);
    }

    public function testRenderEmailBodyContainsTitle(): void
    {
        $body = renderEmailBody('Nouveau signalement', '<p>Content</p>');
        $this->assertStringContainsString('<h2', $body);
        $this->assertStringContainsString('Nouveau signalement</h2>', $body);
    }

    public function testRenderEmailBodyContainsContent(): void
    {
        $body = renderEmailBody('Title', '<p>Some important content</p>');
        $this->assertStringContainsString('<p>Some important content</p>', $body);
    }

    public function testRenderEmailBodyContainsFooter(): void
    {
        $body = renderEmailBody('Title', '<p>Content</p>');
        $this->assertStringContainsString('Cet e-mail a été envoyé automatiquement', $body);
        $this->assertStringContainsString('Ne pas répondre directement à ce message', $body);
    }

    public function testRenderEmailBodyWithCustomSiteName(): void
    {
        $body = renderEmailBody('Title', '<p>Content</p>', 'Mon Organisation');
        $this->assertStringContainsString('Mon Organisation', $body);
    }

    public function testRenderEmailBodyWithConfigSiteName(): void
    {
        updateConfig($this->pdo, 'app_nom_organisation', 'Test Org');
        clearConfigCache();

        $body = renderEmailBody('Title', '<p>Content</p>');
        $this->assertStringContainsString('Test Org', $body);
    }

    public function testRenderEmailBodyEscapesTitle(): void
    {
        $body = renderEmailBody('<script>alert(1)</script>', '<p>Content</p>');
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
    }

    public function testRenderEmailBodyContainsHorizontalRule(): void
    {
        $body = renderEmailBody('Title', '<p>Content</p>');
        $this->assertStringContainsString('<hr', $body);
    }

    public function testRenderEmailBodyHasMaxWidth(): void
    {
        $body = renderEmailBody('Title', '<p>Content</p>');
        $this->assertStringContainsString('max-width:600px', $body);
    }

    public function testRenderEmailBodyHasBrandColor(): void
    {
        updateConfig($this->pdo, 'app_brand_color', '#ff0000');
        clearConfigCache();

        $body = renderEmailBody('Title', '<p>Content</p>');
        $this->assertStringContainsString('#ff0000', $body);
    }

    public function testRenderEmailBodyDefaultBrandColor(): void
    {
        $body = renderEmailBody('Title', '<p>Content</p>');
        $this->assertStringContainsString('#1e40af', $body);
    }
}
