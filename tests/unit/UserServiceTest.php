<?php
/**
 * UserService Unit Tests — Business Logic
 *
 * Tests UserService from src/Services/UserService.php:
 * - create, update, deactivate, reactivate
 * - validate, canDeactivate, canDemote
 * - findById, findAll, countActive
 */

use PHPUnit\Framework\TestCase;
use App\Services\UserService;
use App\Repository\UserRepository;
use App\Event\EventDispatcher;
use App\DTO\CreateUserCommand;
use App\DTO\SiteId;
use App\DTO\UpdateUserCommand;

class UserServiceTest extends TestCase
{
    private PDO $pdo;
    private UserService $service;
    private EventDispatcher $events;
    private UserRepository $repo;
    private int $siteId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');
        clearConfigCache();

        $this->repo = new UserRepository($this->pdo);
        $this->events = new EventDispatcher();
        $this->service = new UserService($this->repo, $this->events);

        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD_SVC', 'Service Site', 1)");
        $this->siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UD_SVC'")->fetchColumn();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // create()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testCreateReturnsUserId(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.create',
            nom: 'Dupont',
            prenom: 'Jean',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: 'test@test.fr',
        );
        $userId = $this->service->create($cmd);
        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);
    }

    public function testCreateInsertsUserInDatabase(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.insert',
            nom: 'Martin',
            prenom: 'Sophie',
            role: ROLE_SUPERVISEUR,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $user = $this->service->findById($userId);
        $this->assertNotNull($user);
        $this->assertEquals('svc.insert', $user['username']);
        $this->assertEquals('Martin', $user['nom']);
        $this->assertEquals('Sophie', $user['prenom']);
        $this->assertEquals(ROLE_SUPERVISEUR, $user['role']);
        $this->assertEquals(1, $user['is_active']);
    }

    public function testCreateDispatchesEvent(): void
    {
        $dispatched = false;
        $this->events->addListener('user.created', function () use (&$dispatched) {
            $dispatched = true;
        });

        $cmd = new CreateUserCommand(
            username: 'svc.event',
            nom: 'Event',
            prenom: 'Test',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $this->service->create($cmd);
        $this->assertTrue($dispatched);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // update()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testUpdateReturnsTrue(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.update',
            nom: 'Original',
            prenom: 'User',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);

        $updateCmd = new UpdateUserCommand(
            username: 'svc.update',
            nom: 'Updated',
            prenom: 'User',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $result = $this->service->update($userId, $updateCmd, $userId);
        $this->assertTrue($result);

        $user = $this->service->findById($userId);
        $this->assertEquals('Updated', $user['nom']);
    }

    // site_id = 0 is the UI sentinel for "no site" ("— Aucun —" option, and the
    // hidden field forced empty in no-site-mode). It must never be bound as a
    // literal 0 into the FK column — regression test for the bug where any
    // update (role change or otherwise) failed with a FOREIGN KEY constraint
    // violation whenever site_id came through as 0, silently leaving the user
    // unchanged.
    public function testUpdateWithSiteIdZeroSucceeds(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.nosite',
            nom: 'Original',
            prenom: 'User',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);

        $updateCmd = new UpdateUserCommand(
            username: 'svc.nosite',
            nom: 'Original',
            prenom: 'User',
            role: ROLE_CHSCT,
            siteId: SiteId::none(),
            email: null,
        );
        $result = $this->service->update($userId, $updateCmd, 999999);
        $this->assertTrue($result);

        $user = $this->service->findById($userId);
        $this->assertEquals(ROLE_CHSCT, $user['role']);
        $this->assertNull($user['site_id']);
    }

    public function testCreateWithSiteIdZeroSucceeds(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.createnosite',
            nom: 'New',
            prenom: 'User',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $this->assertGreaterThan(0, $userId);

        $user = $this->service->findById($userId);
        $this->assertNull($user['site_id']);
    }

    public function testUpdateNonExistentUserThrows(): void
    {
        $updateCmd = new UpdateUserCommand(
            username: 'ghost',
            nom: 'Ghost',
            prenom: 'User',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $this->expectException(\RuntimeException::class);
        $this->service->update(999999, $updateCmd, 1);
    }

    public function testUpdateDispatchesEvent(): void
    {
        $dispatched = false;
        $this->events->addListener('user.updated', function () use (&$dispatched) {
            $dispatched = true;
        });

        $cmd = new CreateUserCommand(
            username: 'svc.upd_evt',
            nom: 'Update',
            prenom: 'Event',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);

        $updateCmd = new UpdateUserCommand(
            username: 'svc.upd_evt',
            nom: 'Updated',
            prenom: 'Event',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $this->service->update($userId, $updateCmd, $userId);
        $this->assertTrue($dispatched);
    }

    public function testUpdateRoleChangeDispatchesRoleChangedEvent(): void
    {
        $roleChanged = false;
        $this->events->addListener('user.role_changed', function () use (&$roleChanged) {
            $roleChanged = true;
        });

        // Create a second superviseur so demote is allowed
        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('svc.multi_sup', 'Multi', 'Sup', 'superviseur', {$this->siteId}, 1)");
        $otherSupId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'svc.multi_sup'")->fetchColumn();

        $cmd = new CreateUserCommand(
            username: 'svc.role_chg',
            nom: 'Role',
            prenom: 'Change',
            role: ROLE_SUPERVISEUR,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);

        $updateCmd = new UpdateUserCommand(
            username: 'svc.role_chg',
            nom: 'Role',
            prenom: 'Change',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $this->service->update($userId, $updateCmd, $userId);
        $this->assertTrue($roleChanged);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // deactivate()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testDeactivateReturnsTrue(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.deact',
            nom: 'Deact',
            prenom: 'User',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $result = $this->service->deactivate($userId, $userId + 1);
        $this->assertTrue($result);

        $user = $this->service->findById($userId);
        $this->assertEquals(0, $user['is_active']);
    }

    public function testDeactivateSelfThrows(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.self_deact',
            nom: 'Self',
            prenom: 'Deact',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $this->expectException(\RuntimeException::class);
        $this->service->deactivate($userId, $userId);
    }

    public function testDeactivateLastSuperviseurThrows(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.last_sup',
            nom: 'Last',
            prenom: 'Sup',
            role: ROLE_SUPERVISEUR,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $this->expectException(\RuntimeException::class);
        $this->service->deactivate($userId, $userId + 1);
    }

    public function testDeactivateDispatchesEvent(): void
    {
        $dispatched = false;
        $this->events->addListener('user.deactivated', function () use (&$dispatched) {
            $dispatched = true;
        });

        $cmd = new CreateUserCommand(
            username: 'svc.deact_evt',
            nom: 'Deact',
            prenom: 'Event',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $this->service->deactivate($userId, $userId + 1);
        $this->assertTrue($dispatched);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // reactivate()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testReactivateReturnsTrue(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.react',
            nom: 'React',
            prenom: 'User',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $this->service->deactivate($userId, $userId + 1);
        $result = $this->service->reactivate($userId);
        $this->assertTrue($result);

        $user = $this->service->findById($userId);
        $this->assertEquals(1, $user['is_active']);
    }

    public function testReactivateAlreadyActiveThrows(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.already_active',
            nom: 'Already',
            prenom: 'Active',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $this->expectException(\RuntimeException::class);
        $this->service->reactivate($userId);
    }

    public function testReactivateDispatchesEvent(): void
    {
        $dispatched = false;
        $this->events->addListener('user.reactivated', function () use (&$dispatched) {
            $dispatched = true;
        });

        $cmd = new CreateUserCommand(
            username: 'svc.react_evt',
            nom: 'React',
            prenom: 'Event',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $this->service->deactivate($userId, $userId + 1);
        $this->service->reactivate($userId);
        $this->assertTrue($dispatched);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // validate()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testValidateReturnsEmptyArrayForValidInput(): void
    {
        $errors = $this->service->validate([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'username' => 'unique_username_val',
            'role' => ROLE_AGENT,
            'site_id' => $this->siteId,
        ]);
        $this->assertEmpty($errors);
    }

    public function testValidateMissingNomReturnsError(): void
    {
        $errors = $this->service->validate([
            'nom' => '',
            'prenom' => 'Jean',
            'username' => 'test_val',
            'role' => ROLE_AGENT,
        ]);
        $this->assertArrayHasKey('nom', $errors);
    }

    public function testValidateMissingPrenomReturnsError(): void
    {
        $errors = $this->service->validate([
            'nom' => 'Dupont',
            'prenom' => '',
            'username' => 'test_val',
            'role' => ROLE_AGENT,
        ]);
        $this->assertArrayHasKey('prenom', $errors);
    }

    public function testValidateMissingUsernameReturnsError(): void
    {
        $errors = $this->service->validate([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'username' => '',
            'role' => ROLE_AGENT,
        ]);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testValidateDuplicateUsernameReturnsError(): void
    {
        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, is_active) VALUES ('existing_user', 'Test', 'User', 'agent', 1)");
        $errors = $this->service->validate([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'username' => 'existing_user',
            'role' => ROLE_AGENT,
        ]);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testValidateDuplicateUsernameWithExcludeIdAllowsSameUser(): void
    {
        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, is_active) VALUES ('same_user', 'Test', 'User', 'agent', 1)");
        $userId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'same_user'")->fetchColumn();
        $errors = $this->service->validate([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'username' => 'same_user',
            'role' => ROLE_AGENT,
        ], $userId);
        $this->assertEmpty($errors);
    }

    public function testValidateInvalidRoleReturnsError(): void
    {
        $errors = $this->service->validate([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'username' => 'test_val',
            'role' => 'invalid_role',
        ]);
        $this->assertArrayHasKey('role', $errors);
    }

    public function testValidateInvalidEmailReturnsError(): void
    {
        $errors = $this->service->validate([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'username' => 'test_val',
            'role' => ROLE_AGENT,
            'email' => 'not-an-email',
        ]);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidateEmptyEmailIsAllowed(): void
    {
        $errors = $this->service->validate([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'username' => 'test_val',
            'role' => ROLE_AGENT,
            'email' => '',
        ]);
        $this->assertArrayNotHasKey('email', $errors);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // canDeactivate()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testCanDeactivateAgentReturnsTrue(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.can_deact',
            nom: 'Can',
            prenom: 'Deact',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $this->assertTrue($this->service->canDeactivate($userId));
    }

    public function testCanDeactivateLastSuperviseurReturnsFalse(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.last_sup2',
            nom: 'Last',
            prenom: 'Sup',
            role: ROLE_SUPERVISEUR,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $this->assertFalse($this->service->canDeactivate($userId));
    }

    public function testCanDeactivateNonExistentUserReturnsFalse(): void
    {
        $this->assertFalse($this->service->canDeactivate(999999));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // canDemote()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testCanDemoteSuperviseurToAgentReturnsErrorWhenLast(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.can_demote',
            nom: 'Can',
            prenom: 'Demote',
            role: ROLE_SUPERVISEUR,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $user = $this->service->findById($userId);
        $errors = $this->service->canDemote($userId, ROLE_AGENT, $user);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('role', $errors);
    }

    public function testCanDemoteSuperviseurToAgentReturnsEmptyWhenNotLast(): void
    {
        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('svc.multi2', 'Multi', 'Sup2', 'superviseur', {$this->siteId}, 1)");

        $cmd = new CreateUserCommand(
            username: 'svc.can_demote2',
            nom: 'Can',
            prenom: 'Demote',
            role: ROLE_SUPERVISEUR,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $user = $this->service->findById($userId);
        $errors = $this->service->canDemote($userId, ROLE_AGENT, $user);
        $this->assertEmpty($errors);
    }

    public function testCanDemoteAgentReturnsEmpty(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.can_demote3',
            nom: 'Can',
            prenom: 'Demote',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $user = $this->service->findById($userId);
        $errors = $this->service->canDemote($userId, ROLE_CHSCT, $user);
        $this->assertEmpty($errors);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Queries
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFindByIdReturnsUser(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.find',
            nom: 'Find',
            prenom: 'Me',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $userId = $this->service->create($cmd);
        $user = $this->service->findById($userId);
        $this->assertNotNull($user);
        $this->assertEquals('svc.find', $user['username']);
    }

    public function testFindByIdReturnsNullForUnknown(): void
    {
        $this->assertNull($this->service->findById(999999));
    }

}
