<?php

/** SessionRepository — Couche d'accès aux données pour les sessions. */

namespace App\Repository;

use PDO;

class SessionRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

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
     * Purge les sessions expirées.
     *
     * @param int $maxLifetime Durée de vie max en secondes (défaut: 24h)
     * @return int Nombre de sessions supprimées
     */
    public function purgeExpired(int $maxLifetime = 86400): int
    {
        $cutoff = time() - $maxLifetime;
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE last_accessed < :cutoff');
        $stmt->execute([':cutoff' => $cutoff]);
        return $stmt->rowCount();
    }
}
