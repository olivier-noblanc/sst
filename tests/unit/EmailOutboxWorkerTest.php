<?php

/**
 * EmailOutboxWorker Tests — Application SST DREETS BFC
 *
 * TDD (phase worker outbox SMTP, après la fondation fix-24) : tests écrits
 * AVANT l'implémentation.
 *
 * Contrat verrouillé ici :
 *   - drain d'un lot borné de messages pending → SMTP → sent ;
 *   - le transport SMTP s'exécute HORS transaction (claimBatch a committé) ;
 *   - un échec transport programme un retry (pending + backoff) sans perte ;
 *   - au-delà de max_attempts, l'échec devient définitif (failed, conservé) ;
 *   - une ligne processing orpheline (worker crashé) est récupérable ;
 *   - une ligne processing fraîche n'est PAS re-envoyée (pas de double envoi) ;
 *   - la déduplication par dedup_key ne produit qu'un seul envoi ;
 *   - rien n'est jamais supprimé.
 *
 * Le transport est neutralisé via le seam injectable setMailerSeam() — aucun
 * socket SMTP n'est ouvert.
 */

use App\Enum\OutboxStatus;
use App\Repository\EmailOutboxRepository;
use App\Services\EmailOutboxWorker;
use App\DTO\OutboxMessage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class EmailOutboxWorkerTest extends TestCase
{
    private PDO $pdo;
    private EmailOutboxRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->repo = new EmailOutboxRepository($this->pdo);
        setMailerSeam(null);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM email_outbox');
        setMailerSeam(null);
    }

    private function worker(
        int $batchSize = 20,
        int $maxAttempts = 5,
        int $staleAfterSeconds = 900,
    ): EmailOutboxWorker {
        return new EmailOutboxWorker($this->repo, $batchSize, $maxAttempts, $staleAfterSeconds);
    }

    private function message(string $dedupKey, string $recipient = 'agent@dreets-bfc.gouv.fr'): OutboxMessage
    {
        return new OutboxMessage(
            recipient: $recipient,
            subject: 'Sujet',
            body: '<p>Corps</p>',
            dedupKey: $dedupKey,
        );
    }

    /** @return array<string, mixed> */
    private function rowByDedupKey(string $dedupKey): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_outbox WHERE dedup_key = :k');
        $stmt->execute([':k' => $dedupKey]);
        /** @var array<string, mixed> $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row;
    }

    private function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn();
    }

    // ═══ Drain d'un lot pending → sent ═══════════════════════════════════════

    public function testRunDrainsPendingAndMarksSent(): void
    {
        $this->assertTrue($this->repo->enqueue($this->message('w-sent-1')));
        $this->assertTrue($this->repo->enqueue($this->message('w-sent-2', 'autre@dreets-bfc.gouv.fr')));

        $sentTo = [];
        setMailerSeam(function (string $to, string $subject, string $body, string $from = '') use (&$sentTo): bool {
            $sentTo[] = $to;
            return true;
        });

        $stats = $this->worker()->run('2026-09-15 10:00:00');

        $this->assertSame(2, $stats['sent']);
        $this->assertSame(0, $stats['retried']);
        $this->assertSame(0, $stats['failed']);
        $this->assertSame(0, $stats['recovered']);
        $this->assertSame(
            ['agent@dreets-bfc.gouv.fr', 'autre@dreets-bfc.gouv.fr'],
            $sentTo,
            'Chaque destinataire est transmis au seam transport'
        );

        foreach (['w-sent-1', 'w-sent-2'] as $key) {
            $row = $this->rowByDedupKey($key);
            $this->assertSame(OutboxStatus::Sent->value, $row['status'], 'pending → sent');
            $this->assertSame('2026-09-15 10:00:00', $row['sent_at']);
        }
    }

    public function testRunSendsOutsideAnyTransaction(): void
    {
        $this->assertTrue($this->repo->enqueue($this->message('w-notx')));

        $inTransaction = null;
        setMailerSeam(function (string $to, string $subject, string $body, string $from = '') use (&$inTransaction): bool {
            $inTransaction = $this->pdo->inTransaction();
            return true;
        });

        $this->worker()->run('2026-09-15 10:00:00');

        $this->assertFalse(
            $inTransaction,
            'Le transport SMTP doit s\'exécuter hors transaction (claimBatch committé avant l\'envoi)'
        );
    }

    public function testRunRespectsBoundedBatch(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->repo->enqueue($this->message('w-batch-' . $i));
        }
        setMailerSeam(fn(string $to, string $subject, string $body, string $from = ''): bool => true);

        $stats = $this->worker(batchSize: 2)->run('2026-09-15 10:00:00');

        $this->assertSame(2, $stats['sent'], 'Le batch est borné par batchSize');
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM email_outbox WHERE status = :s');
        $stmt->execute([':s' => OutboxStatus::Pending->value]);
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'Le reste demeure pending pour le prochain run');
    }

    // ═══ Échec transport → retry/backoff puis échec définitif ═══════════════

    public function testRunSchedulesRetryWithBackoffOnTransportFailure(): void
    {
        $this->repo->enqueue($this->message('w-retry'));
        setMailerSeam(fn(string $to, string $subject, string $body, string $from = ''): bool => false);

        $stats = $this->worker(maxAttempts: 5)->run('2026-09-15 10:00:00');

        $this->assertSame(1, $stats['retried']);
        $this->assertSame(0, $stats['failed']);

        $row = $this->rowByDedupKey('w-retry');
        $this->assertSame(OutboxStatus::Pending->value, $row['status'], 'processing → pending (retry)');
        $this->assertSame(1, (int) $row['attempts']);
        // attempts=1 → backoff 60 s (barème EmailOutboxRepository::backoffSeconds).
        $this->assertSame('2026-09-15 10:01:00', $row['next_attempt_at']);
        $this->assertNotSame('', (string) $row['last_error'], 'L\'échec est tracé (last_error)');
        $this->assertStringContainsString(
            'agent@dreets-bfc.gouv.fr',
            (string) $row['last_error'],
            'La trace nomme le destinataire concerné (sans perte d\'information)'
        );
    }

    public function testRunMarksFailedAfterMaxAttempts(): void
    {
        $this->repo->enqueue($this->message('w-max'));
        // claimBatch incrémente attempts : on pré-positionne à maxAttempts - 1
        // pour que le claim porte le compteur à maxAttempts.
        $this->pdo->exec("UPDATE email_outbox SET attempts = 4 WHERE dedup_key = 'w-max'");
        setMailerSeam(fn(string $to, string $subject, string $body, string $from = ''): bool => false);

        $stats = $this->worker(maxAttempts: 5)->run('2026-09-15 10:00:00');

        $this->assertSame(0, $stats['retried']);
        $this->assertSame(1, $stats['failed']);

        $row = $this->rowByDedupKey('w-max');
        $this->assertSame(OutboxStatus::Failed->value, $row['status'], 'processing → failed (définitif)');
        $this->assertSame(5, (int) $row['attempts']);
        $this->assertSame('2026-09-15 10:00:00', $row['failed_at']);
        $this->assertNotSame('', (string) $row['last_error']);
    }

    public function testFailedRowsAreKeptAndNeverReclaimed(): void
    {
        $this->repo->enqueue($this->message('w-keep'));
        $this->pdo->exec("UPDATE email_outbox SET attempts = 5 WHERE dedup_key = 'w-keep'");
        setMailerSeam(fn(string $to, string $subject, string $body, string $from = ''): bool => false);

        $this->worker(maxAttempts: 5)->run('2026-09-15 10:00:00');
        $second = $this->worker(maxAttempts: 5)->run('2026-09-15 10:05:00');

        $this->assertSame(0, $second['sent']);
        $this->assertSame(0, $second['retried']);
        $this->assertSame(0, $second['failed']);
        $this->assertSame(1, $this->countAll(), 'Aucune ligne n\'est supprimée (failed conservé)');
        $this->assertSame(OutboxStatus::Failed->value, $this->rowByDedupKey('w-keep')['status']);
    }

    // ══ Récupération d'un processing orphelin (worker crashé) ══════════════

    public function testRunRecoversOrphanedProcessingRows(): void
    {
        $this->repo->enqueue($this->message('w-orphan'));
        // Simule un worker interrompu entre claim et clôture : processing
        // depuis 20 minutes, sans état terminal.
        $this->pdo->exec(
            "UPDATE email_outbox SET status = 'processing', attempts = 1, "
            . "processing_at = '2026-09-15 09:40:00' WHERE dedup_key = 'w-orphan'"
        );
        setMailerSeam(fn(string $to, string $subject, string $body, string $from = ''): bool => true);

        // processing_at (09:40) antérieur au cutoff now - staleAfter
        // (10:00 - 900 s = 09:45) → le claim est orphelin, donc récupérable.
        $stats = $this->worker(staleAfterSeconds: 900)->run('2026-09-15 10:00:00');

        $this->assertSame(1, $stats['recovered'], 'Le claim orphelin est récupéré');
        $this->assertSame(1, $stats['sent'], 'La ligne récupérée est drainée dans le même run');
        $this->assertSame(OutboxStatus::Sent->value, $this->rowByDedupKey('w-orphan')['status']);
    }

    public function testRunLeavesFreshProcessingRowsUntouched(): void
    {
        $this->repo->enqueue($this->message('w-inflight'));
        // processing depuis seulement 30 s (< staleAfter) : un worker est
        // peut-être encore en train de l'envoyer → ne pas le doubler.
        $this->pdo->exec(
            "UPDATE email_outbox SET status = 'processing', attempts = 1, "
            . "processing_at = '2026-09-15 09:59:30' WHERE dedup_key = 'w-inflight'"
        );

        $called = false;
        setMailerSeam(function (string $to, string $subject, string $body, string $from = '') use (&$called): bool {
            $called = true;
            return true;
        });

        $stats = $this->worker(staleAfterSeconds: 900)->run('2026-09-15 10:00:00');

        $this->assertSame(0, $stats['recovered']);
        $this->assertSame(0, $stats['sent']);
        $this->assertFalse($called, 'Une ligne processing fraîche ne doit jamais être re-envoyée (double envoi)');
        $this->assertSame(OutboxStatus::Processing->value, $this->rowByDedupKey('w-inflight')['status']);
    }

    // ══ Déduplication ═══════════════════════════════════════════════════════

    public function testRunDoesNotSendDuplicateDedupKeys(): void
    {
        $this->assertTrue($this->repo->enqueue($this->message('w-dup')));
        $this->assertFalse($this->repo->enqueue($this->message('w-dup')), 'Le doublon logique est ignoré à l\'enqueue');

        $sends = 0;
        setMailerSeam(function (string $to, string $subject, string $body, string $from = '') use (&$sends): bool {
            $sends++;
            return true;
        });

        $stats = $this->worker()->run('2026-09-15 10:00:00');

        $this->assertSame(1, $stats['sent']);
        $this->assertSame(1, $sends, 'Un dedup_key déjà en file ne produit qu\'un seul envoi');
    }

    // ══ Budget de temps du run (R1) — le drain ne bloque pas la requête ═════

    /**
     * Construit un worker dont l'horloge est simulée, pour piloter le budget.
     *
     * @param \Closure(): float $clock
     */
    private function workerWithClock(
        \Closure $clock,
        int $drainBudgetSeconds,
        int $batchSize = 20,
    ): EmailOutboxWorker {
        return new EmailOutboxWorker(
            outbox: $this->repo,
            batchSize: $batchSize,
            maxAttempts: 5,
            staleAfterSeconds: 900,
            drainBudgetSeconds: $drainBudgetSeconds,
            clock: $clock,
        );
    }

    /**
     * R1 : un SMTP en trou noir fait payer ~90 s de timeout par message. Sans
     * budget, un lot de 20 bloquerait la requête (login / flush) ~30 min. Le
     * run doit s'arrêter dès le budget épuisé, en laissant les messages non
     * traités en processing (ni échec, ni perte) pour requeueStaleProcessing().
     */
    public function testRunStopsAtTimeBudgetAndDefersUnprocessedMessagesWithoutFailingThem(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->repo->enqueue($this->message('w-budget-' . $i));
        }

        // Transport en trou noir : chaque tentative « consomme » 90 s simulées,
        // comme un SMTP qui accepte la connexion puis ne répond jamais.
        $simulatedSeconds = 0.0;
        $attempts = 0;
        setMailerSeam(function (string $to, string $subject, string $body, string $from = '') use (&$simulatedSeconds, &$attempts): bool {
            $attempts++;
            $simulatedSeconds += 90.0;
            return false;
        });

        $stats = $this->workerWithClock(
            clock: function () use (&$simulatedSeconds): float {
                return $simulatedSeconds;
            },
            drainBudgetSeconds: 5,
        )->run('2026-09-15 10:00:00');

        $this->assertSame(1, $attempts, 'Le budget coupe après la première tentative (~90 s), pas après les 20');
        $this->assertSame(0, $stats['sent']);
        $this->assertSame(1, $stats['retried'], 'Le message tenté sous le budget est traité normalement');
        $this->assertSame(0, $stats['failed'], 'Aucun message non traité n\'est marqué en échec');
        $this->assertSame(19, $stats['deferred'], 'Les 19 messages non traités sont différés');

        // BUG-2 — les messages NON tentés sont RELÂCHÉS (processing → pending,
        // attempts décrémenté) : le claim ne doit pas laisser croire qu'ils ont
        // été tentés. Ils restent éligibles au prochain run sans attendre
        // l'orphelinage, et aucun n'est passé en échec.
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM email_outbox WHERE status = :s AND attempts = 0');
        $stmt->execute([':s' => OutboxStatus::Pending->value]);
        $this->assertSame(
            19,
            (int) $stmt->fetchColumn(),
            'Les messages différés sont relâchés en pending, attempts NON consommé'
        );

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM email_outbox WHERE status = :s');
        $stmt->execute([':s' => OutboxStatus::Processing->value]);
        $this->assertSame(0, (int) $stmt->fetchColumn(), 'Aucun message différé ne reste coincé en processing');

        $tried = $this->rowByDedupKey('w-budget-0');
        $this->assertSame(1, (int) $tried['attempts'], 'Seul le message réellement tenté consomme un attempt');
        $this->assertSame(20, $this->countAll(), 'Aucune ligne n\'est perdue');
    }

    /**
     * Les messages différés par le budget sont relâchés en pending (attempts
     * non consommé) : le run suivant les réclame DIRECTEMENT, sans attendre le
     * délai d'orphelinage. Rien ne reste coincé.
     */
    public function testBudgetDeferredMessagesAreReleasedAndDrainedOnNextRun(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->repo->enqueue($this->message('w-defer-' . $i));
        }

        $simulatedSeconds = 0.0;
        setMailerSeam(function (string $to, string $subject, string $body, string $from = '') use (&$simulatedSeconds): bool {
            $simulatedSeconds += 90.0;
            return false;
        });

        $first = $this->workerWithClock(
            clock: function () use (&$simulatedSeconds): float {
                return $simulatedSeconds;
            },
            drainBudgetSeconds: 5,
        )->run('2026-09-15 10:00:00');

        $this->assertSame(0, $first['recovered']);
        $this->assertSame(1, $first['retried']);
        $this->assertSame(2, $first['deferred']);

        // Les 2 différés sont pending attempts=0 : réclamables dès maintenant.
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM email_outbox WHERE status = :s AND attempts = 0");
        $stmt->execute([':s' => OutboxStatus::Pending->value]);
        $this->assertSame(2, (int) $stmt->fetchColumn());

        // Run suivant : plus aucun processing orphelin (recovered = 0), le
        // retry du 1er message (backoff 60 s échu à 10:20) et les 2 différés
        // relâchés sont réclamés puis envoyés.
        setMailerSeam(fn(string $to, string $subject, string $body, string $from = ''): bool => true);

        $second = $this->worker()->run('2026-09-15 10:20:00');

        $this->assertSame(0, $second['recovered'], 'Les différés ont été relâchés : rien à orpheliner');
        $this->assertSame(3, $second['sent'], 'Les 3 messages finissent envoyés — aucun n\'est perdu');

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM email_outbox WHERE status = :s');
        $stmt->execute([':s' => OutboxStatus::Sent->value]);
        $this->assertSame(3, (int) $stmt->fetchColumn());
    }

    /**
     * Borne basse : budget nul → aucune tentative de transport, tout le lot
     * est différé (le run ne « coûte » rien à la requête hôte).
     */
    public function testZeroBudgetDefersWholeBatchWithoutAnyTransportAttempt(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->repo->enqueue($this->message('w-nobudget-' . $i));
        }

        $attempts = 0;
        setMailerSeam(function (string $to, string $subject, string $body, string $from = '') use (&$attempts): bool {
            $attempts++;
            return true;
        });

        $stats = $this->workerWithClock(
            clock: static fn(): float => 0.0,
            drainBudgetSeconds: 0,
        )->run('2026-09-15 10:00:00');

        $this->assertSame(0, $attempts, 'Budget nul : aucune tentative de transport (arrêt immédiat)');
        $this->assertSame(0, $stats['sent']);
        $this->assertSame(3, $stats['deferred']);

        // BUG-2 — budget nul ⇒ AUCUN attempt consommé : le claim est intégralement relâché.
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM email_outbox WHERE status = 'pending' AND attempts = 0 AND processing_at IS NULL");
        $this->assertSame(
            3,
            (int) $stmt->fetchColumn(),
            'Budget nul : tout le lot est pending, attempts=0, processing_at effacé'
        );
    }

    /**
     * BUG-2 — un defer répété (budget court à chaque run) ne doit pas gonfler
     * `attempts` : sinon un échec réel ultérieur atteindrait max_attempts et
     * marquerait `failed` un message qui n'a jamais été tenté.
     */
    public function testRepeatedDeferralThenRealFailureDoesNotFailPrematurely(): void
    {
        $this->repo->enqueue($this->message('w-defer-real'));

        // 6 runs à budget nul : le message est différé 6 fois sans être tenté.
        for ($i = 0; $i < 6; $i++) {
            $stats = $this->workerWithClock(
                clock: static fn(): float => 0.0,
                drainBudgetSeconds: 0,
            )->run(sprintf('2026-09-15 10:0%d:00', $i));

            $this->assertSame(1, $stats['deferred']);
            $this->assertSame(0, $stats['failed']);
        }

        $deferred = $this->rowByDedupKey('w-defer-real');
        $this->assertSame(0, (int) $deferred['attempts'], 'Aucun attempt consommé par les defers');
        $this->assertSame(OutboxStatus::Pending->value, $deferred['status']);

        // Échec RÉEL : 1re tentative réelle (attempts=1 < maxAttempts=5) → retry, jamais failed.
        setMailerSeam(fn(string $to, string $subject, string $body, string $from = ''): bool => false);
        $real = $this->worker(maxAttempts: 5)->run('2026-09-15 10:10:00');

        $this->assertSame(0, $real['failed'], 'Un defer répété ne doit pas rendre failed prématurément');
        $this->assertSame(1, $real['retried']);

        $after = $this->rowByDedupKey('w-defer-real');
        $this->assertSame(OutboxStatus::Pending->value, $after['status']);
        $this->assertSame(1, (int) $after['attempts'], 'Une seule tentative réelle a été consommée');
    }

    // ══ RISK-4 : verdicts de clôture consommés (compteurs cohérents + trace) ══

    /**
     * @return array{stats: array<string, int>, logged: string}
     */
    private function runCapturingWorkerLog(callable $configure): array
    {
        $logFile = tempnam(sys_get_temp_dir(), 'sst_worker_log_');
        self::assertIsString($logFile, 'tempnam() must return a path');
        $previousLog = ini_get('error_log');
        ini_set('error_log', $logFile);

        try {
            $configure();
            $stats = $this->worker(maxAttempts: 5)->run('2026-09-15 10:00:00');
            /** @var array{recovered:int, sent:int, retried:int, failed:int, deferred:int} $stats */
            $logged = (string) file_get_contents($logFile);
        } finally {
            ini_set('error_log', $previousLog === false ? '' : $previousLog);
            @unlink($logFile);
        }

        return ['stats' => $stats, 'logged' => $logged];
    }

    public function testRunDoesNotCountSentWhenCloseIsRefusedAndLogsTrace(): void
    {
        $this->repo->enqueue($this->message('w-noop-sent'));

        $outcome = $this->runCapturingWorkerLog(function (): void {
            setMailerSeam(function (string $to, string $subject, string $body, string $from = ''): bool {
                // Un autre worker a clos la ligne avant notre markSent.
                $this->pdo->exec("UPDATE email_outbox SET status = 'sent' WHERE dedup_key = 'w-noop-sent'");
                return true;
            });
        });

        $this->assertSame(0, $outcome['stats']['sent'], 'markSent refusé (déjà clos) ⇒ compteur non incrémenté');
        $this->assertStringContainsString('markSent sans effet', $outcome['logged'], 'Clôture refusée tracée');
    }

    public function testRunDoesNotCountRetryWhenCloseIsRefusedAndLogsTrace(): void
    {
        $this->repo->enqueue($this->message('w-noop-retry'));

        $outcome = $this->runCapturingWorkerLog(function (): void {
            setMailerSeam(function (string $to, string $subject, string $body, string $from = ''): bool {
                $this->pdo->exec("UPDATE email_outbox SET status = 'failed' WHERE dedup_key = 'w-noop-retry'");
                return false;
            });
        });

        $this->assertSame(0, $outcome['stats']['retried'], 'scheduleRetry refusé ⇒ compteur non incrémenté');
        $this->assertSame(0, $outcome['stats']['failed']);
        $this->assertStringContainsString('scheduleRetry sans effet', $outcome['logged']);
    }

    public function testRunDoesNotCountFailedWhenCloseIsRefusedAndLogsTrace(): void
    {
        $this->repo->enqueue($this->message('w-noop-failed'));
        $this->pdo->exec("UPDATE email_outbox SET attempts = 4 WHERE dedup_key = 'w-noop-failed'");

        $outcome = $this->runCapturingWorkerLog(function (): void {
            setMailerSeam(function (string $to, string $subject, string $body, string $from = ''): bool {
                $this->pdo->exec("UPDATE email_outbox SET status = 'sent' WHERE dedup_key = 'w-noop-failed'");
                return false;
            });
        });

        $this->assertSame(0, $outcome['stats']['failed'], 'markFailed refusé ⇒ compteur non incrémenté');
        $this->assertStringContainsString('markFailed sans effet', $outcome['logged']);
    }

    // ══ releaseUnclaimed : transitions & dedup (BUG-2) ═══════════════════════

    public function testReleaseUnclaimedIsScopedToGivenIdsAndNeverBelowZero(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->repo->enqueue($this->message('w-release-' . $i));
        }

        $claimed = $this->repo->claimBatch(3, '2026-09-15 10:00:00');
        $this->assertCount(3, $claimed);
        $this->assertSame([1, 1, 1], array_column($claimed, 'attempts'));

        $releasedId = $claimed[0]['id'];
        $keptIds = [$claimed[1]['id'], $claimed[2]['id']];

        $this->assertSame(1, $this->repo->releaseUnclaimed([$releasedId], '2026-09-15 10:00:05'));

        $released = $this->rowByDedupKey('w-release-0');
        $this->assertSame(OutboxStatus::Pending->value, $released['status'], 'processing → pending');
        $this->assertSame(0, (int) $released['attempts'], 'attempts décrémenté');
        $this->assertNull($released['processing_at'], 'processing_at effacé');

        // Les ids non fournis restent en processing (jamais touchés).
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM email_outbox WHERE status = :s AND id IN (?, ?)');
        $stmt->execute([OutboxStatus::Processing->value, $keptIds[0], $keptIds[1]]);
        $this->assertSame(2, (int) $stmt->fetchColumn(), 'releaseUnclaimed ne touche que les ids fournis');

        // Idempotence / dedup : relâcher une ligne déjà pending ne fait rien.
        $this->assertSame(0, $this->repo->releaseUnclaimed([$releasedId], '2026-09-15 10:00:06'));
        // Liste vide : no-op.
        $this->assertSame(0, $this->repo->releaseUnclaimed([], '2026-09-15 10:00:06'));
    }

    public function testReleaseUnclaimedDoesNotResurrectAClosedRow(): void
    {
        $this->repo->enqueue($this->message('w-release-closed'));
        $claimed = $this->repo->claimBatch(1, '2026-09-15 10:00:00');
        $id = $claimed[0]['id'];

        // Un autre chemin a déjà clos la ligne (ex. sent) avant le release.
        $this->assertTrue($this->repo->markSent($id, '2026-09-15 10:00:01'));

        $this->assertSame(0, $this->repo->releaseUnclaimed([$id], '2026-09-15 10:00:02'));
        $this->assertSame(OutboxStatus::Sent->value, $this->rowByDedupKey('w-release-closed')['status']);
    }

    public function testReleaseUnclaimedFloorsAttemptsAtZeroOnInconsistentState(): void
    {
        $this->repo->enqueue($this->message('w-release-floor'));
        $this->pdo->exec("UPDATE email_outbox SET status = 'processing', attempts = 0, processing_at = '2026-09-15 10:00:00' WHERE dedup_key = 'w-release-floor'");
        $id = (int) $this->pdo->query("SELECT id FROM email_outbox WHERE dedup_key = 'w-release-floor'")->fetchColumn();

        $this->assertSame(1, $this->repo->releaseUnclaimed([$id], '2026-09-15 10:00:05'));
        $this->assertSame(0, (int) $this->rowByDedupKey('w-release-floor')['attempts'], 'attempts ne passe jamais sous zéro');
    }
}
