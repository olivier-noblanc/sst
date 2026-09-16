<?php

/**
 * Outbox ↔ action métier — atomicité transactionnelle (TDD, NO-GO « enqueue post-commit »).
 *
 * Invariant visé : l'insertion `email_outbox` participe à la MÊME transaction
 * SQLite que l'action métier. Le rollback métier annule la mise en file ;
 * l'échec d'enqueue annule l'action métier. Aucune ligne outbox orpheline,
 * aucune action sans sa notification.
 *
 * Ces tests échouent tant que les repositories ouvrent leur propre transaction
 * (join-if-active absent) et que `ReportService` dispatche après le commit.
 *
 * Le transport SMTP n'est jamais sollicité : le seam mailer n'est pas branché,
 * on ne teste que la persistance/transaction.
 */

use App\DTO\CreateReportCommand;
use App\DTO\OutboxMessage;
use App\DTO\ReportEventData;
use App\DTO\ReopenReportCommand;
use App\DTO\RespondToReportCommand;
use App\DTO\SessionUser;
use App\DTO\SiteId;
use App\DTO\UpdateUserCommand;
use App\Enum\ReportState;
use App\Event\EventDispatcher;
use App\Repository\EmailOutboxRepository;
use App\Repository\ReportRepository;
use App\Repository\ReportWriteRepository;
use App\Repository\UserRepository;
use App\Services\NotificationService;
use App\Services\ReportService;
use App\Services\ReportStateMachine;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class OutboxAtomicityTest extends TestCase
{
    private PDO $pdo;
    private int $siteId;
    private int $agentId;
    private int $supervisorId;

    protected function setUp(): void
    {
        setMailerSeam(null);
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM report_agents');
        $this->pdo->exec('DELETE FROM report_agent_invites');
        $this->pdo->exec('DELETE FROM report_sequence');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');
        clearConfigCache();

        $this->pdo->exec("INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES ('app_report_visibility_rsst', 'public', 'text', 'app', 'Visibilité RSST', 1)");
        $this->pdo->exec("INSERT INTO sites (code, nom, is_active) VALUES ('UD_ATOM', 'Atomicité Site', 1)");
        $this->siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UD_ATOM'")->fetchColumn();

        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('atom.agent', 'Agent', 'Atom', 'agent', {$this->siteId}, 1, 'atom.agent@dreets-bfc.gouv.fr')");
        $this->agentId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'atom.agent'")->fetchColumn();
        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('atom.sup', 'Sup', 'Atom', 'superviseur', {$this->siteId}, 1, 'atom.sup@dreets-bfc.gouv.fr')");
        $this->supervisorId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'atom.sup'")->fetchColumn();
    }

    protected function tearDown(): void
    {
        setMailerSeam(null);
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM report_agents');
        $this->pdo->exec('DELETE FROM report_agent_invites');
        cleanupAllForTest($this->pdo);
    }

    private function command(): CreateReportCommand
    {
        return new CreateReportCommand(
            type: 'rsst',
            objet: 'Objet atomicité',
            description: 'Description atomicité',
            dateEvenement: '2026-01-15',
            heureEvenement: '10:30',
            lieu: 'Bureau',
            declarantId: $this->agentId,
            declarantNom: 'Agent',
            declarantPrenom: 'Atom',
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
    }

    private function insertReport(string $uuid, string $etat, int $declarantId): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement,
                                 declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat)
            VALUES (:uuid, :ref, :type, :objet, :descr, :date, :declarant_id, :nom, :prenom, :site, 0, :etat)
        ');
        $stmt->execute([
            ':uuid' => $uuid, ':ref' => 'RSST-26-' . substr($uuid, -6), ':type' => 'rsst',
            ':objet' => 'Objet', ':descr' => 'Desc', ':date' => '2026-02-01',
            ':declarant_id' => $declarantId, ':nom' => 'Agent', ':prenom' => 'Atom',
            ':site' => $this->siteId, ':etat' => $etat,
        ]);
    }

    private function setSession(int $userId, string $role): void
    {
        setUserSession(SessionUser::fromArray([
            'id' => $userId, 'username' => 'x', 'role' => $role,
            'site_id' => $this->siteId, 'is_active' => 1,
        ]));
    }

    private function envelope(string $dedupKey): OutboxMessage
    {
        return new OutboxMessage(
            recipient: 'atom.sup@dreets-bfc.gouv.fr',
            subject: 'Sujet',
            body: '<p>Corps</p>',
            dedupKey: $dedupKey,
        );
    }

    private function reportCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
    }

    private function outboxCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn();
    }

    // ═ Join-if-active : les repositories rejoignent la transaction ouverte ══

    public function testBusinessRollbackAnnulsOutboxEnqueue(): void
    {
        $this->pdo->beginTransaction();
        try {
            // Le repository DOIT rejoindre la transaction ouverte (pas de begin imbriqué).
            ReportWriteRepository::instance()->create($this->command());
            (new EmailOutboxRepository($this->pdo))->enqueue($this->envelope('atomic-rollback'));
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->fail('create()/enqueue() doivent rejoindre la transaction ouverte : ' . $e->getMessage());
        }
        $this->pdo->rollBack();

        $this->assertSame(0, $this->reportCount(), 'Le rollback métier annule le signalement');
        $this->assertSame(0, $this->outboxCount(), 'Le rollback métier annule AUSSI la mise en file (atomicité)');
    }

    public function testBusinessCommitPersistsActionAndOutboxRow(): void
    {
        $this->pdo->beginTransaction();
        try {
            ReportWriteRepository::instance()->create($this->command());
            (new EmailOutboxRepository($this->pdo))->enqueue($this->envelope('atomic-commit'));
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->assertSame(1, $this->reportCount(), 'Le commit persiste le signalement');
        $this->assertSame(1, $this->outboxCount(), 'Le commit persiste la mise en file');
    }

    // ══ report.created : dispatch + enqueue DANS la transaction métier ══════

    public function testReportCreatedIsDispatchedInsideBusinessTransaction(): void
    {
        $insideTransaction = null;
        $events = new EventDispatcher();
        $events->addListener('report.created', function (ReportEventData $data) use (&$insideTransaction): void {
            $insideTransaction = $data->pdo !== null && $data->pdo->inTransaction();
            // simule le listener de production (enqueue)
            (new EmailOutboxRepository($this->pdo))->enqueue($this->envelope('atomic-created'));
        });
        $service = new ReportService(new ReportRepository($this->pdo), $events, new ReportStateMachine());
        $this->setSession($this->agentId, 'agent');

        $service->create($this->command());

        $this->assertTrue($insideTransaction, 'report.created doit être dispatché DANS la transaction métier (enqueue atomique)');
        $this->assertSame(1, $this->reportCount());
        $this->assertSame(1, $this->outboxCount(), 'La ligne outbox est committée avec le signalement');
    }

    public function testReportCreateRollsBackWhenListenerFails(): void
    {
        $events = new EventDispatcher();
        $events->addListener('report.created', function (): void {
            throw new RuntimeException('échec d\'enqueue simulé');
        });
        $service = new ReportService(new ReportRepository($this->pdo), $events, new ReportStateMachine());
        $this->setSession($this->agentId, 'agent');

        try {
            $service->create($this->command());
            $this->fail('L\'échec d\'enqueue doit remonter (pas d\'action sans notification)');
        } catch (RuntimeException $e) {
            // attendu : le boundary doit propager
        }

        $this->assertSame(0, $this->reportCount(), 'L\'échec d\'enqueue annule la création du signalement');
        $this->assertSame(0, $this->outboxCount());
    }

    public function testReportCreateWithInvitesQueuesInviteInsideTransaction(): void
    {
        $events = new EventDispatcher();
        $service = new ReportService(new ReportRepository($this->pdo), $events, new ReportStateMachine());
        $this->setSession($this->agentId, 'agent');

        $report = $service->create($this->command(), ['invite.atomic@dreets-bfc.gouv.fr']);

        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM report_agent_invites WHERE report_uuid = '{$report->uuid}'")->fetchColumn(),
            'L\'invitation est persistée dans la transaction de création'
        );
        $this->assertSame(1, $this->outboxCount(), 'Le message d\'invitation est mis en file dans la transaction de création (atomique)');
    }

    public function testReportCreateWithInvitesRollsBackEverythingOnFailure(): void
    {
        $events = new EventDispatcher();
        $events->addListener('report.created', function (): void {
            throw new RuntimeException('échec de mise en file');
        });
        $service = new ReportService(new ReportRepository($this->pdo), $events, new ReportStateMachine());
        $this->setSession($this->agentId, 'agent');

        try {
            $service->create($this->command(), ['invite.atomic@dreets-bfc.gouv.fr']);
            $this->fail('L\'échec d\'enqueue doit annuler la création');
        } catch (RuntimeException $e) {
            // attendu
        }

        $this->assertSame(0, $this->reportCount(), 'Le rollback annule le signalement');
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM report_agent_invites')->fetchColumn(), 'Le rollback annule l\'invitation');
        $this->assertSame(0, $this->outboxCount(), 'Aucune ligne outbox orpheline');
    }

    // ═ report.responded ════════════════════════════════════════════════════

    public function testReportRespondedIsDispatchedInsideBusinessTransaction(): void
    {
        $uuid = 'a2200000-0000-4000-8000-000000000001';
        $this->insertReport($uuid, ReportState::Nouveau->value, $this->agentId);

        $insideTransaction = null;
        $events = new EventDispatcher();
        $events->addListener('report.responded', function (ReportEventData $data) use (&$insideTransaction): void {
            $insideTransaction = $data->pdo !== null && $data->pdo->inTransaction();
            (new EmailOutboxRepository($this->pdo))->enqueue($this->envelope('atomic-responded'));
        });
        $service = new ReportService(new ReportRepository($this->pdo), $events, new ReportStateMachine());
        $this->setSession($this->supervisorId, 'superviseur');

        $service->respond($uuid, new RespondToReportCommand(reponse: 'Réponse', nouvelEtat: ReportState::EnCours), $this->supervisorId);

        $this->assertTrue($insideTransaction, 'report.responded doit être dispatché DANS la transaction métier');
        $this->assertSame(1, $this->outboxCount());
    }

    // ══ report.reopened ════════════════════════════════════════════════════

    public function testReportReopenedIsDispatchedInsideBusinessTransaction(): void
    {
        $uuid = 'a2200000-0000-4000-8000-000000000002';
        $this->insertReport($uuid, ReportState::Traite->value, $this->agentId);

        $insideTransaction = null;
        $events = new EventDispatcher();
        $events->addListener('report.reopened', function (ReportEventData $data) use (&$insideTransaction): void {
            $insideTransaction = $data->pdo !== null && $data->pdo->inTransaction();
            (new EmailOutboxRepository($this->pdo))->enqueue($this->envelope('atomic-reopened'));
        });
        $service = new ReportService(new ReportRepository($this->pdo), $events, new ReportStateMachine());
        $this->setSession($this->supervisorId, 'superviseur');

        $service->reopen($uuid, new ReopenReportCommand(motif: 'Motif suffisamment long'), $this->supervisorId);

        $this->assertTrue($insideTransaction, 'report.reopened doit être dispatché DANS la transaction métier');
        $this->assertSame(1, $this->outboxCount());
    }

    // ══ report.abandoned ═══════════════════════════════════════════════════

    public function testReportAbandonedIsDispatchedInsideBusinessTransaction(): void
    {
        $uuid = 'a2200000-0000-4000-8000-000000000003';
        $this->insertReport($uuid, ReportState::Nouveau->value, $this->agentId);

        $insideTransaction = null;
        $events = new EventDispatcher();
        $events->addListener('report.abandoned', function (ReportEventData $data) use (&$insideTransaction): void {
            $insideTransaction = $data->pdo !== null && $data->pdo->inTransaction();
            (new EmailOutboxRepository($this->pdo))->enqueue($this->envelope('atomic-abandoned'));
        });
        $service = new ReportService(new ReportRepository($this->pdo), $events, new ReportStateMachine());
        $this->setSession($this->agentId, 'agent');

        $service->abandon($uuid, $this->agentId);

        $this->assertTrue($insideTransaction, 'report.abandoned doit être dispatché DANS la transaction métier');
        $this->assertSame(1, $this->outboxCount());
    }

    // ══ Listeners de production : propagation au boundary ══════════════════

    public function testProductionListenerPropagatesEnqueueFailureInsideTransaction(): void
    {
        require_once __DIR__ . '/../../src/Event/event_listeners.php';

        $events = new EventDispatcher();
        $container = new \App\Container\Container();
        $container->set(\App\Services\NotificationService::class, fn() => $this->throwingNotifications());
        registerEventListeners($events, $container);

        $this->pdo->beginTransaction();
        $propagated = false;
        try {
            $events->dispatch('report.created', new ReportEventData(
                reportUuid: 'atomic-uuid',
                type: 'rsst',
                siteId: null,
                pdo: $this->pdo,
            ));
        } catch (RuntimeException $e) {
            $propagated = true;
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }

        $this->assertTrue(
            $propagated,
            'Dans une transaction, l\'échec d\'enqueue du listener doit se propager (rollback), pas être avalé'
        );
    }

    public function testProductionListenerIsBestEffortOutsideTransaction(): void
    {
        require_once __DIR__ . '/../../src/Event/event_listeners.php';

        $events = new EventDispatcher();
        $container = new \App\Container\Container();
        $container->set(\App\Services\NotificationService::class, fn() => $this->throwingNotifications());
        registerEventListeners($events, $container);

        // Hors transaction : un échec de notification ne doit pas casser la requête
        // (l'action métier est déjà committée).
        $events->dispatch('report.created', new ReportEventData(
            reportUuid: 'atomic-uuid',
            type: 'rsst',
            siteId: null,
            pdo: $this->pdo,
        ));

        $this->assertTrue(true, 'Hors transaction, le listener reste best-effort (pas d\'exception)');
    }

    // ══ role_changed : update + notification de rôle atomiques ═══════════════

    public function testRoleUpdateAndNotificationRollbackTogether(): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->updateRoleToSuperviseur();
            (new NotificationService($this->pdo))->notifyRoleChange($this->agentId, 'agent', 'superviseur', 'role-atomic-key');
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->fail('update/notifyRoleChange doivent rejoindre la transaction ouverte : ' . $e->getMessage());
        }
        $this->pdo->rollBack();

        $this->assertSame(
            'agent',
            (string) $this->pdo->query("SELECT role FROM users WHERE id = {$this->agentId}")->fetchColumn(),
            'Le rollback annule le changement de rôle'
        );
        $this->assertSame(0, $this->outboxCount(), 'Le rollback annule la notification de rôle (atomicité)');
    }

    public function testRoleUpdateAndNotificationCommitTogether(): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->updateRoleToSuperviseur();
            (new NotificationService($this->pdo))->notifyRoleChange($this->agentId, 'agent', 'superviseur', 'role-atomic-key');
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->assertSame('superviseur', (string) $this->pdo->query("SELECT role FROM users WHERE id = {$this->agentId}")->fetchColumn());
        $this->assertSame(1, $this->outboxCount(), 'Le commit persiste la notification de rôle');
    }

    private function updateRoleToSuperviseur(): void
    {
        (new UserRepository($this->pdo))->update($this->agentId, new UpdateUserCommand(
            username: 'atom.agent',
            nom: 'Agent',
            prenom: 'Atom',
            role: 'superviseur',
            siteId: SiteId::fromInput($this->siteId),
            email: 'atom.agent@dreets-bfc.gouv.fr',
        ));
    }

    private function throwingNotifications(): object
    {
        return new class {
            public function notifyNewReport(string $reportUuid, string $type, int $siteId): void
            {
                throw new RuntimeException('enqueue indisponible');
            }

            public function notifyReportResponse(string $reportUuid, int $userId, int $responseId): void {}

            public function notifyReportReopen(string $reportUuid, int $userId, ?string $motif, int $stateHistoryId): void {}

            public function notifyReportAbandon(string $reportUuid, int $userId, int $stateHistoryId): void {}

            public function notifyRoleChange(int $userId, string $oldRole, string $newRole, string $eventKey): void {}
        };
    }
}
