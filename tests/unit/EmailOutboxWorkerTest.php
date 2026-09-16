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
}