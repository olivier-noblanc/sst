<?php
/**
 * User Queries Unit Tests — CRUD Operations
 *
 * Tests the user_queries.php functions against an in-memory SQLite database:
 * - createUser(), getUserById(), getUserByUsername()
 * - getAllUsers(), updateUser()
 * - deactivateUser(), reactivateUser(), updateUserRole()
 */

use PHPUnit\Framework\TestCase;

class UserQueriesTest extends TestCase
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

    // ─── createUser() ──────────────────────────────────────────────────────────

    public function testCreateUserReturnsIntId(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'jean.martin', 'nom' => 'Martin', 'prenom' => 'Jean',
            'email' => 'jean.martin@dreets.gouv.fr', 'role' => 'agent', 'site_id' => 1,
        ]);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateUserWithMinimalData(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'min.user', 'nom' => 'User', 'prenom' => 'Min',
            'role' => 'agent', 'site_id' => 1,
        ]);
        $this->assertGreaterThan(0, $id);
        $user = getUserById($this->pdo, $id);
        $this->assertNull($user['email']);
    }

    // ─── getUserById() ─────────────────────────────────────────────────────────

    public function testGetUserByIdReturnsUserWithSiteInfo(): void
    {
        $id = createUser($this->pdo, [
            'username' => 'jean.martin', 'nom' => 'Martin', 'prenom' => 'Jean',
            'email' => 'jean.martin@dreets.gouv.fr', 'role' => 'agent', 'site_id' => 1,
        ]);
        $user = getUserById($this->pdo, $id);
        $this->assertNotNull($user);
        $this->assertEquals('jean.martin', $user['username']);
        $this->assertEquals('Martin', $user['nom']);
        $this->assertEquals('Jean', $user['prenom']);
        $this->assertEquals('agent', $user['role']);
        $this->assertEquals(1, (int) $user['site_id']);
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
            'username' => 'sophie.dupont', 'nom' => 'Dupont', 'prenom' => 'Sophie',
            'role' => 'superviseur', 'site_id' => 2,
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
            'username' => 'inactive.user', 'nom' => 'Inactive', 'prenom' => 'User',
            'role' => 'agent', 'site_id' => 1,
        ]);
        deactivateUser($this->pdo, $id);
        $user = getUserByUsername($this->pdo, 'inactive.user');
        $this->assertNull($user);
    }

    public function testGetUserByUsernameReturnsNullForNonexistent(): void
    {
        $user = getUserByUsername($this->pdo, 'nobody');
        $this->assertNull($user);
    }

    // ─── getAllUsers() ─────────────────────────────────────────────────────────

    public function testGetAllUsersReturnsAllActive(): void
    {
        createUser($this->pdo, ['username' => 'user1', 'nom' => 'Un', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1]);
        createUser($this->pdo, ['username' => 'user2', 'nom' => 'Deux', 'prenom' => 'User', 'role' => 'superviseur', 'site_id' => 2]);
        $users = getAllUsers($this->pdo);
        $this->assertCount(2, $users);
    }

    public function testGetAllUsersFiltersBySite(): void
    {
        createUser($this->pdo, ['username' => 'user1', 'nom' => 'Un', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1]);
        createUser($this->pdo, ['username' => 'user2', 'nom' => 'Deux', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 2]);
        $users = getAllUsers($this->pdo, 1);
        $this->assertCount(1, $users);
        $this->assertEquals('user1', $users[0]['username']);
    }

    public function testGetAllUsersExcludesInactive(): void
    {
        $id = createUser($this->pdo, ['username' => 'active', 'nom' => 'Active', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1]);
        createUser($this->pdo, ['username' => 'inactive', 'nom' => 'Inactive', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1]);
        deactivateUser($this->pdo, $id);
        $users = getAllUsers($this->pdo, 0, true);
        $this->assertCount(1, $users);
        $this->assertEquals('inactive', $users[0]['username']);
    }

    public function testGetAllUsersIncludesInactiveWhenAsked(): void
    {
        $id = createUser($this->pdo, ['username' => 'active', 'nom' => 'Active', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1]);
        deactivateUser($this->pdo, $id);
        $users = getAllUsers($this->pdo, 0, false);
        $this->assertCount(1, $users);
    }

    // ─── updateUser() ──────────────────────────────────────────────────────────

    public function testUpdateUserChangesFields(): void
    {
        $id = createUser($this->pdo, ['username' => 'edit.me', 'nom' => 'Old', 'prenom' => 'Name', 'role' => 'agent', 'site_id' => 1]);
        $result = updateUser($this->pdo, $id, [
            'nom' => 'New', 'prenom' => 'Name', 'email' => 'new@test.fr',
            'username' => 'edit.me', 'role' => 'superviseur', 'site_id' => 2,
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
        $id = createUser($this->pdo, ['username' => 'deac', 'nom' => 'Deac', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1]);
        $result = deactivateUser($this->pdo, $id);
        $this->assertTrue($result);
        $user = getUserById($this->pdo, $id);
        $this->assertEquals(0, (int) $user['is_active']);
    }

    public function testReactivateUserSetsActive(): void
    {
        $id = createUser($this->pdo, ['username' => 'react', 'nom' => 'React', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1]);
        deactivateUser($this->pdo, $id);
        reactivateUser($this->pdo, $id);
        $user = getUserById($this->pdo, $id);
        $this->assertEquals(1, (int) $user['is_active']);
    }

    // ─── updateUserRole() ──────────────────────────────────────────────────────

    public function testUpdateUserRole(): void
    {
        $id = createUser($this->pdo, ['username' => 'promo', 'nom' => 'Promo', 'prenom' => 'User', 'role' => 'agent', 'site_id' => 1]);
        $result = updateUserRole($this->pdo, $id, 'superviseur');
        $this->assertTrue($result);
        $user = getUserById($this->pdo, $id);
        $this->assertEquals('superviseur', $user['role']);
    }
}
