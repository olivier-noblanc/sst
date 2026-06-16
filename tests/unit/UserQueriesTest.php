<?php
/**
 * User Queries Unit Tests — Application SST DREETS BFC
 *
 * Tests the user_queries.php functions against an in-memory SQLite database.
 * These are the highest-ROI tests: queries are isolated, deterministic,
 * and form the data foundation of the application.
 */

use PHPUnit\Framework\TestCase;

class UserQueriesTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        // Clean tables for a fresh start
        $this->pdo->exec('DELETE FROM audit_log');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');

        // Seed a site
        $this->pdo->exec("INSERT INTO sites (id, code, nom, departement, is_active) VALUES (1, 'UR21', 'UR Côte-d''Or', 'Côte-d''Or', 1)");
        $this->pdo->exec("INSERT INTO sites (id, code, nom, departement, is_active) VALUES (2, 'UR25', 'UR Doubs', 'Doubs', 1)");
    }

    // ─── createUser() ──────────────────────────────────────────────────────────

    public function testCreateUserReturnsIntId(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'jean.martin',
            'nom'      => 'Martin',
            'prenom'   => 'Jean',
            'email'    => 'jean.martin@dreets.gouv.fr',
            'role'     => 'agent',
            'site_id'  => 1,
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateUserWithMinimalData(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'min.user',
            'nom'      => 'User',
            'prenom'   => 'Min',
            'role'     => 'agent',
            'site_id'  => 1,
        ]);

        $this->assertGreaterThan(0, $id);

        $user = getUserById($this->pdo, $id);
        $this->assertNull($user['email']);
    }

    // ─── getUserById() ─────────────────────────────────────────────────────────

    public function testGetUserByIdReturnsUserWithSiteInfo(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'jean.martin',
            'nom'      => 'Martin',
            'prenom'   => 'Jean',
            'email'    => 'jean.martin@dreets.gouv.fr',
            'role'     => 'agent',
            'site_id'  => 1,
        ]);

        $user = getUserById($this->pdo, $id);

        $this->assertNotNull($user);
        $this->assertEquals('jean.martin', $user['username']);
        $this->assertEquals('Martin', $user['nom']);
        $this->assertEquals('Jean', $user['prenom']);
        $this->assertEquals('agent', $user['role']);
        $this->assertEquals(1, (int) $user['site_id']);
        // Verify site JOIN
        $this->assertEquals('UR21', $user['site_code']);
        $this->assertEquals("UR Côte-d'Or", $user['site_nom']);
    }

    public function testGetUserByIdReturnsNullForNonexistent(): void
    {
        $user = getUserById($this->pdo, 99999);
        $this->assertNull($user);
    }

    // ─── getUserByUsername() ───────────────────────────────────────────────────

    public function testGetUserByUsernameReturnsUser(): void
    {
        createUser($this->pdo, [
            'username' => 'sophie.dupont',
            'nom'      => 'Dupont',
            'prenom'   => 'Sophie',
            'role'     => 'superviseur',
            'site_id'  => 2,
        ]);

        $user = getUserByUsername($this->pdo, 'sophie.dupont');

        $this->assertNotNull($user);
        $this->assertEquals('Dupont', $user['nom']);
        $this->assertEquals('superviseur', $user['role']);
        $this->assertEquals('UR25', $user['site_code']);
    }

    public function testGetUserByUsernameReturnsNullForDeactivated(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'inactive.user',
            'nom'      => 'Inactive',
            'prenom'   => 'User',
            'role'     => 'agent',
            'site_id'  => 1,
        ]);
        deactivateUser($this->pdo, $id);

        $user = getUserByUsername($this->pdo, 'inactive.user');
        $this->assertNull($user); // getUserByUsername filters is_active = 1
    }

    public function testGetUserByUsernameReturnsNullForNonexistent(): void
    {
        $user = getUserByUsername($this->pdo, 'nobody');
        $this->assertNull($user);
    }

    // ─── getAllUsers() ─────────────────────────────────────────────────────────

    public function testGetAllUsersReturnsAllActive(): void
    {
        createUser($this->pdo, [
            'username' => 'user1', 'nom' => 'Un', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1,
        ]);
        createUser($this->pdo, [
            'username' => 'user2', 'nom' => 'Deux', 'prenom' => 'User', 'role' => 'superviseur', 'site_id' => 2,
        ]);

        $users = getAllUsers($this->pdo);
        $this->assertCount(2, $users);
    }

    public function testGetAllUsersFiltersBySite(): void
    {
        createUser($this->pdo, [
            'username' => 'user1', 'nom' => 'Un', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1,
        ]);
        createUser($this->pdo, [
            'username' => 'user2', 'nom' => 'Deux', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 2,
        ]);

        $users = getAllUsers($this->pdo, 1);
        $this->assertCount(1, $users);
        $this->assertEquals('user1', $users[0]['username']);
    }

    public function testGetAllUsersExcludesInactive(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'active', 'nom' => 'Active', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1,
        ]);
        createUser($this->pdo, [
            'username' => 'inactive', 'nom' => 'Inactive', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1,
        ]);
        deactivateUser($this->pdo, $id);

        $users = getAllUsers($this->pdo, 0, true);
        // Only the inactive one remains active
        $this->assertCount(1, $users);
        $this->assertEquals('inactive', $users[0]['username']);
    }

    public function testGetAllUsersIncludesInactiveWhenAsked(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'active', 'nom' => 'Active', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1,
        ]);
        deactivateUser($this->pdo, $id);

        $users = getAllUsers($this->pdo, 0, false);
        $this->assertCount(1, $users); // inactive included
    }

    // ─── updateUser() ──────────────────────────────────────────────────────────

    public function testUpdateUserChangesFields(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'edit.me', 'nom' => 'Old', 'prenom' => 'Name', 'role' => 'agent', 'site_id' => 1,
        ]);

        $result = updateUser($this->pdo, $id, [
            'nom'      => 'New',
            'prenom'   => 'Name',
            'email'    => 'new@test.fr',
            'username' => 'edit.me',
            'role'     => 'superviseur',
            'site_id'  => 2,
        ]);

        $this->assertTrue($result);

        $user = getUserById($this->pdo, $id);
        $this->assertEquals('New', $user['nom']);
        $this->assertEquals('superviseur', $user['role']);
        $this->assertEquals('UR25', $user['site_code']);
    }

    // ─── deactivateUser() / reactivateUser() ──────────────────────────────────

    public function testDeactivateUserSetsInactive(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'deac', 'nom' => 'Deac', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1,
        ]);

        $result = deactivateUser($this->pdo, $id);
        $this->assertTrue($result);

        $user = getUserById($this->pdo, $id);
        $this->assertEquals(0, (int) $user['is_active']);
    }

    public function testReactivateUserSetsActive(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'react', 'nom' => 'React', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1,
        ]);
        deactivateUser($this->pdo, $id);
        reactivateUser($this->pdo, $id);

        $user = getUserById($this->pdo, $id);
        $this->assertEquals(1, (int) $user['is_active']);
    }

    // ─── updateUserRole() ──────────────────────────────────────────────────────

    public function testUpdateUserRole(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'promo', 'nom' => 'Promo', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1,
        ]);

        $result = updateUserRole($this->pdo, $id, 'superviseur');
        $this->assertTrue($result);

        $user = getUserById($this->pdo, $id);
        $this->assertEquals('superviseur', $user['role']);
    }

    // ─── countActiveUsers() ────────────────────────────────────────────────────

    public function testCountActiveUsers(): void
    {
        $this->assertEquals(0, countActiveUsers($this->pdo));

        createUser($this->pdo, [
            'username' => 'u1', 'nom' => 'U1', 'prenom' => 'Test', 'role' => 'agent', 'site_id' => 1,
        ]);
        $this->assertEquals(1, countActiveUsers($this->pdo));

        $id = createUser($this->pdo, [
            'username' => 'u2', 'nom' => 'U2', 'prenom' => 'Test', 'role' => 'agent', 'site_id' => 1,
        ]);
        $this->assertEquals(2, countActiveUsers($this->pdo));

        deactivateUser($this->pdo, $id);
        $this->assertEquals(1, countActiveUsers($this->pdo));
    }

    // ─── exportUserData() (GDPR) ──────────────────────────────────────────────

    public function testExportUserDataReturnsFullProfile(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'export.test',
            'nom'      => 'Export',
            'prenom'   => 'Test',
            'email'    => 'export@test.fr',
            'role'     => 'agent',
            'site_id'  => 1,
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
            'username' => 'anon.test',
            'nom'      => 'Sensitive',
            'prenom'   => 'Data',
            'email'    => 'sensitive@test.fr',
            'role'     => 'agent',
            'site_id'  => 1,
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
            'username' => 'no.site',
            'nom'      => 'NoSite',
            'prenom'   => 'User',
            'role'     => 'agent',
            'site_id'  => null,
        ]);

        $user = getUserById($this->pdo, $id);
        $this->assertNull($user['site_code']);
        $this->assertNull($user['site_nom']);
    }
}
