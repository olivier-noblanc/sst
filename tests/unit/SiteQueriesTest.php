<?php
/**
 * Site Queries Unit Tests — Application SST DREETS BFC
 *
 * Tests the site_queries.php functions against an in-memory SQLite database.
 */

use PHPUnit\Framework\TestCase;

// Load site queries
require_once __DIR__ . '/../../src/queries/site_queries.php';

class SiteQueriesTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        // Delete in reverse FK dependency order to avoid constraint violations
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');
    }

    // ─── Site CRUD ────────────────────────────────────────────────────────────

    public function testCreateSiteAndRetrieve(): void
    {
        $id = createSite($this->pdo, 'UR21', "UR Côte-d'Or", "Côte-d'Or");
        $this->assertGreaterThan(0, $id);

        $site = getSiteById($this->pdo, $id);
        $this->assertNotNull($site);
        $this->assertEquals('UR21', $site['code']);
        $this->assertEquals("UR Côte-d'Or", $site['nom']);
    }

    public function testGetSiteByCode(): void
    {
        createSite($this->pdo, 'UR25', 'UR Doubs', 'Doubs');

        $site = getSiteByCode($this->pdo, 'UR25');
        $this->assertNotNull($site);
        $this->assertEquals('UR Doubs', $site['nom']);
    }

    public function testGetSiteByCodeReturnsNullForNonexistent(): void
    {
        $site = getSiteByCode($this->pdo, 'FAKE');
        $this->assertNull($site);
    }

    public function testUpdateSite(): void
    {
        $id = createSite($this->pdo, 'UR21', 'Old Name', 'Old Dept');

        $result = updateSite($this->pdo, $id, 'UR21', 'New Name', 'New Dept');
        $this->assertTrue($result);

        $site = getSiteById($this->pdo, $id);
        $this->assertEquals('New Name', $site['nom']);
        $this->assertEquals('New Dept', $site['departement']);
    }

    public function testToggleSiteActive(): void
    {
        $id = createSite($this->pdo, 'UR89', 'UR Yonne', 'Yonne');

        toggleSiteActive($this->pdo, $id, false);
        $site = getSiteById($this->pdo, $id);
        $this->assertEquals(0, (int) $site['is_active']);

        toggleSiteActive($this->pdo, $id, true);
        $site = getSiteById($this->pdo, $id);
        $this->assertEquals(1, (int) $site['is_active']);
    }

    public function testDeleteSiteWhenEmpty(): void
    {
        $id = createSite($this->pdo, 'TEMP', 'Temp Site', '');

        $result = deleteSite($this->pdo, $id);
        $this->assertTrue($result);

        $site = getSiteById($this->pdo, $id);
        $this->assertNull($site);
    }
}
