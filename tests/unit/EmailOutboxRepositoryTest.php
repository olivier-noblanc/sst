<?php
/**
 * EmailOutboxRepository Tests — Application SST DREETS BFC
 *
 * TDD (phase 0-1 outbox SMTP) : tests écrits AVANT l'implémentation.
 *
 * Contrat verrouillé ici :
 *   - enqueue idempotent (déduplication par dedup_key) ;
 *   - enqueue refuse la sentinelle d'anonymisation (jamais persistée) ;
 *   - enqueue PARTICIPE à la transaction de l'appelant (rollback atomique) ;
 *   - claim atomique pending → processing (pas de double-claim, attempts++) ;
 *   - transitions processing → sent / failed ;
 *   - backoff exponentiel borné + ré-éligibilité différée (scheduleRetry) ;
 *   - schéma : table email_outbox, dedup_key UNIQUE, CHECK sur le statut.
 *
 * Conventions AGENTS.md : SQL uniquement dans Repository, enums (jamais de
 * magic string métier), tryFrom plutôt que from. site_id non concerné.
 */

use App\DTO\OutboxMessage;
use App\Enum\OutboxStatus;
use App\Repository\AnonymizationPolicy;
use App\Repository\EmailOutboxRepository;
use PHPUnit\Framework\TestCase;

