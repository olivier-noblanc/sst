<?php
/**
 * Linked Agent Visibility Tests — Application SST DREETS BFC
 *
 * Tests that agents linked via report_agents see those reports
 * in both the count methods and the paginated listing.
 */

use PHPUnit\Framework\TestCase;
use App\Repository\ReportRepository;
use App\DTO\ReportFilter;
use App\Enum\ReportType;
use App\Enum\ReportState;
use App\Enum\VisibilityMode;

class LinkedAgentVisibilityTest extends TestCase
{
    private PDO $pdo;
    private ReportRepository $repo;
    private int $siteId;
    private int $siteId2;
    private int $agentId1;
    private int $agentId2;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->repo = new ReportRepository($this->pdo);

        // Ensure report_agents table exists
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS report_agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_uuid TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(report_uuid, user_id)
        )");

        $this->pdo->exec('DELETE FROM report_agents');
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM report_agent_invites');
        $this->pdo->exec('DELETE FROM sites');

        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote-d-Or', 1)");
        $this->siteId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD25', 'Doubs', 1)");
        $this->siteId2 = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active) VALUES ('Agent', 'Un', 'agent1', 'agent', {$this->siteId}, 1)");
        $this->agentId1 = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active) VALUES ('Agent', 'Deux', 'agent2', 'agent', {$this->siteId2}, 1)");
        $this->agentId2 = (int) $this->pdo->lastInsertId();
    }

    private function createReport(int $declarantId, string $objet, int $isConfidential = 0, ?int $siteId = null): string
    {
        $uuid = 'test-' . uniqid();
        $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, lieu,
                declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, consent_syndicat, etat)
            VALUES (:uuid, :reference, :type, :objet, :description, :date_evenement, :lieu,
                :declarant_id, :declarant_nom, :declarant_prenom, :site_id, :is_confidential, 0, :etat)
        ')->execute([
            ':uuid' => $uuid, ':reference' => 'RSST-25-' . mt_rand(100, 999), ':type' => ReportType::Rsst->value,
            ':objet' => $objet, ':description' => 'Test',
            ':date_evenement' => '2026-01-15', ':lieu' => 'Bureau',
            ':declarant_id' => $declarantId, ':declarant_nom' => 'Agent',
            ':declarant_prenom' => 'Un', ':site_id' => $siteId ?? $this->siteId,
            ':is_confidential' => $isConfidential, ':etat' => ReportState::Nouveau->value,
        ]);
        return $uuid;
    }

    private function linkAgent(string $reportUuid, int $userId): void
    {
        $this->pdo->prepare('INSERT INTO report_agents (report_uuid, user_id) VALUES (:uuid, :user_id)')
            ->execute([':uuid' => $reportUuid, ':user_id' => $userId]);
    }

    // ─── countVisibleForAgent ─────────────────────────────────────────

    public function testCountVisibleForAgent_Confidential_IncludesLinkedReports(): void
    {
        // agent2 creates a confidential report, links agent1
        $uuid = $this->createReport($this->agentId2, 'Report linked to agent1', 1);
        $this->linkAgent($uuid, $this->agentId1);

        // agent1 should see 1 report (the linked one) in confidential mode
        $count = $this->repo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::Confidential->value);
        $this->assertEquals(1, $count);
    }

    public function testCountVisibleForAgent_Confidential_DoesNotIncludeUnlinkedConfidentialReports(): void
    {
        // agent2 creates a confidential report, does NOT link agent1
        $this->createReport($this->agentId2, 'Confidential not linked', 1);

        // agent1 should see 0 reports
        $count = $this->repo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::Confidential->value);
        $this->assertEquals(0, $count);
    }

    public function testCountVisibleForAgent_Confidential_IncludesOwnReports(): void
    {
        // agent1 creates their own report
        $this->createReport($this->agentId1, 'Own report');

        $count = $this->repo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::Confidential->value);
        $this->assertEquals(1, $count);
    }

    public function testCountVisibleForAgent_AgentChoice_IncludesLinkedConfidentialReports(): void
    {
        // agent2 creates a confidential report, links agent1
        $uuid = $this->createReport($this->agentId2, 'Linked confidential', 1);
        $this->linkAgent($uuid, $this->agentId1);

        // agent1 should see it in agent_choice mode (linked = declarant-equivalent access)
        $count = $this->repo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::AgentChoice->value);
        $this->assertEquals(1, $count);
    }

    public function testCountVisibleForAgent_AgentChoice_IncludesPublicReportsFromOthers(): void
    {
        // agent2 creates a public report
        $this->createReport($this->agentId2, 'Public from agent2', 0);

        // agent1 should see it in agent_choice mode
        $count = $this->repo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::AgentChoice->value);
        $this->assertEquals(1, $count);
    }

    public function testCountVisibleForAgent_ExcludesAbandonedReports(): void
    {
        $uuid = $this->createReport($this->agentId1, 'Will be abandoned');
        $this->pdo->prepare("UPDATE reports SET etat = :etat WHERE uuid = :uuid")
            ->execute([':uuid' => $uuid, ':etat' => ReportState::Abandonne->value]);

        $count = $this->repo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::Confidential->value);
        $this->assertEquals(0, $count);
    }

    // ─── findPaginated with linkedAgentId ─────────────────────────────

    public function testFindPaginated_Confidential_IncludesLinkedReports(): void
    {
        $uuid = $this->createReport($this->agentId2, 'Linked report', 1);
        $this->linkAgent($uuid, $this->agentId1);

        $filter = new ReportFilter(type: ReportType::Rsst->value, linkedAgentId: $this->agentId1);
        $result = $this->repo->findPaginated($filter);

        $this->assertEquals(1, $result->total);
        $this->assertCount(1, $result->reports);
        $this->assertEquals($uuid, $result->reports[0]->uuid);
    }

    public function testFindPaginated_Confidential_ExcludesUnlinkedConfidentialReports(): void
    {
        $this->createReport($this->agentId2, 'Not linked', 1);

        $filter = new ReportFilter(type: ReportType::Rsst->value, linkedAgentId: $this->agentId1);
        $result = $this->repo->findPaginated($filter);

        $this->assertEquals(0, $result->total);
        $this->assertCount(0, $result->reports);
    }

    public function testFindPaginated_AgentChoice_IncludesLinkedConfidentialAndPublicReports(): void
    {
        // agent2 creates a confidential report (linked to agent1) + a public report
        $uuidConfidential = $this->createReport($this->agentId2, 'Linked confidential', 1);
        $this->linkAgent($uuidConfidential, $this->agentId1);
        $uuidPublic = $this->createReport($this->agentId2, 'Public from agent2', 0);

        $filter = new ReportFilter(type: ReportType::Rsst->value, linkedAgentId: $this->agentId1, linkedAgentVisibility: VisibilityMode::AgentChoice->value);
        $result = $this->repo->findPaginated($filter);

        // Should see both: the linked confidential + the public one
        $this->assertEquals(2, $result->total);
        $uuids = array_map(fn($r) => $r->uuid, $result->reports);
        $this->assertContains($uuidConfidential, $uuids);
        $this->assertContains($uuidPublic, $uuids);
    }

    // ─── findPaginated with linkedAgentId AND forceSiteId together ────
    //
    // Audit #80 — none of the tests above ever set forceSiteId, so the
    // interaction between the two filters was never exercised. That's
    // exactly why this flip-flopped twice without anyone noticing:
    // 67037c4 (24/07) fixed "linked reports from another site invisible"
    // by skipping force_site_id when linked_agent_id was set. c965c0c /
    // Audit #3-High (25/07) fixed a real cross-site leak (unlinked public
    // reports from every site visible in AgentChoice mode) by applying
    // force_site_id unconditionally — which silently undid 67037c4's fix,
    // since it ANDed the site restriction onto the linked-reports
    // condition too. report_list.php always sets both together for a
    // logged-in agent, so this is the actual production code path.

    public function testFindPaginated_Confidential_ForceSiteId_IncludesLinkedReportFromOtherSite(): void
    {
        // agent1 is at $this->siteId. The report is filed at $this->siteId2
        // (by agent2, who happens to be based there) and agent1 is linked
        // to it. force_site_id is agent1's own site ($this->siteId) — the
        // report must still be visible: being linked is the authorization,
        // regardless of which site the report itself belongs to.
        $uuid = $this->createReport($this->agentId2, 'Linked, filed at another site', 1, $this->siteId2);
        $this->linkAgent($uuid, $this->agentId1);

        $filter = new ReportFilter(type: ReportType::Rsst->value, linkedAgentId: $this->agentId1, forceSiteId: $this->siteId);
        $result = $this->repo->findPaginated($filter);

        $this->assertEquals(1, $result->total, 'A report linked to the agent must be visible even when filed at a different site.');
        $this->assertEquals($uuid, $result->reports[0]->uuid);
    }

    public function testFindPaginated_AgentChoice_ForceSiteId_IncludesLinkedReportFromOtherSite(): void
    {
        $uuid = $this->createReport($this->agentId2, 'Linked, filed at another site', 1, $this->siteId2);
        $this->linkAgent($uuid, $this->agentId1);

        $filter = new ReportFilter(type: ReportType::Rsst->value, linkedAgentId: $this->agentId1, linkedAgentVisibility: VisibilityMode::AgentChoice->value, forceSiteId: $this->siteId);
        $result = $this->repo->findPaginated($filter);

        $this->assertEquals(1, $result->total, 'A report linked to the agent must be visible even when filed at a different site.');
        $this->assertEquals($uuid, $result->reports[0]->uuid);
    }

    public function testFindPaginated_AgentChoice_ForceSiteId_ExcludesUnlinkedPublicReportFromOtherSite(): void
    {
        // The actual leak #3-High (c965c0c) fixed: an unlinked public
        // report filed at a DIFFERENT site must NOT leak into agent1's
        // list just because AgentChoice mode shows public reports. This
        // must keep passing — the fix for the case above must not
        // reopen this one.
        $this->createReport($this->agentId2, 'Public, filed at another site, not linked', 0, $this->siteId2);

        $filter = new ReportFilter(type: ReportType::Rsst->value, linkedAgentId: $this->agentId1, linkedAgentVisibility: VisibilityMode::AgentChoice->value, forceSiteId: $this->siteId);
        $result = $this->repo->findPaginated($filter);

        $this->assertEquals(0, $result->total, 'An unlinked public report from another site must not be visible — cross-site leak (Audit #3-High).');
    }

    public function testFindPaginated_AgentChoice_ForceSiteId_IncludesPublicReportFromOwnSite(): void
    {
        // Sanity check the fallback still works at all: an unlinked public
        // report filed at the AGENT'S OWN site must still show up.
        $uuid = $this->createReport($this->agentId2, 'Public, filed at own site, not linked', 0, $this->siteId);

        $filter = new ReportFilter(type: ReportType::Rsst->value, linkedAgentId: $this->agentId1, linkedAgentVisibility: VisibilityMode::AgentChoice->value, forceSiteId: $this->siteId);
        $result = $this->repo->findPaginated($filter);

        $this->assertEquals(1, $result->total);
        $this->assertEquals($uuid, $result->reports[0]->uuid);
    }
}
