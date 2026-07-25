<?php

/** ConfigRepository — Couche d'accès aux données pour la configuration. */

namespace App\Repository;

use PDO;

class ConfigRepository
{
    public function __construct(private readonly PDO $pdo) {}

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

    public function get(string $cle): ?string
    {
        $stmt = $this->pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
        $stmt->execute([':cle' => $cle]);
        $result = $stmt->fetchColumn();
        return ($result !== false && $result !== null && $result !== '') ? (string) $result : null;
    }

    public function set(string $cle, string $valeur): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable)
            VALUES (:cle, :valeur, "", "", "", 1)
            ON CONFLICT(cle) DO UPDATE SET valeur = :valeur2, updated_at = datetime("now")');
        $stmt->execute([':cle' => $cle, ':valeur' => $valeur, ':valeur2' => $valeur]);
    }

    /**
     * Atomic compare-and-swap for lazy cron locking.
     *
     * Audit #41 — Before this fix, runLazyCronTask did:
     *   1. Read lastRun from config
     *   2. If interval elapsed, write new timestamp to config
     *   3. Execute task
     * Two concurrent logins could both read step 1 before either wrote step 2 →
     * both executed the task (double email sent, double anonymization, etc.).
     *
     * This method does the read+write atomically via SQLite UPSERT + WHERE
     * condition. Returns true if this caller acquired the lock (i.e. the row
     * was either absent or had an old timestamp), false otherwise.
     *
     * @param string $cle       Config key
     * @param int    $minInterval Minimum seconds since last run
     * @return bool True if lock acquired (caller should run task)
     */
    public function claimLazyCronLock(string $cle, int $minInterval): bool
    {
        $now = time();
        $nowIso = date('Y-m-d H:i:s', $now);
        $cutoffIso = date('Y-m-d H:i:s', $now - $minInterval);

        // Try atomic claim: insert if absent, or update only if old enough.
        // SQLite doesn't support WHERE on INSERT ON CONFLICT, so we use a
        // two-step approach inside a transaction with a SELECT FOR UPDATE-like
        // pattern. SQLite has SERIALIZABLE by default in WAL mode, so this is
        // safe under the app's single-writer model.
        $this->pdo->beginTransaction();
        try {
            // Read current value
            $stmt = $this->pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
            $stmt->execute([':cle' => $cle]);
            $current = $stmt->fetchColumn();

            $shouldRun = false;
            if ($current === false || $current === null || $current === '') {
                // Never run before → claim
                $shouldRun = true;
            } else {
                $lastTs = strtotime((string) $current);
                if ($lastTs === false || ($now - $lastTs) >= $minInterval) {
                    $shouldRun = true;
                }
            }

            if ($shouldRun) {
                // Write the new timestamp immediately (release happens implicitly)
                $updateStmt = $this->pdo->prepare('INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable)
                    VALUES (:cle, :valeur, "", "", "", 1)
                    ON CONFLICT(cle) DO UPDATE SET valeur = :valeur2, updated_at = datetime("now")');
                $updateStmt->execute([':cle' => $cle, ':valeur' => $nowIso, ':valeur2' => $nowIso]);
                $this->pdo->commit();
                return true;
            }

            $this->pdo->commit();
            return false;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('[SST-CRON] claimLazyCronLock failed for ' . $cle . ': ' . $e->getMessage());
            return false; // Fail safe — don't run if we can't claim
        }
    }
}
