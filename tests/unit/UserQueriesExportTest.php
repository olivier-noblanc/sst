<?php
/**
 * User Queries Unit Tests — Export, Anonymize, Count, Site Helpers
 *
 * Tests:
 * - App\Repository\UserRepository::countActive() / exportData() / anonymize()
 *   (formerly the countActiveUsers()/exportUserData()/anonymizeUser() wrappers
 *   in src/queries/user_admin_queries.php and user_gdpr_queries.php, removed
 *   as dead code — no callers outside test fixtures)
 * - UserRepository site JOIN (baseQuery returns site_code/site_nom)
 */

use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

class UserQueriesExportTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $users;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM audit_log');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');

        // Seed sites
        $this->pdo->exec("INSERT INTO sites (id, code, nom, departement, is_active) VALUES (1, 'UR21', 'UR Côte-d''Or', 'Côte-d''Or', 1)");
        $this->pdo->exec("INSERT INTO sites (id, code, nom, departement, is_active) VALUES (2, 'UR25', 'UR Doubs', 'Doubs', 1)");

        $this->users = UserRepository::instance();
    }

    // ─── countActive() ─────────────────────────────────────────────────────────

    public function testCountActiveUsers(): void
    {
        $this->assertEquals(0, $this->users->countActive());
        $this->users->create(['username' => 'u1', 'nom' => 'U1', 'prenom' => 'Test', 'role' => 'agent', 'site_id' => 1]);
        $this->assertEquals(1, $this->users->countActive());
        $id = $this->users->create(['username' => 'u2', 'nom' => 'U2', 'prenom' => 'Test', 'role' => 'agent', 'site_id' => 1]);
        $this->assertEquals(2, $this->users->countActive());
        $this->users->deactivate($id);
        $this->assertEquals(1, $this->users->countActive());
    }

    // ─── exportData() (GDPR) ───────────────────────────────────────────────────

    public function testExportUserDataReturnsFullProfile(): void
    {
        $id = $this->users->create([
            'username' => 'export.test', 'nom' => 'Export', 'prenom' => 'Test',
            'email' => 'export@test.fr', 'role' => 'agent', 'site_id' => 1,
        ]);
        $data = $this->users->exportData($id);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals('export.test', $data['user']['username']);
        $this->assertArrayHasKey('reports', $data);
        $this->assertArrayHasKey('responses', $data);
        $this->assertEquals(0, $data['reports_count']);
    }

    // ─── anonymize() (GDPR) ────────────────────────────────────────────────────

    public function testAnonymizeUserRemovesPersonalData(): void
    {
        $id = $this->users->create([
            'username' => 'anon.test', 'nom' => 'Sensitive', 'prenom' => 'Data',
            'email' => 'sensitive@test.fr', 'role' => 'agent', 'site_id' => 1,
        ]);
        $result = $this->users->anonymize($id);
        $this->assertTrue($result);
        $user = $this->users->findById($id);
        $this->assertEquals('Anonymisé', $user['nom']);
        $this->assertEquals('Utilisateur', $user['prenom']);
        $this->assertNull($user['email']);
        $this->assertEquals(0, (int) $user['is_active']);
    }

    // ─── UserRepository site JOIN ─────────────────────────────────────────────

    public function testUserRepositoryReturnsSiteFields(): void
    {
        $user = $this->users->create([
            'username' => 'site.test', 'nom' => 'Site', 'prenom' => 'Test',
            'email' => 'site@test.fr', 'role' => 'agent', 'site_id' => 1,
        ]);
        $result = $this->users->findById($user);
        $this->assertNotNull($result);
        $this->assertEquals('UR21', $result['site_code']);
        $this->assertEquals('UR Côte-d\'Or', $result['site_nom']);
    }

    public function testUserWithoutSiteReturnsNullSiteFields(): void
    {
        $id = $this->users->create([
            'username' => 'no.site', 'nom' => 'NoSite', 'prenom' => 'User',
            'role' => 'agent', 'site_id' => null,
        ]);
        $user = $this->users->findById($id);
        $this->assertNull($user['site_code']);
        $this->assertNull($user['site_nom']);
    }
}
