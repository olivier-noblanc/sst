<?php

declare(strict_types=1);

/**
 * OutboxMutationKillTest — tests ciblés pour rétablir le MSI Infection ≥ 80 %
 * après le lot outbox (mutants échappés du run CI 35082943676).
 *
 * Chaque test verrouille un comportement observable dont la mutation par
 * Infection change le verdict. Aucun changement de comportement produit : ces
 * tests ne font que couvrir ce que le lot outbox a introduit (transaction
 * partagée, flush post-commit, dedup_key d'occurrence, outbox repository).
 */

use App\DTO\OutboxMessage;
use App\DTO\UpdateReportCommand;
use App\Enum\OutboxEvent;
use App\Enum\OutboxStatus;
use App\Enum\ReportState;
use App\Repository\EmailOutboxRepository;
use App\Repository\ReportLifecycleRepository;
use App\Repository\ReportRepository;
use App\Repository\ReportWriteRepository;
use App\Repository\TransactionManager;
use App\Services\NotificationService;
use App\Services\OutboxHealthService;
use App\Services\ReportStateMachine;
use App\Enum\VisibilityMode;
use App\DTO\ReopenReportCommand;
use App\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;

/**
 * Spy : compte les flushOutbox(). En SAPI CLI le vrai drain est neutralisé,
 * seule l'intention de l'appelant est observable.
 */
class FlushOutboxSpyNotificationService extends NotificationService
{
    public int $flushCalls = 0;

    public function flushOutbox(): void
    {
        ++$this->flushCalls;
    }
}

