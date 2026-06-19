<?php
/**
 * User Queries Unit Tests — Export, Anonymize, Count, Site Helpers
 *
 * Tests the user_queries.php functions:
 * - countActiveUsers()
 * - exportUserData()
 * - anonymizeUser()
 * - userSelectWithSite()
 */

use PHPUnit\Framework\TestCase;

class UserQueriesExportTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM audit_log');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');

        // Seed sites
        $this->pdo->exec("INSERT INTO sites (id, code, nom, departement, is_active) VALUES (1, 'UR21', 'UR Côte-d''Or', 'Côte-d''Or', 1)");
        $this->pdo->exec("INSERT INTO sites (id, code, nom, departement, is_active) VALUES (2, 'UR25', 'UR Doubs', 'Doubs', 1)");
    }

    // ─── countActiveUsers() ────────────────────────────────────────────────────

    public function testCountActiveUsers(): void
    {
        $this->assertEquals(0, countActiveUsers($this->pdo));
        createUser($this->pdo, ['username' => 'u1', 'nom' => 'U1', 'prenom' => 'Test', 'role' => 'agent', 'site_id' => 1]);
        $this->assertEquals(1, countActiveUsers($this->pdo));
        $id = createUser($this->pdo, ['username' => 'u2', 'nom' => 'U2', 'prenom' => 'Test', 'role' => 'agent', 'site_id' => 1]);
        $this->assertEquals(2, countActiveUsers($this->pdo));
        deactivateUser($this->pdo, $id);
        $this->assertEquals(1, countActiveUsers($this->pdo));
    }

    // ─── exportUserData() (GDPR) ──────────────────────────────────────────────

    public function testExportUserDataReturnsFullProfile(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'export.test', 'nom' => 'Export', 'prenom' => 'Test',
            'email' => 'export@test.fr', 'role' => 'agent', 'site_id' => 1,
        ]);
        $data = exportUserData($this->pdo, $id);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals('export.test', $data['user']['username']);
        $this->assertArrayHasKey('reports', $data);
        $this->assertArrayHasKey('responses', $data);
        $this->assertEquals(0, $data['reports_count']);
    }

    // ─── anonymizeUser() (GDPR) ───────────────────────────────────────────────

    public function testAnonymizeUserRemovesPersonalData(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'anon.test', 'nom' => 'Sensitive', 'prenom' => 'Data',
            'email' => 'sensitive@test.fr', 'role' => 'agent', 'site_id' => 1,
        ]);
        $result = anonymizeUser($this->pdo, $id);
        $this->assertTrue($result);
        $user = getUserById($this->pdo, $id);
        $this->assertEquals('Anonymisé', $user['nom']);
        $this->assertEquals('Utilisateur', $user['prenom']);
        $this->assertNull($user['email']);
        $this->assertEquals(0, (int) $user['is_active']);
    }

    // ─── userSelectWithSite() centralisation ───────────────────────────────────

    public function testUserSelectWithSiteReturnsSqlFragment(): void
    {
        $sql = userSelectWithSite();
        $this->assertStringContainsString('u.*', $sql);
        $this->assertStringContainsString('site_code', $sql);
        $this->assertStringContainsString('site_nom', $sql);
        $this->assertStringContainsString('LEFT JOIN sites', $sql);
    }

    public function testUserWithoutSiteReturnsNullSiteFields(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'no.site', 'nom' => 'NoSite', 'prenom' => 'User',
            'role' => 'agent', 'site_id' => null,
        ]);
        $user = getUserById($this->pdo, $id);
        $this->assertNull($user['site_code']);
        $this->assertNull($user['site_nom']);
    }
}
