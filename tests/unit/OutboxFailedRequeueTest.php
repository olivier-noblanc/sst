<?php

/**
 * OutboxFailedRequeueTest — Application SST DREETS BFC
 *
 * TDD : un message outbox en échec définitif (status = failed) était terminal
 * et donc irrécupérable sans SQL manuel. Contrat verrouillé ici :
 *
 *   - requeueFailed() ramène les lignes failed en pending : attempts remis à 0
 *     (budget de retry complet), next_attempt_at libéré (éligibilité immédiate),
 *     failed_at/processing_at effacés ;
 *   - l'identité logique (dedup_key) et le payload (destinataire/sujet/corps/
 *     headers) sont préservés — aucune perte, aucune suppression ;
 *   - les lignes non-failed (pending/processing/sent) sont intactes ;
 *   - un message requalifié est immédiatement réclamable par claimBatch() ;
 *   - la route POST outbox_retry est protégée par CSRF et réservée au
 *     Superviseur (aucun rôle moins privilégié).
 *
 * Conventions AGENTS.md : SQL uniquement dans Repository, enums (jamais de
 * magic string métier), tryFrom plutôt que from.
 */

use App\DTO\OutboxMessage;
use App\Enum\OutboxStatus;
use App\Enum\UserRole;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Repository\EmailOutboxRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class OutboxFailedRequeueTest extends TestCase
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

    private function enqueue(string $dedupKey, string $recipient = 'agent@dreets-bfc.gouv.fr'): void
    {
        $this->repo->enqueue(new OutboxMessage(
            recipient: $recipient,
            subject: 'Sujet ' . $dedupKey,
            body: '<p>Corps ' . $dedupKey . '</p>',
            dedupKey: $dedupKey,
        ));
    }

    private function failViaRepo(string $dedupKey, string $error = 'SMTP timeout'): void
    {
        $claimed = $this->repo->claimBatch(1, '2026-09-15 10:00:00');
        $this->assertCount(1, $claimed, "Le message $dedupKey doit être réclamable avant l'échec");
        $this->assertSame($dedupKey, $claimed[0]['dedup_key']);
        $this->assertTrue($this->repo->markFailed($claimed[0]['id'], $error, '2026-09-15 10:00:07'));
    }

    /**
     * Insertion brute — pilote explicitement le statut sans dépendre de l'ordre
     * de claim ni de l'horloge.
     */
    private function insertRaw(
        string $dedupKey,
        string $status,
        int $attempts = 0,
        ?string $lastError = null,
        ?string $failedAt = null,
        ?string $nextAttemptAt = null,
        ?string $processingAt = null,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_outbox
                (dedup_key, recipient, subject, body, headers, status, attempts,
                 last_error, failed_at, next_attempt_at, processing_at, created_at, updated_at)
             VALUES
                (:k, :r, :s, :b, :h, :st, :a, :le, :fa, :na, :pa, :ca, :ca)'
        );
        $stmt->execute([
            ':k'  => $dedupKey,
            ':r'  => 'agent@dreets-bfc.gouv.fr',
            ':s'  => 'Sujet ' . $dedupKey,
            ':b'  => 'Corps',
            ':h'  => '',
            ':st' => $status,
            ':a'  => $attempts,
            ':le' => $lastError,
            ':fa' => $failedAt,
            ':na' => $nextAttemptAt,
            ':pa' => $processingAt,
            ':ca' => '2026-01-01 00:00:00',
        ]);
    }

    /** @return array<string, mixed> */
    private function row(string $dedupKey): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_outbox WHERE dedup_key = :k');
        $stmt->execute([':k' => $dedupKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row, "Ligne $dedupKey introuvable — aucune perte autorisée");

        return $row;
    }

    private function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn();
    }

    // ═══ failed → pending (requalification) ══════════════════════════════════

    public function testRequeueFailedMovesFailedRowBackToPending(): void
    {
        $this->enqueue('rq-1');
        $this->failViaRepo('rq-1', 'SMTP timeout');

        $count = $this->repo->requeueFailed('2026-09-15 10:05:00');

        $this->assertSame(1, $count, 'Une ligne failed est requalifiée');

        $row = $this->row('rq-1');
        $this->assertSame(OutboxStatus::Pending->value, $row['status'], 'failed → pending');
        $this->assertSame(0, (int) $row['attempts'], 'attempts remis à 0 (budget de retry complet)');
        $this->assertNull($row['next_attempt_at'], 'next_attempt_at libéré = éligible immédiatement');
        $this->assertNull($row['failed_at'], 'failed_at effacé : la ligne n\'est plus en échec');
        $this->assertNull($row['processing_at'], 'processing_at effacé');
        $this->assertSame('2026-09-15 10:05:00', $row['updated_at'], 'updated_at = horodatage de la requalification');
        $this->assertStringContainsString('Requalification', (string) $row['last_error'], 'La trace de requalification est conservée');
        $this->assertStringContainsString('SMTP timeout', (string) $row['last_error'], 'L\'erreur d\'origine est conservée (diagnostic)');
    }

    public function testRequeueFailedPreservesDedupKeyAndPayload(): void
    {
        $this->repo->enqueue(new OutboxMessage(
            recipient: 'dest@dreets-bfc.gouv.fr',
            subject: 'Objet original',
            body: '<p>Corps original</p>',
            dedupKey: 'rq-payload',
            headers: 'X-Test: 1',
        ));
        $this->failViaRepo('rq-payload');

        $this->repo->requeueFailed('2026-09-15 10:05:00');

        $row = $this->row('rq-payload');
        $this->assertSame('rq-payload', $row['dedup_key'], 'dedup_key (identité logique) préservée');
        $this->assertSame('dest@dreets-bfc.gouv.fr', $row['recipient']);
        $this->assertSame('Objet original', $row['subject']);
        $this->assertSame('<p>Corps original</p>', $row['body']);
        $this->assertSame('X-Test: 1', $row['headers']);
    }

    public function testRequeueFailedLeavesNonFailedRowsUntouched(): void
    {
        $this->insertRaw('keep-pending', OutboxStatus::Pending->value);
        $this->insertRaw('keep-sent', OutboxStatus::Sent->value);
        $this->insertRaw('keep-processing', OutboxStatus::Processing->value, 1, null, null, null, '2026-01-01 00:00:00');
        $this->insertRaw('do-requeue', OutboxStatus::Failed->value, 5, 'SMTP timeout', '2026-01-01 00:00:00');

        $count = $this->repo->requeueFailed('2026-09-15 10:05:00');

        $this->assertSame(1, $count, 'Seules les lignes failed sont requalifiées');
        $this->assertSame(OutboxStatus::Pending->value, $this->row('keep-pending')['status'], 'pending intact');
        $this->assertSame(OutboxStatus::Sent->value, $this->row('keep-sent')['status'], 'sent intact (terminal)');
        $this->assertSame(OutboxStatus::Processing->value, $this->row('keep-processing')['status'], 'processing intact');
        $this->assertSame(OutboxStatus::Pending->value, $this->row('do-requeue')['status'], 'seul le failed est requalifié');
    }

    public function testRequeueFailedReturnsZeroWhenNoFailed(): void
    {
        $this->enqueue('only-pending');

        $this->assertSame(0, $this->repo->requeueFailed('2026-09-15 10:05:00'), 'Aucun failed → 0');
        $this->assertSame(OutboxStatus::Pending->value, $this->row('only-pending')['status']);
    }

    public function testRequeueFailedNeverDeletesRows(): void
    {
        $this->insertRaw('d-1', OutboxStatus::Failed->value, 5, 'e1', '2026-01-01 00:00:00');
        $this->insertRaw('d-2', OutboxStatus::Failed->value, 3, 'e2', '2026-01-01 00:00:00');
        $before = $this->countAll();

        $count = $this->repo->requeueFailed('2026-09-15 10:05:00');

        $this->assertSame(2, $count);
        $this->assertSame($before, $this->countAll(), 'Aucune ligne supprimée : requalification, jamais purge');
    }

    public function testRequeuedFailedIsImmediatelyClaimable(): void
    {
        $this->insertRaw('claimable', OutboxStatus::Failed->value, 5, 'SMTP timeout', '2026-01-01 00:00:00');

        $this->repo->requeueFailed('2026-09-15 10:05:00');
        $claimed = $this->repo->claimBatch(10, '2026-09-15 10:05:00');

        $this->assertCount(1, $claimed, 'Un message requalifié est réclamable au prochain drain');
        $this->assertSame('claimable', $claimed[0]['dedup_key']);
        $this->assertSame(1, $claimed[0]['attempts'], 'attempts repart de 0 puis incrémenté au claim');
    }

    // ═══ Route POST outbox_retry — CSRF + Superviseur ════════════════════════

    public function testOutboxRetryRouteIsSuperviseurOnlyWithCsrf(): void
    {
        $router = createRouter();

        $this->assertArrayHasKey('outbox_retry', $router->getHandlerMap(), 'La route POST outbox_retry doit être enregistrée');

        $middlewares = $router->getPostMiddleware('outbox_retry');
        $this->assertCount(2, $middlewares, 'outbox_retry doit porter [CsrfMiddleware, RoleMiddleware]');
        $this->assertInstanceOf(CsrfMiddleware::class, $middlewares[0], 'La route doit exiger un jeton CSRF');
        $this->assertInstanceOf(RoleMiddleware::class, $middlewares[1], 'La route doit être réservée par rôle');

        $roles = null;
        if ($middlewares[1] instanceof RoleMiddleware) {
            $prop = new ReflectionProperty($middlewares[1], 'roles');
            /** @var list<string> $value */
            $value = $prop->getValue($middlewares[1]);
            $roles = $value;
        }
        $this->assertSame(
            [UserRole::Superviseur->value],
            $roles,
            'Seul le Superviseur peut déclencher une requalification outbox'
        );
    }

    /**
     * Exécute le RoleMiddleware RÉEL câblé sur la route outbox_retry dans un
     * sous-processus, avec le rôle utilisateur donné.
     *
     * @return array<string, mixed>
     */
    private function runOutboxRetryAs(string $userRole): array
    {
        $router = createRouter();
        $roles = null;
        foreach ($router->getPostMiddleware('outbox_retry') as $mw) {
            if ($mw instanceof RoleMiddleware) {
                $prop = new ReflectionProperty($mw, 'roles');
                /** @var list<string> $value */
                $value = $prop->getValue($mw);
                $roles = $value;
            }
        }
        if ($roles === null) {
            $this->fail('outbox_retry doit avoir un RoleMiddleware câblé');
        }

        $config = [
            'middleware' => 'RoleMiddleware',
            'args' => [$roles],
            'session' => ['user' => ['id' => 2, 'role' => $userRole]],
            'server' => ['REQUEST_METHOD' => 'POST'],
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'outbox_role_');
        file_put_contents($tmpFile, json_encode($config));
        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../middleware_runner.php')
            . ' ' . escapeshellarg($tmpFile) . ' 2>NUL';
        exec($cmd, $output, $exitCode);
        unlink($tmpFile);

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode(implode('', $output), true);

        return $decoded ?? ['error' => 'No output'];
    }

    public function testSuperviseurIsAllowedOnOutboxRetryPost(): void
    {
        $result = $this->runOutboxRetryAs(UserRole::Superviseur->value);

        $this->assertArrayHasKey('next_called', $result, 'Sortie middleware_runner invalide : ' . json_encode($result));
        $this->assertTrue($result['next_called'], 'Le Superviseur doit pouvoir déclencher la requalification');
        $this->assertNull($result['redirect'], 'Le Superviseur ne doit pas être redirigé');
    }

    public function testAgentIsRefusedOnOutboxRetryPost(): void
    {
        $result = $this->runOutboxRetryAs(UserRole::Agent->value);

        $this->assertFalse($result['next_called'] ?? false, 'Un Agent ne doit pas accéder à la requalification outbox');
        $this->assertNotNull($result['redirect'] ?? null, 'Un Agent doit être redirigé (accès refusé)');
    }

    public function testChsctIsRefusedOnOutboxRetryPost(): void
    {
        $result = $this->runOutboxRetryAs(UserRole::Chsct->value);

        $this->assertFalse($result['next_called'] ?? false, 'Le CSA/CHSCT n\'est pas opérateur outbox');
        $this->assertNotNull($result['redirect'] ?? null, 'Le CSA/CHSCT doit être redirigé (accès refusé)');
    }
}
