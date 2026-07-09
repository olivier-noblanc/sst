<?php
/**
 * ReportService Unit Tests — Extended Coverage
 *
 * Tests ReportService from src/Services/ReportService.php:
 * - create (valid/invalid, events, visibility)
 * - respond (valid/invalid, events)
 * - update (valid/invalid, events)
 * - abandon (valid/invalid)
 * - findById
 */

use PHPUnit\Framework\TestCase;
use App\Services\ReportService;
use App\Repository\ReportRepository;
use App\Event\EventDispatcher;
use App\DTO\CreateReportCommand;
use App\DTO\UpdateReportCommand;
use App\DTO\RespondToReportCommand;

class ReportServiceTest extends TestCase
{
    private PDO $pdo;
    private ReportService $service;
    private EventDispatcher $events;
    private int $siteId;
    private int $userId;
    private int $supervisorId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM reports');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');
        clearConfigCache();

        // Set up default config for RSST visibility (public by default per decree 82-453)
        $this->pdo->exec("INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES ('app_report_visibility_rsst', 'public', 'text', 'app', 'Visibilité des signalements RSST', 1)");

        $this->repo = new ReportRepository($this->pdo);
        $this->events = new EventDispatcher();
        $this->service = new ReportService($this->repo, $this->events);

        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD_SVC', 'ReportService Site', 1)");
        $this->siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UD_SVC'")->fetchColumn();

        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('svc.agent', 'Agent', 'Test', 'agent', {$this->siteId}, 1)");
        $this->userId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'svc.agent'")->fetchColumn();

        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('svc.sup', 'Sup', 'Test', 'superviseur', {$this->siteId}, 1)");
        $this->supervisorId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'svc.sup'")->fetchColumn();
    }

    private function createReport(string $type = 'rsst', int $declarantId = 0, ?int $siteId = null): array
    {
        $cmd = new CreateReportCommand(
            type: $type,
            objet: 'Test Report ' . uniqid(),
            description: 'A test report for unit testing',
            dateEvenement: '2026-01-15',
            heureEvenement: '10:30',
            lieu: 'Bureau',
            declarantId: $declarantId ?: $this->userId,
            declarantNom: 'Agent',
            declarantPrenom: 'Test',
            siteId: $siteId ?? $this->siteId,
            siteText: null,
            pole: null,
            serviceAffectation: null,
            telephoneMobile: null,
            isConfidential: 0,
            consentSyndicat: 0,
            natureAuteur: null,
            typeActe: null,
            pourCompteNom: null,
            pourComptePrenom: null,
            attachmentBlob: null,
            attachmentName: null,
            attachmentMime: null,
        );
        return $this->service->create($cmd);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // create()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testCreateReturnsReport(): void
    {
        $report = $this->createReport();
        $this->assertNotEmpty($report['uuid']);
        $this->assertEquals('rsst', $report['type']);
    }

    public function testCreateWithInvalidDataThrows(): void
    {
        $cmd = new CreateReportCommand(
            type: 'rsst',
            objet: '',
            description: '',
            dateEvenement: '',
            heureEvenement: null,
            lieu: '',
            declarantId: $this->userId,
            declarantNom: 'Agent',
            declarantPrenom: 'Test',
            siteId: $this->siteId,
            siteText: null,
            pole: null,
            serviceAffectation: null,
            telephoneMobile: null,
            isConfidential: 0,
            consentSyndicat: 0,
            natureAuteur: null,
            typeActe: null,
            pourCompteNom: null,
            pourComptePrenom: null,
            attachmentBlob: null,
            attachmentName: null,
            attachmentMime: null,
        );
        $this->expectException(\InvalidArgumentException::class);
        $this->service->create($cmd);
    }

    public function testCreateDispatchesEvent(): void
    {
        $dispatched = false;
        $events = new EventDispatcher();
        $events->addListener('report.created', function () use (&$dispatched) {
            $dispatched = true;
        });
        $repo = new ReportRepository($this->pdo);
        $service = new ReportService($repo, $events);

        $cmd = new CreateReportCommand(
            type: 'rsst',
            objet: 'Event Test',
            description: 'Test',
            dateEvenement: '2026-01-15',
            heureEvenement: null,
            lieu: '',
            declarantId: $this->userId,
            declarantNom: 'Agent',
            declarantPrenom: 'Test',
            siteId: $this->siteId,
            siteText: null,
            pole: null,
            serviceAffectation: null,
            telephoneMobile: null,
            isConfidential: 0,
            consentSyndicat: 0,
            natureAuteur: null,
            typeActe: null,
            pourCompteNom: null,
            pourComptePrenom: null,
            attachmentBlob: null,
            attachmentName: null,
            attachmentMime: null,
        );
        $service->create($cmd);
        $this->assertTrue($dispatched);
    }

    public function testCreateEnforcesPublicVisibilityForRSST(): void
    {
        $cmd = new CreateReportCommand(
            type: 'rsst',
            objet: 'Visibility Test',
            description: 'Test',
            dateEvenement: '2026-01-15',
            heureEvenement: null,
            lieu: '',
            declarantId: $this->userId,
            declarantNom: 'Agent',
            declarantPrenom: 'Test',
            siteId: $this->siteId,
            siteText: null,
            pole: null,
            serviceAffectation: null,
            telephoneMobile: null,
            isConfidential: 1, // Agent wants confidential
            consentSyndicat: 0,
            natureAuteur: null,
            typeActe: null,
            pourCompteNom: null,
            pourComptePrenom: null,
            attachmentBlob: null,
            attachmentName: null,
            attachmentMime: null,
        );
        $report = $this->service->create($cmd);
        // RSST visibility is "public" by default in config, so is_confidential should be 0
        $this->assertEquals(0, $report['is_confidential']);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // respond()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testRespondReturnsResult(): void
    {
        $report = $this->createReport();
        $cmd = new RespondToReportCommand(
            reponse: 'Response test',
            nouvelEtat: ETAT_EN_COURS,
        );
        setUserSession([
            'id' => $this->supervisorId,
            'username' => 'svc.sup',
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]);
        $result = $this->service->respond($report['uuid'], $cmd, $this->supervisorId);
        $this->assertIsArray($result);
    }

    public function testRespondThrowsForUnknownReport(): void
    {
        $cmd = new RespondToReportCommand(
            reponse: 'Response test',
            nouvelEtat: ETAT_EN_COURS,
        );
        setUserSession([
            'id' => $this->supervisorId,
            'username' => 'svc.sup',
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]);
        $this->expectException(\RuntimeException::class);
        $this->service->respond('nonexistent-uuid', $cmd, $this->supervisorId);
    }

    public function testRespondDispatchesEvent(): void
    {
        $dispatched = false;
        $events = new EventDispatcher();
        $events->addListener('report.responded', function () use (&$dispatched) {
            $dispatched = true;
        });
        $repo = new ReportRepository($this->pdo);
        $service = new ReportService($repo, $events);

        $report = $this->createReport();
        $cmd = new RespondToReportCommand(
            reponse: 'Event response',
            nouvelEtat: ETAT_EN_COURS,
        );
        setUserSession([
            'id' => $this->supervisorId,
            'username' => 'svc.sup',
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]);
        $service->respond($report['uuid'], $cmd, $this->supervisorId);
        $this->assertTrue($dispatched);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // update()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testUpdateReturnsTrue(): void
    {
        $report = $this->createReport();
        $cmd = new UpdateReportCommand(
            objet: 'Updated Object',
            description: 'Updated description',
            dateEvenement: '2026-02-01',
            heureEvenement: null,
            lieu: 'New Location',
            siteText: null,
            pole: null,
            serviceAffectation: null,
            telephoneMobile: null,
            isConfidential: 0,
            consentSyndicat: 0,
        );
        $result = $this->service->update($report['uuid'], $cmd, $this->userId);
        $this->assertTrue($result);

        $updated = $this->service->findById($report['uuid']);
        $this->assertEquals('Updated Object', $updated['objet']);
    }

    public function testUpdateThrowsForUnknownReport(): void
    {
        $cmd = new UpdateReportCommand(
            objet: 'Test',
            description: 'Test',
            dateEvenement: '2026-01-15',
            heureEvenement: null,
            lieu: null,
            siteText: null,
            pole: null,
            serviceAffectation: null,
            telephoneMobile: null,
            isConfidential: 0,
            consentSyndicat: 0,
        );
        $this->expectException(\RuntimeException::class);
        $this->service->update('nonexistent-uuid', $cmd, $this->userId);
    }

    public function testUpdateDispatchesEvent(): void
    {
        $dispatched = false;
        $events = new EventDispatcher();
        $events->addListener('report.updated', function () use (&$dispatched) {
            $dispatched = true;
        });
        $repo = new ReportRepository($this->pdo);
        $service = new ReportService($repo, $events);

        $report = $this->createReport();
        $cmd = new UpdateReportCommand(
            objet: 'Event Update',
            description: 'Test',
            dateEvenement: '2026-01-15',
            heureEvenement: null,
            lieu: null,
            siteText: null,
            pole: null,
            serviceAffectation: null,
            telephoneMobile: null,
            isConfidential: 0,
            consentSyndicat: 0,
        );
        $service->update($report['uuid'], $cmd, $this->userId);
        $this->assertTrue($dispatched);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // abandon()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testAbandonReturnsTrue(): void
    {
        $report = $this->createReport();
        setUserSession([
            'id' => $this->supervisorId,
            'username' => 'svc.sup',
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]);
        $result = $this->service->abandon($report['uuid'], $this->supervisorId);
        $this->assertTrue($result);
    }

    public function testAbandonThrowsForUnknownReport(): void
    {
        setUserSession([
            'id' => $this->supervisorId,
            'username' => 'svc.sup',
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]);
        $this->expectException(\RuntimeException::class);
        $this->service->abandon('nonexistent-uuid', $this->supervisorId);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // findById()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFindByIdReturnsReport(): void
    {
        $report = $this->createReport();
        $found = $this->service->findById($report['uuid']);
        $this->assertNotNull($found);
        $this->assertEquals($report['uuid'], $found['uuid']);
    }

    public function testFindByIdReturnsNullForUnknown(): void
    {
        $this->assertNull($this->service->findById('nonexistent-uuid'));
    }
}
