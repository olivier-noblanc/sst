<?php
/**
 * Site Repository Unit Tests — Application SST DREETS BFC
 *
 * Tests SiteRepository CRUD operations against an in-memory SQLite database.
 */

use App\Repository\SiteRepository;
use PHPUnit\Framework\TestCase;

class SiteQueriesTest extends TestCase
{
    private SiteRepository $sites;

    protected function setUp(): void
    {
        $pdo = getDB();
        cleanupAllForTest($pdo);
        $pdo->exec('DELETE FROM notification_settings');
        $pdo->exec('DELETE FROM sites');
        $pdo->exec('DELETE FROM config_app');

        $this->sites = SiteRepository::instance();
    }

    public function testCreateSiteAndRetrieve(): void
    {
        $id = $this->sites->create('UR21', "UR Côte-d'Or", "Côte-d'Or");
        $this->assertGreaterThan(0, $id);

        $site = $this->sites->findById($id);
        $this->assertNotNull($site);
        $this->assertEquals('UR21', $site['code']);
        $this->assertEquals("UR Côte-d'Or", $site['nom']);
    }

    public function testGetSiteByCode(): void
    {
        $this->sites->create('UR25', 'UR Doubs', 'Doubs');

        $site = $this->sites->findByCode('UR25');
        $this->assertNotNull($site);
        $this->assertEquals('UR Doubs', $site['nom']);
    }

    public function testGetSiteByCodeReturnsNullForNonexistent(): void
    {
        $site = $this->sites->findByCode('FAKE');
        $this->assertNull($site);
    }

    public function testUpdateSite(): void
    {
        $id = $this->sites->create('UR21', 'Old Name', 'Old Dept');

        $result = $this->sites->update($id, 'UR21', 'New Name', 'New Dept');
        $this->assertTrue($result);

        $site = $this->sites->findById($id);
        $this->assertEquals('New Name', $site['nom']);
        $this->assertEquals('New Dept', $site['departement']);
    }

    public function testToggleSiteActive(): void
    {
        $id = $this->sites->create('UR89', 'UR Yonne', 'Yonne');

        $this->sites->toggleActive($id, false);
        $site = $this->sites->findById($id);
        $this->assertEquals(0, (int) $site['is_active']);

        $this->sites->toggleActive($id, true);
        $site = $this->sites->findById($id);
        $this->assertEquals(1, (int) $site['is_active']);
    }

    public function testDeleteSiteWhenEmpty(): void
    {
        $id = $this->sites->create('TEMP', 'Temp Site', '');

        $result = $this->sites->delete($id);
        $this->assertTrue($result);

        $site = $this->sites->findById($id);
        $this->assertNull($site);
    }
}
