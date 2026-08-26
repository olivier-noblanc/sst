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
use App\Services\ReportStateMachine;
use App\DTO\CreateReportCommand;
use App\DTO\SiteId;
use App\DTO\UpdateReportCommand;
use App\DTO\RespondToReportCommand;
use App\DTO\SessionUser;
use App\Enum\ReportState;
use App\Enum\ReportType;

class ReportServiceTest extends TestCase
{
    private PDO $pdo;
    private ReportRepository $repo;
    private ReportService $service;
    private EventDispatcher $events;
    private ReportStateMachine $stateMachine;
    private int $siteId;
    private int $userId;
    private int $supervisorId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');
        clearConfigCache();

        // Set up default config for RSST visibility (public by default per decree 82-453)
        $this->pdo->exec("INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES ('app_report_visibility_rsst', 'public', 'text', 'app', 'Visibilité des signalements RSST', 1)");

        $this->repo = new ReportRepository($this->pdo);
        $this->events = new EventDispatcher();
        $this->stateMachine = new ReportStateMachine();
        $this->service = new ReportService($this->repo, $this->events, $this->stateMachine);

        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD_SVC', 'ReportService Site', 1)");
        $this->siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UD_SVC'")->fetchColumn();

        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('svc.agent', 'Agent', 'Test', 'agent', {$this->siteId}, 1)");
        $this->userId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'svc.agent'")->fetchColumn();

        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('svc.sup', 'Sup', 'Test', 'superviseur', {$this->siteId}, 1)");
        $this->supervisorId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'svc.sup'")->fetchColumn();
    }

    private function createReport(ReportType $type = ReportType::Rsst, int $declarantId = 0, ?int $siteId = null): \App\DTO\ReportData
    {
        $cmd = new CreateReportCommand(
            type: $type->value,
            objet: 'Test Report ' . uniqid(),
            description: 'A test report for unit testing',
            dateEvenement: '2026-01-15',
            heureEvenement: '10:30',
            lieu: 'Bureau',
            declarantId: $declarantId ?: $this->userId,
            declarantNom: 'Agent',
            declarantPrenom: 'Test',
            siteId: SiteId::fromInput($siteId ?? $this->siteId),
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
        return $this->service->create($cmd);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // create()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testCreateReturnsReport(): void
    {
        $report = $this->createReport();
        $this->assertNotEmpty($report->uuid);
        $this->assertEquals('rsst', $report->type);
    }

    public function testCreateWithInvalidDataThrows(): void
    {
        $cmd = new CreateReportCommand(
            type: ReportType::Rsst->value,
            objet: '',
            description: '',
            dateEvenement: '',
            heureEvenement: null,
            lieu: '',
            declarantId: $this->userId,
            declarantNom: 'Agent',
            declarantPrenom: 'Test',
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
        $stateMachine = new ReportStateMachine();
        $service = new ReportService($repo, $events, $stateMachine);

        $cmd = new CreateReportCommand(
            type: ReportType::Rsst->value,
            objet: 'Event Test',
            description: 'Test',
            dateEvenement: '2026-01-15',
            heureEvenement: null,
            lieu: '',
            declarantId: $this->userId,
            declarantNom: 'Agent',
            declarantPrenom: 'Test',
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
        $service->create($cmd);
        $this->assertTrue($dispatched);
    }

    public function testCreateEnforcesPublicVisibilityForRSST(): void
    {
        $cmd = new CreateReportCommand(
            type: ReportType::Rsst->value,
            objet: 'Visibility Test',
            description: 'Test',
            dateEvenement: '2026-01-15',
            heureEvenement: null,
            lieu: '',
            declarantId: $this->userId,
            declarantNom: 'Agent',
            declarantPrenom: 'Test',
            siteId: SiteId::fromInput($this->siteId),
            siteText: null,
            pole: null,
            serviceAffectation: null,
            telephoneMobile: null,
            isConfidential: true, // Agent wants confidential
            consentSyndicat: false,
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
        $this->assertEquals(0, $report->isConfidential);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // respond()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testRespondReturnsResult(): void
    {
        $report = $this->createReport();
        $cmd = new RespondToReportCommand(
            reponse: 'Response test',
            nouvelEtat: ReportState::EnCours,
        );
        setUserSession(SessionUser::fromArray([
            'id' => $this->supervisorId,
            'username' => 'svc.sup',
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
        $result = $this->service->respond($report->uuid, $cmd, $this->supervisorId);
        $this->assertIsArray($result);
    }

    public function testRespondThrowsForUnknownReport(): void
    {
        $cmd = new RespondToReportCommand(
            reponse: 'Response test',
            nouvelEtat: ReportState::EnCours,
        );
        setUserSession(SessionUser::fromArray([
            'id' => $this->supervisorId,
            'username' => 'svc.sup',
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
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
        $stateMachine = new ReportStateMachine();
        $service = new ReportService($repo, $events, $stateMachine);

        $report = $this->createReport();
        $cmd = new RespondToReportCommand(
            reponse: 'Event response',
            nouvelEtat: ReportState::EnCours,
        );
        setUserSession(SessionUser::fromArray([
            'id' => $this->supervisorId,
            'username' => 'svc.sup',
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
        $service->respond($report->uuid, $cmd, $this->supervisorId);
        $this->assertTrue($dispatched);
    }

    // Test pour valider que validateTransition est appelé correctement dans respond()
    public function testRespondValidatesTransitionSuccess(): void
    {
        $report = $this->createReport();
        $cmd = new RespondToReportCommand(
            reponse: 'Response test',
            nouvelEtat: ReportState::EnCours,
        );
        setUserSession(SessionUser::fromArray([
            'id' => $this->supervisorId,
            'username' => 'svc.sup',
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
        
        // Ce test vérifie que l'appel à validateTransition ne lance pas d'exception
        // et que la transition est valide
        $result = $this->service->respond($report->uuid, $cmd, $this->supervisorId);
        $this->assertIsArray($result);
    }

    public function testRespondValidatesTransitionFailureThrowsInvalidArgumentException(): void
    {
        $report = $this->createReport();
        // Essayer une transition invalide : rester dans le même état
        $cmd = new RespondToReportCommand(
            reponse: 'Response test',
            nouvelEtat: ReportState::Nouveau, // Même état - transition invalide
        );
        setUserSession(SessionUser::fromArray([
            'id' => $this->supervisorId,
            'username' => 'svc.sup',
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
        
        // Cette tentative devrait lancer une InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transition invalide');
        $this->service->respond($report->uuid, $cmd, $this->supervisorId);
    }

    public function testRespondValidatesTransitionFailureThrowsRuntimeException(): void
    {
        $report = $this->createReport();
        // Essayer une transition non autorisée : un agent ne peut pas passer à EnCours
        $cmd = new RespondToReportCommand(
            reponse: 'Response test',
            nouvelEtat: ReportState::EnCours,
        );
        setUserSession(SessionUser::fromArray([
            'id' => $this->userId,
            'username' => 'svc.agent',
            'role' => ROLE_AGENT,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
        
        // Cette tentative devrait lancer une RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Accès refusé');
        $this->service->respond($report->uuid, $cmd, $this->userId);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // update()
    // ═══════════════════════════════════════════════════════════════════════════════

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
        $stateMachine = new ReportStateMachine();
        $service = new ReportService($repo, $events, $stateMachine);

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
        $service->update($report->uuid, $cmd, $this->userId);
        $this->assertTrue($dispatched);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // abandon()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testAbandonReturnsTrue(): void
    {
        $report = $this->createReport();
        setUserSession(SessionUser::fromArray([
            'id' => $this->userId,
            'username' => 'svc.agent',
            'role' => ROLE_AGENT,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
        $result = $this->service->abandon($report->uuid, $this->userId);
        $this->assertTrue($result);
    }

    public function testAbandonThrowsForUnknownReport(): void
    {
        setUserSession(SessionUser::fromArray([
            'id' => $this->userId,
            'username' => 'svc.agent',
            'role' => ROLE_AGENT,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
        $this->expectException(\RuntimeException::class);
        $this->service->abandon('nonexistent-uuid', $this->userId);
    }

    // Test pour valider que validateTransition est appelé correctement dans abandon()
    public function testAbandonValidatesTransitionSuccess(): void
    {
        $report = $this->createReport();
        setUserSession(SessionUser::fromArray([
            'id' => $this->userId,
            'username' => 'svc.agent',
            'role' => ROLE_AGENT,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
        
        // Ce test vérifie que l'appel à validateTransition ne lance pas d'exception
        // et que la transition est valide
        $result = $this->service->abandon($report->uuid, $this->userId);
        $this->assertTrue($result);
    }

    public function testAbandonValidatesTransitionFailureThrowsInvalidArgumentException(): void
    {
        $report = $this->createReport();
        // Essayer une transition invalide : essayer de passer de Nouveau à Nouveau (même état)
        $cmd = new RespondToReportCommand(
            reponse: 'Response test',
            nouvelEtat: ReportState::Nouveau, // Même état - transition invalide
        );
        setUserSession(SessionUser::fromArray([
            'id' => $this->userId,
            'username' => 'svc.agent',
            'role' => ROLE_AGENT,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
        
        // Cette tentative devrait lancer une InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Transition invalide');
        // On tente une transition invalide via respond pour tester validateTransition
        $this->service->respond($report->uuid, $cmd, $this->userId);
    }

    public function testAbandonValidatesTransitionFailureThrowsRuntimeException(): void
    {
        $report = $this->createReport();
        // Essayer une transition non autorisée : un superviseur ne peut pas abandonner
        setUserSession(SessionUser::fromArray([
            'id' => $this->supervisorId,
            'username' => 'svc.sup',
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
        
        // Cette tentative devrait lancer une RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Accès refusé');
        $this->service->abandon($report->uuid, $this->supervisorId);
    }
}