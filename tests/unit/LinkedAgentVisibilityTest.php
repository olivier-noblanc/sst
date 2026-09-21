<?php

/**
 * Linked Agent Visibility Tests — Application SST DREETS BFC
 *
 * Tests that agents linked via report_agents see those reports
 * in both the count methods and the paginated listing.
 */

use PHPUnit\Framework\TestCase;
use App\Repository\ReportRepository;
use App\Repository\ReportAgentRepository;
use App\DTO\ReportFilter;
use App\Enum\ReportType;
use App\Enum\ReportState;
use App\Enum\VisibilityMode;

class LinkedAgentVisibilityTest extends TestCase
{
    private PDO $pdo;
    private ReportRepository $repo;
    private ReportAgentRepository $agentRepo;
    private int $siteId;
    private int $siteId2;
    private int $agentId1;
    private int $agentId2;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->repo = new ReportRepository($this->pdo);
        $this->agentRepo = new ReportAgentRepository($this->pdo);

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

        $this->pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active, email) VALUES ('Agent', 'Un', 'agent1', 'agent', {$this->siteId}, 1, 'fixture@dreets-bfc.gouv.fr')");
        $this->agentId1 = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active, email) VALUES ('Agent', 'Deux', 'agent2', 'agent', {$this->siteId2}, 1, 'fixture@dreets-bfc.gouv.fr')");
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

    /** Fixe created_at (tri DESC de la liste = tri de navigation getAdjacentUuids). */
    private function setCreatedAt(string $reportUuid, string $createdAt): void
    {
        $this->pdo->prepare('UPDATE reports SET created_at = :created_at WHERE uuid = :uuid')
            ->execute([':created_at' => $createdAt, ':uuid' => $reportUuid]);
    }

    /** Filtre de liste d'un agent en mode AgentChoice (celui de report_list/report_view). */
    private function agentChoiceFilter(int $agentId, int $siteId): ReportFilter
    {
        return new ReportFilter(
            type: ReportType::Rsst->value,
            forceSiteId: $siteId,
            linkedAgentId: $agentId,
            linkedAgentVisibility: VisibilityMode::AgentChoice->value,
        );
    }

    // ─── countVisibleForAgent ─────────────────────────────────────────

    public function testCountVisibleForAgent_Confidential_IncludesLinkedReports(): void
    {
        // agent2 creates a confidential report, links agent1
        $uuid = $this->createReport($this->agentId2, 'Report linked to agent1', 1);
        $this->linkAgent($uuid, $this->agentId1);

        // agent1 should see 1 report (the linked one) in confidential mode
        $count = $this->agentRepo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::Confidential->value);
        $this->assertEquals(1, $count);
    }

    public function testCountVisibleForAgent_Confidential_DoesNotIncludeUnlinkedConfidentialReports(): void
    {
        // agent2 creates a confidential report, does NOT link agent1
        $this->createReport($this->agentId2, 'Confidential not linked', 1);

        // agent1 should see 0 reports
        $count = $this->agentRepo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::Confidential->value);
        $this->assertEquals(0, $count);
    }

    public function testCountVisibleForAgent_Confidential_IncludesOwnReports(): void
    {
        // agent1 creates their own report
        $this->createReport($this->agentId1, 'Own report');

        $count = $this->agentRepo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::Confidential->value);
        $this->assertEquals(1, $count);
    }

    public function testCountVisibleForAgent_AgentChoice_IncludesLinkedConfidentialReports(): void
    {
        // agent2 creates a confidential report, links agent1
        $uuid = $this->createReport($this->agentId2, 'Linked confidential', 1);
        $this->linkAgent($uuid, $this->agentId1);

        // agent1 should see it in agent_choice mode (linked = declarant-equivalent access)
        $count = $this->agentRepo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::AgentChoice->value);
        $this->assertEquals(1, $count);
    }

    public function testCountVisibleForAgent_AgentChoice_IncludesPublicReportsFromOthers(): void
    {
        // agent2 creates a public report
        $this->createReport($this->agentId2, 'Public from agent2', 0);

        // agent1 should see it in agent_choice mode
        $count = $this->agentRepo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::AgentChoice->value);
        $this->assertEquals(1, $count);
    }

    public function testCountVisibleForAgent_ExcludesAbandonedReports(): void
    {
        $uuid = $this->createReport($this->agentId1, 'Will be abandoned');
        $this->pdo->prepare('UPDATE reports SET etat = :etat WHERE uuid = :uuid')
            ->execute([':uuid' => $uuid, ':etat' => ReportState::Abandonne->value]);

        $count = $this->agentRepo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::Confidential->value);
        $this->assertEquals(0, $count);
    }

    // ─── countVisibleForAgent vs findPaginated : parité cross-site ─────
    //
    // Le compteur des cartes de registre (countVisibleForAgent) et la liste
    // (ReportQueryRepository::findPaginated) doivent appliquer la MÊME
    // logique de visibilité. findPaginated restreint le site de force
    // (force_site_id) uniquement à la branche « rapports publics des autres »
    // (r.is_confidential = 0) : un signalement dont l'agent est déclarant ou
    // rattaché reste visible quel que soit son site (Audit #80). Avant ces
    // tests, countVisibleForAgent ANDait le filtre site sur TOUT le OR et
    // sous-comptait donc les rattachés cross-site visibles dans la liste.

    public function testCountVisibleForAgent_AgentChoice_IncludesLinkedReportFromOtherSite(): void
    {
        $uuid = $this->createReport($this->agentId2, 'Linked, filed at another site', 1, $this->siteId2);
        $this->linkAgent($uuid, $this->agentId1);

        $count = $this->agentRepo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::AgentChoice->value);

        $this->assertEquals(1, $count, 'AgentChoice : un signalement rattaché doit être compté même si son site diffère (contrat findPaginated).');
    }

    public function testCountVisibleForAgent_AgentChoice_IncludesLinkedPublicReportFromOtherSite(): void
    {
        $uuid = $this->createReport($this->agentId2, 'Linked public, filed at another site', 0, $this->siteId2);
        $this->linkAgent($uuid, $this->agentId1);

        $count = $this->agentRepo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::AgentChoice->value);

        $this->assertEquals(1, $count, 'AgentChoice : un signalement public rattaché doit être compté quel que soit son site.');
    }

    public function testCountVisibleForAgent_AgentChoice_ExcludesUnlinkedPublicReportFromOtherSite(): void
    {
        // Le leak cross-site fermé par l'Audit #3-High doit rester fermé :
        // le correctif de parité ne doit PAS élargir l'accès.
        $this->createReport($this->agentId2, 'Public, filed at another site, not linked', 0, $this->siteId2);

        $count = $this->agentRepo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::AgentChoice->value);

        $this->assertEquals(0, $count, 'Un signalement public non rattaché d\'un autre site ne doit pas être compté.');
    }

    public function testCountVisibleForAgent_Confidential_IncludesLinkedReportFromOtherSite(): void
    {
        $uuid = $this->createReport($this->agentId2, 'Linked confidential, filed at another site', 1, $this->siteId2);
        $this->linkAgent($uuid, $this->agentId1);

        $count = $this->agentRepo->countVisibleForAgent(ReportType::Rsst->value, $this->agentId1, $this->siteId, VisibilityMode::Confidential->value);

        $this->assertEquals(1, $count, 'Confidential : le compteur ne filtre pas par site, comme findPaginated.');
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

    // ─── getAdjacentUuids : respecte l'état/visibilité de la liste (BUG-3) ───
    //
    // La navigation précédent/suivant doit s'appuyer sur la MÊME visibilité que
    // findPaginated (listes report_list/report_view) : jamais de lien vers un
    // rapport abandonné ou inaccessible (confidentiel d'un tiers non rattaché).

    public function testGetAdjacentUuidsExcludesAbandonedReport(): void
    {
        $older = $this->createReport($this->agentId1, 'Ancien visible');
        $abandoned = $this->createReport($this->agentId1, 'Intermédiaire abandonné');
        $current = $this->createReport($this->agentId1, 'Courant');

        $this->setCreatedAt($older, '2026-01-01 10:00:00');
        $this->setCreatedAt($abandoned, '2026-02-01 10:00:00');
        $this->setCreatedAt($current, '2026-03-01 10:00:00');
        $this->pdo->prepare('UPDATE reports SET etat = :etat WHERE uuid = :uuid')
            ->execute([':uuid' => $abandoned, ':etat' => ReportState::Abandonne->value]);

        $result = $this->repo->getAdjacentUuids(
            $this->agentChoiceFilter($this->agentId1, $this->siteId),
            '2026-03-01 10:00:00',
            $current,
        );

        $this->assertNull($result->prev, 'L\'abandonné (plus récent) ne doit pas être proposé en précédent');
        $this->assertSame($older, $result->next, 'Le suivant saute l\'abandonné pour le visible réel');
    }

    public function testGetAdjacentUuidsExcludesInaccessibleConfidentialReport(): void
    {
        $older = $this->createReport($this->agentId1, 'Ancien agent1');
        $secret = $this->createReport($this->agentId2, 'Confidentiel agent2 non rattaché', 1, $this->siteId2);
        $current = $this->createReport($this->agentId1, 'Courant agent1');

        $this->setCreatedAt($older, '2026-01-01 10:00:00');
        $this->setCreatedAt($secret, '2026-02-01 10:00:00');
        $this->setCreatedAt($current, '2026-03-01 10:00:00');

        $result = $this->repo->getAdjacentUuids(
            $this->agentChoiceFilter($this->agentId1, $this->siteId),
            '2026-03-01 10:00:00',
            $current,
        );

        $this->assertNull($result->prev, 'Un confidentiel inaccessible ne doit jamais être proposé');
        $this->assertSame($older, $result->next);
    }

    public function testGetAdjacentUuidsIncludesLinkedConfidentialReport(): void
    {
        // Non-régression : être rattaché EST l'autorisation, même confidentiel
        // et même cross-site. Le filtre de navigation ne doit pas sur-restreindre.
        $older = $this->createReport($this->agentId1, 'Ancien agent1');
        $linked = $this->createReport($this->agentId2, 'Confidentiel agent2 rattaché', 1, $this->siteId2);
        $current = $this->createReport($this->agentId1, 'Courant agent1');
        $this->linkAgent($linked, $this->agentId1);

        $this->setCreatedAt($older, '2026-01-01 10:00:00');
        $this->setCreatedAt($linked, '2026-03-01 10:00:00');
        $this->setCreatedAt($current, '2026-02-01 10:00:00');

        $result = $this->repo->getAdjacentUuids(
            $this->agentChoiceFilter($this->agentId1, $this->siteId),
            '2026-02-01 10:00:00',
            $current,
        );

        $this->assertSame($linked, $result->prev, 'Le confidentiel rattaché reste navigable');
        $this->assertSame($older, $result->next);
    }

    public function testGetAdjacentUuidsForSupervisorStillReachesConfidential(): void
    {
        // Non-régression superviseur : il voit tout (canAccessReport true), la
        // navigation ne doit pas le restreindre à tort.
        $older = $this->createReport($this->agentId1, 'Ancien agent1');
        $secret = $this->createReport($this->agentId2, 'Confidentiel agent2', 1, $this->siteId2);
        $current = $this->createReport($this->agentId1, 'Courant agent1');

        $this->setCreatedAt($older, '2026-01-01 10:00:00');
        $this->setCreatedAt($secret, '2026-03-01 10:00:00');
        $this->setCreatedAt($current, '2026-02-01 10:00:00');

        $filter = new ReportFilter(type: ReportType::Rsst->value);
        $result = $this->repo->getAdjacentUuids($filter, '2026-02-01 10:00:00', $current);

        $this->assertSame($secret, $result->prev, 'Le superviseur navigue vers un confidentiel');
        $this->assertSame($older, $result->next);
    }

    /**
     * Décision métier (Oracle) — la navigation CSA/CHSCT n'est plus bornée par
     * le consentement : `chsctConsentOnly` est sans effet, un signalement non
     * consenti reste proposé, comme dans la liste.
     */
    public function testGetAdjacentUuidsIgnoresChsctConsentScope(): void
    {
        $consentOlder = $this->createReport($this->agentId1, 'Consentement ancien');
        $notConsent = $this->createReport($this->agentId1, 'Sans consentement');
        $consentCurrent = $this->createReport($this->agentId1, 'Consentement courant');
        $this->pdo->exec("UPDATE reports SET consent_syndicat = 1 WHERE uuid IN ('$consentOlder', '$consentCurrent')");

        $this->setCreatedAt($consentOlder, '2026-01-01 10:00:00');
        $this->setCreatedAt($notConsent, '2026-02-01 10:00:00');
        $this->setCreatedAt($consentCurrent, '2026-03-01 10:00:00');

        $filter = new ReportFilter(type: ReportType::Rsst->value, chsctConsentOnly: true);
        $result = $this->repo->getAdjacentUuids($filter, '2026-03-01 10:00:00', $consentCurrent);

        $this->assertNull($result->prev);
        $this->assertSame(
            $notConsent,
            $result->next,
            'Le signalement sans consentement reste dans le périmètre de navigation CSA/CHSCT'
        );
    }
}
