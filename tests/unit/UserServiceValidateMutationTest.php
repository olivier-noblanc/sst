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

    /** @return array<string, string> */
    private function validInput(): array
    {
        return [
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'username' => 'jean.dupont',
            'role' => 'agent',
            'site_id' => (string) $this->siteId,
            'email' => 'jean@gouv.fr',
        ];
    }

    // ═══ Nom validation ═══

    public function testValidateRejectsEmptyNom(): void
    {
        $errors = $this->service->validate(['nom' => '', 'prenom' => 'P', 'username' => 'u', 'role' => 'agent']);
        $this->assertArrayHasKey('nom', $errors);
        $this->assertStringContainsString('requis', $errors['nom']);
    }

    public function testValidateRejectsWhitespaceOnlyNom(): void
    {
        // Kill UnwrapTrim mutant — trim('   ') = '' → empty
        $errors = $this->service->validate(['nom' => '   ', 'prenom' => 'P', 'username' => 'u', 'role' => 'agent']);
        $this->assertArrayHasKey('nom', $errors, 'whitespace-only nom must be rejected');
    }

    public function testValidateRejectsMissingNom(): void
    {
        // Kill Coalesce mutant on ?? ''
        $errors = $this->service->validate(['prenom' => 'P', 'username' => 'u', 'role' => 'agent']);
        $this->assertArrayHasKey('nom', $errors);
    }

    public function testValidateTrimsNom(): void
    {
        // Kill UnwrapTrim mutant — '  Dupont  ' must become 'Dupont'
        $input = $this->validInput();
        $input['nom'] = '  Dupont  ';
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('nom', $errors, 'trimmed nom must pass');
    }

    // ═══ Prenom validation ═══

    public function testValidateRejectsEmptyPrenom(): void
    {
        $errors = $this->service->validate(['nom' => 'N', 'prenom' => '', 'username' => 'u', 'role' => 'agent']);
        $this->assertArrayHasKey('prenom', $errors);
    }

    public function testValidateRejectsWhitespaceOnlyPrenom(): void
    {
        $errors = $this->service->validate(['nom' => 'N', 'prenom' => '   ', 'username' => 'u', 'role' => 'agent']);
        $this->assertArrayHasKey('prenom', $errors);
    }

    public function testValidateTrimsPrenom(): void
    {
        $input = $this->validInput();
        $input['prenom'] = '  Jean  ';
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('prenom', $errors);
    }

    // ═══ Username validation ═══

    public function testValidateRejectsEmptyUsername(): void
    {
        $errors = $this->service->validate(['nom' => 'N', 'prenom' => 'P', 'username' => '', 'role' => 'agent']);
        $this->assertArrayHasKey('username', $errors);
        $this->assertStringContainsString('requis', $errors['username']);
    }

    public function testValidateRejectsWhitespaceOnlyUsername(): void
    {
        $errors = $this->service->validate(['nom' => 'N', 'prenom' => 'P', 'username' => '   ', 'role' => 'agent']);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testValidateTrimsUsername(): void
    {
        $input = $this->validInput();
        $input['username'] = '  jean.dupont  ';
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('username', $errors);
    }

    public function testValidateRejectsDuplicateUsername(): void
    {
        // Create a user first
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role) VALUES (?, ?, ?, ?)')
            ->execute(['existing.user', 'Test', 'User', 'agent']);

        $input = $this->validInput();
        $input['username'] = 'existing.user';
        $errors = $this->service->validate($input);
        $this->assertArrayHasKey('username', $errors);
        $this->assertStringContainsString('déjà utilisé', $errors['username']);
    }

    public function testValidateAllowsSameUsernameWithExcludeId(): void
    {
        // Kill CastInt mutant on $excludeId
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role) VALUES (?, ?, ?, ?, ?)')
            ->execute([42, 'existing.user', 'Test', 'User', 'agent']);

        $input = $this->validInput();
        $input['username'] = 'existing.user';
        $errors = $this->service->validate($input, 42);
        $this->assertArrayNotHasKey('username', $errors, 'same username with excludeId must pass');
    }

    public function testValidateRejectsUsernameWithSpaces(): void
    {
        // Kill PregMatch mutant — pattern /^[a-zA-Z0-9.\-_]{2,100}$/
        $input = $this->validInput();
        $input['username'] = 'user with spaces';
        $errors = $this->service->validate($input);
        $this->assertArrayHasKey('username', $errors);
        $this->assertStringContainsString('ne doit contenir', $errors['username']);
    }

    public function testValidateRejectsUsernameWithSpecialChars(): void
    {
        $input = $this->validInput();
        $input['username'] = 'user@domain!';
        $errors = $this->service->validate($input);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testValidateRejectsUsernameTooShort(): void
    {
        // Kill PregMatchRemoveCaret / {2,100} mutant — single char must fail
        $input = $this->validInput();
        $input['username'] = 'a';
        $errors = $this->service->validate($input);
        $this->assertArrayHasKey('username', $errors, '1-char username must fail');
    }

    public function testValidateAcceptsValidUsernameFormats(): void
    {
        $validUsernames = ['jean.dupont', 'jean-dupont', 'jean_dupont', 'jean123', 'JD', 'a.b-c_d'];
        foreach ($validUsernames as $username) {
            $input = $this->validInput();
            $input['username'] = $username;
            $errors = $this->service->validate($input);
            $this->assertArrayNotHasKey('username', $errors, "username '$username' should be valid");
        }
    }

    public function testValidateAcceptsUsernameWithDigitsAndDots(): void
    {
        // Kill PregMatchRemoveDollar mutant — pattern must be anchored at end
        $input = $this->validInput();
        $input['username'] = 'user.name.123';
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('username', $errors);
    }

    // ═══ Role validation ═══

    public function testValidateRejectsInvalidRole(): void
    {
        // Kill UserRole::tryFrom mutant
        $input = $this->validInput();
        $input['role'] = 'admin';
        $errors = $this->service->validate($input);
        $this->assertArrayHasKey('role', $errors);
        $this->assertStringContainsString('invalide', $errors['role']);
    }

    public function testValidateRejectsEmptyRole(): void
    {
        $input = $this->validInput();
        $input['role'] = '';
        $errors = $this->service->validate($input);
        $this->assertArrayHasKey('role', $errors);
    }

    public function testValidateAcceptsAllValidRoles(): void
    {
        foreach (['agent', 'superviseur', 'chsct'] as $role) {
            $input = $this->validInput();
            $input['role'] = $role;
            $errors = $this->service->validate($input);
            $this->assertArrayNotHasKey('role', $errors, "role '$role' should be valid");
        }
    }

    public function testValidateTrimsRole(): void
    {
        $input = $this->validInput();
        $input['role'] = '  agent  ';
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('role', $errors, 'trimmed role must pass');
    }

    // ═══ Site ID validation ═══

    public function testValidateRejectsInvalidSiteId(): void
    {
        // Kill CastInt mutant on (int) $siteIdVal + findById null check
        $input = $this->validInput();
        $input['site_id'] = '99999'; // doesn't exist
        $errors = $this->service->validate($input);
        $this->assertArrayHasKey('site_id', $errors);
        $this->assertStringContainsString('invalide', $errors['site_id']);
    }

    public function testValidateAcceptsValidSiteId(): void
    {
        $input = $this->validInput();
        $input['site_id'] = (string) $this->siteId;
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('site_id', $errors);
    }

    public function testValidateAcceptsZeroSiteIdWhenNotInNoSiteMode(): void
    {
        // Kill LogicalNot mutant on $siteId > 0 — site_id=0 must pass (no site)
        $input = $this->validInput();
        $input['site_id'] = '0';
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('site_id', $errors, 'site_id=0 must pass (no site selected)');
    }

    public function testValidateCastsSiteIdFromString(): void
    {
        // Kill CastInt mutant
        $input = $this->validInput();
        $input['site_id'] = (string) $this->siteId; // string
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('site_id', $errors);
    }

    public function testValidateSiteIdDefaultsToZeroWhenMissing(): void
    {
        // Kill Coalesce mutant on ?? '0'
        $input = $this->validInput();
        unset($input['site_id']);
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('site_id', $errors, 'missing site_id must default to 0');
    }

    // ═══ Email validation ═══

    public function testValidateRejectsInvalidEmail(): void
    {
        // Kill filter_var mutant
        $input = $this->validInput();
        $input['email'] = 'not-an-email';
        $errors = $this->service->validate($input);
        $this->assertArrayHasKey('email', $errors);
        $this->assertStringContainsString('invalide', $errors['email']);
    }

    public function testValidateAcceptsValidEmail(): void
    {
        $input = $this->validInput();
        $input['email'] = 'jean.dupont@gouv.fr';
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function testValidateAcceptsEmptyEmail(): void
    {
        // Kill LogicalNot mutant on !empty($email) — empty email is optional
        $input = $this->validInput();
        $input['email'] = '';
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('email', $errors, 'empty email must pass (optional)');
    }

    public function testValidateTrimsEmail(): void
    {
        $input = $this->validInput();
        $input['email'] = '  jean@gouv.fr  ';
        $errors = $this->service->validate($input);
        $this->assertArrayNotHasKey('email', $errors, 'trimmed email must pass');
    }

    // ═══ Full valid input ═══

    public function testValidateReturnsNoErrorsForValidInput(): void
    {
        $errors = $this->service->validate($this->validInput());
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
        $errors = $this->service->canDemote(1, 'superviseur', ['role' => 'agent']);
        $this->assertSame([], $errors);
    }

    public function testCanDemoteReturnsNoErrorForSuperviseurStayingSuperviseur(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur']);
        $errors = $this->service->canDemote(1, 'superviseur', ['role' => 'superviseur']);
        $this->assertSame([], $errors, 'same role → no demote error');
    }

    public function testCanDemoteReturnsErrorWhenDemotingLastSuperviseur(): void
    {
        // Kill mutant on countActiveSuperviseurs() <= 1
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur']);
        $errors = $this->service->canDemote(1, 'agent', ['role' => 'superviseur']);
        $this->assertArrayHasKey('role', $errors);
        $this->assertStringContainsString('dernier superviseur', $errors['role']);
    }

    public function testCanDemoteReturnsNoErrorWhenMultipleSuperviseurs(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([1, 'sup1', 'A', 'B', 'superviseur']);
        $this->pdo->prepare('INSERT INTO users (id, username, nom, prenom, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([2, 'sup2', 'C', 'D', 'superviseur']);
        $errors = $this->service->canDemote(1, 'agent', ['role' => 'superviseur']);
        $this->assertSame([], $errors, 'multiple superviseurs → demote allowed');
    }
}
