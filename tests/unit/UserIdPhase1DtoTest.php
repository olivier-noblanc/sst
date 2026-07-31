<?php
/**
 * Phase 1 DTO refactoring tests — verifies new typed signatures:
 *   - UserRepository::create(CreateUserCommand): int
 *   - UserRepository::update(int, UpdateUserCommand): bool
 *   - UserService::validate(CreateUserCommand|UpdateUserCommand): array
 *   - UserService::canDemote(int, string, string): array
 */

use PHPUnit\Framework\TestCase;
use App\Repository\UserRepository;
use App\Services\UserService;
use App\Event\EventDispatcher;
use App\DTO\CreateUserCommand;
use App\DTO\UpdateUserCommand;
use App\DTO\SiteId;

class UserIdPhase1DtoTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repo;
    private UserService $service;
    private int $siteId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');

        $this->repo = new UserRepository($this->pdo);
        $events = new EventDispatcher();
        $this->service = new UserService($this->repo, $events);

        $this->pdo->prepare('INSERT INTO sites (code, nom) VALUES (?, ?)')->execute(['UR21', 'UR Test']);
        $this->siteId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');
    }

    // ═══ UserRepository::create(CreateUserCommand) ═══

    public function testRepoCreateAcceptsCreateUserCommand(): void
    {
        $cmd = new CreateUserCommand(
            username: 'dto.user1',
            nom: 'Dupont',
            prenom: 'Jean',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: 'test@test.fr',
        );
        $id = $this->repo->create($cmd);
        $this->assertGreaterThan(0, $id);

        $user = $this->repo->findById($id);
        $this->assertNotNull($user);
        $this->assertSame('dto.user1', $user['username']);
        $this->assertSame('Dupont', $user['nom']);
        $this->assertSame('Jean', $user['prenom']);
        $this->assertSame(ROLE_AGENT, $user['role']);
        $this->assertSame($this->siteId, (int) $user['site_id']);
    }

    public function testRepoCreateWithNoneSiteIdSetsNull(): void
    {
        $cmd = new CreateUserCommand(
            username: 'dto.nosite',
            nom: 'No',
            prenom: 'Site',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $id = $this->repo->create($cmd);
        $user = $this->repo->findById($id);
        $this->assertNull($user['site_id'], 'SiteId::none() must produce NULL in DB');
    }

    public function testRepoCreateDefaultsRoleToAgent(): void
    {
        $cmd = new CreateUserCommand(
            username: 'dto.defrole',
            nom: 'Default',
            prenom: 'Role',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $id = $this->repo->create($cmd);
        $user = $this->repo->findById($id);
        $this->assertSame(ROLE_AGENT, $user['role']);
    }

    // ═══ UserRepository::update(int, UpdateUserCommand) ═══

    public function testRepoUpdateAcceptsUpdateUserCommand(): void
    {
        $createCmd = new CreateUserCommand(
            username: 'dto.upd1',
            nom: 'Original',
            prenom: 'User',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $id = $this->repo->create($createCmd);

        $updateCmd = new UpdateUserCommand(
            username: 'dto.upd1',
            nom: 'Updated',
            prenom: 'User',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $result = $this->repo->update($id, $updateCmd);
        $this->assertTrue($result);

        $user = $this->repo->findById($id);
        $this->assertSame('Updated', $user['nom']);
    }

    public function testRepoUpdateWithNoneSiteIdSetsNull(): void
    {
        $createCmd = new CreateUserCommand(
            username: 'dto.upd2',
            nom: 'Has',
            prenom: 'Site',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $id = $this->repo->create($createCmd);

        $updateCmd = new UpdateUserCommand(
            username: 'dto.upd2',
            nom: 'Has',
            prenom: 'Site',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $this->repo->update($id, $updateCmd);
        $user = $this->repo->findById($id);
        $this->assertNull($user['site_id'], 'SiteId::none() in update must produce NULL');
    }

    // ═══ UserService::validate(CreateUserCommand|UpdateUserCommand) ═══

    public function testServiceValidateAcceptsCreateUserCommand(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.val1',
            nom: 'Dupont',
            prenom: 'Jean',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $errors = $this->service->validate($cmd);
        $this->assertEmpty($errors);
    }

    public function testServiceValidateAcceptsUpdateUserCommand(): void
    {
        $cmd = new UpdateUserCommand(
            username: 'svc.val2',
            nom: 'Dupont',
            prenom: 'Jean',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $errors = $this->service->validate($cmd);
        $this->assertEmpty($errors);
    }

    public function testServiceValidateRejectsEmptyNomOnCreateCommand(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.val3',
            nom: '',
            prenom: 'Jean',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('nom', $errors);
    }

    public function testServiceValidateRejectsEmptyPrenomOnUpdateCommand(): void
    {
        $cmd = new UpdateUserCommand(
            username: 'svc.val4',
            nom: 'Dupont',
            prenom: '',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('prenom', $errors);
    }

    public function testServiceValidateRejectsEmptyUsernameOnCreateCommand(): void
    {
        $cmd = new CreateUserCommand(
            username: '',
            nom: 'Dupont',
            prenom: 'Jean',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testServiceValidateRejectsInvalidRoleOnCreateCommand(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.val5',
            nom: 'Dupont',
            prenom: 'Jean',
            role: 'invalid_role',
            siteId: SiteId::none(),
            email: null,
        );
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('role', $errors);
    }

    public function testServiceValidateRejectsInvalidEmailOnUpdateCommand(): void
    {
        $cmd = new UpdateUserCommand(
            username: 'svc.val6',
            nom: 'Dupont',
            prenom: 'Jean',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: 'not-an-email',
        );
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testServiceValidateAllowsEmptyEmailOnCreateCommand(): void
    {
        $cmd = new CreateUserCommand(
            username: 'svc.val7',
            nom: 'Dupont',
            prenom: 'Jean',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: '',
        );
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function testServiceValidateRejectsDuplicateUsernameOnCreateCommand(): void
    {
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?)')
            ->execute(['dup_user', 'Test', 'User', 'agent', 1]);

        $cmd = new CreateUserCommand(
            username: 'dup_user',
            nom: 'Dupont',
            prenom: 'Jean',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testServiceValidateAllowsSameUsernameWithExcludeIdOnUpdateCommand(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([42, 'same_user', 'Test', 'User', 'agent', 1]);

        $cmd = new UpdateUserCommand(
            username: 'same_user',
            nom: 'Dupont',
            prenom: 'Jean',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $errors = $this->service->validate($cmd, 42);
        $this->assertEmpty($errors);
    }

    // ═══ UserService::canDemote(int, string, string) ═══

    public function testCanDemoteAcceptsStringCurrentRole(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur', 1]);
        $errors = $this->service->canDemote(1, ROLE_AGENT, ROLE_SUPERVISEUR);
        $this->assertArrayHasKey('role', $errors);
    }

    public function testCanDemoteReturnsEmptyForNonLastSuperviseur(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur', 1]);
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([2, 'sup2', 'C', 'D', 'superviseur', 1]);
        $errors = $this->service->canDemote(1, ROLE_AGENT, ROLE_SUPERVISEUR);
        $this->assertEmpty($errors);
    }

    public function testCanDemoteReturnsEmptyForAgentToChsct(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([1, 'agent1', 'A', 'B', 'agent', 1]);
        $errors = $this->service->canDemote(1, ROLE_CHSCT, ROLE_AGENT);
        $this->assertEmpty($errors);
    }

    public function testCanDemoteReturnsErrorWhenLastSuperviseur(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur', 1]);
        $errors = $this->service->canDemote(1, ROLE_AGENT, ROLE_SUPERVISEUR);
        $this->assertArrayHasKey('role', $errors);
        $this->assertStringContainsString('dernier superviseur', $errors['role']);
    }

    public function testCanDemoteReturnsEmptyForSuperviseurStayingSuperviseur(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur', 1]);
        $errors = $this->service->canDemote(1, ROLE_SUPERVISEUR, ROLE_SUPERVISEUR);
        $this->assertEmpty($errors);
    }
}
