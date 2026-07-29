<?php
/**
 * AuthService Unit Tests — Authentication & Provisioning
 *
 * Tests AuthService from src/Services/AuthService.php:
 * - extractUsername (static)
 * - parseSuperviseurUsernames (static)
 * - determineRole
 * - findOrCreateUser
 * - autoProvision
 * - checkAndPromote
 */

use PHPUnit\Framework\TestCase;
use App\Services\AuthService;
use App\Repository\UserRepository;
use App\Event\EventDispatcher;

class AuthServiceTest extends TestCase
{
    private PDO $pdo;
    private AuthService $service;
    private UserRepository $repo;
    private EventDispatcher $events;
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
        $this->service = new AuthService($this->repo, $this->events);

        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD_AUTH', 'Auth Site', 1)");
        $this->siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UD_AUTH'")->fetchColumn();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // extractUsername() — static
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testExtractUsernameFromDomainBackslash(): void
    {
        $this->assertEquals('jean.martin', AuthService::extractUsername('DREETS\\jean.martin'));
    }

    public function testExtractUsernameFromEmailFormat(): void
    {
        $this->assertEquals('jean.martin', AuthService::extractUsername('jean.martin@dreets.gouv.fr'));
    }

    public function testExtractUsernameFromPlainString(): void
    {
        $this->assertEquals('jean.martin', AuthService::extractUsername('jean.martin'));
    }

    public function testExtractUsernameTrimsAndLowercases(): void
    {
        $this->assertEquals('jean.martin', AuthService::extractUsername('  JEAN.MARTIN  '));
    }

    public function testExtractUsernameEmptyStringReturnsEmpty(): void
    {
        $this->assertEquals('', AuthService::extractUsername(''));
    }

    public function testExtractUsernameWhitespaceOnlyReturnsEmpty(): void
    {
        $this->assertEquals('', AuthService::extractUsername('   '));
    }

