<?php

/**
 * EmailOutboxRepository — Couche d'accès aux données de l'outbox SMTP.
 *
 * Outbox transactionnel : enqueue() écrit dans la MÊME transaction que
 * l'appelant (aucun commit propre), donc un rollback métier annule aussi la
 * mise en file — le message n'est jamais envoyé pour une opération qui a
 * échoué. Un worker (phase ultérieure) réclame les messages via
 * claimBatch() (claim atomique pending → processing), puis les clôt par
 * markSent() (succès), markFailed() (échec définitif) ou scheduleRetry()
 * (échec temporaire + backoff exponentiel borné).
 *
 * Idempotence : dedup_key est UNIQUE — enqueue() utilise
 * `ON CONFLICT(dedup_key) DO NOTHING` ; rejouer le même événement logique ne
 * produit pas de doublon et ne lève pas.
 *
 * La sentinelle d'anonymisation (AnonymizationPolicy::ANONYMIZED_EMAIL) n'est
 * JAMAIS mise en file : aucun message ne peut viser un compte anonymisé.
 *
 * SQL confiné ici (NoSqlOutsideRepositoryRule) ; valeurs de statut portées par
 * l'enum OutboxStatus (jamais de magic string métier).
 */

namespace App\Repository;

use App\DTO\OutboxMessage;
use App\Enum\OutboxStatus;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final readonly class EmailOutboxRepository
{
    /** Premier palier de backoff (attempts = 1). */
    private const int BASE_BACKOFF_SECONDS = 60;

    /** Cap du backoff exponentiel (attempts >= 7). */
    private const int MAX_BACKOFF_SECONDS = 3600;

    public function __construct(private PDO $pdo) {}

    /** @phpstan-ignore shipmonk.deadMethod */
    public static function instance(): self
    {
        static $instance = null;
        if ($instance === null) {
            if (function_exists('getContainer') && getContainer()->has(self::class)) {
                $instance = getContainer()->get(self::class);
            } else {
                $instance = new self(getDB());
            }
        }
        return $instance;
    }

    /**
     * Met un message en file, de façon idempotente.
     *
     * Participe à la transaction de l'appelant si une transaction est active
     * (aucun begin/commit propre) : le rollback métier annule l'enqueue.
     *
     * @return bool true si une nouvelle ligne a été insérée ; false si un
     *              message portant le même dedup_key existe déjà, ou si le
     *              destinataire est la sentinelle d'anonymisation.
     *
     * @phpstan-ignore shipmonk.deadMethod
     */
    public function enqueue(OutboxMessage $message): bool
    {
        // La sentinelle d'anonymisation ne doit jamais être mise en file
        // (comparaison insensible à la casse — source de vérité unique).
        if (AnonymizationPolicy::isAnonymizedEmail($message->recipient)) {
            return false;
        }

        if (trim($message->dedupKey) === '') {
            throw new InvalidArgumentException(
                'EmailOutboxRepository::enqueue() exige un dedupKey non vide (identité logique de l\'événement) — '
                . 'une clé vide dédupliquerait arbitrairement toutes les mises en file.'
            );
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO email_outbox (dedup_key, recipient, subject, body, headers, status, attempts, next_attempt_at)
            VALUES (:dedup_key, :recipient, :subject, :body, :headers, :status, 0, NULL)
            ON CONFLICT(dedup_key) DO NOTHING
        ');
        $stmt->execute([
            ':dedup_key' => $message->dedupKey,
            ':recipient' => $message->recipient,
            ':subject'   => $message->subject,
            ':body'      => $message->body,
            ':headers'   => $message->headers,
            ':status'    => OutboxStatus::Pending->value,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Réclame atomiquement jusqu'à $limit messages éligibles
     * (status = pending, next_attempt_at NULL ou échu) et les passe en
     * processing (attempts + 1, processing_at = $now).
     *
     * La sélection + le passage en processing se font dans une seule
     * transaction, avec `AND status = pending` sur l'UPDATE : aucune ligne ne
     * peut être réclamée deux fois. Si l'appelant est déjà en transaction, on
     * la rejoint sans la committer.
     *
     * @return list<array{id:int, dedup_key:string, recipient:string, subject:string, body:string, headers:string, attempts:int}>
     *
     * @phpstan-ignore shipmonk.deadMethod
     */
    public function claimBatch(int $limit, ?string $now = null): array
    {
        if ($limit < 1) {
            return [];
        }

        $now ??= self::nowUtc();
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $select = $this->pdo->prepare('
                SELECT id, dedup_key, recipient, subject, body, headers, attempts
                FROM email_outbox
                WHERE status = :pending
                  AND (next_attempt_at IS NULL OR next_attempt_at <= :now)
                ORDER BY COALESCE(next_attempt_at, created_at) ASC, id ASC
                LIMIT :limit
            ');
            $select->bindValue(':pending', OutboxStatus::Pending->value);
            $select->bindValue(':now', $now);
            $select->bindValue(':limit', $limit, PDO::PARAM_INT);
            $select->execute();
            /** @var list<array{id:int|string, dedup_key:string, recipient:string, subject:string, body:string, headers:string, attempts:int|string}> $rows */
            $rows = $select->fetchAll();
            $select->closeCursor();

            if ($rows === []) {
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return [];
            }

            /** @var list<int> $ids */
            $ids = array_map(
                static fn(array $row): int => (int) $row['id'],
                $rows
            );

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $update = $this->pdo->prepare(sprintf(
                'UPDATE email_outbox
                 SET status = ?, attempts = attempts + 1, processing_at = ?, updated_at = ?
                 WHERE status = ? AND id IN (%s)',
                $placeholders
            ));
            $params = [OutboxStatus::Processing->value, $now, $now, OutboxStatus::Pending->value];
            foreach ($ids as $id) {
                $params[] = $id;
            }
            $update->execute($params);

            if ($update->rowCount() !== count($ids)) {
                // Un autre worker a réclamé une ligne entre notre SELECT et notre
                // UPDATE : on annule tout le lot plutôt que de livrer deux fois.
                throw new RuntimeException(
                    'Claim concurrent détecté sur email_outbox — lot annulé (attendu '
                    . count($ids) . ' lignes, obtenu ' . $update->rowCount() . ').'
                );
            }

            $claimed = [];
            foreach ($rows as $row) {
                $claimed[] = [
                    'id'         => (int) $row['id'],
                    'dedup_key'  => (string) $row['dedup_key'],
                    'recipient'  => (string) $row['recipient'],
                    'subject'    => (string) $row['subject'],
                    'body'       => (string) $row['body'],
                    'headers'    => (string) $row['headers'],
                    'attempts'   => (int) $row['attempts'] + 1,
                ];
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return $claimed;
        } catch (Throwable $e) {
            $this->rollBackIfOwned($ownsTransaction);
            throw $e;
        }
    }

    /**
     * Annule la transaction uniquement si CET appel l'a ouverte et qu'elle est
     * encore active (le test d'état est isolé dans cette méthode : PHPStan
     * considère PDO::inTransaction() comme pure et replierait un
     * `!$owns && inTransaction()` en « toujours faux » s'il était inline ici).
     */
    private function rollBackIfOwned(bool $ownsTransaction): void
    {
        if ($ownsTransaction && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * processing → sent (succès terminal). Retourne false si la ligne n'était
     * pas en processing (déjà traitée par un autre chemin).
     *
     * @phpstan-ignore shipmonk.deadMethod
     */
    public function markSent(int $id, ?string $now = null): bool
    {
        $now ??= self::nowUtc();

        $stmt = $this->pdo->prepare('
            UPDATE email_outbox
            SET status = :sent, sent_at = :sent_at, updated_at = :updated_at, last_error = NULL
            WHERE id = :id AND status = :processing
        ');
        $stmt->execute([
            ':sent'        => OutboxStatus::Sent->value,
            ':sent_at'     => $now,
            ':updated_at'  => $now,
            ':id'          => $id,
            ':processing'  => OutboxStatus::Processing->value,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * processing → failed (échec définitif). Le message ne sera plus réclamé
     * tel quel ; un retry explicite passe par scheduleRetry() avant l'échec.
     *
     * @phpstan-ignore shipmonk.deadMethod
     */
    public function markFailed(int $id, string $error, ?string $now = null): bool
    {
        $now ??= self::nowUtc();

        $stmt = $this->pdo->prepare('
            UPDATE email_outbox
            SET status = :failed, last_error = :error, failed_at = :failed_at, updated_at = :updated_at
            WHERE id = :id AND status = :processing
        ');
        $stmt->execute([
            ':failed'      => OutboxStatus::Failed->value,
            ':error'       => $error,
            ':failed_at'   => $now,
            ':updated_at'  => $now,
            ':id'          => $id,
            ':processing'  => OutboxStatus::Processing->value,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * processing → pending (échec temporaire) avec backoff exponentiel borné :
     * next_attempt_at = $now + backoffSeconds(attempts). La ligne n'est pas
     * re-réclamable avant cette échéance (claimBatch filtre dessus).
     *
     * @phpstan-ignore shipmonk.deadMethod
     */
    public function scheduleRetry(int $id, string $error, ?string $now = null): bool
    {
        $now ??= self::nowUtc();

        $select = $this->pdo->prepare('SELECT attempts FROM email_outbox WHERE id = :id AND status = :processing');
        $select->execute([':id' => $id, ':processing' => OutboxStatus::Processing->value]);
        $attempts = $select->fetchColumn();
        $select->closeCursor();

        if ($attempts === false) {
            return false;
        }

        $nextAttemptAt = self::addSeconds($now, $this->backoffSeconds((int) $attempts));

        $update = $this->pdo->prepare('
            UPDATE email_outbox
            SET status = :pending, last_error = :error, next_attempt_at = :next_attempt_at, updated_at = :updated_at
            WHERE id = :id AND status = :processing
        ');
        $update->execute([
            ':pending'         => OutboxStatus::Pending->value,
            ':error'           => $error,
            ':next_attempt_at' => $nextAttemptAt,
            ':updated_at'      => $now,
            ':id'              => $id,
            ':processing'      => OutboxStatus::Processing->value,
        ]);

        return $update->rowCount() === 1;
    }

    /**
     * Récupère les claims orphelins : une ligne laissée en processing par un
     * worker interrompu (crash/redémarrage entre claimBatch et clôture) est
     * repassée en pending, immédiatement éligible, pour être drainée au
     * prochain run. La ligne n'est JAMAIS supprimée ; attempts (déjà
     * incrémenté au claim avorté) est conservé et last_error porte la trace
     * de la récupération.
     *
     * Seules les lignes dont processing_at est antérieur à $now - $staleAfter
     * sont touchées : une ligne processing fraîche peut appartenir à un worker
     * encore actif, la réclamer provoquerait un double envoi.
     *
     * @return int Nombre de lignes récupérées (0 si aucune)
     *
     * @phpstan-ignore shipmonk.deadMethod
     */
    public function requeueStaleProcessing(int $staleAfterSeconds, ?string $now = null): int
    {
        if ($staleAfterSeconds < 0) {
            $staleAfterSeconds = 0;
        }
        $now ??= self::nowUtc();
        $cutoff = self::addSeconds($now, -$staleAfterSeconds);

        $stmt = $this->pdo->prepare('
            UPDATE email_outbox
            SET status = :pending,
                last_error = :error,
                next_attempt_at = NULL,
                processing_at = NULL,
                updated_at = :updated_at
            WHERE status = :processing
              AND processing_at IS NOT NULL
              AND processing_at <= :cutoff
        ');
        $stmt->execute([
            ':pending'    => OutboxStatus::Pending->value,
            ':error'      => 'Claim orphelin récupéré (worker interrompu avant clôture)',
            ':updated_at' => $now,
            ':processing' => OutboxStatus::Processing->value,
            ':cutoff'     => $cutoff,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Requalifie TOUS les messages en échec définitif (failed → pending) pour
     * un nouvel essai : attempts remis à 0 (budget de retry complet),
     * next_attempt_at libéré (éligible immédiatement au prochain drain),
     * processing_at/failed_at effacés. La dedup_key et le payload sont
     * préservés ; AUCUNE ligne n'est supprimée (requalification, jamais purge).
     *
     * last_error conserve l'erreur d'origine, préfixée du motif de
     * requalification, pour que la ligne reste exploitable en diagnostic.
     *
     * Action opérateur explicite uniquement (superviseur, route outbox_retry) :
     * le worker ne requalifie jamais un failed tout seul.
     *
     * @return int Nombre de lignes requalifiées (0 si aucun échec en attente)
     *
     * @phpstan-ignore shipmonk.deadMethod
     */
    public function requeueFailed(?string $now = null): int
    {
        $now ??= self::nowUtc();

        $stmt = $this->pdo->prepare('
            UPDATE email_outbox
            SET status = :pending,
                attempts = 0,
                next_attempt_at = NULL,
                processing_at = NULL,
                failed_at = NULL,
                last_error = :requeue_trace || COALESCE(last_error, :unknown_error),
                updated_at = :updated_at
            WHERE status = :failed
        ');
        $stmt->execute([
            ':pending'       => OutboxStatus::Pending->value,
            ':requeue_trace' => 'Requalification manuelle superviseur — ',
            ':unknown_error' => 'erreur non renseignée',
            ':updated_at'    => $now,
            ':failed'        => OutboxStatus::Failed->value,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Compte les lignes nécessitant une intervention, pour la bannière locale
     * d'état SMTP/outbox (lecture seule : aucune écriture, aucun verrou).
     *
     *   - failed        : échec définitif (terminal, conservé) — le transport
     *                     SMTP n'aboutit plus ;
     *   - stale_pending : message pending ÉCHU (next_attempt_at NULL ou ≤ $now)
     *                     et en file depuis plus de $stalePendingSeconds. Les
     *                     messages en attente de backoff (next_attempt_at futur)
     *                     sont exclus : leur attente est programmée, pas un
     *                     incident.
     *
     * @return array{failed:int, stale_pending:int}
     */
    public function healthCounts(int $stalePendingSeconds, ?string $now = null): array
    {
        if ($stalePendingSeconds < 0) {
            $stalePendingSeconds = 0;
        }
        $now ??= self::nowUtc();
        $cutoff = self::addSeconds($now, -$stalePendingSeconds);

        $stmt = $this->pdo->prepare('
            SELECT
                COALESCE(SUM(CASE WHEN status = :failed THEN 1 ELSE 0 END), 0) AS failed_count,
                COALESCE(SUM(CASE
                    WHEN status = :pending
                     AND (next_attempt_at IS NULL OR next_attempt_at <= :now)
                     AND created_at <= :cutoff
                    THEN 1 ELSE 0 END), 0) AS stale_pending_count
            FROM email_outbox
        ');
        $stmt->execute([
            ':failed'  => OutboxStatus::Failed->value,
            ':pending' => OutboxStatus::Pending->value,
            ':now'     => $now,
            ':cutoff'  => $cutoff,
        ]);
        /** @var array{failed_count:int|string|null, stale_pending_count:int|string|null}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return [
            'failed'        => (int) ($row['failed_count'] ?? 0),
            'stale_pending' => (int) ($row['stale_pending_count'] ?? 0),
        ];
    }

    /**
     * Backoff exponentiel borné : base * 2^(attempts-1), plafonné à
     * MAX_BACKOFF_SECONDS. attempts < 1 est ramené au premier palier (la
     * fonction reste totale, jamais d'exposant négatif).
     */
    public function backoffSeconds(int $attempts): int
    {
        $attempts = max(1, $attempts);
        $seconds = self::BASE_BACKOFF_SECONDS * (2 ** ($attempts - 1));

        return (int) min($seconds, self::MAX_BACKOFF_SECONDS);
    }

    private static function nowUtc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private static function addSeconds(string $base, int $seconds): string
    {
        // %+d produit « +60 » ou « -900 » : DateTimeImmutable::modify() exige un
        // signe explicite pour un décalage négatif (un « +-900 » serait ignoré).
        return new DateTimeImmutable($base, new DateTimeZone('UTC'))
            ->modify(sprintf('%+d seconds', $seconds))
            ->format('Y-m-d H:i:s');
    }
}
