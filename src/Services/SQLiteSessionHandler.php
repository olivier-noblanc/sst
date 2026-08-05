<?php

/**
 * SQLiteSessionHandler — Session storage in SQLite database.
 *
 * Implements SessionHandlerInterface so PHP stores sessions in the
 * existing sst.db instead of files on disk. More reliable than file-based
 * sessions in CI environments (no filesystem permission issues).
 */

namespace App\Services;

use SessionHandlerInterface;
use PDO;

class SQLiteSessionHandler implements SessionHandlerInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string|false
    {
        $stmt = $this->pdo->prepare('SELECT data FROM sessions WHERE id = :id');
        $stmt->execute([':id' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return '';
        }
        return $row['data'] ?? '';
    }

    public function write(string $sessionId, string $data): bool
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO sessions (id, data, last_accessed)
            VALUES (:id, :data, :now)
            ON CONFLICT(id) DO UPDATE SET data = :data, last_accessed = :now
        ');
        return $stmt->execute([
            ':id'   => $sessionId,
            ':data' => $data,
            ':now'  => time(),
        ]);
    }

    public function destroy(string $sessionId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = :id');
        return $stmt->execute([':id' => $sessionId]);
    }

    public function gc(int $maxLifetime): int|false
    {
        $cutoff = time() - $maxLifetime;
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE last_accessed < :cutoff');
        $stmt->execute([':cutoff' => $cutoff]);
        return $stmt->rowCount();
    }
}
