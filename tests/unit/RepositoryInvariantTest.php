<?php
use PHPUnit\Framework\TestCase;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Repository\SiteRepository;
use App\Repository\StatsRepository;
use App\Container\Container;
use App\DTO\CreateReportCommand;
use App\DTO\ReportFilter;

class RepositoryInvariantTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_state_history');
        $this->pdo->exec('DELETE FROM report_agent_invites');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        // Ensure report_agents table exists (may not be in schema.sql yet)
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS report_agents (
            report_uuid TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            UNIQUE(report_uuid, user_id)
        )");
        $this->pdo->exec('DELETE FROM report_agents');
    }

    private function seedSite(): int
    {
        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote-d-Or', 1)");
        return (int) $this->pdo->lastInsertId();
    }

    private function seedUser(int $siteId, string $role = 'agent'): int
    {
        $this->pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active) VALUES ('Martin', 'Jean', 'jean.martin', '$role', $siteId, 1)");
        return (int) $this->pdo->lastInsertId();
    }

    private function seedUserWithUsername(int $siteId, string $username, string $role = 'agent'): int
    {
        $this->pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active) VALUES ('Dupont', 'Paul', '$username', '$role', $siteId, 1)");
        return (int) $this->pdo->lastInsertId();
    }

    private function seedReport(int $siteId, int $userId, string $etat = 'nouveau', string $type = 'rsst'): string
    {
        $cmd = new CreateReportCommand(
            type: $type, objet: 'Test', description: 'Desc',
            dateEvenement: '2026-01-15', heureEvenement: '10:30',
            lieu: 'Bureau', declarantId: $userId, declarantNom: 'Martin',
            declarantPrenom: 'Jean', siteId: $siteId, siteText: null,
            pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: 1, consentSyndicat: 0,
            natureAuteur: null, typeActe: null,
            pourCompteNom: null, pourComptePrenom: null,
            attachmentBlob: null, attachmentName: null, attachmentMime: null,
        );
        $uuid = (new ReportRepository($this->pdo))->create($cmd);

        if ($etat !== 'nouveau') {
            $this->pdo->prepare("UPDATE reports SET etat = ? WHERE uuid = ?")->execute([$etat, $uuid]);
        }

        return $uuid;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // ReportRepository
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFindByIdReturnsArrayOrNull(): void
    {
        $repo = new ReportRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $uuid = $this->seedReport($siteId, $userId);

        $result = $repo->findById($uuid);
        $this->assertIsArray($result);

        $this->assertNull($repo->findById('00000000-0000-0000-0000-000000000000'));
    }

    public function testFindPaginatedReturnsArrayWithReportsKey(): void
    {
        $repo = new ReportRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $this->seedReport($siteId, $userId);

        $filter = new ReportFilter(
            type: 'rsst',
            forceSiteId: $siteId,
            seeAllSites: false,
        );
        $result = $repo->findPaginated($filter);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reports', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertIsArray($result['reports']);
        $this->assertIsInt($result['total']);
    }

    public function testFindBySiteReturnsArray(): void
    {
        $repo = new ReportRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $this->seedReport($siteId, $userId);

        $result = $repo->findBySite($siteId);
        $this->assertIsArray($result);
    }

    public function testGetResponsesReturnsArray(): void
    {
        $repo = new ReportRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $uuid = $this->seedReport($siteId, $userId);

        $result = $repo->getResponses($uuid);
        $this->assertIsArray($result);
    }

    public function testGetLinkedAgentsReturnsArray(): void
    {
        $repo = new ReportRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $uuid = $this->seedReport($siteId, $userId);

        $result = $repo->getLinkedAgents($uuid);
        $this->assertIsArray($result);
    }

    public function testGetPendingInvitesReturnsArray(): void
    {
        $repo = new ReportRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $uuid = $this->seedReport($siteId, $userId);

        $result = $repo->getPendingInvites($uuid);
        $this->assertIsArray($result);
    }

    public function testGetAgentInviteByTokenReturnsArrayOrNull(): void
    {
        $repo = new ReportRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $uuid = $this->seedReport($siteId, $userId);

        $token = $repo->createAgentInvite($uuid, 'test@example.com');
        $result = $repo->getAgentInviteByToken($token);
        $this->assertIsArray($result);

        $this->assertNull($repo->getAgentInviteByToken('nonexistent-token'));
    }

    public function testCountByStateReturnsArrayWithExpectedKeys(): void
    {
        $repo = new ReportRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $this->seedReport($siteId, $userId, 'nouveau');
        $this->seedReport($siteId, $userId, 'en_cours');
        $this->seedReport($siteId, $userId, 'traite');

        $result = $repo->countByState('rsst');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nouveau', $result);
        $this->assertArrayHasKey('en_cours', $result);
        $this->assertArrayHasKey('traite', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testGetStatisticsReturnsArray(): void
    {
        $repo = new ReportRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $this->seedReport($siteId, $userId);

        $result = $repo->getStatistics();
        $this->assertIsArray($result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // UserRepository
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testUserFindByIdReturnsArrayOrNull(): void
    {
        $repo = new UserRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);

        $this->assertIsArray($repo->findById($userId));
        $this->assertNull($repo->findById(999999));
    }

    public function testUserFindByUsernameReturnsArrayOrNull(): void
    {
        $repo = new UserRepository($this->pdo);
        $siteId = $this->seedSite();
        $this->seedUser($siteId);

        $this->assertIsArray($repo->findByUsername('jean.martin'));
        $this->assertNull($repo->findByUsername('nonexistent'));
    }

    public function testUserFindByRoleReturnsArray(): void
    {
        $repo = new UserRepository($this->pdo);
        $siteId = $this->seedSite();
        $this->seedUser($siteId, 'agent');
        $this->seedUserWithUsername($siteId, 'paul.durand', 'agent');

        $result = $repo->findByRole('agent');
        $this->assertIsArray($result);
    }

    public function testUserFindAllReturnsArray(): void
    {
        $repo = new UserRepository($this->pdo);
        $siteId = $this->seedSite();
        $this->seedUser($siteId);

        $result = $repo->findAll();
        $this->assertIsArray($result);
    }

    public function testUserExistsByUsernameReturnsBool(): void
    {
        $repo = new UserRepository($this->pdo);
        $siteId = $this->seedSite();
        $this->seedUser($siteId);

        $this->assertTrue($repo->existsByUsername('jean.martin'));
        $this->assertFalse($repo->existsByUsername('nonexistent'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // SiteRepository
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSiteFindByIdReturnsArrayOrNull(): void
    {
        $repo = new SiteRepository($this->pdo);
        $siteId = $this->seedSite();

        $this->assertIsArray($repo->findById($siteId));
        $this->assertNull($repo->findById(999999));
    }

    public function testSiteFindByCodeReturnsArrayOrNull(): void
    {
        $repo = new SiteRepository($this->pdo);
        $this->seedSite();

        $this->assertIsArray($repo->findByCode('UD21'));
        $this->assertNull($repo->findByCode('FAKE'));
    }

    public function testSiteFindAllReturnsArray(): void
    {
        $repo = new SiteRepository($this->pdo);
        $this->seedSite();

        $result = $repo->findAll();
        $this->assertIsArray($result);
    }

    public function testSiteFindActiveReturnsArray(): void
    {
        $repo = new SiteRepository($this->pdo);
        $this->seedSite();

        $result = $repo->findActive();
        $this->assertIsArray($result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // StatsRepository
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetIndicateursReturnsArrayWithTotalKeys(): void
    {
        $repo = new StatsRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $this->seedReport($siteId, $userId);

        $result = $repo->getIndicateurs();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_reports', $result);
        $this->assertArrayHasKey('total_nouveau', $result);
        $this->assertArrayHasKey('total_en_cours', $result);
        $this->assertArrayHasKey('total_traite', $result);
        $this->assertArrayHasKey('total_abandonne', $result);
        $this->assertArrayHasKey('total_rsst', $result);
        $this->assertArrayHasKey('total_rami', $result);
        $this->assertArrayHasKey('total_dgi', $result);
    }

    public function testGetAvailableYearsReturnsArray(): void
    {
        $repo = new StatsRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $this->seedReport($siteId, $userId);

        $result = $repo->getAvailableYears();
        $this->assertIsArray($result);
    }

    public function testGetSynthesisReturnsArray(): void
    {
        $repo = new StatsRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $this->seedReport($siteId, $userId);

        $result = $repo->getSynthesis(date('Y'));
        $this->assertIsArray($result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Container
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testContainerGetReturnsInstanceOfClass(): void
    {
        $container = new Container();
        $container->set(\stdClass::class, fn() => new \stdClass());

        $result = $container->get(\stdClass::class);
        $this->assertInstanceOf(\stdClass::class, $result);
    }
}
