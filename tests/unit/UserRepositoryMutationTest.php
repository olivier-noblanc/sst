<?php
/**
 * Tests UserRepository exhaustively — kills Infection mutants on:
 *   - findByRole (active filter, role filter)
 *   - findAll (active filter, siteId filter, order)
 *   - countActive (zero, positive)
 *   - existsByUsername (exists, not exists, excludeId)
 *   - countActiveSuperviseurs (zero, positive)
 *   - create (lastInsertId, defaults, NULL site_id)
 *   - update (rowCount, site_id NULL handling)
 *   - updateSite (site_chosen_at written)
 *   - deactivate/reactivate (is_active toggle)
 *   - exportData (user data, reports, responses, missing user)
 */

use PHPUnit\Framework\TestCase;
use App\Repository\UserRepository;
use App\Enum\UserRole;
use App\DTO\CreateUserCommand;
use App\DTO\UpdateUserCommand;
use App\DTO\SiteId;

class UserRepositoryMutationTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repo;
    private int $siteId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');

        $this->repo = new UserRepository($this->pdo);

        $this->pdo->prepare('INSERT INTO sites (code, nom) VALUES (?, ?)')->execute(['UR21', 'UR Test']);
        $this->siteId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');
    }

    private function seedUser(string $username, string $role = 'agent', int $isActive = 1, ?int $siteId = null): int
    {
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$username, 'Nom' . $username, 'Prenom' . $username, $role, $siteId ?? $this->siteId, $isActive]);
        return (int) $this->pdo->lastInsertId();
    }

    // ═══ findById / findByUsername ═══

    public function testFindByIdReturnsUserWhenExists(): void
    {
        $id = $this->seedUser('test.user');
        $user = $this->repo->findById($id);
        $this->assertNotNull($user);
        $this->assertSame('test.user', $user['username']);
        $this->assertSame($this->siteId, (int) $user['site_id']);
        $this->assertSame('UR21', $user['site_code']);
        $this->assertSame('UR Test', $user['site_nom']);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findById(99999));
    }

    public function testFindByUsernameReturnsUserWhenActive(): void
    {
        $this->seedUser('active.user', 'agent', 1);
        $user = $this->repo->findByUsername('active.user');
        $this->assertNotNull($user);
        $this->assertSame('active.user', $user['username']);
    }

    public function testFindByUsernameReturnsNullForInactive(): void
    {
        // Kill is_active = 1 filter mutant
        $this->seedUser('inactive.user', 'agent', 0);
        $this->assertNull($this->repo->findByUsername('inactive.user'));
    }

    public function testFindByUsernameReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findByUsername('nonexistent.user'));
    }

    public function testFindByUsernameOrAnyReturnsUserEvenIfInactive(): void
    {
        $this->seedUser('inactive.user', 'agent', 0);
        $user = $this->repo->findByUsernameOrAny('inactive.user');
        $this->assertNotNull($user);
        $this->assertSame('inactive.user', $user['username']);
    }

    // ═══ findByRole ═══

    public function testFindByRoleReturnsOnlyActiveUsersWithRole(): void
    {
        $this->seedUser('agent1', 'agent', 1);
        $this->seedUser('agent2', 'agent', 1);
        $this->seedUser('agent3', 'agent', 0); // inactive
        $this->seedUser('sup1', 'superviseur', 1);

        $result = $this->repo->findByRole('agent');
        $this->assertCount(2, $result, 'should return 2 active agents');
        $usernames = array_column($result, 'username');
        $this->assertContains('agent1', $usernames);
        $this->assertContains('agent2', $usernames);
    }

    public function testFindByRoleReturnsEmptyForUnknownRole(): void
    {
        $this->seedUser('test', 'agent', 1);
        $this->assertSame([], $this->repo->findByRole('unknown_role'));
    }

    // ═══ findAll ═══

    public function testFindAllReturnsOnlyActiveByDefault(): void
    {
        $this->seedUser('active1', 'agent', 1);
        $this->seedUser('inactive1', 'agent', 0);
        $result = $this->repo->findAll();
        $this->assertCount(1, $result);
        $this->assertSame('active1', $result[0]['username']);
    }

    public function testFindAllReturnsAllWhenActiveFalse(): void
    {
        // Kill TrueValue mutant on $active parameter
        $this->seedUser('active1', 'agent', 1);
        $this->seedUser('inactive1', 'agent', 0);
        $result = $this->repo->findAll(0, false);
        $this->assertCount(2, $result);
    }

    public function testFindAllFiltersBySiteId(): void
    {
        // Kill GreaterThan mutant on $siteId > 0
        $this->seedUser('user1', 'agent', 1, $this->siteId);
        $this->pdo->prepare('INSERT INTO sites (code, nom) VALUES (?, ?)')->execute(['UR25', 'UR 25']);
        $site2 = (int) $this->pdo->lastInsertId();
        $this->seedUser('user2', 'agent', 1, $site2);

        $result = $this->repo->findAll($this->siteId);
        $this->assertCount(1, $result);
        $this->assertSame('user1', $result[0]['username']);
    }

    public function testFindAllOrdersByNomThenPrenom(): void
    {
        $this->seedUser('z.user', 'agent', 1);
        $this->pdo->prepare('UPDATE users SET nom = ? WHERE username = ?')->execute(['AAA', 'z.user']);
        $this->seedUser('a.user', 'agent', 1);
        $this->pdo->prepare('UPDATE users SET nom = ? WHERE username = ?')->execute(['ZZZ', 'a.user']);

        $result = $this->repo->findAll();
        $this->assertSame('AAA', $result[0]['nom'], 'ordered by nom ASC');
        $this->assertSame('ZZZ', $result[1]['nom']);
    }

    // ═══ countActive ═══

    public function testCountActiveReturnsZeroWhenNoUsers(): void
    {
        $this->assertSame(0, $this->repo->countActive());
    }

    public function testCountActiveReturnsCorrectCount(): void
    {
        $this->seedUser('u1', 'agent', 1);
        $this->seedUser('u2', 'agent', 1);
        $this->seedUser('u3', 'agent', 0);
        $this->assertSame(2, $this->repo->countActive());
    }

    // ═══ existsByUsername ═══

    public function testExistsByUsernameReturnsTrueWhenExists(): void
    {
        $this->seedUser('existing.user');
        $this->assertTrue($this->repo->existsByUsername('existing.user'));
    }

    public function testExistsByUsernameReturnsFalseWhenMissing(): void
    {
        $this->assertFalse($this->repo->existsByUsername('nonexistent'));
    }

    public function testExistsByUsernameExcludesId(): void
    {
        // Kill GreaterThan mutant on $excludeId > 0
        $id = $this->seedUser('existing.user');
        $this->assertFalse($this->repo->existsByUsername('existing.user', $id));
    }

    public function testExistsByUsernameWithZeroExcludeIdIncludesSelf(): void
    {
        $this->seedUser('existing.user');
        $this->assertTrue($this->repo->existsByUsername('existing.user', 0));
    }

    // ═══ countActiveSuperviseurs ═══

    public function testCountActiveSuperviseursReturnsZeroWhenNone(): void
    {
        $this->assertSame(0, $this->repo->countActiveSuperviseurs());
    }

    public function testCountActiveSuperviseursReturnsCorrectCount(): void
    {
        $this->seedUser('sup1', 'superviseur', 1);
        $this->seedUser('sup2', 'superviseur', 1);
        $this->seedUser('sup3', 'superviseur', 0); // inactive
        $this->seedUser('agent1', 'agent', 1);
        $this->assertSame(2, $this->repo->countActiveSuperviseurs());
    }

    // ═══ create ═══

    public function testCreateReturnsPositiveId(): void
    {
        $cmd = new CreateUserCommand(
            username: 'new.user',
            nom: 'New',
            prenom: 'User',
            role: ROLE_AGENT,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $id = $this->repo->create($cmd);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateWithNoneSiteIdSetsNull(): void
    {
        $cmd = new CreateUserCommand(
            username: 'nosite.user',
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

    public function testCreateDefaultsRoleToAgent(): void
    {
        $cmd = new CreateUserCommand(
            username: 'default.role',
            nom: 'D',
            prenom: 'R',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $id = $this->repo->create($cmd);
        $user = $this->repo->findById($id);
        $this->assertSame('agent', $user['role']);
    }

    // ═══ update ═══

    public function testUpdateModifiesUserFields(): void
    {
        $id = $this->seedUser('update.user');
        $cmd = new UpdateUserCommand(
            username: 'updated',
            nom: 'Updated',
            prenom: 'User',
            role: ROLE_SUPERVISEUR,
            siteId: SiteId::fromInput($this->siteId),
            email: null,
        );
        $result = $this->repo->update($id, $cmd);
        $this->assertTrue($result);
        $user = $this->repo->findById($id);
        $this->assertSame('updated', $user['username']);
        $this->assertSame('Updated', $user['nom']);
    }

    public function testUpdateWithNoneSiteIdSetsNull(): void
    {
        $id = $this->seedUser('update.user', 'agent', 1, $this->siteId);
        $cmd = new UpdateUserCommand(
            username: 'update.user',
            nom: 'N',
            prenom: 'P',
            role: ROLE_AGENT,
            siteId: SiteId::none(),
            email: null,
        );
        $this->repo->update($id, $cmd);
        $user = $this->repo->findById($id);
        $this->assertNull($user['site_id'], 'SiteId::none() in update must produce NULL');
    }

    // ═══ updateSite ═══

    public function testUpdateSiteSetsSiteIdAndChosenAt(): void
    {
        $id = $this->seedUser('site.user', 'agent', 1, null);
        $result = $this->repo->updateSite($id, $this->siteId);
        $this->assertTrue($result);
        $user = $this->repo->findById($id);
        $this->assertSame($this->siteId, (int) $user['site_id']);
        $this->assertNotNull($user['site_chosen_at'], 'site_chosen_at must be set');
    }

    // ═══ deactivate / reactivate ═══

    public function testDeactivateSetsIsActiveToZero(): void
    {
        $id = $this->seedUser('deactivate.user');
        $result = $this->repo->deactivate($id);
        $this->assertTrue($result);
        $user = $this->repo->findByUsernameOrAny('deactivate.user');
        $this->assertSame(0, (int) $user['is_active']);
    }

    public function testReactivateSetsIsActiveToOne(): void
    {
        $id = $this->seedUser('reactivate.user', 'agent', 0);
        $result = $this->repo->reactivate($id);
        $this->assertTrue($result);
        $user = $this->repo->findByUsername('reactivate.user');
        $this->assertNotNull($user, 'should be findable after reactivation');
        $this->assertSame(1, (int) $user['is_active']);
    }

    // ═══ exportData ═══

    public function testExportDataReturnsEmptyForMissingUser(): void
    {
        $result = $this->repo->exportData(99999);
        $this->assertSame(0, $result['reports_count']);
        $this->assertSame(0, $result['responses_count']);
        $this->assertSame([], $result['reports']);
        $this->assertSame([], $result['responses']);
        // User shape is a zeroed default (toArray() uses camelCase keys)
        $this->assertArrayHasKey('user', $result);
        $this->assertSame(0, $result['user']['id']);
        $this->assertSame('', $result['user']['username']);
        $this->assertNull($result['user']['siteId']);
        $this->assertArrayHasKey('siteChosenAt', $result['user']);
        $this->assertArrayHasKey('updatedAt', $result['user']);
    }

    public function testExportDataReturnsUserData(): void
    {
        $id = $this->seedUser('export.user');
        $result = $this->repo->exportData($id);
        $this->assertSame('export.user', $result['user']['username']);
        $this->assertSame('Nomexport.user', $result['user']['nom']);
        $this->assertSame($this->siteId, $result['user']['siteId']);
    }

    public function testExportDataReturnsReportsAndResponses(): void
    {
        $id = $this->seedUser('export.user');
        // Seed a report
        $this->pdo->prepare('INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute(['uuid-exp-1', 'rsst-25-001', 'rsst', 'Obj', 'Desc', '2026-01-15', $id, 'Nom', 'Prenom', $this->siteId, 'nouveau']);
        // Seed a response
        $this->pdo->prepare('INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat) VALUES (?, ?, ?, ?)')
            ->execute(['uuid-exp-1', $id, 'Response text', 'en_cours']);

        $result = $this->repo->exportData($id);
        $this->assertSame(1, $result['reports_count']);
        $this->assertCount(1, $result['reports']);
        $this->assertSame(1, $result['responses_count']);
        $this->assertCount(1, $result['responses']);
    }
}
