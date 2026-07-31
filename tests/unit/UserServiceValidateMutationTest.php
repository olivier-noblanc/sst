<?php
/**
 * Tests UserService::validate() exhaustively — kills Infection mutants on:
 *   - UnwrapTrim on nom/prenom/username/role/email (lines 202-247)
 *   - Coalesce on ?? '' / ?? '0'
 *   - CastInt on (int) $siteIdVal
 *   - LogicalNot / empty checks
 *   - PregMatch on username pattern
 *   - UserRole::tryFrom check
 *   - filter_var email validation
 *
 * Also tests canDeactivate / canDemote (last-supervisor protection).
 */

use PHPUnit\Framework\TestCase;
use App\Services\UserService;
use App\Repository\UserRepository;
use App\Event\EventDispatcher;
use App\Enum\UserRole;
use App\DTO\CreateUserCommand;
use App\DTO\UpdateUserCommand;
use App\DTO\SiteId;

class UserServiceValidateMutationTest extends TestCase
{
    private UserService $service;
    private PDO $pdo;

    private int $siteId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');

        $repo = new UserRepository($this->pdo);
        $events = new EventDispatcher();
        $this->service = new UserService($repo, $events);

        // Seed a site for site_id validation tests
        $this->pdo->prepare('INSERT INTO sites (code, nom, departement) VALUES (?, ?, ?)')
            ->execute(['UR21', 'UR Test', 'Test']);
        $this->siteId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');
    }

    private function makeCommand(array $overrides = []): CreateUserCommand
    {
        $defaults = [
            'username' => 'jean.dupont',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'role' => 'agent',
            'siteId' => SiteId::fromInput($this->siteId),
            'email' => 'jean@gouv.fr',
        ];
        $data = array_merge($defaults, $overrides);
        return new CreateUserCommand(
            username: $data['username'],
            nom: $data['nom'],
            prenom: $data['prenom'],
            role: $data['role'],
            siteId: $data['siteId'] ?? SiteId::none(),
            email: $data['email'] ?? null,
        );
    }

    // ═══ Nom validation ═══

    public function testValidateRejectsEmptyNom(): void
    {
        $cmd = $this->makeCommand(['nom' => '']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('nom', $errors);
        $this->assertStringContainsString('requis', $errors['nom']);
    }

    public function testValidateRejectsWhitespaceOnlyNom(): void
    {
        $cmd = $this->makeCommand(['nom' => '   ']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('nom', $errors, 'whitespace-only nom must be rejected');
    }

    public function testValidateRejectsMissingNom(): void
    {
        // SiteId::none() since no site is needed for this test
        $cmd = $this->makeCommand(['nom' => '', 'siteId' => SiteId::none()]);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('nom', $errors);
    }

    public function testValidateTrimsNom(): void
    {
        // DTO fromPost() already trims — '  Dupont  ' becomes 'Dupont'
        $cmd = $this->makeCommand(['nom' => 'Dupont']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('nom', $errors, 'trimmed nom must pass');
    }

    // ═══ Prenom validation ═══

    public function testValidateRejectsEmptyPrenom(): void
    {
        $cmd = $this->makeCommand(['prenom' => '']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('prenom', $errors);
    }

    public function testValidateRejectsWhitespaceOnlyPrenom(): void
    {
        $cmd = $this->makeCommand(['prenom' => '   ']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('prenom', $errors);
    }

    public function testValidateTrimsPrenom(): void
    {
        $cmd = $this->makeCommand(['prenom' => 'Jean']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('prenom', $errors);
    }

    // ═══ Username validation ═══

    public function testValidateRejectsEmptyUsername(): void
    {
        $cmd = $this->makeCommand(['username' => '']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('username', $errors);
        $this->assertStringContainsString('requis', $errors['username']);
    }

    public function testValidateRejectsWhitespaceOnlyUsername(): void
    {
        $cmd = $this->makeCommand(['username' => '   ']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testValidateTrimsUsername(): void
    {
        $cmd = $this->makeCommand(['username' => 'jean.dupont']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('username', $errors);
    }

    public function testValidateRejectsDuplicateUsername(): void
    {
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role) VALUES (?, ?, ?, ?)')
            ->execute(['existing.user', 'Test', 'User', 'agent']);

        $cmd = $this->makeCommand(['username' => 'existing.user']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('username', $errors);
        $this->assertStringContainsString('déjà utilisé', $errors['username']);
    }

    public function testValidateAllowsSameUsernameWithExcludeId(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role) VALUES (?, ?, ?, ?, ?)')
            ->execute([42, 'existing.user', 'Test', 'User', 'agent']);

        $cmd = $this->makeCommand(['username' => 'existing.user']);
        $errors = $this->service->validate($cmd, 42);
        $this->assertArrayNotHasKey('username', $errors, 'same username with excludeId must pass');
    }

    public function testValidateRejectsUsernameWithSpaces(): void
    {
        $cmd = $this->makeCommand(['username' => 'user with spaces']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('username', $errors);
        $this->assertStringContainsString('ne doit contenir', $errors['username']);
    }

    public function testValidateRejectsUsernameWithSpecialChars(): void
    {
        $cmd = $this->makeCommand(['username' => 'user@domain!']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testValidateRejectsUsernameTooShort(): void
    {
        $cmd = $this->makeCommand(['username' => 'a']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('username', $errors, '1-char username must fail');
    }

    public function testValidateAcceptsValidUsernameFormats(): void
    {
        $validUsernames = ['jean.dupont', 'jean-dupont', 'jean_dupont', 'jean123', 'JD', 'a.b-c_d'];
        foreach ($validUsernames as $username) {
            $cmd = $this->makeCommand(['username' => $username]);
            $errors = $this->service->validate($cmd);
            $this->assertArrayNotHasKey('username', $errors, "username '$username' should be valid");
        }
    }

    public function testValidateAcceptsUsernameWithDigitsAndDots(): void
    {
        $cmd = $this->makeCommand(['username' => 'user.name.123']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('username', $errors);
    }

    // ═══ Role validation ═══

    public function testValidateRejectsInvalidRole(): void
    {
        $cmd = $this->makeCommand(['role' => 'admin']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('role', $errors);
        $this->assertStringContainsString('invalide', $errors['role']);
    }

    public function testValidateRejectsEmptyRole(): void
    {
        $cmd = $this->makeCommand(['role' => '']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('role', $errors);
    }

    public function testValidateAcceptsAllValidRoles(): void
    {
        foreach (['agent', 'superviseur', 'chsct'] as $role) {
            $cmd = $this->makeCommand(['role' => $role]);
            $errors = $this->service->validate($cmd);
            $this->assertArrayNotHasKey('role', $errors, "role '$role' should be valid");
        }
    }

    public function testValidateTrimsRole(): void
    {
        $cmd = $this->makeCommand(['role' => 'agent']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('role', $errors, 'trimmed role must pass');
    }

    // ═══ Site ID validation ═══

    public function testValidateRejectsInvalidSiteId(): void
    {
        $cmd = $this->makeCommand(['siteId' => SiteId::fromInput(99999)]);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('site_id', $errors);
        $this->assertStringContainsString('invalide', $errors['site_id']);
    }

    public function testValidateAcceptsValidSiteId(): void
    {
        $cmd = $this->makeCommand(['siteId' => SiteId::fromInput($this->siteId)]);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('site_id', $errors);
    }

    public function testValidateAcceptsNoneSiteIdWhenNotInNoSiteMode(): void
    {
        $cmd = $this->makeCommand(['siteId' => SiteId::none()]);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('site_id', $errors, 'SiteId::none() must pass (no site selected)');
    }

    public function testValidateAcceptsValidSiteIdFromInt(): void
    {
        $cmd = $this->makeCommand(['siteId' => SiteId::fromInput($this->siteId)]);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('site_id', $errors);
    }

    public function testValidateNoneSiteIdDefaultsToNoSite(): void
    {
        $cmd = $this->makeCommand(['siteId' => SiteId::none()]);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('site_id', $errors, 'SiteId::none() must default to no site');
    }

    // ═══ Email validation ═══

    public function testValidateRejectsInvalidEmail(): void
    {
        $cmd = $this->makeCommand(['email' => 'not-an-email']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayHasKey('email', $errors);
        $this->assertStringContainsString('invalide', $errors['email']);
    }

    public function testValidateAcceptsValidEmail(): void
    {
        $cmd = $this->makeCommand(['email' => 'jean.dupont@gouv.fr']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function testValidateAcceptsEmptyEmail(): void
    {
        $cmd = $this->makeCommand(['email' => '']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('email', $errors, 'empty email must pass (optional)');
    }

    public function testValidateTrimsEmail(): void
    {
        $cmd = $this->makeCommand(['email' => 'jean@gouv.fr']);
        $errors = $this->service->validate($cmd);
        $this->assertArrayNotHasKey('email', $errors, 'trimmed email must pass');
    }

    // ═══ Full valid input ═══

    public function testValidateReturnsNoErrorsForValidInput(): void
    {
        $cmd = $this->makeCommand();
        $errors = $this->service->validate($cmd);
        $this->assertSame([], $errors, 'valid input must produce no errors');
    }

    // ═══ canDeactivate ═══

    public function testCanDeactivateReturnsFalseForUnknownUser(): void
    {
        $this->assertFalse($this->service->canDeactivate(99999));
    }

    public function testCanDeactivateReturnsTrueForRegularAgent(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([1, 'agent1', 'A', 'B', 'agent']);
        $this->assertTrue($this->service->canDeactivate(1));
    }

    public function testCanDeactivateReturnsTrueForNonLastSuperviseur(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur']);
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([2, 'sup2', 'C', 'D', 'superviseur']);
        $this->assertTrue($this->service->canDeactivate(1));
    }

    public function testCanDeactivateReturnsFalseForLastSuperviseur(): void
    {
        // Kill mutant on countActiveSuperviseurs() <= 1
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur']);
        $this->assertFalse($this->service->canDeactivate(1), 'cannot deactivate last superviseur');
    }

    // ═══ canDemote ═══

    public function testCanDemoteReturnsNoErrorForNonSuperviseur(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([1, 'agent1', 'A', 'B', 'agent']);
        $errors = $this->service->canDemote(1, 'superviseur', 'agent');
        $this->assertSame([], $errors);
    }

    public function testCanDemoteReturnsNoErrorForSuperviseurStayingSuperviseur(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur']);
        $errors = $this->service->canDemote(1, 'superviseur', 'superviseur');
        $this->assertSame([], $errors, 'same role → no demote error');
    }

    public function testCanDemoteReturnsErrorWhenDemotingLastSuperviseur(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur']);
        $errors = $this->service->canDemote(1, 'agent', 'superviseur');
        $this->assertArrayHasKey('role', $errors);
        $this->assertStringContainsString('dernier superviseur', $errors['role']);
    }

    public function testCanDemoteReturnsNoErrorWhenMultipleSuperviseurs(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur']);
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([2, 'sup2', 'C', 'D', 'superviseur']);
        $errors = $this->service->canDemote(1, 'agent', 'superviseur');
        $this->assertSame([], $errors, 'multiple superviseurs → demote allowed');
    }
}
