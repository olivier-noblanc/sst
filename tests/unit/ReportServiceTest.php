<?php
use PHPUnit\Framework\TestCase;

class ReportServiceTest extends TestCase
{
    private PDO $pdo;
    private ReportService $service;
    private int $siteId;
    private int $userId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $repo = new ReportRepository($this->pdo);
        $events = new EventDispatcher();
        $this->service = new ReportService($repo, $events);
        // Seed a site with unique code to avoid conflicts across tests
        $this->pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UD_SVC', 'ReportService Site', 1)");
        $this->siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UD_SVC'")->fetchColumn();
        // Seed a user with unique username
        $this->pdo->exec("INSERT OR IGNORE INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('svc.test', 'Svc', 'Test', 'agent', {$this->siteId}, 1)");
        $this->userId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'svc.test'")->fetchColumn();
    }

    public function testCreateReturnsReport(): void
    {
        $cmd = new CreateReportCommand(
            type: 'rsst', objet: 'Test Report', description: 'A test report',
            dateEvenement: '2026-01-15', heureEvenement: '10:30',
            lieu: 'Bureau', declarantId: $this->userId, declarantNom: 'Svc',
            declarantPrenom: 'Test', siteId: $this->siteId, siteText: null,
            pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: 1, consentSyndicat: 0,
            natureAuteur: null, typeActe: null,
            pourCompteNom: null, pourComptePrenom: null,
            attachmentBlob: null, attachmentName: null, attachmentMime: null,
        );
        $report = $this->service->create($cmd);
        $this->assertNotEmpty($report['uuid']);
        $this->assertEquals('Test Report', $report['objet']);
        $this->assertEquals('rsst', $report['type']);
    }

    public function testCreateWithInvalidDataThrows(): void
    {
        $cmd = new CreateReportCommand(
            type: 'rsst', objet: '', description: '',
            dateEvenement: '', heureEvenement: null,
            lieu: '', declarantId: $this->userId, declarantNom: 'Svc',
            declarantPrenom: 'Test', siteId: $this->siteId, siteText: null,
            pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: 0, consentSyndicat: 0,
            natureAuteur: null, typeActe: null,
            pourCompteNom: null, pourComptePrenom: null,
            attachmentBlob: null, attachmentName: null, attachmentMime: null,
        );
        $this->expectException(\InvalidArgumentException::class);
        $this->service->create($cmd);
    }

    public function testCreateDispatchesEvent(): void
    {
        $dispatched = false;
        $events = new EventDispatcher();
        $events->addListener('report.created', function() use (&$dispatched) { $dispatched = true; });
        $repo = new ReportRepository($this->pdo);
        $service = new ReportService($repo, $events);

        $cmd = new CreateReportCommand(
            type: 'rsst', objet: 'Event Test', description: 'Test',
            dateEvenement: '2026-01-15', heureEvenement: null,
            lieu: '', declarantId: $this->userId, declarantNom: 'Svc',
            declarantPrenom: 'Test', siteId: $this->siteId, siteText: null,
            pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: 0, consentSyndicat: 0,
            natureAuteur: null, typeActe: null,
            pourCompteNom: null, pourComptePrenom: null,
            attachmentBlob: null, attachmentName: null, attachmentMime: null,
        );
        $service->create($cmd);
        $this->assertTrue($dispatched);
    }

    public function testFindByIdReturnsNullForUnknown(): void
    {
        $this->assertNull($this->service->findById('nonexistent') ?? null);
    }
}
