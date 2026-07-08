<?php
use PHPUnit\Framework\TestCase;
use App\Repository\ReportRepository;
use App\DTO\CreateReportCommand;

class ReportRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ReportRepository $repo;
    private int $siteId;
    private int $userId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->repo = new ReportRepository($this->pdo);
        // Clean and seed (getDB() is a singleton, so we must reset)
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_state_history');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote-d-Or', 1)");
        $this->siteId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO users (nom, prenom, username, role, site_id, is_active) VALUES ('Martin', 'Jean', 'jean.martin', 'agent', {$this->siteId}, 1)");
        $this->userId = (int) $this->pdo->lastInsertId();
    }

    public function testCreateAndFindById(): void
    {
        $cmd = new CreateReportCommand(
            type: 'rsst', objet: 'Test', description: 'Desc',
            dateEvenement: '2026-01-15', heureEvenement: '10:30',
            lieu: 'Bureau', declarantId: $this->userId, declarantNom: 'Martin',
            declarantPrenom: 'Jean', siteId: $this->siteId, siteText: null,
            pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: 1, consentSyndicat: 0,
            natureAuteur: null, typeActe: null,
            pourCompteNom: null, pourComptePrenom: null,
            attachmentBlob: null, attachmentName: null, attachmentMime: null,
        );
        $uuid = $this->repo->create($cmd);
        $this->assertNotEmpty($uuid);

        $report = $this->repo->findById($uuid);
        $this->assertNotNull($report);
        $this->assertEquals('Test', $report['objet']);
        $this->assertEquals('rsst', $report['type']);
    }

    public function testFindByIdReturnsNullForUnknownUuid(): void
    {
        $this->assertNull($this->repo->findById('nonexistent-uuid'));
    }

    public function testFindBySite(): void
    {
        $cmd = new CreateReportCommand(
            type: 'rsst', objet: 'Site Test', description: 'Desc',
            dateEvenement: '2026-01-15', heureEvenement: null,
            lieu: null, declarantId: $this->userId, declarantNom: 'Martin',
            declarantPrenom: 'Jean', siteId: $this->siteId, siteText: null,
            pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: 0, consentSyndicat: 0,
            natureAuteur: null, typeActe: null,
            pourCompteNom: null, pourComptePrenom: null,
            attachmentBlob: null, attachmentName: null, attachmentMime: null,
        );
        $this->repo->create($cmd);

        $reports = $this->repo->findBySite($this->siteId);
        $this->assertCount(1, $reports);
    }
}
