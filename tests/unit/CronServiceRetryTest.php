<?php
/**
 * CronService — Reprise des tâches lazy-cron après échec (contrat du verrou)
 *
 * Contrat testé :
 * - Un échec du callback LIBÈRE le verrou → la prochaine connexion retente
 *   la tâche sans attendre la fin de la fenêtre (24h/7j).
 * - Un succès CONSERVE le verrou → intervalle entre exécutions inchangé
 *   (politique métier, Audit #41).
 * - Pendant l'exécution du callback, le verrou refuse tout claim concurrent
 *   (pas de double exécution).
 * - L'échec d'une tâche n'empêche pas l'exécution des autres tâches
 *   (isolation du dispatcher).
 */

use PHPUnit\Framework\TestCase;
use App\Services\CronService;
use App\Repository\ConfigRepository;
use App\Repository\ReportRepository;
use App\Repository\AuditRepository;
use App\Repository\SessionRepository;

class CronServiceRetryTest extends TestCase
{
    private PDO $pdo;
    private ConfigRepository $configRepo;
    private CronService $service;

    private const TASK_A = 'testtask';
    private const TASK_B = 'testtask2';
    private const KEYS = ['last_lazy_cron_testtask', 'last_lazy_cron_testtask2'];

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->deleteTestKeys();
        $this->configRepo = new ConfigRepository($this->pdo);
        $this->service = new CronService(
            $this->pdo,
            $this->configRepo,
            new ReportRepository($this->pdo),
            new AuditRepository($this->pdo),
            new SessionRepository($this->pdo),
        );
    }

    protected function tearDown(): void
    {
        $this->deleteTestKeys();
    }

    private function deleteTestKeys(): void
    {
        $placeholders = implode(', ', array_fill(0, count(self::KEYS), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM config_app WHERE cle IN ({$placeholders})");
        $stmt->execute(self::KEYS);
    }

    /**
     * Invoque runLazyCronTask (privé) avec un callback espion.
     */
    private function runTask(string $taskName, callable $callback, int $interval = 3600): void
    {
        $method = new ReflectionMethod($this->service, 'runLazyCronTask');
        $method->invoke($this->service, $taskName, $interval, $callback);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Reprise après échec (le bug : verrou jamais libéré)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFailedTaskIsRetriedOnNextLoginWithinWindow(): void
    {
        $calls = 0;
        $fail = function () use (&$calls): void {
            $calls++;
            throw new RuntimeException('boom');
        };
        $ok = function () use (&$calls): void {
            $calls++;
        };

        // 1re tentative (login n) : échoue
        $this->runTask(self::TASK_A, $fail);

        // 2e tentative (login n+1, dans la fenêtre) : doit être permise
        $this->runTask(self::TASK_A, $ok);

        $this->assertSame(
            2,
            $calls,
            "Un échec doit libérer le verrou pour permettre une nouvelle tentative dans la fenêtre"
        );
    }

    public function testFailureThenSuccessResetsIntervalFromNewRun(): void
    {
        $calls = 0;
        $fail = function () use (&$calls): void {
            $calls++;
            throw new RuntimeException('boom');
        };
        $ok = function () use (&$calls): void {
            $calls++;
        };

        $this->runTask(self::TASK_A, $fail);              // échec
        $this->runTask(self::TASK_A, $ok);                // reprise : succès (verrou posé à T)
        $this->runTask(self::TASK_A, $ok, 3600);          // fenêtre non écoulée : refusé

        $this->assertSame(2, $calls, 'Le nouvel intervalle part de la tentative réussie');
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Succès : politique inchangée (intervalle 24h/7j)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSuccessfulTaskIsNotRetriedWithinInterval(): void
    {
        $calls = 0;
        $ok = function () use (&$calls): void {
            $calls++;
        };

        $this->runTask(self::TASK_A, $ok);
        $this->runTask(self::TASK_A, $ok); // fenêtre non écoulée

        $this->assertSame(1, $calls, 'Un succès conserve le verrou (intervalle inchangé)');
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Isolation du dispatcher : un échec n'impacte pas les autres tâches
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFailureOfOneTaskDoesNotBlockIndependentTask(): void
    {
        $bCalls = 0;
        $fail = function (): void {
            throw new RuntimeException('boom-a');
        };
        $ok = function () use (&$bCalls): void {
            $bCalls++;
        };

        $this->runTask(self::TASK_A, $fail);
        $this->runTask(self::TASK_B, $ok);

        $this->assertSame(1, $bCalls, "L'échec d'une tâche ne doit pas bloquer les autres");
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Contrat du verrou au niveau ConfigRepository
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testClaimThenReleaseAllowsImmediateReclaim(): void
    {
        $key = 'last_lazy_cron_testtask';

        $this->assertTrue($this->configRepo->claimLazyCronLock($key, 3600), 'Premier claim acquis');
        $this->assertFalse(
            $this->configRepo->claimLazyCronLock($key, 3600),
            'Claim concurrent refusé pendant la tenue du verrou (pas de double exécution)'
        );

        $this->configRepo->releaseLazyCronLock($key);

        $this->assertTrue(
            $this->configRepo->claimLazyCronLock($key, 3600),
            'Après libération (échec), le re-claim est immédiatement possible'
        );
    }

    public function testReleaseResetsKeyToEmptyValue(): void
    {
        $key = 'last_lazy_cron_testtask';
        $this->configRepo->claimLazyCronLock($key, 3600);

        $this->configRepo->releaseLazyCronLock($key);

        $stmt = $this->pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
        $stmt->execute([':cle' => $key]);
        $this->assertSame('', (string) $stmt->fetchColumn(), 'La clé revient à l\'état pré-seed (jamais exécuté)');
    }

    public function testReleaseOnMissingKeyIsSafe(): void
    {
        $key = 'last_lazy_cron_testtask';

        $this->configRepo->releaseLazyCronLock($key); // clé absente : upsert, aucune erreur

        $this->assertTrue(
            $this->configRepo->claimLazyCronLock($key, 3600),
            'Après libération sur clé absente, le claim reste possible'
        );
    }
}