class OutboxMutationKillTest extends TestCase
{
    private PDO $pdo;
    private int $siteId;
    private int $declarantId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM report_agents');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM registries');
        \App\Repository\RegistryRepository::instance()->seedDefaults();

        $this->pdo->prepare('INSERT INTO sites (code, nom) VALUES (?, ?)')->execute(['URMK', 'UR Mutation Kill']);
        $this->siteId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute(['mk.declarant', 'Dupont', 'Jean', 'agent', $this->siteId, 1, 'fixture@dreets-bfc.gouv.fr']);
        $this->declarantId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM email_outbox');
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM registries');
        reseedDefaultRegistries($this->pdo);
    }

    // ───────────────────────── helpers ─────────────────────────

    private function seedReport(string $etat = 'nouveau', ?int $siteId = null): string
    {
        $hex = bin2hex(random_bytes(16));
        $uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
            . '-' . dechex((hexdec(substr($hex, 16, 2)) & 0x3F) | 0x80) . substr($hex, 18, 2)
            . '-' . substr($hex, 20, 12);
        $ref = 'rsst-' . substr($hex, 0, 4);
        $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement,
                declarant_id, declarant_nom, declarant_prenom, site_id, etat, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $uuid, $ref, 'rsst', 'Test', 'Desc', '2026-01-15',
            $this->declarantId, 'Dupont', 'Jean', $siteId ?? $this->siteId, $etat, '2026-01-15 10:00:00',
        ]);
        return $uuid;
    }

    private function minUpdateCommand(): UpdateReportCommand
    {
        return new UpdateReportCommand(
            objet: 'Updated', description: 'Desc updated', dateEvenement: '2026-02-01',
            heureEvenement: null, lieu: null, siteText: null, pole: null,
            serviceAffectation: null, telephoneMobile: null,
            isConfidential: false, consentSyndicat: false,
        );
    }

    private function insertOutbox(string $dedupKey, string $status, string $createdAt, ?string $next = null): void
    {
        $this->pdo->prepare(
            'INSERT INTO email_outbox (dedup_key, recipient, subject, body, status, attempts, created_at, next_attempt_at)
             VALUES (:k, :r, :s, :b, :st, 0, :c, :n)'
        )->execute([
            ':k' => $dedupKey, ':r' => 'agent@dreets-bfc.gouv.fr', ':s' => 'Sujet', ':b' => 'Corps',
            ':st' => $status, ':c' => $createdAt, ':n' => $next,
        ]);
    }

    private function seedGlobalRecipient(string $email): void
    {
        $this->pdo->prepare("INSERT INTO notification_settings (site_id, type, registry, email) VALUES (NULL, 'global', '*', ?)")
            ->execute([$email]);
    }

    private function outboxBodyByRecipient(string $recipient): string
    {
        $stmt = $this->pdo->prepare('SELECT body FROM email_outbox WHERE recipient = :r ORDER BY id DESC LIMIT 1');
        $stmt->execute([':r' => $recipient]);
        $body = $stmt->fetchColumn();
        $this->assertIsString($body, "aucun message en file pour $recipient");

        return $body;
    }

    // ───────────────────── OutboxEvent::dedupKey ─────────────────────

    public function testDedupKeyNormalisesRecipientCaseAndSpaces(): void
    {
        // UnwrapStrToLower : strtolower(trim($recipient)) doit normaliser.
        $key = OutboxEvent::AgentInvite->dedupKey('u-1', '  Foo.BAR@Dreets.gouv.FR  ');

        $this->assertSame('agent_invite:u-1:foo.bar@dreets.gouv.fr', $key);
    }

    // ───────────── NotificationService flush post-notification ─────────────

    public function testFlushOutboxCalledByNotifyNewReport(): void
    {
        $uuid = $this->seedReport();
        $spy = new FlushOutboxSpyNotificationService($this->pdo);

        $spy->notifyNewReport($uuid, 'rsst', $this->siteId);

        $this->assertSame(1, $spy->flushCalls, 'notifyNewReport doit déclencher le flush post-enqueue');
    }

    public function testFlushOutboxCalledByNotifyReportResponse(): void
    {
        $uuid = $this->seedReport();
        $spy = new FlushOutboxSpyNotificationService($this->pdo);

        $spy->notifyReportResponse($uuid, $this->declarantId, 7);

        $this->assertSame(1, $spy->flushCalls, 'notifyReportResponse doit déclencher le flush post-enqueue');
    }

    public function testFlushOutboxCalledByNotifyReportReopen(): void
    {
        $uuid = $this->seedReport('traite');
        $spy = new FlushOutboxSpyNotificationService($this->pdo);

        $spy->notifyReportReopen($uuid, $this->declarantId, 'motif de test', 11);

        $this->assertSame(1, $spy->flushCalls, 'notifyReportReopen doit déclencher le flush post-enqueue');
    }

    public function testFlushOutboxCalledByNotifyRoleChange(): void
    {
        $spy = new FlushOutboxSpyNotificationService($this->pdo);

        $spy->notifyRoleChange($this->declarantId, 'agent', 'superviseur', 'role-key-1');

        $this->assertSame(1, $spy->flushCalls, 'notifyRoleChange doit déclencher le flush post-enqueue');
    }

    // ───────────── Reopen : dedup_key d'occurrence (identité) ─────────────

    public function testReopenEnqueuesDeclarantWithOccurrenceIdentity(): void
    {
        // Concat/ConcatOperandRemoval sur $reportUuid . ':' . $stateHistoryId.
        $uuid = $this->seedReport('traite');
        $this->pdo->prepare('UPDATE reports SET repondant_id = ? WHERE uuid = ?')
            ->execute([$this->declarantId, $uuid]);
        // Un autre utilisateur réouvre : le déclarant est notifié.
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?,?,?,?,?,?,?)')
            ->execute(['mk.other', 'Autre', 'Agent', 'superviseur', $this->siteId, 1, 'fixture@dreets-bfc.gouv.fr']);
        $otherId = (int) $this->pdo->lastInsertId();
        // Le déclarant doit avoir une adresse e-mail réelle.
        $this->pdo->prepare('UPDATE users SET email = ? WHERE id = ?')
            ->execute(['declarant@dreets-bfc.gouv.fr', $this->declarantId]);

        $spy = new FlushOutboxSpyNotificationService($this->pdo);
        $spy->notifyReportReopen($uuid, $otherId, null, 4242);

        $stmt = $this->pdo->prepare('SELECT dedup_key FROM email_outbox');
        $stmt->execute();
        $keys = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains(
            'report_reopened:' . $uuid . ':4242:declarant@dreets-bfc.gouv.fr',
            $keys,
            'l\'identité d\'occurrence uuid:stateHistoryId doit composer le dedup_key'
        );
    }

    // ────────────────── EmailOutboxRepository ───────────────────

    public function testEnqueueRejectsEmptyDedupKeyWithFullMessage(): void
    {
        $repo = new EmailOutboxRepository($this->pdo);

        $this->expectException(InvalidArgumentException::class);
        try {
            $repo->enqueue(new OutboxMessage(recipient: 'a@dreets-bfc.gouv.fr', subject: 's', body: 'b', dedupKey: '   '));
        } catch (InvalidArgumentException $e) {
            $this->assertSame(
                'EmailOutboxRepository::enqueue() exige un dedupKey non vide (identité logique de l\'événement) — '
                . 'une clé vide dédupliquerait arbitrairement toutes les mises en file.',
                $e->getMessage()
            );
            throw $e;
        }
    }

    public function testClaimBatchJoinsCallerTransactionWithoutCommitting(): void
    {
        $repo = new EmailOutboxRepository($this->pdo);
        $repo->enqueue(new OutboxMessage(recipient: 'a@dreets-bfc.gouv.fr', subject: 's', body: 'b', dedupKey: 'claim-joined'));

        $this->pdo->beginTransaction();
        try {
            $claimed = $repo->claimBatch(1, '2030-01-01 00:00:00');

            $this->assertCount(1, $claimed);
            $this->assertTrue($this->pdo->inTransaction(), 'claimBatch rejoint la transaction appelante sans la committer');
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    public function testClaimBatchCommitsOwnedTransactionWhenNoRows(): void
    {
        $repo = new EmailOutboxRepository($this->pdo);

        $this->assertSame([], $repo->claimBatch(5, '2030-01-01 00:00:00'));
        $this->assertFalse($this->pdo->inTransaction(), 'transaction possédée clôturée même sans ligne');
    }

    public function testClaimBatchZeroLimitReturnsEmptyEvenWithPendingRows(): void
    {
        $repo = new EmailOutboxRepository($this->pdo);
        $repo->enqueue(new OutboxMessage(recipient: 'a@dreets-bfc.gouv.fr', subject: 's', body: 'b', dedupKey: 'claim-zero'));

        $this->assertSame([], $repo->claimBatch(0), 'limit < 1 : aucun claim');
    }

    public function testClaimBatchUsesExplicitNow(): void
    {
        $repo = new EmailOutboxRepository($this->pdo);
        $repo->enqueue(new OutboxMessage(recipient: 'a@dreets-bfc.gouv.fr', subject: 's', body: 'b', dedupKey: 'claim-now'));

        $claimed = $repo->claimBatch(1, '2030-01-01 00:00:00');
        $this->assertCount(1, $claimed);

        $processingAt = $this->pdo->query("SELECT processing_at FROM email_outbox WHERE dedup_key = 'claim-now'")->fetchColumn();
        $this->assertSame('2030-01-01 00:00:00', $processingAt, 'le $now explicite doit être utilisé, pas l\'horloge');
    }

    public function testScheduleRetryReturnsFalseWhenRowNotProcessing(): void
    {
        $repo = new EmailOutboxRepository($this->pdo);
        $repo->enqueue(new OutboxMessage(recipient: 'a@dreets-bfc.gouv.fr', subject: 's', body: 'b', dedupKey: 'retry-nop'));

        $this->assertFalse($repo->scheduleRetry(999999, 'boom'));
    }

    public function testRequeueStaleProcessingClampsNegativeThreshold(): void
    {
        $repo = new EmailOutboxRepository($this->pdo);

        // Un processing très ancien : seuil négatif ramené à 0 → cutoff = now → récupéré.
        $this->insertOutbox('stale-neg', OutboxStatus::Processing->value, '2000-01-01 00:00:00');
        $this->pdo->exec("UPDATE email_outbox SET processing_at = '2000-01-01 00:00:00' WHERE dedup_key = 'stale-neg'");

        $this->assertSame(1, $repo->requeueStaleProcessing(-5, '2030-01-01 00:00:00'));
    }

    public function testHealthCountsClampsNegativeThreshold(): void
    {
        $repo = new EmailOutboxRepository($this->pdo);
        $this->insertOutbox('hc-failed', OutboxStatus::Failed->value, '2000-01-01 00:00:00');

        $counts = $repo->healthCounts(-5, '2030-01-01 00:00:00');

        $this->assertSame(1, $counts['failed']);
        $this->assertSame(0, $counts['stale_pending']);
    }

    // ─────────────────── TransactionManager ───────────────────

    public function testTransactionManagerCallsAfterCommitWhenOwner(): void
    {
        $tm = new TransactionManager($this->pdo);
        $calls = [];

        $result = $tm->run(static fn(): string => 'v', function () use (&$calls): void {
            $calls[] = 'after';
        });

        $this->assertSame('v', $result);
        $this->assertSame(['after'], $calls, 'afterCommit appelé quand on possède la transaction');
        $this->assertFalse($this->pdo->inTransaction());
    }

    public function testTransactionManagerSkipsAfterCommitOnJoinedTransaction(): void
    {
        $tm = new TransactionManager($this->pdo);
        $calls = [];

        $this->pdo->beginTransaction();
        try {
            $tm->run(static fn(): null => null, function () use (&$calls): void {
                $calls[] = 'after';
            });
            $this->assertSame([], $calls, 'afterCommit jamais appelé sur une transaction jointe');
            $this->assertTrue($this->pdo->inTransaction(), 'la transaction appelante reste ouverte');
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    public function testTransactionManagerRollsBackOwnedTransactionOnException(): void
    {
        $tm = new TransactionManager($this->pdo);

        try {
            $tm->run(static function (): void {
                throw new RuntimeException('boom');
            });
            $this->fail('l\'exception doit être repropagée');
        } catch (RuntimeException) {
            // attendu
        }

        $this->assertFalse($this->pdo->inTransaction(), 'rollback de la transaction possédée');
    }

    public function testTransactionManagerDoesNotRollBackCallerTransaction(): void
    {
        $tm = new TransactionManager($this->pdo);
        $this->pdo->beginTransaction();

        try {
            $tm->run(static function (): void {
                throw new RuntimeException('boom');
            });
            $this->fail('l\'exception doit être repropagée');
        } catch (RuntimeException) {
            // attendu
        }

        $this->assertTrue($this->pdo->inTransaction(), 'une transaction jointe n\'est jamais rollbackée par le manager');
        $this->pdo->rollBack();
    }

    // ─────────────────── OutboxHealthService ──────────────────

    public function testOutboxHealthServiceSnapshotShapeWhenClean(): void
    {
        $svc = new OutboxHealthService(new EmailOutboxRepository($this->pdo), 3600);

        $snapshot = $svc->snapshot('2030-01-01 00:00:00');

        $this->assertSame(['failed' => 0, 'stale_pending' => 0, 'incident' => false], $snapshot);
        $this->assertFalse($svc->hasIncident('2030-01-01 00:00:00'));
    }

    public function testOutboxHealthServiceReportsIncidentOnFailed(): void
    {
        $this->insertOutbox('h-failed', OutboxStatus::Failed->value, '2000-01-01 00:00:00');
        $svc = new OutboxHealthService(new EmailOutboxRepository($this->pdo), 3600);

        $snapshot = $svc->snapshot('2030-01-01 00:00:00');

        $this->assertSame(['failed' => 1, 'stale_pending' => 0, 'incident' => true], $snapshot);
        $this->assertTrue($svc->hasIncident('2030-01-01 00:00:00'));
    }

    // ─────────────────── ReportLifecycleRepository ───────────────────

    public function testAbandonCommitsOwnedTransaction(): void
    {
        $uuid = $this->seedReport('nouveau');
        $repo = new ReportLifecycleRepository($this->pdo);

        $id = $repo->abandon($uuid, $this->declarantId);

        $this->assertGreaterThan(0, $id);
        $this->assertFalse($this->pdo->inTransaction(), 'commit de la transaction possédée');
    }

    public function testAbandonMissingReportRollsBackOwnedTransaction(): void
    {
        $repo = new ReportLifecycleRepository($this->pdo);

        $id = $repo->abandon('missing-uuid', $this->declarantId);

        $this->assertSame(0, $id);
        $this->assertFalse($this->pdo->inTransaction(), 'rollback de la transaction possédée si aucun abandon');
    }

    /**
     * L'abandon est atteignable depuis Nouveau/EnCours/Traite/Reouvert : chaque
     * état doit être abandonnable (la clause IN du SQL doit porter le bon code).
     */
    public function testAbandonAcceptsEveryAbandonableState(): void
    {
        $repo = new ReportLifecycleRepository($this->pdo);

        foreach ([ReportState::Nouveau, ReportState::EnCours, ReportState::Traite, ReportState::Reouvert] as $state) {
            $etat = $state->value;
            $uuid = $this->seedReport($etat);

            $id = $repo->abandon($uuid, $this->declarantId);

            $this->assertGreaterThan(0, $id, "abandon depuis l'état $etat");

            $etatFinal = $this->pdo->query("SELECT etat FROM reports WHERE uuid = '$uuid'")->fetchColumn();
            $this->assertSame(ReportState::Abandonne->value, $etatFinal);
        }
    }

    public function testReopenFromAbandonneCommitsAndReturnsId(): void
    {
        $uuid = $this->seedReport('abandonne');
        $repo = new ReportLifecycleRepository($this->pdo);

        $id = $repo->reopen($uuid, $this->declarantId, 'Motif de réouverture suffisant');

        $this->assertGreaterThan(0, $id);
        $this->assertFalse($this->pdo->inTransaction(), 'commit de la transaction possédée');

        $etatFinal = $this->pdo->query("SELECT etat FROM reports WHERE uuid = '$uuid'")->fetchColumn();
        $this->assertSame(ReportState::Reouvert->value, $etatFinal);
    }

    public function testReopenMissingReportRollsBackAndReturnsZero(): void
    {
        $repo = new ReportLifecycleRepository($this->pdo);

        $id = $repo->reopen('missing-uuid', $this->declarantId, 'motif');

        $this->assertSame(0, $id);
        $this->assertFalse($this->pdo->inTransaction(), 'rollback si aucune réouverture');
    }

    public function testRespondCommitsOwnedTransaction(): void
    {
        $uuid = $this->seedReport('nouveau');
        $repo = new ReportLifecycleRepository($this->pdo);

        $result = $repo->respondToReport($uuid, $this->declarantId, 'Réponse', ReportState::EnCours->value);

        $this->assertSame(\App\Enum\RespondStatus::Ok, $result['status']);
        $this->assertFalse($this->pdo->inTransaction(), 'commit de la transaction possédée');
    }

    public function testRespondToMissingReportReturnsConcurrentAndRollsBack(): void
    {
        $repo = new ReportLifecycleRepository($this->pdo);

        $result = $repo->respondToReport('missing-uuid', $this->declarantId, 'Réponse', ReportState::EnCours->value);

        $this->assertSame(\App\Enum\RespondStatus::Concurrent, $result['status']);
        $this->assertFalse($this->pdo->inTransaction(), 'rollback si aucune ligne mise à jour');
    }

    // ─────────────────── ReportWriteRepository ───────────────────

    public function testReportWriteUpdateCommitsOwnedTransaction(): void
    {
        $uuid = $this->seedReport('nouveau');
        $repo = new ReportWriteRepository($this->pdo);

        $updated = $repo->update($uuid, $this->minUpdateCommand(), $this->declarantId);

        $this->assertTrue($updated);
        $this->assertFalse($this->pdo->inTransaction(), 'commit de la transaction possédée par update()');
    }

    public function testReportWriteCreateCommitsOwnedTransaction(): void
    {
        $cmd = new \App\DTO\CreateReportCommand(
            type: 'rsst', objet: 'Créé', description: 'Description', dateEvenement: '2026-01-15',
            heureEvenement: null, lieu: null, declarantId: $this->declarantId,
            declarantNom: 'Dupont', declarantPrenom: 'Jean',
            siteId: \App\DTO\SiteId::fromInput($this->siteId), siteText: null, pole: null,
            serviceAffectation: null, telephoneMobile: null, isConfidential: false,
            consentSyndicat: false, natureAuteur: null, typeActe: null,
            pourCompteNom: null, pourComptePrenom: null,
            attachmentBlob: null, attachmentName: null, attachmentMime: null,
        );
        $repo = new ReportWriteRepository($this->pdo);

        $uuid = $repo->create($cmd);

        $this->assertNotSame('', $uuid);
        $this->assertFalse($this->pdo->inTransaction(), 'commit de la transaction possédée par create()');
    }

    // ─────────────────── ReportAgentRepository ───────────────────

    public function testGetLinkedAgentsReturnsArrayForReportWithoutLinks(): void
    {
        $repo = new \App\Repository\ReportAgentRepository($this->pdo);
        $uuid = $this->seedReport();

        // CastBool : le fetch(false) ne doit pas devenir bool.
        $this->assertSame([], $repo->getLinkedAgents($uuid));
    }

    // ──────────── NotificationService : corps d'e-mail & flush ─────────────

    public function testFlushOutboxCalledByNotifyReportAbandon(): void
    {
        $this->seedGlobalRecipient('sup@dreets-bfc.gouv.fr');
        $uuid = $this->seedReport('nouveau');
        $spy = new FlushOutboxSpyNotificationService($this->pdo);

        $spy->notifyReportAbandon($uuid, $this->declarantId, 5);

        $this->assertSame(1, $spy->flushCalls, 'notifyReportAbandon doit déclencher le flush post-enqueue');
    }

    public function testAbandonNotificationBodyIsFullyBuilt(): void
    {
        $this->seedGlobalRecipient('sup@dreets-bfc.gouv.fr');
        $uuid = $this->seedReport('nouveau');
        $report = ReportRepository::instance()->findById($uuid);
        $this->assertNotNull($report);

        (new NotificationService($this->pdo))->notifyReportAbandon($uuid, $this->declarantId, 909);

        $label = getRegistryShortLabel('rsst');
        $expected = '<html><body>'
            . '<h2>Signalement abandonné</h2>'
            . '<p><strong>Référence :</strong> ' . e($report->reference) . '</p>'
            . "<p><strong>Registre :</strong> $label</p>"
            . '<p><strong>Objet :</strong> ' . e($report->objet) . '</p>'
            . '<p><strong>Déclarant :</strong> ' . e($report->declarantPrenom . ' ' . $report->declarantNom) . '</p>'
            . '<p><a href="' . absoluteUrl('report_view', ['uuid' => $uuid]) . '">Consulter le signalement</a></p>'
            . '</body></html>';

        $this->assertSame($expected, $this->outboxBodyByRecipient('sup@dreets-bfc.gouv.fr'));
    }

    public function testReopenNotificationBodiesForDeclarantAndLinkedAgent(): void
    {
        $uuid = $this->seedReport('traite');
        $this->pdo->prepare('UPDATE users SET email = ? WHERE id = ?')
            ->execute(['declarant@dreets-bfc.gouv.fr', $this->declarantId]);

        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?,?,?,?,?,?,?)')
            ->execute(['mk.linked', 'Lie', 'Agent', 'agent', $this->siteId, 1, 'linked@dreets-bfc.gouv.fr']);
        $linkedId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO report_agents (report_uuid, user_id) VALUES (?, ?)')->execute([$uuid, $linkedId]);

        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?,?,?,?,?,?,?)')
            ->execute(['mk.reopener', 'Re', 'Ouvreur', 'superviseur', $this->siteId, 1, 'reopener@dreets-bfc.gouv.fr']);
        $reopenerId = (int) $this->pdo->lastInsertId();

        (new NotificationService($this->pdo))->notifyReportReopen($uuid, $reopenerId, 'Motif explicite', 77);

        $report = ReportRepository::instance()->findById($uuid);
        $this->assertNotNull($report);
        $motifHtml = '<p><strong>Motif :</strong> ' . e('Motif explicite') . '</p>';
        $link = '<p><a href="' . absoluteUrl('report_view', ['uuid' => $uuid]) . '">Consulter le signalement</a></p>';

        $declarantBody = '<html><body><h2>Votre signalement a été réouvert</h2>'
            . '<p><strong>Référence :</strong> ' . e($report->reference) . '</p>' . $motifHtml . $link . '</body></html>';
        $linkedBody = '<html><body><h2>Signalement réouvert</h2>'
            . '<p>Bonjour ' . e('Agent') . ',</p>'
            . '<p>Le signalement <strong>' . e($report->reference) . '</strong> auquel vous êtes rattaché(e) a été réouvert.</p>'
            . $motifHtml . $link . '</body></html>';

        $this->assertSame($declarantBody, $this->outboxBodyByRecipient('declarant@dreets-bfc.gouv.fr'));
        $this->assertSame($linkedBody, $this->outboxBodyByRecipient('linked@dreets-bfc.gouv.fr'));
    }

    // ───────────── ReportLifecycleRepository : réponse / archive ─────────────

    public function testReopenPersistsResponseWithMotif(): void
    {
        $uuid = $this->seedReport('traite');
        (new ReportLifecycleRepository($this->pdo))->reopen($uuid, $this->declarantId, 'Motif de test 1234');

        $reponse = $this->pdo->query("SELECT reponse FROM report_responses WHERE report_uuid = '$uuid' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $this->assertSame('Réouverture du signalement. Motif : Motif de test 1234', $reponse);
    }

    public function testRespondAcceptedFromEveryAllowedState(): void
    {
        $repo = new ReportLifecycleRepository($this->pdo);

        foreach ([ReportState::Nouveau, ReportState::EnCours, ReportState::Reouvert] as $state) {
            $uuid = $this->seedReport($state->value);
            $result = $repo->respondToReport($uuid, $this->declarantId, 'Réponse', ReportState::EnCours->value);

            $this->assertSame(\App\Enum\RespondStatus::Ok, $result['status'], "réponse acceptée depuis {$state->value}");
        }
    }

    public function testRespondRejectedFromTraiteAndAbandonne(): void
    {
        $repo = new ReportLifecycleRepository($this->pdo);

        foreach ([ReportState::Traite, ReportState::Abandonne] as $state) {
            $uuid = $this->seedReport($state->value);
            $result = $repo->respondToReport($uuid, $this->declarantId, 'Réponse', ReportState::EnCours->value);

            $this->assertSame(\App\Enum\RespondStatus::Concurrent, $result['status'], "réponse refusée depuis {$state->value}");
        }
    }

    public function testRespondArchivesPreviousResponseOnReouvert(): void
    {
        $uuid = $this->seedReport('reouvert');
        $this->pdo->prepare("UPDATE reports SET reponse = ?, repondant_id = ? WHERE uuid = ?")
            ->execute(['Réponse initiale', $this->declarantId, $uuid]);

        $repo = new ReportLifecycleRepository($this->pdo);
        $result = $repo->respondToReport($uuid, $this->declarantId, 'Nouvelle réponse', ReportState::EnCours->value);

        $this->assertSame(\App\Enum\RespondStatus::Ok, $result['status']);

        $archived = $this->pdo->query("SELECT reponse, user_id FROM report_responses WHERE report_uuid = '$uuid' AND reponse LIKE '[Réponse initiale archivée]%'")->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($archived, 'la réponse initiale doit être archivée');
        $this->assertSame('[Réponse initiale archivée] Réponse initiale', $archived['reponse']);
        $this->assertSame($this->declarantId, (int) $archived['user_id'], 'l\'archive conserve le répondant d\'origine');
    }

    public function testReopenWritesHistoryTransitionFromPreviousState(): void
    {
        $uuid = $this->seedReport('traite');
        (new ReportLifecycleRepository($this->pdo))->reopen($uuid, $this->declarantId, 'Motif de test 1234');

        $row = $this->pdo->query("SELECT etat_precedent, etat_suivant FROM report_state_history WHERE report_uuid = '$uuid' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame(ReportState::Traite->value, $row['etat_precedent']);
        $this->assertSame(ReportState::Reouvert->value, $row['etat_suivant']);
    }

    public function testCountReopensCountsOnlyReouvertTransitions(): void
    {
        $uuid = $this->seedReport('traite');
        $repo = new ReportLifecycleRepository($this->pdo);
        $repo->reopen($uuid, $this->declarantId, 'Motif de test 1234');
        $repo->abandon($uuid, $this->declarantId);
        $repo->reopen($uuid, $this->declarantId, 'Motif de test 5678');

        $this->assertSame(2, $repo->countReopens($uuid));
    }

    public function testHasUnconfirmedInviteIsCaseInsensitiveAndConfirmedAware(): void
    {
        $uuid = $this->seedReport();
        $repo = new \App\Repository\ReportAgentRepository($this->pdo);
        $this->pdo->prepare("INSERT INTO report_agent_invites (report_uuid, email, token, confirmed) VALUES (?, ?, ?, 0)")
            ->execute([$uuid, 'Agent.Lie@Dreets.gouv.fr', 'tok-1']);

        $this->assertTrue($repo->hasUnconfirmedInvite($uuid, 'agent.lie@dreets.gouv.fr'));
        $this->assertFalse($repo->hasUnconfirmedInvite('other-uuid', 'agent.lie@dreets.gouv.fr'));
    }

    public function testUpdateRejectedFromTraiteState(): void
    {
        $uuid = $this->seedReport('traite');
        $repo = new ReportWriteRepository($this->pdo);

        $this->assertFalse($repo->update($uuid, $this->minUpdateCommand(), $this->declarantId), 'traite hors clause IN de update');
    }

    public function testUpdateAcceptedFromNouveauAndEnCours(): void
    {
        $repo = new ReportWriteRepository($this->pdo);

        foreach ([ReportState::Nouveau, ReportState::EnCours] as $state) {
            $uuid = $this->seedReport($state->value);
            $this->assertTrue(
                $repo->update($uuid, $this->minUpdateCommand(), $this->declarantId),
                "update accepté depuis {$state->value}"
            );
        }
    }

    public function testReopenByDeclarantDoesNotNotifyThemselves(): void
    {
        $uuid = $this->seedReport('traite');
        $this->pdo->prepare('UPDATE users SET email = ? WHERE id = ?')
            ->execute(['declarant@dreets-bfc.gouv.fr', $this->declarantId]);

        (new NotificationService($this->pdo))->notifyReportReopen($uuid, $this->declarantId, 'motif', 5);

        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn(), 'le déclarant qui réouvre n\'est pas notifié à lui-même');
    }

    public function testReopenWithNullMotifOmitsMotifBlock(): void
    {
        $uuid = $this->seedReport('traite');
        $this->pdo->prepare('UPDATE users SET email = ? WHERE id = ?')
            ->execute(['declarant@dreets-bfc.gouv.fr', $this->declarantId]);
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?,?,?,?,?,?,?)')
            ->execute(['mk.reop3', 'Re', 'Trois', 'superviseur', $this->siteId, 1, 'reop3@dreets-bfc.gouv.fr']);
        $reopenerId = (int) $this->pdo->lastInsertId();

        (new NotificationService($this->pdo))->notifyReportReopen($uuid, $reopenerId, null, 6);

        $this->assertStringNotContainsString('Motif :', $this->outboxBodyByRecipient('declarant@dreets-bfc.gouv.fr'));
    }

    public function testAbandonByNonOwnerReturnsFalse(): void
    {
        $this->seedGlobalRecipient('sup@dreets-bfc.gouv.fr');
        $uuid = $this->seedReport('nouveau');
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?,?,?,?,?,?,?)')
            ->execute(['mk.other2', 'Autre', 'Agent', 'agent', $this->siteId, 1, 'other2@dreets-bfc.gouv.fr']);
        $otherId = (int) $this->pdo->lastInsertId();

        setUserSession(\App\DTO\SessionUser::fromArray([
            'id' => $otherId, 'username' => 'mk.other2', 'nom' => 'Autre', 'prenom' => 'Agent',
            'role' => 'agent', 'site_id' => $this->siteId, 'is_active' => 1,
        ]));

        $spy = new FlushOutboxSpyNotificationService($this->pdo);
        $service = new \App\Services\ReportService(
            new ReportRepository($this->pdo),
            new \App\Event\EventDispatcher(),
            new \App\Services\ReportStateMachine(),
            $spy,
        );

        $this->assertFalse($service->abandon($uuid, $otherId), '0 transition → false');
        $this->assertSame(1, $spy->flushCalls, 'flush post-commit déclenché même quand l\'abandon n\'a pas eu lieu');
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn(), 'aucun événement dispatché pour un abandon avorté');
    }

    public function testHealthCountsStalePendingUsesExplicitNow(): void
    {
        $repo = new EmailOutboxRepository($this->pdo);
        // Pending échu, créé "près" du $now explicite : sans le $now fourni,
        // l'horloge réelle le rendrait non-stale.
        $this->insertOutbox('hc-stale', OutboxStatus::Pending->value, '2029-12-31 22:00:00', null);

        $counts = $repo->healthCounts(3600, '2030-01-01 00:00:00');

        $this->assertSame(0, $counts['failed']);
        $this->assertSame(1, $counts['stale_pending'], 'le $now explicite doit piloter le cutoff');
    }

    public function testReopenLinkedAgentDedupKeyUsesOccurrenceIdentity(): void
    {
        $uuid = $this->seedReport('traite');
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?,?,?,?,?,?,?)')
            ->execute(['mk.link2', 'Lie', 'Deux', 'agent', $this->siteId, 1, 'agent.lie@dreets-bfc.gouv.fr']);
        $linkedId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO report_agents (report_uuid, user_id) VALUES (?, ?)')->execute([$uuid, $linkedId]);
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?,?,?,?,?,?,?)')
            ->execute(['mk.reop2', 'Re', 'Deux', 'superviseur', $this->siteId, 1, 'reop2@dreets-bfc.gouv.fr']);
        $reopenerId = (int) $this->pdo->lastInsertId();

        (new NotificationService($this->pdo))->notifyReportReopen($uuid, $reopenerId, null, 313);

        $keys = $this->pdo->query('SELECT dedup_key FROM email_outbox')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('report_reopened:' . $uuid . ':313:agent.lie@dreets-bfc.gouv.fr', $keys);
    }

    // ───────────── ReportAgentRepository : countVisibleForAgent ─────────────

    public function testCountVisibleForAgentDefaultSiteCountsEverySite(): void
    {
        // Le défaut $siteId = 0 doit signifier « aucun filtre de site ».
        // Un site réel différent de 1 : un défaut muté en 1 filtrerait sur le
        // site 1 (vide) et renverrait 0.
        $this->pdo->prepare('INSERT INTO sites (code, nom) VALUES (?, ?)')->execute(['REAL2', 'Real 2']);
        $realSite = (int) $this->pdo->lastInsertId();
        $this->assertNotSame(1, $realSite);
        $this->seedReport('nouveau', $realSite);

        $repo = new \App\Repository\ReportAgentRepository($this->pdo);
        $count = $repo->countVisibleForAgent('rsst', $this->declarantId, visibility: VisibilityMode::Public->value);

        $this->assertSame(1, $count, 'siteId par défaut (0) → aucun filtre de site');
    }

    public function testCountVisibleForAgentExplicitZeroSiteAppliesNoSiteFilter(): void
    {
        $this->seedReport('nouveau');

        $repo = new \App\Repository\ReportAgentRepository($this->pdo);
        $count = $repo->countVisibleForAgent('rsst', $this->declarantId, 0, VisibilityMode::Public->value);

        $this->assertSame(1, $count, 'siteId = 0 → pas de clause site_id (aucune ligne n\'a site_id=0)');
    }

    public function testCountVisibleForAgentChoiceExcludesUnlinkedConfidential(): void
    {
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute(['mk.count', 'Compt', 'Autre', 'agent', $this->siteId, 1, 'count@dreets-bfc.gouv.fr']);
        $otherId = (int) $this->pdo->lastInsertId();

        $uuid = $this->seedReport('nouveau');
        $this->pdo->prepare('UPDATE reports SET is_confidential = 1 WHERE uuid = ?')->execute([$uuid]);

        $repo = new \App\Repository\ReportAgentRepository($this->pdo);
        $count = $repo->countVisibleForAgent('rsst', $otherId, 0, VisibilityMode::AgentChoice->value);

        $this->assertSame(0, $count, 'agent_choice : confidentiel non rattaché exclu');
    }

    public function testGetLinkedAgentsReturnsEveryLinkedAgent(): void
    {
        $uuid = $this->seedReport();
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute(['mk.linkA', 'Aaa', 'Un', 'agent', $this->siteId, 1, 'a@dreets-bfc.gouv.fr']);
        $idA = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute(['mk.linkB', 'Bbb', 'Deux', 'agent', $this->siteId, 1, 'b@dreets-bfc.gouv.fr']);
        $idB = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO report_agents (report_uuid, user_id) VALUES (?, ?)')->execute([$uuid, $idA]);
        $this->pdo->prepare('INSERT INTO report_agents (report_uuid, user_id) VALUES (?, ?)')->execute([$uuid, $idB]);

        $repo = new \App\Repository\ReportAgentRepository($this->pdo);

        $this->assertCount(2, $repo->getLinkedAgents($uuid), 'tous les agents liés sont retournés');
    }

    public function testGetPendingInvitesReturnsEveryPendingInvite(): void
    {
        $uuid = $this->seedReport();
        $this->pdo->prepare('INSERT INTO report_agent_invites (report_uuid, email, token, confirmed) VALUES (?, ?, ?, 0)')
            ->execute([$uuid, 'i1@dreets-bfc.gouv.fr', 'tok-e1']);
        $this->pdo->prepare('INSERT INTO report_agent_invites (report_uuid, email, token, confirmed) VALUES (?, ?, ?, 0)')
            ->execute([$uuid, 'i2@dreets-bfc.gouv.fr', 'tok-e2']);

        $repo = new \App\Repository\ReportAgentRepository($this->pdo);

        $this->assertCount(2, $repo->getPendingInvites($uuid), 'toutes les invitations en attente sont retournées (execute lié au report_uuid)');
    }

    // ───────────── ReportWriteRepository : sémentique des pièces jointes ─────────────

    private function seedAttachment(string $uuid, string $blob): void
    {
        $this->pdo->prepare("UPDATE reports SET attachment_blob = ?, attachment_name = 'f.bin', attachment_mime = 'application/octet-stream' WHERE uuid = ?")
            ->execute([$blob, $uuid]);
    }

    private function attachmentBlobOf(string $uuid): mixed
    {
        $stmt = $this->pdo->prepare('SELECT attachment_blob FROM reports WHERE uuid = ?');
        $stmt->execute([$uuid]);

        return $stmt->fetchColumn();
    }

    public function testUpdateWithRemoveAttachmentClearsExistingAttachment(): void
    {
        $uuid = $this->seedReport('nouveau');
        $this->seedAttachment($uuid, 'OLD-BLOB');
        $cmd = new UpdateReportCommand(
            objet: 'O', description: 'D', dateEvenement: '2026-02-01', heureEvenement: null,
            lieu: null, siteText: null, pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: false, consentSyndicat: false, removeAttachment: true,
        );

        $this->assertTrue((new ReportWriteRepository($this->pdo))->update($uuid, $cmd, $this->declarantId));
        $this->assertNull($this->attachmentBlobOf($uuid), 'removeAttachment=true doit vider la PJ existante');
    }

    public function testUpdateWithNewAttachmentPersistsIt(): void
    {
        $uuid = $this->seedReport('nouveau');
        $cmd = new UpdateReportCommand(
            objet: 'O', description: 'D', dateEvenement: '2026-02-01', heureEvenement: null,
            lieu: null, siteText: null, pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: false, consentSyndicat: false,
            attachmentBlob: 'NEW-BLOB', attachmentName: 'n.bin', attachmentMime: 'text/plain',
        );

        $this->assertTrue((new ReportWriteRepository($this->pdo))->update($uuid, $cmd, $this->declarantId));
        $this->assertSame('NEW-BLOB', $this->attachmentBlobOf($uuid), 'une nouvelle PJ est écrite');
    }

    public function testUpdateWithoutAttachmentChangeKeepsExistingAttachment(): void
    {
        $uuid = $this->seedReport('nouveau');
        $this->seedAttachment($uuid, 'KEEP-BLOB');
        $cmd = new UpdateReportCommand(
            objet: 'O', description: 'D', dateEvenement: '2026-02-01', heureEvenement: null,
            lieu: null, siteText: null, pole: null, serviceAffectation: null, telephoneMobile: null,
            isConfidential: false, consentSyndicat: false,
        );

        $this->assertTrue((new ReportWriteRepository($this->pdo))->update($uuid, $cmd, $this->declarantId));
        $this->assertSame('KEEP-BLOB', $this->attachmentBlobOf($uuid), 'sans removeAttachment ni nouvelle PJ, la PJ existante est préservée');
    }

    // ───────── NotificationService : dédup agent lié vs déclarant ─────────

    public function testReopenLinkedAgentSharingDeclarantEmailIsNotDuplicated(): void
    {
        $uuid = $this->seedReport('traite');
        $email = 'dup@dreets-bfc.gouv.fr';
        $this->pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $this->declarantId]);

        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute(['mk.duplink', 'Dup', 'Link', 'agent', $this->siteId, 1, $email]);
        $linkedId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO report_agents (report_uuid, user_id) VALUES (?, ?)')->execute([$uuid, $linkedId]);

        $this->pdo->prepare('INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute(['mk.dupreop', 'Re', 'Dup', 'superviseur', $this->siteId, 1, 'reop-dup@dreets-bfc.gouv.fr']);
        $reopenerId = (int) $this->pdo->lastInsertId();

        (new NotificationService($this->pdo))->notifyReportReopen($uuid, $reopenerId, 'motif', 42);

        $this->assertSame(
            1,
            (int) $this->pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn(),
            'un agent lié partageant l\'e-mail du déclarant ne reçoit pas de second message'
        );
    }

    // ───────── EmailOutboxRepository : bornes des seuils négatifs ─────────

    public function testRequeueStaleProcessingNegativeThresholdClampsToZeroCutoff(): void
    {
        $repo = new EmailOutboxRepository($this->pdo);
        $this->insertOutbox('clamp-req', OutboxStatus::Processing->value, '2030-01-01 00:00:00');
        $this->pdo->exec("UPDATE email_outbox SET processing_at = '2030-01-01 00:00:01' WHERE dedup_key = 'clamp-req'");

        // Seuil négatif ramené à 0 → cutoff = now. Une ligne à now+1s reste hors cutoff.
        $this->assertSame(
            0,
            $repo->requeueStaleProcessing(-5, '2030-01-01 00:00:00'),
            'seuil négatif borné à 0 (cutoff = now), la ligne à now+1s n\'est pas récupérée'
        );
    }

    public function testHealthCountsNegativeThresholdClampsToZeroCutoff(): void
    {
        $repo = new EmailOutboxRepository($this->pdo);
        $this->insertOutbox('clamp-hc', OutboxStatus::Pending->value, '2030-01-01 00:00:01', null);

        $counts = $repo->healthCounts(-5, '2030-01-01 00:00:00');

        $this->assertSame(0, $counts['stale_pending'], 'seuil négatif borné à 0 → created_at à now+1s non stale');
    }

    // ───────── ReportService : limite de réouvertures (bornes) ─────────

    private function loginAsSuperviseur(): void
    {
        setUserSession(\App\DTO\SessionUser::fromArray([
            'id' => $this->declarantId,
            'username' => 'mk.declarant',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'role' => 'superviseur',
            'site_id' => $this->siteId,
            'is_active' => 1,
        ]));
    }

    private function reportService(): \App\Services\ReportService
    {
        return new \App\Services\ReportService(
            new ReportRepository($this->pdo),
            new EventDispatcher(),
            new ReportStateMachine(),
        );
    }

    public function testReopenAllowedWhenMaxReopensConfigIsZero(): void
    {
        $uuid = $this->seedReport('traite');
        \getConfigService()->set('app_max_reopens_per_report', '0');
        $this->loginAsSuperviseur();

        try {
            $this->assertTrue(
                $this->reportService()->reopen($uuid, new ReopenReportCommand('Motif de test 1234'), $this->declarantId),
                'limite 0 = aucune limite → la réouverture aboutit'
            );
        } finally {
            \getConfigService()->set('app_max_reopens_per_report', '3');
        }
    }

    public function testReopenRefusedAtExactLimitBoundary(): void
    {
        $uuid = $this->seedReport('traite');
        \getConfigService()->set('app_max_reopens_per_report', '1');
        $this->loginAsSuperviseur();

        $lifecycle = new ReportLifecycleRepository($this->pdo);
        $this->assertGreaterThan(0, $lifecycle->reopen($uuid, $this->declarantId, 'Motif initial 1234'));
        $this->assertGreaterThan(0, $lifecycle->abandon($uuid, $this->declarantId));

        $this->expectException(RuntimeException::class);
        try {
            $this->reportService()->reopen($uuid, new ReopenReportCommand('Motif second 1234'), $this->declarantId);
        } finally {
            \getConfigService()->set('app_max_reopens_per_report', '3');
        }
    }
}