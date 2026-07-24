<?php
/**
 * Mail Helper Unit Tests — Notification Recipients
 *
 * Tests mail functions from src/mail.php:
 * - getNotificationRecipients()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
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
        $siteId = \App\Repository\SiteRepository::instance()->create('UR21', 'UR Test', 'Test');
        $result = getNotificationRecipients($this->pdo, $siteId);
        $this->assertEquals([], $result);
    }

    public function testGetNotificationRecipientsPerSiteOnly(): void
    {
        $siteId = \App\Repository\SiteRepository::instance()->create('UR21', 'UR Test', 'Test');

        $stmt = $this->pdo->prepare(
            "INSERT INTO notification_settings (site_id, type, registry, email) VALUES (:site_id, 'site', 'rsst', :email)"
        );
        $stmt->execute([':site_id' => $siteId, ':email' => 'site1@test.gouv.fr']);

        $result = getNotificationRecipients($this->pdo, $siteId);
        $this->assertEquals(['site1@test.gouv.fr'], $result);
    }

    public function testGetNotificationRecipientsGlobalOnly(): void
    {
        $siteId = \App\Repository\SiteRepository::instance()->create('UR21', 'UR Test', 'Test');

        $stmt = $this->pdo->prepare(
            "INSERT INTO notification_settings (site_id, type, registry, email) VALUES (NULL, 'global', 'rsst', :email)"
        );
        $stmt->execute([':email' => 'global@test.gouv.fr']);

        $result = getNotificationRecipients($this->pdo, $siteId);
        $this->assertEquals(['global@test.gouv.fr'], $result);
    }

    public function testGetNotificationRecipientsPerSiteAndGlobal(): void
    {
        $siteId = \App\Repository\SiteRepository::instance()->create('UR21', 'UR Test', 'Test');

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
        $siteId = \App\Repository\SiteRepository::instance()->create('UR21', 'UR Test', 'Test');

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
        $site1 = \App\Repository\SiteRepository::instance()->create('UR21', 'UR Test 1', 'Test1');
        $site2 = \App\Repository\SiteRepository::instance()->create('UR58', 'UR Test 2', 'Test2');

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
        $siteId = \App\Repository\SiteRepository::instance()->create('UR21', 'UR Test', 'Test');

        $stmt = $this->pdo->prepare(
            "INSERT INTO notification_settings (site_id, type, registry, email) VALUES (:site_id, 'site', 'rsst', :email)"
        );
        $stmt->execute([':site_id' => $siteId, ':email' => 'email1@test.gouv.fr']);
        $stmt->execute([':site_id' => $siteId, ':email' => 'email2@test.gouv.fr']);

        $result = getNotificationRecipients($this->pdo, $siteId);
        $this->assertCount(2, $result);
    }
}
