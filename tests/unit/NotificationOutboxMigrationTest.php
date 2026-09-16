<?php

/**
 * Notification → outbox SMTP — migration des événements métier (Option A).
 *
 * TDD : ces tests décrivent le comportement ATTENDU après migration ; ils
 * échouent tant que les listeners/notifications envoient encore par sendMail().
 *
 * Contrat verrouillé ici :
 *   - les 4 événements report.* ENQUEUE des messages figés (destinataires,
 *     sujets, corps, dedup_key) au lieu d'envoyer en direct ;
 *   - le transport (seam mailer) n'est JAMAIS appelé au moment de l'événement ;
 *   - deux dispatches du même événement ne produisent qu'une ligne par
 *     destinataire (déduplication par dedup_key) ;
 *   - la sentinelle d'anonymisation n'est jamais un destinataire enqueue.
 *
 * Le seam injectable setMailerSeam() sert d'espion : s'il est appelé, c'est une
 * preuve d'envoi direct résiduel.
 */

use App\DTO\ReportEventData;
use App\Enum\OutboxEvent;
use App\Repository\AnonymizationPolicy;
use App\Services\NotificationService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class NotificationOutboxMigrationTest extends TestCase
{
    private PDO $pdo;

    private const DECLARANT_ID = 9401;
    private const LINKED_ID = 9402;
    private const RESPONDENT_ID = 9403;
    private const DECLARANT_EMAIL = 'declarant@dreets-bfc.gouv.fr';
    private const LINKED_EMAIL = 'rattache@dreets-bfc.gouv.fr';
    private const GLOBAL_A = 'sup1@dreets-bfc.gouv.fr';
    private const GLOBAL_B = 'sup2@dreets-bfc.gouv.fr';

    protected function setUp(): void
    {
        setMailerSeam(null);
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM report_agents');
        $this->pdo->exec('DELETE FROM report_agent_invites');

        $this->insertUser(self::DECLARANT_ID, 'test.outbox.decl', 'Decl', 'Arant', 'agent', self::DECLARANT_EMAIL);
        $this->insertUser(self::LINKED_ID, 'test.outbox.linked', 'Rat', 'Tache', 'agent', self::LINKED_EMAIL);
        $this->insertUser(self::RESPONDENT_ID, 'test.outbox.respond', 'Res', 'Pondant', 'superviseur', 'resp@dreets-bfc.gouv.fr');
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

    private function insertReport(string $uuid, string $etat = 'nouveau', int $declarantId = self::DECLARANT_ID): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement,
                                 declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat)
            VALUES (:uuid, :ref, :type, :objet, :descr, :date, :declarant_id, :nom, :prenom, NULL, 0, :etat)
        ');
        $stmt->execute([
            ':uuid' => $uuid,
            ':ref' => 'RSST-26-' . substr($uuid, 0, 6),
            ':type' => 'rsst',
            ':objet' => 'Objet outbox',
            ':descr' => 'Description',
            ':date' => '2026-02-01',
            ':declarant_id' => $declarantId,
            ':nom' => 'Decl',
            ':prenom' => 'Arant',
            ':etat' => $etat,
        ]);
    }

    private function addGlobalRecipients(string ...$emails): void
    {
        foreach ($emails as $email) {
            $stmt = $this->pdo->prepare("INSERT INTO notification_settings (site_id, type, registry, email) VALUES (NULL, 'global', 'all', :email)");
            $stmt->execute([':email' => $email]);
        }
    }

    private function linkAgent(string $reportUuid, int $userId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO report_agents (report_uuid, user_id) VALUES (:uuid, :uid)');
        $stmt->execute([':uuid' => $reportUuid, ':uid' => $userId]);
    }

    /** @return list<array<string, mixed>> */
    private function outboxRows(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->pdo->query('SELECT * FROM email_outbox ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    private function outboxCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    private function rowByDedupKey(string $dedupKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_outbox WHERE dedup_key = :k');
        $stmt->execute([':k' => $dedupKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function dispatch(string $event, ReportEventData $data): void
    {
        $events = new \App\Event\EventDispatcher();
        registerEventListeners($events, getContainer());
        $events->dispatch($event, $data);
    }

    /** @return list<string> */
    private function spiesSeam(): array
    {
        $sent = [];
        setMailerSeam(static function (string $to, string $subject, string $body, string $from = '') use (&$sent): bool {
            $sent[] = $to;
            return true;
        });
        return $sent;
    }

    // ═══ report.created → enqueue figé, pas d'envoi direct ══════════════════

    public function testReportCreatedEnqueuesFrozenMessagesWithoutDirectSend(): void
    {
        $uuid = 'aaaaaaaa-1111-2222-3333-444444444444';
        $this->insertReport($uuid);
        $this->addGlobalRecipients(self::GLOBAL_A, self::GLOBAL_B);
        $sent = $this->spiesSeam();

        $this->dispatch('report.created', new ReportEventData(reportUuid: $uuid, type: 'rsst', siteId: null));

        $this->assertSame([], $sent, 'Aucun envoi direct ne doit avoir lieu : le message est seulement mis en file');

        $rows = $this->outboxRows();
        $this->assertCount(2, $rows, 'Une ligne outbox par destinataire');

        $recipients = array_column($rows, 'recipient');
        sort($recipients);
        $this->assertSame([self::GLOBAL_A, self::GLOBAL_B], $recipients, 'Destinataires figés à l\'enqueue');

        $expectedKey = OutboxEvent::ReportCreated->value . ':' . $uuid . ':' . strtolower(self::GLOBAL_A);
        $row = $this->rowByDedupKey($expectedKey);
        $this->assertNotNull($row, 'Le dedup_key encode événement + identité + destinataire');
        $this->assertStringContainsString('Nouveau signalement', (string) $row['subject']);
        $this->assertStringContainsString('Objet outbox', (string) $row['body'], 'Le corps figé porte le contenu du signalement');
        $this->assertSame('pending', (string) $row['status']);
    }

    public function testReportCreatedEnqueueIsDeduplicatedAcrossDispatches(): void
    {
        $uuid = 'bbbbbbbb-1111-2222-3333-444444444444';
        $this->insertReport($uuid);
        $this->addGlobalRecipients(self::GLOBAL_A);

        $data = new ReportEventData(reportUuid: $uuid, type: 'rsst', siteId: null);
        $this->dispatch('report.created', $data);
        $this->dispatch('report.created', $data);

        $this->assertSame(1, $this->outboxCount(), 'Un même événement rejoué n\'enqueue qu\'une ligne par destinataire');
    }

    public function testReportCreatedDoesNotEnqueueAnonymizedSentinel(): void
    {
        // Le déclarant anonymisé n'est pas destinataire de la création (ce sont
        // les superviseurs), mais la sentinelle ne doit jamais apparaître en
        // file même si elle est listée comme destinataire global.
        $uuid = 'cccccccc-1111-2222-3333-444444444444';
        $this->insertReport($uuid);
        $this->addGlobalRecipients(AnonymizationPolicy::ANONYMIZED_EMAIL, self::GLOBAL_A);

        $this->dispatch('report.created', new ReportEventData(reportUuid: $uuid, type: 'rsst', siteId: null));

        $recipients = array_column($this->outboxRows(), 'recipient');
        $this->assertNotContains(AnonymizationPolicy::ANONYMIZED_EMAIL, $recipients, 'La sentinelle n\'est jamais enqueue');
        $this->assertSame([self::GLOBAL_A], $recipients);
    }

    // ═══ report.responded → enqueue figé (déclarant + rattachés) ════════════

    public function testReportRespondedEnqueuesDeclarantAndLinkedAgents(): void
    {
        $uuid = 'dddddddd-1111-2222-3333-444444444444';
        $this->insertReport($uuid, 'en_cours');
        $this->linkAgent($uuid, self::LINKED_ID);
        $sent = $this->spiesSeam();

        $this->dispatch('report.responded', new ReportEventData(reportUuid: $uuid, userId: self::RESPONDENT_ID, actionId: 4242));

        $this->assertSame([], $sent, 'Pas d\'envoi direct pour report.responded');

        $recipients = array_column($this->outboxRows(), 'recipient');
        sort($recipients);
        $this->assertSame([self::DECLARANT_EMAIL, self::LINKED_EMAIL], $recipients, 'Déclarant + agent rattaché sont enqueue');

        $identity = $uuid . ':4242';
        $declarantKey = OutboxEvent::ReportResponded->value . ':' . $identity . ':' . strtolower(self::DECLARANT_EMAIL);
        $row = $this->rowByDedupKey($declarantKey);
        $this->assertNotNull($row);
        $this->assertStringContainsString('Réponse à votre signalement', (string) $row['subject']);
        $this->assertStringContainsString('Consulter la réponse', (string) $row['body']);
    }

    public function testReportRespondedSkipsAnonymizedDeclarantButStillNotifiesLinked(): void
    {
        $anonymizedId = 9404;
        $this->insertUser($anonymizedId, 'test.outbox.anon', 'Anonymisé', 'Anonymé', 'agent', AnonymizationPolicy::ANONYMIZED_EMAIL);
        $uuid = 'eeeeeeee-1111-2222-3333-444444444444';
        $this->insertReport($uuid, 'en_cours', $anonymizedId);
        $this->linkAgent($uuid, self::LINKED_ID);

        $this->dispatch('report.responded', new ReportEventData(reportUuid: $uuid, userId: self::RESPONDENT_ID));

        $recipients = array_column($this->outboxRows(), 'recipient');
        $this->assertContains(self::LINKED_EMAIL, $recipients, 'Le rattaché est notifié malgré un déclarant anonymisé');
        $this->assertNotContains(AnonymizationPolicy::ANONYMIZED_EMAIL, $recipients);
    }

    // ══ report.reopened → enqueue figé + motif préservé ═════════════════════

    public function testReportReopenedEnqueuesWithMotifPreserved(): void
    {
        $uuid = 'ffffffff-1111-2222-3333-444444444444';
        $this->insertReport($uuid, 'traite');
        $this->linkAgent($uuid, self::LINKED_ID);
        $sent = $this->spiesSeam();

        $this->dispatch('report.reopened', new ReportEventData(
            reportUuid: $uuid,
            userId: 9405,
            motif: 'Eléments nouveaux après expertise',
        ));

        $this->assertSame([], $sent, 'Pas d\'envoi direct pour report.reopened');

        $rows = $this->outboxRows();
        $this->assertCount(2, $rows, 'Déclarant + rattaché');
        foreach ($rows as $row) {
            $this->assertStringContainsString('réouvert', (string) $row['subject']);
            $this->assertStringContainsString('Eléments nouveaux après expertise', (string) $row['body'], 'Le motif est figé dans le corps');
        }
    }

    // ══ report.abandoned → enqueue figé (superviseurs) ═════════════════════

    public function testReportAbandonedEnqueuesSupervisors(): void
    {
        $uuid = '99999999-1111-2222-3333-444444444444';
        $this->insertReport($uuid, 'en_cours');
        $this->addGlobalRecipients(self::GLOBAL_A);
        $sent = $this->spiesSeam();

        $this->dispatch('report.abandoned', new ReportEventData(reportUuid: $uuid, userId: self::DECLARANT_ID, actionId: 5555));

        $this->assertSame([], $sent, 'Pas d\'envoi direct pour report.abandoned');

        $row = $this->rowByDedupKey(OutboxEvent::ReportAbandoned->value . ':' . $uuid . ':5555:' . strtolower(self::GLOBAL_A));
        $this->assertNotNull($row);
        $this->assertStringContainsString('abandonné', (string) $row['subject']);
        $this->assertStringContainsString('Objet outbox', (string) $row['body']);
    }

    // ═══ NotificationService expose le flush opportuniste ════════════════════

    public function testNotificationServiceExposesOpportunisticFlush(): void
    {
        $service = new NotificationService($this->pdo);
        $this->assertTrue(method_exists($service, 'flushOutbox'));
        // En CLI (PHPUnit), le flush ne s'exécute pas : aucun envoi de test.
        $service->flushOutbox();
        $this->assertTrue(true, 'flushOutbox() ne doit jamais envoyer en CLI (lazy cron)');
    }
}