    public function testExtractUsernameComplexDomainFormat(): void
    {
        $this->assertEquals('admin', AuthService::extractUsername('CORP\\DOMAIN\\admin'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // parseSuperviseurUsernames() — static
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testParseSuperviseurUsernamesSingle(): void
    {
        $result = AuthService::parseSuperviseurUsernames('jean.martin');
        $this->assertEquals(['jean.martin'], $result);
    }

    public function testParseSuperviseurUsernamesMultiple(): void
    {
        $result = AuthService::parseSuperviseurUsernames('jean.martin, sophie.dupont');
        $this->assertEquals(['jean.martin', 'sophie.dupont'], $result);
    }

    public function testParseSuperviseurUsernamesTrimsWhitespace(): void
    {
        $result = AuthService::parseSuperviseurUsernames('  jean.martin ,  sophie.dupont  ');
        $this->assertEquals(['jean.martin', 'sophie.dupont'], $result);
    }

    public function testParseSuperviseurUsernamesLowercasesAll(): void
    {
        $result = AuthService::parseSuperviseurUsernames('Jean.Martin, Sophie.Dupont');
        $this->assertEquals(['jean.martin', 'sophie.dupont'], $result);
    }

    public function testParseSuperviseurUsernamesFiltersEmptyEntries(): void
    {
        $result = AuthService::parseSuperviseurUsernames('jean.martin,,sophie.dupont,');
        $this->assertEquals(['jean.martin', 'sophie.dupont'], $result);
    }

    public function testParseSuperviseurUsernamesEmptyStringReturnsEmpty(): void
    {
        $result = AuthService::parseSuperviseurUsernames('');
        $this->assertEmpty($result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // determineRole()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testDetermineRoleReturnsAgentByDefault(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', '');
        clearConfigCache();
        $this->assertEquals(ROLE_AGENT, $this->service->determineRole('jean.martin'));
    }

    public function testDetermineRoleReturnsSuperviseurWhenInConfigList(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin, admin.super');
        clearConfigCache();
        $this->assertEquals(ROLE_SUPERVISEUR, $this->service->determineRole('jean.martin'));
    }

    public function testDetermineRoleReturnsAgentWhenNotInConfigList(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'admin.super');
        clearConfigCache();
        $this->assertEquals(ROLE_AGENT, $this->service->determineRole('jean.martin'));
    }

    public function testDetermineRoleIsCaseInsensitive(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'Jean.Martin');
        clearConfigCache();
        $this->assertEquals(ROLE_SUPERVISEUR, $this->service->determineRole('jean.martin'));
        $this->assertEquals(ROLE_SUPERVISEUR, $this->service->determineRole('JEAN.MARTIN'));
    }

    public function testDetermineRoleWithEmptyConfigReturnsAgent(): void
    {
        clearConfigCache();
        $this->assertEquals(ROLE_AGENT, $this->service->determineRole('jean.martin'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // autoProvision()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testAutoProvisionCreatesUser(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', '');
        clearConfigCache();
        $user = $this->service->autoProvision('jean.martin');
        $this->assertNotNull($user);
        $this->assertEquals('jean.martin', $user['username']);
        $this->assertEquals(ROLE_AGENT, $user['role']);
    }

    public function testAutoProvisionExtractsNamesFromUsername(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', '');
        clearConfigCache();
        $user = $this->service->autoProvision('jean.martin');
        $this->assertEquals('Jean', $user['prenom']);
        $this->assertEquals('Martin', $user['nom']);
    }

    public function testAutoProvisionThreePartUsername(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', '');
        clearConfigCache();
        $user = $this->service->autoProvision('jean.pierre.martin');
        $this->assertEquals('Jean', $user['prenom']);
        $this->assertEquals('Pierre Martin', $user['nom']);
    }

    public function testAutoProvisionSetsEmail(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', '');
        clearConfigCache();
        $user = $this->service->autoProvision('jean.martin');
        $this->assertEquals('jean.martin@dreets.gouv.fr', $user['email']);
    }

    public function testAutoProvisionSetsRoleFromConfig(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin');
        clearConfigCache();
        $user = $this->service->autoProvision('jean.martin');
        $this->assertEquals(ROLE_SUPERVISEUR, $user['role']);
    }

    public function testAutoProvisionDispatchesEvent(): void
    {
        $dispatched = false;
        $this->events->addListener('user.provisioned', function () use (&$dispatched) {
            $dispatched = true;
        });

        updateConfig($this->pdo, 'app_superviseur_usernames', '');
        clearConfigCache();
        $this->service->autoProvision('jean.martin');
        $this->assertTrue($dispatched);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // findOrCreateUser()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFindOrCreateUserReturnsExistingUser(): void
    {
        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, is_active) VALUES ('existing.user', 'Existing', 'User', 'agent', 1)");
        $user = $this->service->findOrCreateUser('existing.user');
        $this->assertNotNull($user);
        $this->assertEquals('existing.user', $user['username']);
    }

    public function testFindOrCreateUserReturnsNullForInactiveUser(): void
    {
        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, is_active) VALUES ('inactive.user', 'Inactive', 'User', 'agent', 0)");
        $user = $this->service->findOrCreateUser('inactive.user');
        $this->assertNull($user);
    }

    public function testFindOrCreateUserAutoProvisionsNewUser(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', '');
        clearConfigCache();
        $user = $this->service->findOrCreateUser('new.user');
        $this->assertNotNull($user);
        $this->assertEquals('new.user', $user['username']);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // checkAndPromote()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testCheckAndPromoteAgentToSuperviseur(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin');
        clearConfigCache();
        $user = ['id' => 1, 'role' => ROLE_AGENT];
        $result = $this->service->checkAndPromote($user, 'jean.martin');
        $this->assertEquals(ROLE_SUPERVISEUR, $result['role']);
    }

    public function testCheckAndPromoteAgentNotInListStaysAgent(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'admin.super');
        clearConfigCache();
        $user = ['id' => 1, 'role' => ROLE_AGENT];
        $result = $this->service->checkAndPromote($user, 'jean.martin');
        $this->assertEquals(ROLE_AGENT, $result['role']);
    }

    public function testCheckAndPromoteAlreadySuperviseurStaysSuperviseur(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin');
        clearConfigCache();
        $user = ['id' => 1, 'role' => ROLE_SUPERVISEUR];
        $result = $this->service->checkAndPromote($user, 'jean.martin');
        $this->assertEquals(ROLE_SUPERVISEUR, $result['role']);
    }

    public function testCheckAndPromoteChsctNotPromoted(): void
    {
        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin');
        clearConfigCache();
        $user = ['id' => 1, 'role' => ROLE_CHSCT];
        $result = $this->service->checkAndPromote($user, 'jean.martin');
        $this->assertEquals(ROLE_CHSCT, $result['role']);
    }

    public function testCheckAndPromoteDispatchesEventOnPromotion(): void
    {
        $dispatched = false;
        $this->events->addListener('user.promoted', function () use (&$dispatched) {
            $dispatched = true;
        });

        updateConfig($this->pdo, 'app_superviseur_usernames', 'jean.martin');
        clearConfigCache();
        $user = ['id' => 1, 'role' => ROLE_AGENT];
        $this->service->checkAndPromote($user, 'jean.martin');
        $this->assertTrue($dispatched);
    }

    public function testCheckAndPromoteNoEventWhenNotInList(): void
    {
        $dispatched = false;
        $this->events->addListener('user.promoted', function () use (&$dispatched) {
            $dispatched = true;
        });

        updateConfig($this->pdo, 'app_superviseur_usernames', 'admin.super');
        clearConfigCache();
        $user = ['id' => 1, 'role' => ROLE_AGENT];
        $this->service->checkAndPromote($user, 'jean.martin');
        $this->assertFalse($dispatched);
    }
}
