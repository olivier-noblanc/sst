<?php

/**
 * Outbox — aucune perte déterministe (TDD, NO-GO Oracle).
 *
 * Le dedup_key d'un message doit encoder l'identité de l'ACTION (l'occurrence
 * réelle), pas seulement le type d'événement : sinon deux actions distinctes
 * produisant le même triplet (événement, entité, destinataire) partagent la
 * même clé, `ON CONFLICT DO NOTHING` avale silencieusement la seconde et la
 * notification est PERDUE de façon reproductible.
 *
 * Scénarios prouvés ici (chacun échouait avec les identités « Option A ») :
 *   - réponses successives au même signalement (responseId) ;
 *   - réouvertures répétées (state_history.id) ;
 *   - abandons répétés (state_history.id) ;
 *   - changements de rôle cycliques A→B→A→B (eventKey du handler) ;
 *   - ré-invitation après expiration de l'invite (token de l'invite).
 *
 * Le contrat inverse est verrouillé aussi : rejouer LA MÊME action (même
 * identité) reste idempotent (une seule ligne).
 *
 * Le transport est neutralisé : ces fonctions ne font QUE mettre en file.
 */

use App\DTO\ReportEventData;
use App\DTO\RespondToReportCommand;
use App\DTO\ReopenReportCommand;
use App\DTO\SessionUser;
use App\Enum\OutboxEvent;
use App\Enum\ReportState;
use App\Enum\RespondStatus;
use App\Event\EventDispatcher;
use App\Repository\ReportRepository;
use App\Services\ReportService;
use App\Services\ReportStateMachine;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class OutboxDeterministicLossTest extends TestCase
{
    private const DECLARANT_ID = 7101;
    private const ACTOR_ID = 7102;
    private const ROLE_USER_ID = 7103;
    private const DECLARANT_EMAIL = 'decl.loss@dreets-bfc.gouv.fr';
    private const GLOBAL_EMAIL = 'global.loss@dreets-bfc.gouv.fr';
    private const ROLE_EMAIL = 'role.loss@dreets-bfc.gouv.fr';

    private PDO $pdo;

    protected function setUp(): void
    {
        setMailerSeam(null);
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM report_agents');
        $this->pdo->exec('DELETE FROM report_agent_invites');

        $this->insertUser(self::DECLARANT_ID, 'loss.decl', 'Decl', 'Arant', 'agent', self::DECLARANT_EMAIL);
        $this->insertUser(self::ACTOR_ID, 'loss.actor', 'Res', 'Pondant', 'superviseur', 'actor.loss@dreets-bfc.gouv.fr');
        $this->insertUser(self::ROLE_USER_ID, 'loss.role', 'Ro', 'Le', 'agent', self::ROLE_EMAIL);
    }

    protected function tearDown(): void
    {
        setMailerSeam(null);
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM report_agents');
        $this->pdo->exec('DELETE FROM report_agent_invites');
        cleanupAllForTest($this->pdo);
    }

    private function insertUser(int $id, string $username, string $nom, string $prenom, string $role, string $email): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email)
            VALUES (:id, :username, :nom, :prenom, :role, NULL, 1, :email)
        ');
        $stmt->execute([
            ':id' => $id, ':username' => $username, ':nom' => $nom,
            ':prenom' => $prenom, ':role' => $role, ':email' => $email,
        ]);
    }

    private function insertReport(string $uuid, string $etat, int $declarantId = self::DECLARANT_ID): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement,
                                 declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat)
            VALUES (:uuid, :ref, :type, :objet, :descr, :date, :declarant_id, :nom, :prenom, NULL, 0, :etat)
        ');
        $stmt->execute([
            ':uuid' => $uuid,
            ':ref' => 'RSST-26-' . substr($uuid, -6),
            ':type' => 'rsst',
            ':objet' => 'Objet perte',
            ':descr' => 'Description',
            ':date' => '2026-02-01',
            ':declarant_id' => $declarantId,
            ':nom' => 'Decl',
            ':prenom' => 'Arant',
            ':etat' => $etat,
        ]);
    }

    private function addGlobalRecipient(string $email): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO notification_settings (site_id, type, registry, email) VALUES (NULL, 'global', 'all', :email)");
        $stmt->execute([':email' => $email]);
    }

    private function outboxCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn();
    }

    /** @return list<string> */
    private function outboxKeys(): array
    {
        /** @var list<string> $keys */
        $keys = $this->pdo->query('SELECT dedup_key FROM email_outbox ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        return $keys;
    }

    // ══ Réponses successives (responseId) ══════════════════════════════════

    public function testSuccessiveResponsesProduceDistinctOutboxRows(): void
    {
        $uuid = 'a1100000-0000-4000-8000-000000000001';
        $this->insertReport($uuid, ReportState::EnCours->value);

        notifyReportResponse($this->pdo, $uuid, self::ACTOR_ID, 101);
        notifyReportResponse($this->pdo, $uuid, self::ACTOR_ID, 102);

        $this->assertSame(2, $this->outboxCount(), 'Deux réponses distinctes ne doivent jamais partager la même clé de dédup');
        $this->assertContains(
            OutboxEvent::ReportResponded->value . ':' . $uuid . ':101:' . strtolower(self::DECLARANT_EMAIL),
            $this->outboxKeys()
        );
        $this->assertContains(
            OutboxEvent::ReportResponded->value . ':' . $uuid . ':102:' . strtolower(self::DECLARANT_EMAIL),
            $this->outboxKeys()
        );
    }

    public function testSameResponseIsIdempotent(): void
    {
        $uuid = 'a1100000-0000-4000-8000-000000000002';
        $this->insertReport($uuid, ReportState::EnCours->value);

        notifyReportResponse($this->pdo, $uuid, self::ACTOR_ID, 101);
        notifyReportResponse($this->pdo, $uuid, self::ACTOR_ID, 101);

        $this->assertSame(1, $this->outboxCount(), 'Rejouer la même réponse (même identité) reste idempotent');
    }

    // ═══ Réouvertures répétées (state_history.id) ══════════════════════════

    public function testSuccessiveReopensProduceDistinctOutboxRows(): void
    {
        $uuid = 'a1100000-0000-4000-8000-000000000003';
        $this->insertReport($uuid, ReportState::Reouvert->value);

        $service = new \App\Services\NotificationService($this->pdo);
        // Même motif : c'est bien l'occurrence (historique) qui distingue, pas le motif.
        $service->notifyReportReopen($uuid, self::ACTOR_ID, 'Éléments nouveaux', 201);
        $service->notifyReportReopen($uuid, self::ACTOR_ID, 'Éléments nouveaux', 202);

        $this->assertSame(2, $this->outboxCount(), 'Deux réouvertures répétées ne doivent pas perdre la seconde');
        $this->assertContains(
            OutboxEvent::ReportReopened->value . ':' . $uuid . ':201:' . strtolower(self::DECLARANT_EMAIL),
            $this->outboxKeys()
        );
        $this->assertContains(
            OutboxEvent::ReportReopened->value . ':' . $uuid . ':202:' . strtolower(self::DECLARANT_EMAIL),
            $this->outboxKeys()
        );
    }

    // ═══ Abandons répétés (state_history.id) ════════════════════════════════

    public function testSuccessiveAbandonsProduceDistinctOutboxRows(): void
    {
        $uuid = 'a1100000-0000-4000-8000-000000000004';
        $this->insertReport($uuid, ReportState::EnCours->value);
        $this->addGlobalRecipient(self::GLOBAL_EMAIL);

        $service = new \App\Services\NotificationService($this->pdo);
        $service->notifyReportAbandon($uuid, self::ACTOR_ID, 301);
        $service->notifyReportAbandon($uuid, self::ACTOR_ID, 302);

        $this->assertSame(2, $this->outboxCount(), 'Deux abandons répétés (cycles reopen→abandon) ne doivent pas perdre le second');
        $this->assertContains(
            OutboxEvent::ReportAbandoned->value . ':' . $uuid . ':301:' . strtolower(self::GLOBAL_EMAIL),
            $this->outboxKeys()
        );
        $this->assertContains(
            OutboxEvent::ReportAbandoned->value . ':' . $uuid . ':302:' . strtolower(self::GLOBAL_EMAIL),
            $this->outboxKeys()
        );
    }

    // ══ Changements de rôle cycliques (eventKey) ═══════════════════════════

    public function testCyclicRoleChangesProduceDistinctOutboxRows(): void
    {
        notifyRoleChange($this->pdo, self::ROLE_USER_ID, 'agent', 'superviseur', 'key-1');
        notifyRoleChange($this->pdo, self::ROLE_USER_ID, 'superviseur', 'agent', 'key-2');
        notifyRoleChange($this->pdo, self::ROLE_USER_ID, 'agent', 'superviseur', 'key-3');

        $this->assertSame(
            3,
            $this->outboxCount(),
            'Le cycle agent→superviseur→agent→superviseur ne doit perdre aucune notification (clé = eventKey du handler)'
        );
    }

    public function testSameRoleChangeEventKeyIsIdempotent(): void
    {
        notifyRoleChange($this->pdo, self::ROLE_USER_ID, 'agent', 'superviseur', 'same-key');
        notifyRoleChange($this->pdo, self::ROLE_USER_ID, 'agent', 'superviseur', 'same-key');

        $this->assertSame(1, $this->outboxCount(), 'Rejouer la même transition (même eventKey) reste idempotent');
    }

    // ══ Ré-invitation après expiration de l'invite (token) ═════════════════

    public function testReinviteAfterInviteExpiryIsNotLost(): void
    {
        $uuid = 'a1100000-0000-4000-8000-000000000005';
        $this->insertReport($uuid, ReportState::Nouveau->value);
        $email = 'reinvite@dreets-bfc.gouv.fr';

        sendAgentInviteEmails($this->pdo, $uuid, [$email]);
        $this->assertSame(1, $this->outboxCount());

        // Le lazy cron purge les invitations non confirmées de plus de 30 jours
        // (cron_cleanup.php). L'invite disparaît ; son message est envoyé.
        $this->pdo->exec("UPDATE email_outbox SET status = 'sent'");
        $this->pdo->exec("DELETE FROM report_agent_invites WHERE report_uuid = '$uuid'");

        sendAgentInviteEmails($this->pdo, $uuid, [$email]);

        $this->assertSame(
            2,
            $this->outboxCount(),
            'Ré-inviter après expiration/confirmation doit produire une NOUVELLE mise en file (identité = token de l\'invite)'
        );
        $token = (string) $this->pdo->query(
            "SELECT token FROM report_agent_invites WHERE report_uuid = '$uuid'"
        )->fetchColumn();
        $this->assertNotSame('', $token, 'Une nouvelle invite (nouveau token) est persistée');
        $this->assertContains(
            OutboxEvent::AgentInvite->value . ':' . $uuid . ':' . $token . ':' . strtolower($email),
            $this->outboxKeys()
        );
    }

    public function testReinviteWhileInviteStillLiveStaysIdempotent(): void
    {
        $uuid = 'a1100000-0000-4000-8000-000000000006';
        $this->insertReport($uuid, ReportState::Nouveau->value);
        $email = 'live@dreets-bfc.gouv.fr';

        sendAgentInviteEmails($this->pdo, $uuid, [$email]);
        sendAgentInviteEmails($this->pdo, $uuid, [$email]);

        $this->assertSame(1, $this->outboxCount(), 'Tant que l\'invite est vivante (non confirmée), pas de doublon');
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM report_agent_invites WHERE report_uuid = '$uuid'")->fetchColumn()
        );
    }

    // ══ Plumbing : ReportService transmet l'identité d'occurrence ═════════

    public function testReportServiceForwardsDistinctLifecycleIdentities(): void
    {
        $events = new EventDispatcher();
        /** @var array<string, list<int|null>> $captured */
        $captured = ['responded' => [], 'reopened' => [], 'abandoned' => []];
        $events->addListener('report.responded', function (ReportEventData $d) use (&$captured): void {
            $captured['responded'][] = $d->actionId;
        });
        $events->addListener('report.reopened', function (ReportEventData $d) use (&$captured): void {
            $captured['reopened'][] = $d->actionId;
        });
        $events->addListener('report.abandoned', function (ReportEventData $d) use (&$captured): void {
            $captured['abandoned'][] = $d->actionId;
        });
        $service = new ReportService(new ReportRepository($this->pdo), $events, new ReportStateMachine());

        $respondA = 'a1100000-0000-4000-8000-00000000000a';
        $respondB = 'a1100000-0000-4000-8000-00000000000b';
        $this->insertReport($respondA, ReportState::Nouveau->value);
        $this->insertReport($respondB, ReportState::Nouveau->value);
        setUserSession(SessionUser::fromArray([
            'id' => self::ACTOR_ID, 'username' => 'loss.actor', 'role' => ROLE_SUPERVISEUR,
            'site_id' => null, 'is_active' => 1,
        ]));
        $cmd = new RespondToReportCommand(reponse: 'Réponse A', nouvelEtat: ReportState::EnCours);
        $this->assertSame(RespondStatus::Ok, $service->respond($respondA, $cmd, self::ACTOR_ID)['status']);
        $this->assertSame(RespondStatus::Ok, $service->respond($respondB, $cmd, self::ACTOR_ID)['status']);

        $reopenA = 'a1100000-0000-4000-8000-00000000000c';
        $reopenB = 'a1100000-0000-4000-8000-00000000000d';
        $this->insertReport($reopenA, ReportState::Traite->value);
        $this->insertReport($reopenB, ReportState::Traite->value);
        $reopenCmd = new ReopenReportCommand(motif: 'Éléments nouveaux suffisamment longs');
        $this->assertTrue($service->reopen($reopenA, $reopenCmd, self::ACTOR_ID));
        $this->assertTrue($service->reopen($reopenB, $reopenCmd, self::ACTOR_ID));

        $abandonA = 'a1100000-0000-4000-8000-00000000000e';
        $abandonB = 'a1100000-0000-4000-8000-00000000000f';
        $this->insertReport($abandonA, ReportState::Nouveau->value);
        $this->insertReport($abandonB, ReportState::Nouveau->value);
        setUserSession(SessionUser::fromArray([
            'id' => self::DECLARANT_ID, 'username' => 'loss.decl', 'role' => ROLE_AGENT,
            'site_id' => null, 'is_active' => 1,
        ]));
        $this->assertTrue($service->abandon($abandonA, self::DECLARANT_ID));
        $this->assertTrue($service->abandon($abandonB, self::DECLARANT_ID));

        foreach (['responded', 'reopened', 'abandoned'] as $event) {
            $ids = $captured[$event];
            $this->assertCount(2, $ids, $event . ' : deux actions distinctes doivent porter deux identités');
            $this->assertNotContains(null, $ids, $event . ' : l\'identité d\'occurrence doit être transmise');
            $this->assertNotSame($ids[0], $ids[1], $event . ' : deux actions distinctes ne peuvent pas partager la même identité');
        }
    }
}
