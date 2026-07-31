<?php
/**
 * Tests ReportRepository methods — kills Infection mutants on:
 *   - countByState (match arms, CastInt, LogicalNot, siteId filter)
 *   - countActive (GreaterThan, LogicalAnd, confidentialMode)
 *   - getAdjacentUuids (prev/next navigation, ORDER BY)
 *   - create (lastInsertId, array mapping)
 *   - respond (status check, INSERT response, UPDATE etat)
 *   - abandon (UPDATE etat, rowCount)
 *   - reopen (UPDATE etat, archive previous response)
 */

use PHPUnit\Framework\TestCase;
use App\Repository\ReportRepository;
use App\DTO\CreateReportCommand;
use App\DTO\SiteId;
use App\DTO\RespondToReportCommand;
use App\Enum\ReportState;
use App\Enum\ReportType;

class ReportRepositoryMethodsMutationTest extends TestCase
{
    private PDO $pdo;
    private ReportRepository $repo;
    private int $siteId;
    private int $declarantId;
    private int $supervisorId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM registries');
        \App\Repository\RegistryRepository::instance()->seedDefaults();

        $this->repo = new ReportRepository($this->pdo);

        $this->pdo->prepare('INSERT INTO sites (code, nom) VALUES (?, ?)')->execute(['UR21', 'UR Test']);
        $this->siteId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['decl.user', 'Dupont', 'Jean', 'agent', $this->siteId, 1]);
        $this->declarantId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['sup.user', 'Martin', 'Sophie', 'superviseur', $this->siteId, 1]);
        $this->supervisorId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM registries');
    }

    private function seedReport(string $type = 'rsst', string $etat = 'nouveau', ?int $siteId = null, string $createdAt = '2026-01-15 10:00:00'): string
    {
        $hex = bin2hex(random_bytes(16));
        $uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-' . dechex((hexdec(substr($hex, 16, 2)) & 0x3F) | 0x80) . substr($hex, 18, 2) . '-' . substr($hex, 20, 12);
        $ref = $type . '-' . substr($hex, 0, 4);
        $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement,
                declarant_id, declarant_nom, declarant_prenom, site_id, etat, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $uuid, $ref, $type, 'Test', 'Desc', '2026-01-15',
            $this->declarantId, 'Dupont', 'Jean', $siteId ?? $this->siteId, $etat, $createdAt,
        ]);
        return $uuid;
    }

    // ═══ countByState() ═══

    public function testCountByStateReturnsCorrectCounts(): void
    {
        $this->seedReport('rsst', ReportState::Nouveau->value);
        $this->seedReport('rsst', ReportState::Nouveau->value);
        $this->seedReport('rsst', ReportState::EnCours->value);
        $this->seedReport('rsst', ReportState::Traite->value);
        $this->seedReport('rsst', ReportState::Reouvert->value);
        // Abandonne should be excluded
        $this->seedReport('rsst', ReportState::Abandonne->value);

        $result = $this->repo->countByState('rsst');

        // Kill CastInt/Coalesce on each count
        $this->assertSame(2, $result->nouveau, 'nouveau count');
        $this->assertSame(1, $result->enCours, 'enCours count');
        $this->assertSame(1, $result->traite, 'traite count');
        $this->assertSame(1, $result->reouvert, 'reouvert count');
        // Kill total calculation — total excludes abandonne
        $this->assertSame(5, $result->total, 'total excludes abandonne');
    }

    public function testCountByStateReturnsZeroWhenNoReports(): void
    {
        $result = $this->repo->countByState('rsst');
        $this->assertSame(0, $result->nouveau);
        $this->assertSame(0, $result->enCours);
        $this->assertSame(0, $result->traite);
        $this->assertSame(0, $result->reouvert);
        $this->assertSame(0, $result->total);
    }

    public function testCountByStateFiltersBySiteIdWhenSeeAllSitesFalse(): void
    {
        // Kill LogicalNot on !$seeAllSites
        $this->pdo->prepare('INSERT INTO sites (code, nom) VALUES (?, ?)')->execute(['UR25', 'UR 25']);
        $site2 = (int) $this->pdo->lastInsertId();

        $this->seedReport('rsst', ReportState::Nouveau->value, $this->siteId);
        $this->seedReport('rsst', ReportState::Nouveau->value, $site2);

        $result = $this->repo->countByState('rsst', $this->siteId, false);
        $this->assertSame(1, $result->nouveau, 'should only count reports for this site');
        $this->assertSame(1, $result->total);
    }

    public function testCountByStateIgnoresSiteIdWhenSeeAllSitesTrue(): void
    {
        $this->pdo->prepare('INSERT INTO sites (code, nom) VALUES (?, ?)')->execute(['UR25', 'UR 25']);
        $site2 = (int) $this->pdo->lastInsertId();

        $this->seedReport('rsst', ReportState::Nouveau->value, $this->siteId);
        $this->seedReport('rsst', ReportState::Nouveau->value, $site2);

        $result = $this->repo->countByState('rsst', $this->siteId, true);
        $this->assertSame(2, $result->nouveau, 'should count all sites');
        $this->assertSame(2, $result->total);
    }

    public function testCountByStateReturnsReportStateCountsInstance(): void
    {
        $this->assertInstanceOf(\App\DTO\ReportStateCounts::class, $this->repo->countByState('rsst'));
    }

    // ═══ countActive() ═══

    public function testCountActiveReturnsCorrectCount(): void
    {
        $this->seedReport('rsst', ReportState::Nouveau->value);
        $this->seedReport('rsst', ReportState::EnCours->value);
        $this->seedReport('rsst', ReportState::Traite->value);
        // Abandonne excluded
        $this->seedReport('rsst', ReportState::Abandonne->value);

        // Kill CastInt on return
        $this->assertSame(3, $this->repo->countActive('rsst'), 'abandonne excluded');
    }

    public function testCountActiveReturnsZeroWhenNoReports(): void
    {
        $this->assertSame(0, $this->repo->countActive('rsst'));
    }

    public function testCountActiveFiltersBySiteId(): void
    {
        // Kill GreaterThan on $siteId > 0
        $this->pdo->prepare('INSERT INTO sites (code, nom) VALUES (?, ?)')->execute(['UR25', 'UR 25']);
        $site2 = (int) $this->pdo->lastInsertId();

        $this->seedReport('rsst', ReportState::Nouveau->value, $this->siteId);
        $this->seedReport('rsst', ReportState::Nouveau->value, $site2);

        $this->assertSame(1, $this->repo->countActive('rsst', $this->siteId), 'only count for this site');
    }

    public function testCountActiveWithConfidentialModeAndUserId(): void
    {
        // Kill LogicalAnd on $confidentialMode && $userId > 0
        $uuid1 = $this->seedReport('rsst', ReportState::Nouveau->value);
        $this->pdo->exec("UPDATE reports SET is_confidential = 1, declarant_id = {$this->declarantId} WHERE uuid = '$uuid1'");

        $uuid2 = $this->seedReport('rsst', ReportState::Nouveau->value);
        $this->pdo->exec("UPDATE reports SET is_confidential = 0 WHERE uuid = '$uuid2'");

        // confidentialMode=true, userId=declarant → should see own + non-confidential
        $this->assertSame(2, $this->repo->countActive('rsst', 0, $this->declarantId, true));
        // confidentialMode=true, userId=other → should see only non-confidential
        $this->assertSame(1, $this->repo->countActive('rsst', 0, 99999, true));
    }

    public function testCountActiveWithoutConfidentialModeCountsAll(): void
    {
        $uuid1 = $this->seedReport('rsst', ReportState::Nouveau->value);
        $this->pdo->exec("UPDATE reports SET is_confidential = 1 WHERE uuid = '$uuid1'");
        $this->seedReport('rsst', ReportState::Nouveau->value);

        // Kill mutant that applies confidential filter when mode=false
        $this->assertSame(2, $this->repo->countActive('rsst', 0, 0, false), 'no confidential filter');
    }

    // ═══ getAdjacentUuids() ═══

    public function testGetAdjacentUuidsReturnsNullsForSingleReport(): void
    {
        $uuid = $this->seedReport('rsst', ReportState::Nouveau->value);
        $report = $this->repo->findById($uuid);
        $result = $this->repo->getAdjacentUuids($report->toArray());
        $this->assertNull($result->prev);
        $this->assertNull($result->next);
    }

    public function testGetAdjacentUuidsReturnsPrevAndNext(): void
    {
        $uuid1 = $this->seedReport('rsst', ReportState::Nouveau->value, null, '2026-01-01 10:00:00');
        $uuid2 = $this->seedReport('rsst', ReportState::Nouveau->value, null, '2026-02-01 10:00:00');
        $uuid3 = $this->seedReport('rsst', ReportState::Nouveau->value, null, '2026-03-01 10:00:00');

        $report = $this->repo->findById($uuid2);
        $result = $this->repo->getAdjacentUuids($report->toArray());
        $this->assertSame($uuid3, $result->prev, 'prev = newer report');
        $this->assertSame($uuid1, $result->next, 'next = older report');
    }

    public function testGetAdjacentUuidsReturnsNullPrevForNewest(): void
    {
        $this->seedReport('rsst', ReportState::Nouveau->value, null, '2026-01-01 10:00:00');
        $uuid2 = $this->seedReport('rsst', ReportState::Nouveau->value, null, '2026-02-01 10:00:00');

        $report = $this->repo->findById($uuid2);
        $result = $this->repo->getAdjacentUuids($report->toArray());
        $this->assertNull($result->prev, 'newest report has no prev');
        $this->assertNotNull($result->next);
    }

    public function testGetAdjacentUuidsReturnsNullNextForOldest(): void
    {
        $uuid1 = $this->seedReport('rsst', ReportState::Nouveau->value, null, '2026-01-01 10:00:00');
        $this->seedReport('rsst', ReportState::Nouveau->value, null, '2026-02-01 10:00:00');

        $report = $this->repo->findById($uuid1);
        $result = $this->repo->getAdjacentUuids($report->toArray());
        $this->assertNotNull($result->prev);
        $this->assertNull($result->next, 'oldest report has no next');
    }

    public function testGetAdjacentUuidsReturnsNullsForEmptyArray(): void
    {
        $result = $this->repo->getAdjacentUuids([]);
        $this->assertNull($result->prev);
        $this->assertNull($result->next);
    }

    // ═══ create() ═══

    public function testCreateInsertsReportAndReturnsUuid(): void
    {
        $cmd = new CreateReportCommand(
            type: 'rsst',
            objet: 'Created report',
            description: 'Description',
            dateEvenement: '2026-01-15',
            heureEvenement: '10:30',
            lieu: 'Bureau',
            declarantId: $this->declarantId,
            declarantNom: 'Dupont',
            declarantPrenom: 'Jean',
            siteId: SiteId::fromInput($this->siteId),
            siteText: null,
            pole: null,
            serviceAffectation: null,
            telephoneMobile: null,
            isConfidential: false,
            consentSyndicat: false,
            natureAuteur: null,
            typeActe: null,
            pourCompteNom: null,
            pourComptePrenom: null,
            attachmentBlob: null,
            attachmentName: null,
            attachmentMime: null,
        );

        $uuid = $this->repo->create($cmd);
        $this->assertNotEmpty($uuid, 'create must return a UUID string');

        $report = $this->repo->findById($uuid);
        $this->assertNotNull($report);
        $this->assertSame('Created report', $report->objet);
        $this->assertSame('rsst', $report->type);
        $this->assertSame($this->declarantId, $report->declarantId);
    }

    // ═══ respond() ═══

    public function testRespondUpdatesEtatAndInsertsResponse(): void
    {
        $uuid = $this->seedReport('rsst', ReportState::Nouveau->value);
        $cmd = new RespondToReportCommand(
            reponse: 'Pris en charge',
            nouvelEtat: ReportState::EnCours,
        );

        $result = $this->repo->respond($uuid, $cmd, $this->supervisorId);
        $this->assertIsArray($result);
        $this->assertTrue($result['success'] ?? false);

        // Verify etat was updated
        $report = $this->repo->findById($uuid);
        $this->assertSame(ReportState::EnCours->value, $report->etat);

        // Verify response was inserted
        $stmt = $this->pdo->prepare('SELECT * FROM report_responses WHERE report_uuid = ?');
        $stmt->execute([$uuid]);
        $responses = $stmt->fetchAll();
        $this->assertCount(1, $responses);
        $this->assertSame('Pris en charge', $responses[0]['reponse']);
        $this->assertSame($this->supervisorId, (int) $responses[0]['user_id']);
    }

    public function testRespondReturnsErrorForMissingReport(): void
    {
        $cmd = new RespondToReportCommand(reponse: 'test', nouvelEtat: ReportState::EnCours);
        $result = $this->repo->respond('nonexistent-uuid', $cmd, $this->supervisorId);
        $this->assertFalse($result['success'] ?? true);
    }

    // ═══ abandon() ═══

    public function testAbandonUpdatesEtatToAbandonne(): void
    {
        $uuid = $this->seedReport('rsst', ReportState::Nouveau->value);
        $result = $this->repo->abandon($uuid, $this->declarantId);
        $this->assertTrue($result);

        $report = $this->repo->findById($uuid);
        $this->assertSame(ReportState::Abandonne->value, $report->etat);
    }

    public function testAbandonReturnsFalseForMissingReport(): void
    {
        $this->assertFalse($this->repo->abandon('nonexistent-uuid', $this->declarantId));
    }

    // ═══ reopen() ═══

    public function testReopenUpdatesEtatToReouvert(): void
    {
        $uuid = $this->seedReport('rsst', ReportState::Traite->value);
        $result = $this->repo->reopen($uuid, $this->supervisorId, 'Motif de réouverture');
        $this->assertTrue($result);

        $report = $this->repo->findById($uuid);
        $this->assertSame(ReportState::Reouvert->value, $report->etat);
    }

    public function testReopenReturnsFalseForMissingReport(): void
    {
        $this->assertFalse($this->repo->reopen('nonexistent-uuid', $this->supervisorId, 'motif'));
    }

    // ═══ update() ═══

    public function testUpdateModifiesReportFields(): void
    {
        $uuid = $this->seedReport('rsst', ReportState::Nouveau->value);
        $cmd = new \App\DTO\UpdateReportCommand(
            objet: 'Updated objet',
            description: 'Updated description',
            dateEvenement: '2026-02-01',
            heureEvenement: '14:00',
            lieu: 'Updated lieu',
            siteText: 'UR25',
            pole: 'Pôle B',
            serviceAffectation: 'Service C',
            telephoneMobile: '0601020304',
            isConfidential: true,
            consentSyndicat: true,
        );

        $result = $this->repo->update($uuid, $cmd, $this->declarantId);
        $this->assertTrue($result);

        $report = $this->repo->findById($uuid);
        $this->assertSame('Updated objet', $report->objet);
        $this->assertSame('Updated description', $report->description);
        $this->assertSame('2026-02-01', $report->dateEvenement);
        $this->assertSame('14:00', $report->heureEvenement);
        $this->assertSame('Updated lieu', $report->lieu);
        $this->assertSame(1, $report->isConfidential);
        $this->assertSame(1, $report->consentSyndicat);
    }

    public function testUpdateReturnsFalseForMissingReport(): void
    {
        $cmd = new \App\DTO\UpdateReportCommand(
            objet: 'test', description: 'test', dateEvenement: '2026-01-01',
            heureEvenement: null, lieu: null, siteText: null, pole: null,
            serviceAffectation: null, telephoneMobile: null,
            isConfidential: false, consentSyndicat: false,
        );
        $this->assertFalse($this->repo->update('nonexistent-uuid', $cmd, $this->declarantId));
    }
}
