<?php

/** BackupService — Wraps backup.php global functions into an injectable service. */

namespace App\Services;

use PDO;

class BackupService
{
    public function getDbFingerprint(): array
    {
        return \getDbFingerprint($this->pdo());
    }

    public function shouldBackup(): bool
    {
        return \shouldBackup($this->pdo());
    }

    public function performBackup(): bool
    {
        return \performBackup($this->pdo());
    }

    public function backupBeforeMigration(): bool
    {
        return \backupBeforeMigration($this->pdo());
    }

    public function rotateBackups(): void
    {
        \rotateBackups();
    }

    private function pdo(): PDO
    {
        return \getDB();
    }
}
