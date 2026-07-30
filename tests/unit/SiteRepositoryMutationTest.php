<?php
/**
 * Tests SiteRepository exhaustively — kills Infection mutants on:
 *   - findAll / findActive (order, is_active filter, empty result)
 *   - findById / findByCode (existing, missing, null return)
 *   - create (lastInsertId, departement default)
 *   - update (rowCount, no-op)
 *   - toggleActive (true→1, false→0, rowCount)
 *   - countUsers / countReports (zero, positive)
 *   - delete (cascade check: users, reports, notification_settings; transaction)
 *   - countActiveSites (zero, positive, false stmt)
 */

use PHPUnit\Framework\TestCase;
use App\Repository\SiteRepository;

class SiteRepositoryMutationTest extends TestCase
{
    private PDO $pdo;
    private SiteRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->repo = new SiteRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
    }

    private function seedSite(string $code = 'UR21', string $nom = 'UR Test', string $dep = 'Test'): int
    {
        $id = $this->repo->create($code, $nom, $dep);
        $this->assertGreaterThan(0, $id);
        return $id;
    }

    // ═══ findAll / findActive ═══

    public function testFindAllReturnsEmptyWhenNoSites(): void
    {
        $this->assertSame([], $this->repo->findAll());
    }

    public function testFindAllReturnsAllSitesOrderedByCode(): void
    {
        $this->seedSite('UR25', 'UR 25');
        $this->seedSite('UR21', 'UR 21');
        $this->seedSite('UR39', 'UR 39');

        $result = $this->repo->findAll();
        $this->assertCount(3, $result);
        // Kill OrderBy mutant — must be sorted by code ASC
        $this->assertSame('UR21', $result[0]['code']);
        $this->assertSame('UR25', $result[1]['code']);
        $this->assertSame('UR39', $result[2]['code']);
    }

    public function testFindAllReturnsAllFields(): void
    {
        $id = $this->seedSite('UR21', 'UR Test', 'Doubs');
        $result = $this->repo->findAll();
        $this->assertSame($id, $result[0]['id']);
        $this->assertSame('UR21', $result[0]['code']);
        $this->assertSame('UR Test', $result[0]['nom']);
        $this->assertSame('Doubs', $result[0]['departement']);
        $this->assertSame(1, (int) $result[0]['is_active'], 'new site must be active by default');
    }

    public function testFindActiveExcludesInactiveSites(): void
    {
        $id1 = $this->seedSite('UR21', 'UR 21');
        $id2 = $this->seedSite('UR25', 'UR 25');
        $this->repo->toggleActive($id2, false);

        $result = $this->repo->findActive();
        $this->assertCount(1, $result);
        $this->assertSame('UR21', $result[0]['code']);
    }

    public function testFindActiveReturnsEmptyWhenAllInactive(): void
    {
        $id = $this->seedSite('UR21', 'UR 21');
        $this->repo->toggleActive($id, false);
        $this->assertSame([], $this->repo->findActive());
    }

    // ═══ findById / findByCode ═══

    public function testFindByIdReturnsSiteWhenExists(): void
    {
        $id = $this->seedSite('UR21', 'UR Test');
        $site = $this->repo->findById($id);
        $this->assertNotNull($site);
        $this->assertSame($id, $site['id']);
        $this->assertSame('UR21', $site['code']);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findById(99999));
    }

    public function testFindByCodeReturnsSiteWhenExists(): void
    {
        $this->seedSite('UR21', 'UR Test');
        $site = $this->repo->findByCode('UR21');
        $this->assertNotNull($site);
        $this->assertSame('UR21', $site['code']);
    }

    public function testFindByCodeReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findByCode('NONEXISTENT'));
    }

    // ═══ create ═══

    public function testCreateReturnsPositiveId(): void
    {
        // Kill CastInt mutant on (int) lastInsertId
        $id = $this->repo->create('UR21', 'UR Test', 'Doubs');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateWithEmptyDepartementDefaultsToEmpty(): void
    {
        // Kill default value mutant — departement default is ''
        $id = $this->repo->create('UR21', 'UR Test');
        $site = $this->repo->findById($id);
        $this->assertSame('', $site['departement']);
    }

    public function testCreateSetsIsActiveToOneByDefault(): void
    {
        $id = $this->repo->create('UR21', 'UR Test');
        $site = $this->repo->findById($id);
        $this->assertSame(1, (int) $site['is_active']);
    }

    // ═══ update ═══

    public function testUpdateModifiesSiteFields(): void
    {
        $id = $this->seedSite('UR21', 'Old Name', 'Old Dep');
        $result = $this->repo->update($id, 'UR99', 'New Name', 'New Dep');
        $this->assertTrue($result);
        $site = $this->repo->findById($id);
        $this->assertSame('UR99', $site['code']);
        $this->assertSame('New Name', $site['nom']);
        $this->assertSame('New Dep', $site['departement']);
    }

    public function testUpdateReturnsFalseWhenSiteNotFound(): void
    {
        $result = $this->repo->update(99999, 'UR99', 'Name', 'Dep');
        $this->assertFalse($result);
    }

    public function testUpdateReturnsFalseWhenNoChanges(): void
    {
        // Kill rowCount > 0 mutant — same values → rowCount=0
        $id = $this->seedSite('UR21', 'Same', 'Same');
        $result = $this->repo->update($id, 'UR21', 'Same', 'Same');
        $this->assertFalse($result, 'no-op update must return false');
    }

    // ═══ toggleActive ═══

    public function testToggleActiveTrueSetsIsEnabledTo1(): void
    {
        $id = $this->seedSite('UR21', 'UR 21');
        $this->repo->toggleActive($id, false); // deactivate first
        $result = $this->repo->toggleActive($id, true);
        $this->assertTrue($result);
        $site = $this->repo->findById($id);
        $this->assertSame(1, (int) $site['is_active']);
    }

    public function testToggleActiveFalseSetsIsEnabledTo0(): void
    {
        $id = $this->seedSite('UR21', 'UR 21');
        $result = $this->repo->toggleActive($id, false);
        $this->assertTrue($result);
        $site = $this->repo->findById($id);
        $this->assertSame(0, (int) $site['is_active']);
    }

    public function testToggleActiveReturnsFalseWhenSiteNotFound(): void
    {
        $this->assertFalse($this->repo->toggleActive(99999, true));
    }

    public function testToggleActiveReturnsFalseWhenNoChange(): void
    {
        $id = $this->seedSite('UR21', 'UR 21'); // is_active=1 by default
        $result = $this->repo->toggleActive($id, true); // already active
        $this->assertFalse($result, 'no-op toggle must return false');
    }

    // ═══ countUsers ═══

    public function testCountUsersReturnsZeroWhenNoUsers(): void
    {
        $id = $this->seedSite('UR21', 'UR 21');
        $this->assertSame(0, $this->repo->countUsers($id));
    }

    public function testCountUsersReturnsActiveUsersOnly(): void
    {
        $id = $this->seedSite('UR21', 'UR 21');
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['u1', 'A', 'B', 'agent', $id, 1]);
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['u2', 'C', 'D', 'agent', $id, 0]); // inactive
        $this->assertSame(1, $this->repo->countUsers($id), 'inactive users must be excluded');
    }

    // ═══ countReports ═══

    public function testCountReportsReturnsZeroWhenNoReports(): void
    {
        $id = $this->seedSite('UR21', 'UR 21');
        $this->assertSame(0, $this->repo->countReports($id));
    }

    public function testCountReportsReturnsAllReportsForSite(): void
    {
        $siteId = $this->seedSite('UR21', 'UR 21');
        $otherSite = $this->seedSite('UR25', 'UR 25');
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['decl', 'D', 'E', 'agent', $siteId, 1]);
        $declarantId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare('INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute(['uuid1', 'rsst-25-001', 'rsst', 'Obj1', 'Desc1', '2026-01-01', $declarantId, 'D', 'E', $siteId, 'nouveau']);
        $this->pdo->prepare('INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute(['uuid2', 'rsst-25-002', 'rsst', 'Obj2', 'Desc2', '2026-01-02', $declarantId, 'D', 'E', $otherSite, 'nouveau']);

        $this->assertSame(1, $this->repo->countReports($siteId), 'only reports for this site');
    }

    // ═══ delete ═══

    public function testDeleteReturnsFalseWhenSiteHasUsers(): void
    {
        $id = $this->seedSite('UR21', 'UR 21');
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['u1', 'A', 'B', 'agent', $id, 1]);
        $this->assertFalse($this->repo->delete($id), 'cannot delete site with users');
        // Site must still exist
        $this->assertNotNull($this->repo->findById($id));
    }

    public function testDeleteReturnsFalseWhenSiteHasReports(): void
    {
        $id = $this->seedSite('UR21', 'UR 21');
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['decl', 'D', 'E', 'agent', $id, 1]);
        $declarantId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute(['uuid1', 'rsst-25-001', 'rsst', 'Obj1', 'Desc1', '2026-01-01', $declarantId, 'D', 'E', $id, 'nouveau']);
        $this->assertFalse($this->repo->delete($id), 'cannot delete site with reports');
    }

    public function testDeleteRemovesSiteAndNotificationSettings(): void
    {
        $id = $this->seedSite('UR21', 'UR 21');
        // Add notification settings for this site
        $this->pdo->prepare('INSERT INTO notification_settings (site_id, type, registry, email) VALUES (?, ?, ?, ?)')
            ->execute([$id, 'site', 'rsst', 'test@gouv.fr']);

        $result = $this->repo->delete($id);
        $this->assertTrue($result);
        $this->assertNull($this->repo->findById($id), 'site must be deleted');

        // Notification settings must also be deleted
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notification_settings WHERE site_id = ?');
        $stmt->execute([$id]);
        $this->assertSame(0, (int) $stmt->fetchColumn(), 'notification_settings must be cascaded');
    }

    public function testDeleteReturnsFalseWhenSiteNotFound(): void
    {
        $this->assertFalse($this->repo->delete(99999));
    }

    // ═══ countActiveSites ═══

    public function testCountActiveSitesReturnsZeroWhenEmpty(): void
    {
        $this->assertSame(0, $this->repo->countActiveSites());
    }

    public function testCountActiveSitesReturnsCount(): void
    {
        $this->seedSite('UR21', 'UR 21');
        $this->seedSite('UR25', 'UR 25');
        $this->assertSame(2, $this->repo->countActiveSites());
    }

    public function testCountActiveSitesExcludesInactive(): void
    {
        $id1 = $this->seedSite('UR21', 'UR 21');
        $this->seedSite('UR25', 'UR 25');
        $this->repo->toggleActive($id1, false);
        $this->assertSame(1, $this->repo->countActiveSites());
    }
}
