<?php

/**
 * TransactionManager — Périmètre transactionnel pour les actions métier.
 *
 * Objectif (invariant outbox) : l'insertion `email_outbox` doit partager la
 * MÊME transaction que l'action métier qui la produit. `run()` ouvre une
 * transaction si l'appelant n'en a pas déjà une, exécute l'action (dont les
 * repositories « join-if-active » — ils ne committent pas s'ils ne possèdent
 * pas la transaction), puis commit ; toute exception provoque le rollback
 * complet avant d'être repropagée au boundary.
 *
 * Aucun transport SMTP n'est exécuté ici : le flush opportuniste est un
 * callback post-commit (`$afterCommit`), jamais dans la transaction.
 */

namespace App\Repository;

use PDO;
use Throwable;

final readonly class TransactionManager
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @template T
     * @param callable():T $fn
     * @param (callable():void)|null $afterCommit Appelé seulement si CET appel
     *        possède et a committé la transaction (jamais sur une transaction
     *        déjà ouverte par un appelant).
     * @return T
     */
    public function run(callable $fn, ?callable $afterCommit = null): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $fn();

            if ($ownsTransaction) {
                $this->pdo->commit();
                if ($afterCommit !== null) {
                    $afterCommit();
                }
            }

            return $result;
        } catch (Throwable $e) {
            $this->rollBackIfOwned($ownsTransaction);
            throw $e;
        }
    }

    /**
     * Le test d'état est isolé ici : PHPStan considère PDO::inTransaction()
     * comme pure et replierait un `!$owns && inTransaction()` inline en
     * « toujours faux » (même contournement que EmailOutboxRepository).
     */
    private function rollBackIfOwned(bool $ownsTransaction): void
    {
        if ($ownsTransaction && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
