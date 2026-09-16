<?php
/**
 * CronService — Drain de l'outbox SMTP via lazy cron (tâche mail_drain)
 *
 * TDD (intégration lazy cron du worker EmailOutboxWorker) : tests écrits AVANT
 * le wiring — ils échouent tant que runLazyCron() n'invoque pas le worker.
 *
 * Contrat verrouillé ici :
 *   - le container DI câble CronService → EmailOutboxWorker (bootstrap_services) ;
 *   - runLazyCron() draine les messages pending de l'outbox → sent ;
 *   - un échec transport programme un retry (claim/backoff portés par le worker) ;
 *   - le lot drainé est borné (batchSize du worker, pas de drain illimité) ;
 *   - l'intervalle 300 s empêche un second drain immédiat (verrou lazy cron).
 *
 * Le transport est neutralisé via le seam injectable setMailerSeam() — aucun
 * socket SMTP n'est ouvert.
 */

use App\DTO\OutboxMessage;
use App\Enum\OutboxStatus;
use App\Repository\EmailOutboxRepository;
use App\Services\CronService;
use App\Services\EmailOutboxWorker;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class CronServiceOutboxDrainTest extends TestCase
{
    private const MAIL_DRAIN_LOCK_KEY = 'last_lazy_cron_mail_drain';

    private PDO $pdo;
    private EmailOutboxRepository $outbox;
    private CronService $cron;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->deleteMailDrainLock();

        // Neutralise les autres tâches lazy cron : aucune donnée à anonymiser,
        // aucune alerte délai à envoyer (les deux lectures par défaut du schéma).
        getConfigService()->set('app_alert_delay_days', '0');
        getConfigService()->set('app_retention_years', '0');

        $this->outbox = new EmailOutboxRepository($this->pdo);
        // Résolution via le container : c'est le câblage de production
        // (bootstrap_services.php) qui est testé, pas une construction manuelle.
        $this->cron = getContainer()->get(CronService::class);
        setMailerSeam(null);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->deleteMailDrainLock();
        setMailerSeam(null);
    }

    private function deleteMailDrainLock(): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM config_app WHERE cle = :cle');
        $stmt->execute([':cle' => self::MAIL_DRAIN_LOCK_KEY]);
    }

    private function enqueue(string $dedupKey): void
    {
        $this->outbox->enqueue(new OutboxMessage(
            recipient: 'agent@dreets-bfc.gouv.fr',
            subject: 'Sujet',
            body: '<p>Corps</p>',
            dedupKey: $dedupKey,
        ));
    }

    private function statusOf(string $dedupKey): string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM email_outbox WHERE dedup_key = :k');
        $stmt->execute([':k' => $dedupKey]);
        return (string) $stmt->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function rowByKey(string $dedupKey): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_outbox WHERE dedup_key = :k');
        $stmt->execute([':k' => $dedupKey]);
        /** @var array<string, mixed> $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row;
    }

    private function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM email_outbox WHERE status = :s');
        $stmt->execute([':s' => $status]);
        return (int) $stmt->fetchColumn();
    }

    // ═══ Wiring + exécution via le container ════════════════════════════════

    public function testContainerWiresMailDrainAndDrainsPendingMessage(): void
    {
        $this->enqueue('cron-drain-1');

        $sentTo = [];
        setMailerSeam(function (string $to, string $subject, string $body, string $from = '') use (&$sentTo): bool {
            $sentTo[] = $to;
            return true;
        });

        $this->cron->runLazyCron();

        $this->assertSame(
            ['agent@dreets-bfc.gouv.fr'],
            $sentTo,
            'runLazyCron() doit drainer l\'outbox via EmailOutboxWorker (wiring du container)'
        );
        $this->assertSame(OutboxStatus::Sent->value, $this->statusOf('cron-drain-1'), 'pending → sent');
    }

    // ═══ Échec transport → retry porté par le worker ═══════════════════════

    public function testLazyCronSchedulesRetryOnTransportFailure(): void
    {
        $this->enqueue('cron-retry-1');
        setMailerSeam(fn(string $to, string $subject, string $body, string $from = ''): bool => false);

        $this->cron->runLazyCron();

        $row = $this->rowByKey('cron-retry-1');
        $this->assertSame(OutboxStatus::Pending->value, $row['status'], 'processing → pending (retry)');
        $this->assertSame(1, (int) $row['attempts'], 'Le claim du worker incrémente attempts');
        $this->assertNotNull($row['next_attempt_at'], 'Le retry est différé (backoff borné)');
        $this->assertNotSame('', (string) $row['last_error'], 'L\'échec est tracé (last_error)');
    }

    // ═══ Lot borné ══════════════════════════════════════════════════════════

    public function testLazyCronDrainIsBoundedByWorkerBatchSize(): void
    {
        $overBatch = EmailOutboxWorker::DEFAULT_BATCH_SIZE + 1;
        for ($i = 0; $i < $overBatch; $i++) {
            $this->enqueue('cron-batch-' . $i);
        }
        setMailerSeam(fn(string $to, string $subject, string $body, string $from = ''): bool => true);

        $this->cron->runLazyCron();

        $this->assertSame(
            EmailOutboxWorker::DEFAULT_BATCH_SIZE,
            $this->countByStatus(OutboxStatus::Sent->value),
            'Un run ne draine qu\'un lot borné (batchSize du worker)'
        );
        $this->assertSame(
            1,
            $this->countByStatus(OutboxStatus::Pending->value),
            'Le surplus reste pending pour le prochain run'
        );
    }

    // ═══ Intervalle 300 s (verrou lazy cron) ════════════════════════════════

    public function testMailDrainIntervalPreventsImmediateSecondRun(): void
    {
        setMailerSeam(fn(string $to, string $subject, string $body, string $from = ''): bool => true);

        $this->enqueue('cron-int-1');
        $this->cron->runLazyCron();
        $this->assertSame(OutboxStatus::Sent->value, $this->statusOf('cron-int-1'), 'Premier drain effectué');

        $this->enqueue('cron-int-2');
        $this->cron->runLazyCron(); // dans la fenêtre de 300 s → verrou non re-claimable

        $this->assertSame(
            OutboxStatus::Pending->value,
            $this->statusOf('cron-int-2'),
            'Le verrou 300 s empêche un second drain immédiat (pas de double exécution rapprochée)'
        );
    }
}