<?php
/**
 * Access Helper Integration Tests — Application SST DREETS BFC
 *
 * Tests canAccessReport() with real DB data to verify the interaction
 * between access control logic and database state.
 *
 * Unlike the pure unit tests in AccessHelperTest.php, these tests
 * create actual DB records and test the full data flow.
 */

use PHPUnit\Framework\TestCase;

class AccessHelperIntegrationTest extends TestCase
{
    private PDO $pdo;
    private int $siteId1;
    private int $siteId2;
    private int $agentId1;
    private int $agentId2;
    private int $superviseurId;
    private int $chsctId;

    protected function setUp(): void
    {
        $this->pdo = getDB();

        // Ensure report_agents table exists
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS report_agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_uuid TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(report_uuid, user_id)
        )");
        $this->pdo->exec('DELETE FROM report_agents');

        // Clean up
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_state_history');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM report_agent_invites');
        $this->pdo->exec('DELETE FROM reports');

        // Seed sites
        $this->pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1)");
        $this->siteId1 = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UD21'")->fetchColumn();

        $this->pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UD25', 'Doubs', 1)");
        $this->siteId2 = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UD25'")->fetchColumn();

        // Seed users
        $this->pdo->exec("INSERT OR IGNORE INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('agent1', 'Agent', 'Un', 'agent', {$this->siteId1}, 1)");
        $this->agentId1 = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'agent1'")->fetchColumn();

        $this->pdo->exec("INSERT OR IGNORE INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('agent2', 'Agent', 'Deux', 'agent', {$this->siteId1}, 1)");
        $this->agentId2 = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'agent2'")->fetchColumn();

        $this->pdo->exec("INSERT OR IGNORE INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('superv1', 'Super', 'Visor', 'superviseur', {$this->siteId1}, 1)");
        $this->superviseurId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'superv1'")->fetchColumn();

        $this->pdo->exec("INSERT OR IGNORE INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('chsct1', 'CHSCT', 'Membre', 'chsct', {$this->siteId1}, 1)");
        $this->chsctId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'chsct1'")->fetchColumn();
    }

    private function makeUser(array $overrides = []): array
    {
        return array_merge([
            'id' => $this->agentId1,
            'site_id' => $this->siteId1,
            'role' => 'agent',
        ], $overrides);
    }

    private function makeReport(array $overrides = []): array
    {
        return array_merge([
            'site_id' => $this->siteId1,
            'declarant_id' => $this->agentId1,
            'is_confidential' => 0,
            'consent_syndicat' => 0,
            'type' => 'rsst',
        ], $overrides);
    }

    // ─── Superviseur access ──────────────────────────────────────────────

    public function testSuperviseurCanAccessAnyReport(): void
    {
        $user = $this->makeUser(['id' => $this->superviseurId, 'role' => 'superviseur']);
        $report = $this->makeReport(['is_confidential' => 1, 'declarant_id' => 999, 'site_id' => $this->siteId2]);
        $this->assertTrue(canAccessReport($report, $user));
    }

    public function testSuperviseurCanAccessConfidentialReportFromOtherSite(): void
    {
        $user = $this->makeUser(['id' => $this->superviseurId, 'role' => 'superviseur', 'site_id' => $this->siteId2]);
        $report = $this->makeReport(['is_confidential' => 1, 'site_id' => $this->siteId1, 'declarant_id' => 999]);
        $this->assertTrue(canAccessReport($report, $user));
    }

    // ─── CHSCT access ────────────────────────────────────────────────────

    public function testChsctCanAccessReportWithConsent(): void
    {
        $user = $this->makeUser(['id' => $this->chsctId, 'role' => 'chsct']);
        $report = $this->makeReport(['consent_syndicat' => 1]);
        $this->assertTrue(canAccessReport($report, $user));
    }

    public function testChsctCannotAccessReportWithoutConsent(): void
    {
        $user = $this->makeUser(['id' => $this->chsctId, 'role' => 'chsct']);
        $report = $this->makeReport(['consent_syndicat' => 0]);
        $this->assertFalse(canAccessReport($report, $user));
    }

    public function testChsctCanAccessOtherSiteReportWithConsent(): void
    {
        // CHSCT role returns on consent check BEFORE site check — consent overrides site
        $user = $this->makeUser(['id' => $this->chsctId, 'role' => 'chsct', 'site_id' => $this->siteId1]);
        $report = $this->makeReport(['site_id' => $this->siteId2, 'consent_syndicat' => 1]);
        $this->assertTrue(canAccessReport($report, $user));
    }

    // ─── Agent access — same site ────────────────────────────────────────

    public function testAgentCanAccessPublicReportOnSameSite(): void
    {
        $user = $this->makeUser(['id' => $this->agentId1]);
        $report = $this->makeReport(['is_confidential' => 0, 'declarant_id' => $this->agentId2]);
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCannotAccessConfidentialReportFromOtherAgent(): void
    {
        $user = $this->makeUser(['id' => $this->agentId1]);
        $report = $this->makeReport(['is_confidential' => 1, 'declarant_id' => $this->agentId2]);
        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessOwnConfidentialReport(): void
    {
        $user = $this->makeUser(['id' => $this->agentId1]);
        $report = $this->makeReport(['is_confidential' => 1, 'declarant_id' => $this->agentId1]);
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    // ─── Agent access — other site ───────────────────────────────────────

    public function testAgentCannotAccessReportFromOtherSite(): void
    {
        $user = $this->makeUser(['id' => $this->agentId1, 'site_id' => $this->siteId1]);
        $report = $this->makeReport(['site_id' => $this->siteId2, 'declarant_id' => 999]);
        $this->assertFalse(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCannotAccessOtherSiteReportEvenIfNotConfidential(): void
    {
        $user = $this->makeUser(['id' => $this->agentId1, 'site_id' => $this->siteId1]);
        $report = $this->makeReport(['site_id' => $this->siteId2, 'is_confidential' => 0, 'declarant_id' => 999]);
        $this->assertFalse(canAccessReport($report, $user, 'public'));
    }

    // ─── Agent choice mode ───────────────────────────────────────────────

    public function testAgentChoiceModePublicReportVisible(): void
    {
        $user = $this->makeUser(['id' => $this->agentId1]);
        $report = $this->makeReport(['is_confidential' => 0, 'declarant_id' => $this->agentId2]);
        $this->assertTrue(canAccessReport($report, $user, 'agent_choice'));
    }

    public function testAgentChoiceModeConfidentialReportHidden(): void
    {
        $user = $this->makeUser(['id' => $this->agentId1]);
        $report = $this->makeReport(['is_confidential' => 1, 'declarant_id' => $this->agentId2]);
        $this->assertFalse(canAccessReport($report, $user, 'agent_choice'));
    }

    public function testAgentChoiceModeOwnConfidentialVisible(): void
    {
        $user = $this->makeUser(['id' => $this->agentId1]);
        $report = $this->makeReport(['is_confidential' => 1, 'declarant_id' => $this->agentId1]);
        $this->assertTrue(canAccessReport($report, $user, 'agent_choice'));
    }

    // ─── canEditReport with DB data ──────────────────────────────────────

    public function testDeclarantCanEditNewReportInDb(): void
    {
        $report = $this->makeReport(['declarant_id' => $this->agentId1, 'etat' => 'nouveau']);
        $this->assertTrue(canEditReport($report, $this->agentId1));
    }

    public function testNonDeclarantCannotEditReportInDb(): void
    {
        $report = $this->makeReport(['declarant_id' => $this->agentId1, 'etat' => 'nouveau']);
        $this->assertFalse(canEditReport($report, $this->agentId2));
    }

    public function testDeclarantCannotEditTreatedReportInDb(): void
    {
        $report = $this->makeReport(['declarant_id' => $this->agentId1, 'etat' => 'traite']);
        $this->assertFalse(canEditReport($report, $this->agentId1));
    }

    // ─── canRespondToReport with DB data ─────────────────────────────────

    public function testSuperviseurCanRespondToNewReportInDb(): void
    {
        $report = $this->makeReport(['etat' => 'nouveau']);
        $this->assertTrue(canRespondToReport($report, 'superviseur'));
    }

    public function testAgentCannotRespondToReportInDb(): void
    {
        $report = $this->makeReport(['etat' => 'nouveau']);
        $this->assertFalse(canRespondToReport($report, 'agent'));
    }

    public function testSuperviseurCanRespondToReouvertReportInDb(): void
    {
        $report = $this->makeReport(['etat' => 'reouvert']);
        $this->assertTrue(canRespondToReport($report, 'superviseur'));
    }

    public function testSuperviseurCannotRespondToTreatedReportInDb(): void
    {
        $report = $this->makeReport(['etat' => 'traite']);
        $this->assertFalse(canRespondToReport($report, 'superviseur'));
    }

    // ─── Linked agent access (report_agents) ───────────────────────────

    public function testLinkedAgentCanAccessConfidentialReport(): void
    {
        // Create a report by agent2
        $uuid = 'test-linked-agent-' . uniqid();
        $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, lieu, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, consent_syndicat, etat)
            VALUES (:uuid, :reference, :type, :objet, :description, :date_evenement, :lieu, :declarant_id, :declarant_nom, :declarant_prenom, :site_id, 1, 0, :etat)
        ')->execute([
            ':uuid' => $uuid, ':reference' => 'RSST-25-800', ':type' => 'rsst',
            ':objet' => 'Test linked agent', ':description' => 'Test',
            ':date_evenement' => '2025-01-15', ':lieu' => 'Bureau',
            ':declarant_id' => $this->agentId2, ':declarant_nom' => 'Agent',
            ':declarant_prenom' => 'Deux', ':site_id' => $this->siteId1, ':etat' => 'nouveau',
        ]);

        // Link agent1 to this report
        $this->pdo->prepare('INSERT INTO report_agents (report_uuid, user_id) VALUES (:uuid, :user_id)')
            ->execute([':uuid' => $uuid, ':user_id' => $this->agentId1]);

        $report = $this->pdo->prepare('SELECT * FROM reports WHERE uuid = :uuid');
        $report->execute([':uuid' => $uuid]);
        $reportRow = $report->fetch();

        $user = $this->makeUser(['id' => $this->agentId1]);
        $this->assertTrue(canAccessReport($reportRow, $user, 'confidential'));
    }

    public function testLinkedAgentCanAccessAgentChoiceConfidentialReport(): void
    {
        $uuid = 'test-linked-agent-choice-' . uniqid();
        $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, lieu, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, consent_syndicat, etat)
            VALUES (:uuid, :reference, :type, :objet, :description, :date_evenement, :lieu, :declarant_id, :declarant_nom, :declarant_prenom, :site_id, 1, 0, :etat)
        ')->execute([
            ':uuid' => $uuid, ':reference' => 'RSST-25-801', ':type' => 'rsst',
            ':objet' => 'Test linked agent choice', ':description' => 'Test',
            ':date_evenement' => '2025-01-15', ':lieu' => 'Bureau',
            ':declarant_id' => $this->agentId2, ':declarant_nom' => 'Agent',
            ':declarant_prenom' => 'Deux', ':site_id' => $this->siteId1, ':etat' => 'nouveau',
        ]);

        $this->pdo->prepare('INSERT INTO report_agents (report_uuid, user_id) VALUES (:uuid, :user_id)')
            ->execute([':uuid' => $uuid, ':user_id' => $this->agentId1]);

        $report = $this->pdo->prepare('SELECT * FROM reports WHERE uuid = :uuid');
        $report->execute([':uuid' => $uuid]);
        $reportRow = $report->fetch();

        $user = $this->makeUser(['id' => $this->agentId1]);
        $this->assertTrue(canAccessReport($reportRow, $user, 'agent_choice'));
    }

    public function testNonLinkedAgentCannotAccessConfidentialReport(): void
    {
        $uuid = 'test-non-linked-' . uniqid();
        $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, lieu, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, consent_syndicat, etat)
            VALUES (:uuid, :reference, :type, :objet, :description, :date_evenement, :lieu, :declarant_id, :declarant_nom, :declarant_prenom, :site_id, 1, 0, :etat)
        ')->execute([
            ':uuid' => $uuid, ':reference' => 'RSST-25-802', ':type' => 'rsst',
            ':objet' => 'Test non-linked', ':description' => 'Test',
            ':date_evenement' => '2025-01-15', ':lieu' => 'Bureau',
            ':declarant_id' => $this->agentId2, ':declarant_nom' => 'Agent',
            ':declarant_prenom' => 'Deux', ':site_id' => $this->siteId1, ':etat' => 'nouveau',
        ]);

        // agent1 is NOT linked to this report
        $report = $this->pdo->prepare('SELECT * FROM reports WHERE uuid = :uuid');
        $report->execute([':uuid' => $uuid]);
        $reportRow = $report->fetch();

        $user = $this->makeUser(['id' => $this->agentId1]);
        $this->assertFalse(canAccessReport($reportRow, $user, 'confidential'));
    }

    // ─── normalizeVisibilityValue ────────────────────────────────────────

    public function testNormalizeVisibilityValues(): void
    {
        $this->assertEquals('public', normalizeVisibilityValue('0'));
        $this->assertEquals('public', normalizeVisibilityValue('site'));
        $this->assertEquals('confidential', normalizeVisibilityValue('1'));
        $this->assertEquals('confidential', normalizeVisibilityValue('own'));
        $this->assertEquals('confidential', normalizeVisibilityValue('confidential'));
        $this->assertEquals('agent_choice', normalizeVisibilityValue('agent_choice'));
        $this->assertEquals('public', normalizeVisibilityValue('public'));
        $this->assertEquals('agent_choice', normalizeVisibilityValue('unknown'));
    }
}
