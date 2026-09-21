<?php

/**
 * Migration Lock & Transaction Primitives — Application SST DREETS BFC
 *
 * Cross-process serialization of schema migrations, plus the SQLite
 * transaction primitive the destructive table rebuilds need.
 *
 * Why a lock: getDB() runs migrateSchema() on every worker's first request.
 * Under IIS several worker processes boot at once and would each run the
 * destructive rebuilds concurrently. SQLite allows a single writer at a time,
 * so the losers crashed with "database is locked".
 *
 * Why BEGIN IMMEDIATE: PDO::beginTransaction() issues a deferred BEGIN. With
 * a concurrent writer, the first write must upgrade read→write, and SQLite
 * refuses that upgrade WITHOUT invoking the busy handler — so PRAGMA
 * busy_timeout never applies and the migration failed in ~0 ms
 * (migration_columns.php:860). BEGIN IMMEDIATE takes the write lock up front,
 * where busy_timeout IS honoured (measured: 0 ms vs ~5.6 s). The two are
 * complementary: the lock serializes migrators, BEGIN IMMEDIATE arbitrates
 * against ordinary request writers.
 */

/**
 * Default cross-process wait for the migration lock, in seconds.
 */
const MIGRATION_LOCK_TIMEOUT_SECONDS = 30.0;

/**
 * Poll interval while waiting for the migration lock, in microseconds.
 */
const MIGRATION_LOCK_RETRY_INTERVAL_US = 50000;

/**
 * Begin a write transaction immediately (SQLite BEGIN IMMEDIATE).
 *
 * Unlike PDO::beginTransaction() (deferred BEGIN), this acquires the write
 * lock at BEGIN time so PRAGMA busy_timeout bounds the wait instead of the
 * first write failing instantly with SQLITE_BUSY. PDO::commit()/rollBack()
 * stay usable afterwards: pdo_sqlite reports inTransaction() from the
 * connection's autocommit state, not from who issued the BEGIN.
 *
 * @param PDO $pdo Database connection.
 */
function migrationBeginImmediate(PDO $pdo): void
{
    if ($pdo->exec('BEGIN IMMEDIATE') === false) {
        throw new RuntimeException('Migration : BEGIN IMMEDIATE a échoué sans exception.');
    }
}

/**
 * Run $work while holding an exclusive cross-process migration lock.
 *
 * Bounded retry (non-blocking flock polled until the timeout), then a loud
 * RuntimeException if the lock can never be acquired — a migration worker
 * must never silently proceed without the lock, that is exactly the
 * concurrent-rebuild bug. The OS releases the lock automatically when the
 * holder's handle is closed or its process dies, so a crashed worker cannot
 * leave the lock stuck.
 *
 * @param Closure():void $work           Migration work to run under the lock.
 * @param string|null    $lockFile       Override for tests; defaults next to the DB.
 * @param float          $timeoutSeconds Bounded wait before failing loudly.
 */
function withMigrationLock(
    Closure $work,
    ?string $lockFile = null,
    float $timeoutSeconds = MIGRATION_LOCK_TIMEOUT_SECONDS
): void {
    $lockFile ??= dirname((string) DB_PATH) . '/.migration.lock';

    $handle = @fopen($lockFile, 'c');
    if ($handle === false) {
        throw new RuntimeException('Migration : impossible d’ouvrir le verrou inter-processus ' . $lockFile . '.');
    }

    $acquired = false;
    try {
        $deadline = microtime(true) + $timeoutSeconds;
        for (;;) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $acquired = true;
                break;
            }
            if (microtime(true) >= $deadline) {
                throw new RuntimeException(
                    'Migration : verrou inter-processus non acquis en ' . $timeoutSeconds
                    . ' s — une autre instance migre la base de données.'
                );
            }
            usleep(MIGRATION_LOCK_RETRY_INTERVAL_US);
        }

        $work();
    } finally {
        if ($acquired) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}
