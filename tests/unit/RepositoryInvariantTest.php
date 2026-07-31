<?php
use PHPUnit\Framework\TestCase;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Repository\SiteRepository;
use App\Repository\StatsRepository;
use App\Container\Container;
use App\DTO\CreateReportCommand;
use App\DTO\ReportFilter;
use App\DTO\SiteId;
use App\Enum\ReportType;

class RepositoryInvariantTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM report_agent_invites');
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
            declarantPrenom: 'Jean', siteId: SiteId::fromInput($siteId), siteText: null,
            pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: true, consentSyndicat: false,
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

    public function testFindByIdReturnsReportDataOrNull(): void
    {
        $repo = new ReportRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);
        $uuid = $this->seedReport($siteId, $userId);

        $result = $repo->findById($uuid);
        // Audit #60 — findById now returns ?ReportData (DTO object), not ?array.
        $this->assertInstanceOf(\App\DTO\ReportData::class, $result);

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

        $this->assertInstanceOf(\App\DTO\PaginatedReports::class, $result);
        $this->assertIsArray($result->reports);
        $this->assertIsInt($result->total);
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

        $token = bin2hex(random_bytes(32));
        $repo->createAgentInviteWithToken($uuid, 'test@example.com', $token);
        $result = $repo->getAgentInviteByToken($token);
        $this->assertIsArray($result);

        $this->assertNull($repo->getAgentInviteByToken('nonexistent-token'));
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

        // Audit #60 — getIndicateurs now returns IndicateursData (DTO object),
        // not an array. assertIsArray was failing silently.
        $this->assertInstanceOf(\App\DTO\IndicateursData::class, $result);
        $this->assertIsInt($result->totalReports);
        $this->assertIsInt($result->totalNouveau);
        $this->assertIsInt($result->totalEnCours);
        $this->assertIsInt($result->totalTraite);
        $this->assertIsArray($result->registryTotals);
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

    // The above only checks the return SHAPE — it would still pass even if
    // the year boundary filter (:year_start/:year_next in the SQL) were
    // completely broken, e.g. mixing years together or matching nothing.
    // This creates one report backdated to 2024 and one dated 2026, and
    // checks that querying year 2026 counts only the 2026 report — not 0,
    // not 2, not both years merged.
    public function testGetSynthesisFiltersByYear(): void
    {
        $repo = new StatsRepository($this->pdo);
        $siteId = $this->seedSite();
        $userId = $this->seedUser($siteId);

        $uuid2024 = $this->seedReport($siteId, $userId);
        $this->pdo->prepare("UPDATE reports SET created_at = '2024-06-15 10:00:00' WHERE uuid = ?")->execute([$uuid2024]);

        $uuid2026 = $this->seedReport($siteId, $userId);
        $this->pdo->prepare("UPDATE reports SET created_at = '2026-06-15 10:00:00' WHERE uuid = ?")->execute([$uuid2026]);

        $rows2026 = $repo->getSynthesis('2026', $siteId);
        $total2026 = array_sum(array_column($rows2026, 'total'));
        $this->assertEquals(1, $total2026, 'getSynthesis(2026) should count only the report dated in 2026, not the one from 2024.');

        $rows2024 = $repo->getSynthesis('2024', $siteId);
        $total2024 = array_sum(array_column($rows2024, 'total'));
        $this->assertEquals(1, $total2024, 'getSynthesis(2024) should count only the report dated in 2024.');

        $rows2025 = $repo->getSynthesis('2025', $siteId);
        $total2025 = array_sum(array_column($rows2025, 'total'));
        $this->assertEquals(0, $total2025, 'getSynthesis(2025) should count neither report — year boundary is exclusive/correct.');
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
