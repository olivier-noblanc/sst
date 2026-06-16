<?php
/**
 * Notification Queries Unit Tests — Application SST DREETS BFC
 *
 * Tests the notification-related functions in stats_queries.php
 * against an in-memory SQLite database.
 */

use PHPUnit\Framework\TestCase;

// Load the queries file that contains notification functions
require_once __DIR__ . '/../../src/queries/stats_queries.php';

class NotificationQueriesTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        // Clean up in reverse FK dependency order
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');
    }

    // ─── getNotificationSettings ─────────────────────────────────────────────

    public function testGetNotificationSettingsReturnsEmptyWhenNone(): void
    {
        $result = getNotificationSettings($this->pdo);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetNotificationSettingsReturnsSiteSettings(): void
    {
        $siteId = createSite($this->pdo, 'UR21', "UR Côte-d'Or", "Côte-d'Or");

        saveNotificationSetting($this->pdo, $siteId, 'site', 'all', 'agent@dreets.gouv.fr');
        saveNotificationSetting($this->pdo, $siteId, 'site', 'rsst', 'rsst@dreets.gouv.fr');

        $result = getNotificationSettings($this->pdo);
        $this->assertCount(2, $result);

        // Both should be type=site
        $types = array_column($result, 'type');
        $this->assertEquals(['site', 'site'], $types);
    }

    public function testGetNotificationSettingsReturnsGlobalSettings(): void
    {
        saveNotificationSetting($this->pdo, null, 'global', 'all', 'global@dreets.gouv.fr');

        $result = getNotificationSettings($this->pdo);
        $this->assertCount(1, $result);
        $this->assertEquals('global', $result[0]['type']);
        $this->assertEquals('global@dreets.gouv.fr', $result[0]['email']);
    }

    public function testGetNotificationSettingsReturnsMixedTypes(): void
    {
        $siteId = createSite($this->pdo, 'UR25', 'UR Doubs', 'Doubs');

        saveNotificationSetting($this->pdo, $siteId, 'site', 'all', 'site@dreets.gouv.fr');
        saveNotificationSetting($this->pdo, null, 'global', 'all', 'global@dreets.gouv.fr');

        $result = getNotificationSettings($this->pdo);
        $this->assertCount(2, $result);

        // Global should come first (ORDER BY ns.type)
        $this->assertEquals('global', $result[0]['type']);
        $this->assertEquals('site', $result[1]['type']);
    }

    // ─── saveNotificationSetting / deleteNotificationSetting ─────────────────

    public function testSaveNotificationSettingReturnsInsertId(): void
    {
        $id = saveNotificationSetting($this->pdo, null, 'global', 'all', 'test@dreets.gouv.fr');
        $this->assertGreaterThan(0, $id);
    }

    public function testDeleteNotificationSetting(): void
    {
        $id = saveNotificationSetting($this->pdo, null, 'global', 'all', 'test@dreets.gouv.fr');
        $result = deleteNotificationSetting($this->pdo, $id);
        $this->assertTrue($result);

        // Should be gone
        $settings = getNotificationSettings($this->pdo);
        $this->assertEmpty($settings);
    }

    // ─── getSiteNotificationEmails / getGlobalNotificationEmails ─────────────

    public function testGetSiteNotificationEmails(): void
    {
        $siteId = createSite($this->pdo, 'UR21', "UR Côte-d'Or", "Côte-d'Or");

        saveNotificationSetting($this->pdo, $siteId, 'site', 'all', 'a@dreets.gouv.fr');
        saveNotificationSetting($this->pdo, $siteId, 'site', 'all', 'b@dreets.gouv.fr');
        saveNotificationSetting($this->pdo, null, 'global', 'all', 'global@dreets.gouv.fr');

        $emails = getSiteNotificationEmails($this->pdo, $siteId);
        $this->assertCount(2, $emails);
        $this->assertContains('a@dreets.gouv.fr', $emails);
        $this->assertContains('b@dreets.gouv.fr', $emails);
        // Global should not appear
        $this->assertNotContains('global@dreets.gouv.fr', $emails);
    }

    public function testGetGlobalNotificationEmails(): void
    {
        $siteId = createSite($this->pdo, 'UR25', 'UR Doubs', 'Doubs');

        saveNotificationSetting($this->pdo, null, 'global', 'all', 'global1@dreets.gouv.fr');
        saveNotificationSetting($this->pdo, null, 'global', 'all', 'global2@dreets.gouv.fr');
        saveNotificationSetting($this->pdo, $siteId, 'site', 'all', 'site@dreets.gouv.fr');

        $emails = getGlobalNotificationEmails($this->pdo);
        $this->assertCount(2, $emails);
        $this->assertContains('global1@dreets.gouv.fr', $emails);
        $this->assertContains('global2@dreets.gouv.fr', $emails);
        // Site email should not appear
        $this->assertNotContains('site@dreets.gouv.fr', $emails);
    }

    // ─── deleteNotificationSettingsByType ────────────────────────────────────

    public function testDeleteNotificationSettingsByTypeOnlyDeletesMatching(): void
    {
        $siteId = createSite($this->pdo, 'UR21', "UR Côte-d'Or", "Côte-d'Or");

        saveNotificationSetting($this->pdo, $siteId, 'site', 'all', 'site@dreets.gouv.fr');
        saveNotificationSetting($this->pdo, null, 'global', 'all', 'global@dreets.gouv.fr');

        // Delete only site settings
        $deleted = deleteNotificationSettingsByType($this->pdo, 'site');
        $this->assertEquals(1, $deleted);

        // Global should remain
        $settings = getNotificationSettings($this->pdo);
        $this->assertCount(1, $settings);
        $this->assertEquals('global', $settings[0]['type']);
    }

    // ─── Integration: simulates the settings page data flow ─────────────────

    public function testSettingsPageOrganizationFlow(): void
    {
        $siteId1 = createSite($this->pdo, 'UR21', "UR Côte-d'Or", "Côte-d'Or");
        $siteId2 = createSite($this->pdo, 'UR25', 'UR Doubs', 'Doubs');

        saveNotificationSetting($this->pdo, $siteId1, 'site', 'all', 'a@dreets.gouv.fr');
        saveNotificationSetting($this->pdo, $siteId2, 'site', 'all', 'b@dreets.gouv.fr');
        saveNotificationSetting($this->pdo, null, 'global', 'all', 'g@dreets.gouv.fr');

        // Reproduce the exact logic from settings.php lines 22-35
        $currentSettings = getNotificationSettings($this->pdo);
        $siteEmails = [];
        $globalEmails = [];

        foreach ($currentSettings as $setting) {
            if ($setting['type'] === 'global') {
                $globalEmails[] = $setting;
            } else {
                $sId = (int) $setting['site_id'];
                if (!isset($siteEmails[$sId])) {
                    $siteEmails[$sId] = [];
                }
                $siteEmails[$sId][] = $setting;
            }
        }

        // Verify organization
        $this->assertCount(1, $globalEmails);
        $this->assertEquals('g@dreets.gouv.fr', $globalEmails[0]['email']);
        $this->assertCount(2, $siteEmails);
        $this->assertCount(1, $siteEmails[$siteId1]);
        $this->assertCount(1, $siteEmails[$siteId2]);
        $this->assertEquals('a@dreets.gouv.fr', $siteEmails[$siteId1][0]['email']);
        $this->assertEquals('b@dreets.gouv.fr', $siteEmails[$siteId2][0]['email']);
    }
}
