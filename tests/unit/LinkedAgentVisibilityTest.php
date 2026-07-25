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
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_state_history');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM report_agent_invites');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');

        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote-d-Or', 1)");
        $this->siteId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active) VALUES ('Agent', 'Un', 'agent1', 'agent', {$this->siteId}, 1)");
        $this->agentId1 = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active) VALUES ('Agent', 'Deux', 'agent2', 'agent', {$this->siteId}, 1)");
        $this->agentId2 = (int) $this->pdo->lastInsertId();
    }

    private function createReport(int $declarantId, string $objet, int $isConfidential = 0): string
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
            ':declarant_prenom' => 'Un', ':site_id' => $this->siteId,
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
}
