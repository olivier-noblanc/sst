<?php
/**
 * User Queries Unit Tests — CRUD Operations
 *
 * Tests the user_queries.php read functions — getUserById(), getUserByUsername(),
 * App\Repository\UserRepository::findAll() (formerly getAllUsers()) — against
 * an in-memory SQLite database.
 *
 * Write operations (create, update, deactivate, reactivate, role change) go
 * through App\Repository\UserRepository directly: the procedural wrappers
 * (createUser(), updateUser(), deactivateUser(), reactivateUser(),
 * updateUserRole() in src/queries/user_admin_queries.php) were removed as
 * dead code — they had no callers outside this test file and its siblings,
 * and UserRepository/UserService already carry equivalent coverage in
 * UserServiceTest.php.
 */

use App\Repository\UserRepository;
use App\DTO\CreateUserCommand;
use App\DTO\UpdateUserCommand;
use App\DTO\SiteId;
use PHPUnit\Framework\TestCase;

class UserQueriesTest extends TestCase
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

    // ─── create() ──────────────────────────────────────────────────────────────

    public function testCreateUserReturnsIntId(): void
    {
        $id = $this->users->create(new CreateUserCommand(
            username: 'jean.martin', nom: 'Martin', prenom: 'Jean',
            email: 'jean.martin@dreets.gouv.fr', role: ROLE_AGENT, siteId: SiteId::fromInput(1),
        ));
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateUserWithMinimalData(): void
    {
        $id = $this->users->create(new CreateUserCommand(
            username: 'min.user', nom: 'User', prenom: 'Min',
            role: ROLE_AGENT, siteId: SiteId::fromInput(1), email: null,
        ));
        $this->assertGreaterThan(0, $id);
        $user = $this->users->findById($id);
        $this->assertNull($user['email']);
    }

    // ─── getUserById() ─────────────────────────────────────────────────────────

    public function testGetUserByIdReturnsUserWithSiteInfo(): void
    {
        $id = $this->users->create(new CreateUserCommand(
            username: 'jean.martin', nom: 'Martin', prenom: 'Jean',
            email: 'jean.martin@dreets.gouv.fr', role: ROLE_AGENT, siteId: SiteId::fromInput(1),
        ));
        $user = $this->users->findById($id);
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
        $user = $this->users->findById(99999);
        $this->assertNull($user);
    }

    // ─── getUserByUsername() ───────────────────────────────────────────────────

    public function testGetUserByUsernameReturnsUser(): void
    {
        $this->users->create(new CreateUserCommand(
            username: 'sophie.dupont', nom: 'Dupont', prenom: 'Sophie',
            role: ROLE_SUPERVISEUR, siteId: SiteId::fromInput(2), email: null,
        ));
        $user = $this->users->findByUsername('sophie.dupont');
        $this->assertNotNull($user);
        $this->assertEquals('Dupont', $user['nom']);
        $this->assertEquals('superviseur', $user['role']);
        $this->assertEquals('UR25', $user['site_code']);
    }

    public function testGetUserByUsernameReturnsNullForDeactivated(): void
    {
        $id = $this->users->create(new CreateUserCommand(
            username: 'inactive.user', nom: 'Inactive', prenom: 'User',
            role: ROLE_AGENT, siteId: SiteId::fromInput(1), email: null,
        ));
        $this->users->deactivate($id);
        $user = $this->users->findByUsername('inactive.user');
        $this->assertNull($user);
    }

    public function testGetUserByUsernameReturnsNullForNonexistent(): void
    {
        $user = $this->users->findByUsername('nobody');
        $this->assertNull($user);
    }

    // ─── findAll() ─────────────────────────────────────────────────────────────

    public function testGetAllUsersReturnsAllActive(): void
    {
        $this->users->create(new CreateUserCommand(username: 'user1', nom: 'Un', prenom: 'User', role: ROLE_AGENT, siteId: SiteId::fromInput(1), email: null));
        $this->users->create(new CreateUserCommand(username: 'user2', nom: 'Deux', prenom: 'User', role: ROLE_SUPERVISEUR, siteId: SiteId::fromInput(2), email: null));
        $users = $this->users->findAll();
        $this->assertCount(2, $users);
    }

    public function testGetAllUsersFiltersBySite(): void
    {
        $this->users->create(new CreateUserCommand(username: 'user1', nom: 'Un', prenom: 'User', role: ROLE_AGENT, siteId: SiteId::fromInput(1), email: null));
        $this->users->create(new CreateUserCommand(username: 'user2', nom: 'Deux', prenom: 'User', role: ROLE_AGENT, siteId: SiteId::fromInput(2), email: null));
        $users = $this->users->findAll(1);
        $this->assertCount(1, $users);
        $this->assertEquals('user1', $users[0]['username']);
    }

    public function testGetAllUsersExcludesInactive(): void
    {
        $id = $this->users->create(new CreateUserCommand(username: 'active', nom: 'Active', prenom: 'User', role: ROLE_AGENT, siteId: SiteId::fromInput(1), email: null));
        $this->users->create(new CreateUserCommand(username: 'inactive', nom: 'Inactive', prenom: 'User', role: ROLE_AGENT, siteId: SiteId::fromInput(1), email: null));
        $this->users->deactivate($id);
        $users = $this->users->findAll(0, true);
        $this->assertCount(1, $users);
        $this->assertEquals('inactive', $users[0]['username']);
    }

    public function testGetAllUsersIncludesInactiveWhenAsked(): void
    {
        $id = $this->users->create(new CreateUserCommand(username: 'active', nom: 'Active', prenom: 'User', role: ROLE_AGENT, siteId: SiteId::fromInput(1), email: null));
        $this->users->deactivate($id);
        $users = $this->users->findAll(0, false);
        $this->assertCount(1, $users);
    }

    // ─── update() ──────────────────────────────────────────────────────────────

    public function testUpdateUserChangesFields(): void
    {
        $id = $this->users->create(new CreateUserCommand(
            username: 'edit.me', nom: 'Old', prenom: 'Name',
            role: ROLE_AGENT, siteId: SiteId::fromInput(1), email: null,
        ));
        $result = $this->users->update($id, new UpdateUserCommand(
            nom: 'New', prenom: 'Name', email: 'new@test.fr',
            username: 'edit.me', role: ROLE_SUPERVISEUR, siteId: SiteId::fromInput(2),
        ));
        $this->assertTrue($result);
        $user = $this->users->findById($id);
        $this->assertEquals('New', $user['nom']);
        $this->assertEquals('superviseur', $user['role']);
        $this->assertEquals('UR25', $user['site_code']);
    }

    // ─── deactivate() / reactivate() ────────────────────────────────────────────

    public function testDeactivateUserSetsInactive(): void
    {
        $id = $this->users->create(new CreateUserCommand(
            username: 'deac', nom: 'Deac', prenom: 'User',
            role: ROLE_AGENT, siteId: SiteId::fromInput(1), email: null,
        ));
        $result = $this->users->deactivate($id);
        $this->assertTrue($result);
        $user = $this->users->findById($id);
        $this->assertEquals(0, (int) $user['is_active']);
    }

    public function testReactivateUserSetsActive(): void
    {
        $id = $this->users->create(new CreateUserCommand(
            username: 'react', nom: 'React', prenom: 'User',
            role: ROLE_AGENT, siteId: SiteId::fromInput(1), email: null,
        ));
        $this->users->deactivate($id);
        $this->users->reactivate($id);
        $user = $this->users->findById($id);
        $this->assertEquals(1, (int) $user['is_active']);
    }

    // ─── updateRole() ────────────────────────────────────────────────────────────

    public function testUpdateUserRole(): void
    {
        $id = $this->users->create(new CreateUserCommand(
            username: 'promo', nom: 'Promo', prenom: 'User',
            role: ROLE_AGENT, siteId: SiteId::fromInput(1), email: null,
        ));
        $user = $this->users->findById($id);
        $this->assertNotNull($user);
        $result = $this->users->update($id, new UpdateUserCommand(
            nom: $user['nom'],
            prenom: $user['prenom'],
            email: $user['email'],
            username: $user['username'],
            role: ROLE_SUPERVISEUR,
            siteId: SiteId::fromInput(1),
        ));
        $this->assertTrue($result);
        $user = $this->users->findById($id);
        $this->assertEquals('superviseur', $user['role']);
    }
}