class EmailOutboxRepositoryTest extends TestCase
{
    private PDO $pdo;
    private EmailOutboxRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->repo = new EmailOutboxRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM email_outbox');
    }

    private function message(
        string $dedupKey = 'dedup-1',
        string $recipient = 'agent@dreets-bfc.gouv.fr',
        string $subject = 'Sujet',
        string $body = '<p>Corps</p>',
    ): OutboxMessage {
        return new OutboxMessage(
            recipient: $recipient,
            subject: $subject,
            body: $body,
            dedupKey: $dedupKey,
        );
    }

    /** @return array<string, mixed>|null */
    private function rowByDedupKey(string $dedupKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_outbox WHERE dedup_key = :k');
        $stmt->execute([':k' => $dedupKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn();
    }

    // ═══ Enqueue idempotent / déduplication ══════════════════════════════════

    public function testEnqueueInsertsPendingRowWithDefaults(): void
    {
        $this->assertTrue($this->repo->enqueue($this->message('enq-default')));

        $row = $this->rowByDedupKey('enq-default');
        $this->assertNotNull($row);
        $this->assertSame(OutboxStatus::Pending->value, $row['status'], 'Un message enqueued démarre en pending');
        $this->assertSame(0, (int) $row['attempts'], 'attempts démarre à 0 (incrémenté au claim)');
        $this->assertNull($row['next_attempt_at'], 'next_attempt_at NULL = éligible immédiatement');
        $this->assertNull($row['processing_at']);
        $this->assertNull($row['sent_at']);
        $this->assertNull($row['failed_at']);
        $this->assertNull($row['last_error']);
        $this->assertSame('agent@dreets-bfc.gouv.fr', $row['recipient']);
        $this->assertSame('Sujet', $row['subject']);
        $this->assertSame('<p>Corps</p>', $row['body']);
        $this->assertSame('', $row['headers']);
    }

    public function testEnqueueIsIdempotentOnDedupKey(): void
    {
        $this->assertTrue($this->repo->enqueue($this->message('dedup-same', subject: 'Premier')));

        // Même clé, payload différent : le doublon est ignoré, pas écrasé.
        $this->assertFalse(
            $this->repo->enqueue($this->message('dedup-same', subject: 'Second')),
            'Un doublon de dedup_key ne doit pas être enqueued (retour false)'
        );

        $this->assertSame(1, $this->countAll(), 'Une seule ligne pour un même dedup_key');
        $this->assertSame('Premier', $this->rowByDedupKey('dedup-same')['subject'], 'Le payload d\'origine est préservé');
    }

    public function testEnqueueDistinctDedupKeysCreateSeparateRows(): void
    {
        $this->assertTrue($this->repo->enqueue($this->message('dedup-a')));
        $this->assertTrue($this->repo->enqueue($this->message('dedup-b')));
        $this->assertSame(2, $this->countAll());
    }

    public function testEnqueueDistinctRecipientsSameKeyAreDeduped(): void
    {
        // La déduplication porte UNIQUEMENT sur dedup_key (l'identité logique
        // de l'événement), pas sur le destinataire.
        $this->assertTrue($this->repo->enqueue($this->message('dedup-c', recipient: 'a@dreets-bfc.gouv.fr')));
        $this->assertFalse($this->repo->enqueue($this->message('dedup-c', recipient: 'b@dreets-bfc.gouv.fr')));
        $this->assertSame(1, $this->countAll());
    }

    // ═══ Sentinelle d'anonymisation — jamais enqueue ═════════════════════════

    public function testEnqueueRefusesAnonymizedSentinelRecipient(): void
    {
        $this->assertFalse(
            $this->repo->enqueue($this->message('sentinel-exact', recipient: AnonymizationPolicy::ANONYMIZED_EMAIL)),
            'La sentinelle d\'anonymisation ne doit jamais être enqueue (retour false)'
        );
        $this->assertSame(0, $this->countAll(), 'Aucune ligne pour la sentinelle');
    }

    public function testEnqueueRefusesAnonymizedSentinelCaseInsensitive(): void
    {
        $this->assertFalse($this->repo->enqueue($this->message('sentinel-upper', recipient: 'ANONYME@ANONYME.INVALID')));
        $this->assertFalse($this->repo->enqueue($this->message('sentinel-mixed', recipient: 'Anonyme@Anonyme.invalid')));
        $this->assertSame(0, $this->countAll(), 'Aucune variante de casse de la sentinelle n\'est persistée');
    }

    public function testEnqueueRejectsBlankDedupKeyHard(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->enqueue($this->message(''));
    }

    // ══ Rollback atomique (participation à la transaction appelante) ════════

    public function testEnqueueParticipatesInCallerTransactionRollback(): void
    {
        $this->pdo->beginTransaction();
        $this->assertTrue($this->repo->enqueue($this->message('tx-rollback')));
        $this->pdo->rollBack();

        $this->assertSame(0, $this->countAll(), 'Le rollback de la transaction appelante annule l\'enqueue (outbox transactionnel)');
    }

    public function testEnqueueParticipatesInCallerTransactionCommit(): void
    {
        $this->pdo->beginTransaction();
        $this->assertTrue($this->repo->enqueue($this->message('tx-commit')));
        $this->pdo->commit();

        $this->assertSame(1, $this->countAll(), 'Le commit de la transaction appelante persiste l\'enqueue');
    }

    // ══ Claim atomique ═════════════════════════════════════════════════════

    public function testClaimBatchTransitionsPendingToProcessing(): void
    {
        $this->repo->enqueue($this->message('claim-1'));
        $this->repo->enqueue($this->message('claim-2'));

        $claimed = $this->repo->claimBatch(10, '2026-09-15 10:00:00');

        $this->assertCount(2, $claimed, 'Les deux messages pending sont réclamés');

        $row = $this->rowByDedupKey('claim-1');
        $this->assertSame(OutboxStatus::Processing->value, $row['status'], 'pending → processing');
        $this->assertSame(1, (int) $row['attempts'], 'attempts incrémenté au claim');
        $this->assertSame('2026-09-15 10:00:00', $row['processing_at']);
    }

    public function testClaimBatchDoesNotDoubleClaim(): void
    {
        $this->repo->enqueue($this->message('nc-1'));
        $this->repo->enqueue($this->message('nc-2'));

        $first = $this->repo->claimBatch(1, '2026-09-15 10:00:00');
        $this->assertCount(1, $first);

        $second = $this->repo->claimBatch(10, '2026-09-15 10:00:01');
        $this->assertCount(1, $second, 'Le message déjà processing n\'est pas réclamé deux fois');
        $this->assertNotSame($first[0]['id'], $second[0]['id'], 'Deux claims successifs renvoient des messages différents');

        $third = $this->repo->claimBatch(10, '2026-09-15 10:00:02');
        $this->assertSame([], $third, 'Plus rien à réclamer');
    }

    public function testClaimBatchRespectsLimit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->repo->enqueue($this->message('lim-' . $i));
        }
        $this->assertCount(2, $this->repo->claimBatch(2, '2026-09-15 10:00:00'));
    }

    public function testClaimBatchZeroLimitReturnsEmpty(): void
    {
        $this->repo->enqueue($this->message('zero-limit'));
        $this->assertSame([], $this->repo->claimBatch(0, '2026-09-15 10:00:00'));
    }

    public function testClaimBatchSkipsRowsScheduledInTheFuture(): void
    {
        $this->repo->enqueue($this->message('future'));
        $this->pdo->exec("UPDATE email_outbox SET next_attempt_at = '2026-09-15 11:00:00' WHERE dedup_key = 'future'");

        $this->assertSame([], $this->repo->claimBatch(10, '2026-09-15 10:00:00'), 'next_attempt_at futur = non éligible');

        $claimed = $this->repo->claimBatch(10, '2026-09-15 11:00:00');
        $this->assertCount(1, $claimed, 'À l\'échéance, le message redevient éligible');
    }

    public function testClaimBatchReturnsMessagePayload(): void
    {
        $this->repo->enqueue(new OutboxMessage(
            recipient: 'dest@dreets-bfc.gouv.fr',
            subject: 'Objet',
            body: 'Corps',
            dedupKey: 'payload',
            headers: 'X-Test: 1',
        ));

        $claimed = $this->repo->claimBatch(1, '2026-09-15 10:00:00');

        $this->assertCount(1, $claimed);
        $this->assertSame('dest@dreets-bfc.gouv.fr', $claimed[0]['recipient']);
        $this->assertSame('Objet', $claimed[0]['subject']);
        $this->assertSame('Corps', $claimed[0]['body']);
        $this->assertSame('X-Test: 1', $claimed[0]['headers']);
        $this->assertSame(1, $claimed[0]['attempts']);
    }

    // ═══ Transitions processing → sent / failed ══════════════════════════════

    public function testMarkSentTransitionsProcessingToSent(): void
    {
        $this->repo->enqueue($this->message('sent-1'));
        $claimed = $this->repo->claimBatch(1, '2026-09-15 10:00:00');

        $this->assertTrue($this->repo->markSent($claimed[0]['id'], '2026-09-15 10:00:05'));

        $row = $this->rowByDedupKey('sent-1');
        $this->assertSame(OutboxStatus::Sent->value, $row['status']);
        $this->assertSame('2026-09-15 10:00:05', $row['sent_at']);
    }

    public function testMarkSentRefusesNonProcessingRow(): void
    {
        $this->repo->enqueue($this->message('sent-2'));
        $id = (int) $this->rowByDedupKey('sent-2')['id'];

        $this->assertFalse($this->repo->markSent($id, '2026-09-15 10:00:05'), 'Une ligne pending ne peut pas passer directement à sent');
        $this->assertSame(OutboxStatus::Pending->value, $this->rowByDedupKey('sent-2')['status']);
    }

    public function testMarkFailedTransitionsProcessingToFailed(): void
    {
        $this->repo->enqueue($this->message('failed-1'));
        $claimed = $this->repo->claimBatch(1, '2026-09-15 10:00:00');

        $this->assertTrue($this->repo->markFailed($claimed[0]['id'], 'SMTP timeout', '2026-09-15 10:00:07'));

        $row = $this->rowByDedupKey('failed-1');
        $this->assertSame(OutboxStatus::Failed->value, $row['status']);
        $this->assertSame('SMTP timeout', $row['last_error']);
        $this->assertSame('2026-09-15 10:00:07', $row['failed_at']);
    }

    public function testMarkFailedRefusesNonProcessingRow(): void
    {
        $this->repo->enqueue($this->message('failed-2'));
        $id = (int) $this->rowByDedupKey('failed-2')['id'];

        $this->assertFalse($this->repo->markFailed($id, 'erreur'), 'Une ligne pending ne peut pas passer directement à failed');
    }

    // ═══ Backoff ══════════════════════════════════════════════════════════════

    public function testBackoffSecondsGrowsExponentiallyAndIsCapped(): void
    {
        $this->assertSame(60, $this->repo->backoffSeconds(1));
        $this->assertSame(120, $this->repo->backoffSeconds(2));
        $this->assertSame(240, $this->repo->backoffSeconds(3));
        $this->assertSame(3600, $this->repo->backoffSeconds(7), 'Le backoff est borné (cap)');
        $this->assertSame(3600, $this->repo->backoffSeconds(50));
    }

    public function testBackoffSecondsClampsNonPositiveAttempts(): void
    {
        // attempts ne peut pas être < 1 après un claim, mais la fonction reste
        // totale : 0 ou négatif retombe sur le premier palier (pas de 2**-1).
        $this->assertSame(60, $this->repo->backoffSeconds(0));
        $this->assertSame(60, $this->repo->backoffSeconds(-3));
    }

    public function testScheduleRetryReturnsToPendingWithBackoff(): void
    {
        $this->repo->enqueue($this->message('retry-1'));
        $claimed = $this->repo->claimBatch(1, '2026-09-15 10:00:00');
        $this->assertSame(1, $claimed[0]['attempts']);

        $this->assertTrue($this->repo->scheduleRetry($claimed[0]['id'], 'Erreur temporaire', '2026-09-15 10:00:00'));

        $row = $this->rowByDedupKey('retry-1');
        $this->assertSame(OutboxStatus::Pending->value, $row['status'], 'processing → pending (retry)');
        $this->assertSame('Erreur temporaire', $row['last_error']);
        // attempts=1 → backoff 60 s.
        $this->assertSame('2026-09-15 10:01:00', $row['next_attempt_at'], 'next_attempt_at = now + backoff(attempts)');
    }

    public function testScheduleRetryBackoffDefersReclaimUntilDue(): void
    {
        $this->repo->enqueue($this->message('retry-2'));
        $claimed = $this->repo->claimBatch(1, '2026-09-15 10:00:00');
        $this->repo->scheduleRetry($claimed[0]['id'], 'Erreur', '2026-09-15 10:00:00');

        $this->assertSame([], $this->repo->claimBatch(10, '2026-09-15 10:00:30'), 'Pas de re-claim avant l\'échéance de backoff');
        $this->assertCount(1, $this->repo->claimBatch(10, '2026-09-15 10:01:00'), 'Re-claim à l\'échéance du backoff');
    }

    public function testScheduleRetryRefusesNonProcessingRow(): void
    {
        $this->repo->enqueue($this->message('retry-3'));
        $id = (int) $this->rowByDedupKey('retry-3')['id'];

        $this->assertFalse($this->repo->scheduleRetry($id, 'erreur'), 'Seule une ligne processing peut être reprogrammée');
    }

    // ═══ Schéma (phase 0) ═══════════════════════════════════════════════════

    public function testSchemaDeclaresOutboxContract(): void
    {
        $sql = (string) $this->pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'email_outbox'")->fetchColumn();
        $this->assertNotSame('', $sql, 'La table email_outbox doit exister (schema.sql + migration_tables.php)');
        $this->assertStringContainsString(
            "CHECK (status IN ('pending','processing','sent','failed'))",
            $sql,
            'Le statut est contraint aux 4 états de l\'outbox'
        );
        $this->assertStringContainsString("DEFAULT 'pending'", $sql, 'Le statut par défaut est pending');

        $hasDedupUnique = false;
        foreach ($this->pdo->query('PRAGMA index_list(email_outbox)')->fetchAll() as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1) {
                continue;
            }
            $columns = array_column(
                $this->pdo->query("PRAGMA index_info('" . $index['name'] . "')")->fetchAll(),
                'name'
            );
            if ($columns === ['dedup_key']) {
                $hasDedupUnique = true;
            }
        }
        $this->assertTrue($hasDedupUnique, 'dedup_key doit porter une contrainte UNIQUE (enqueue idempotent)');
    }
}