<?php

/**
 * Backup — Application SST DREETS BFC
 *
 * Autonomous SQLite backup strategy for IIS/Windows.
 * No external scripts, no cron, no task scheduler.
 *
 * How it works:
 *   1. On every page load, check if the DB has changed since the last backup
 *      (compare filemtime + filesize — no WAL checkpoint needed for detection).
 *   2. If unchanged → skip. Zero wasted I/O.
 *   3. If changed → WAL checkpoint + VACUUM INTO creates a compact, consistent snapshot.
 *   4. Rotation: keep the N most recent backups, delete the rest.
 *   5. Before schema migration: forced backup (safety net).
 *
 * Backup files are stored in data/backups/ named sst_YYYY-MMDD_HHMMSS.db
 * A marker file data/backups/.last_backup stores the DB fingerprint
 * (filemtime + filesize) of the last backed-up state.
 */

require_once __DIR__ . '/backup_protection.php';

define('BACKUP_DIR', __DIR__ . '/../data/backups');
define('BACKUP_MAX_FILES', 10);
define('BACKUP_MARKER_FILE', BACKUP_DIR . '/.last_backup');

/**
 * Get a fingerprint of the current database file.
 * Reads filemtime + filesize of both the main .db and the WAL file
 * to detect changes without forcing a checkpoint.
 *
 * Audit #47 — Cache disabled. Before this fix, the static $cached was set on
 * the first call (e.g. before migration in database.php) and returned on
 * the second call (after migration) → post-migration fingerprint was the
 * pre-migration one → backup was skipped after every migration. Now each
 * call reads the fresh state from disk.
 *
 * @return array{mtime: int, size: int}
 */
function getDbFingerprint(PDO $pdo): array
{
    clearstatcache(true, DB_PATH);
    if (!file_exists(DB_PATH)) {
        return ['mtime' => 0, 'size' => 0];
    }
    $mtime = (int) filemtime(DB_PATH);
    $size = (int) filesize(DB_PATH);

    // In WAL mode, writes go to the -wal file without changing .db
    // Include the WAL file's mtime/size to detect pending changes
    $walPath = DB_PATH . '-wal';
    if (file_exists($walPath)) {
        $mtime = max($mtime, (int) filemtime($walPath));
        $size += (int) filesize($walPath);
    }

    return ['mtime' => $mtime, 'size' => $size];
}

/**
 * Read the fingerprint of the last backup from the marker file.
 *
 * @return array{mtime: int, size: int}|null  Fingerprint or null if no marker
 */
function getLastBackupFingerprint(): ?array
{
    if (!file_exists(BACKUP_MARKER_FILE)) {
        return null;
    }
    $content = file_get_contents(BACKUP_MARKER_FILE);
    if ($content === false) {
        return null;
    }
    $data = json_decode($content, true);
    if (!is_array($data) || !isset($data['mtime'], $data['size'])) {
        return null;
    }
    return $data;
}

/**
 * Write the current fingerprint to the marker file.
 *
 * @param array{mtime: int, size: int} $fingerprint
 */
function setLastBackupFingerprint(array $fingerprint): void
{
    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0755, true);
    }
    file_put_contents(BACKUP_MARKER_FILE, json_encode($fingerprint, JSON_PRETTY_PRINT));
}

/**
 * Check whether a backup is needed (DB has changed since last backup).
 *
 * @param PDO $pdo
 * @return bool
 */
function shouldBackup(PDO $pdo): bool
{
    $current = getDbFingerprint($pdo);
    $last = getLastBackupFingerprint();

    if ($last === null) {
        return true; // Never backed up
    }

    // Same fingerprint → no change → skip
    return ($current['mtime'] !== $last['mtime'] || $current['size'] !== $last['size']);
}

/**
 * Internal backup implementation shared by performBackup() and backupBeforeMigration().
 *
 * @param PDO    $pdo      Database connection
 * @param string $prefix   Filename prefix ('sst' or 'sst_pre_migration')
 * @param bool   $force    Skip shouldBackup() check (for pre-migration)
 * @return bool            True if backup was created, false if skipped or failed
 */
function performBackupInternal(PDO $pdo, string $prefix = 'sst', bool $force = false): bool
{
    if (!$force && !shouldBackup($pdo)) {
        return false;
    }

    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0755, true);
    }

    writeBackupProtection();

    $timestamp = date('Y-m-d_His');
    $backupFile = BACKUP_DIR . '/' . $prefix . '_' . $timestamp . '.db';

    // Path traversal check: ensure backup file stays within BACKUP_DIR
    $backupDir = defined('BACKUP_DIR') ? BACKUP_DIR : __DIR__ . '/../data/backups';
    $realBackupFile = realpath(dirname($backupFile));
    $realBackupDir = realpath($backupDir);
    if (!str_starts_with($realBackupFile !== false ? $realBackupFile : dirname($backupFile), $realBackupDir !== false ? $realBackupDir : $backupDir)) {
        error_log("SST: backup path traversal blocked - $backupFile");
        return false;
    }

    // Avoid filename collision (two backups in the same second)
    if (file_exists($backupFile)) {
        $backupFile = BACKUP_DIR . '/' . $prefix . '_' . $timestamp . '_' . random_int(100, 999) . '.db';
    }

    // Flush WAL before VACUUM INTO to ensure backup captures all pending writes.
    // Neither call is wrapped here: a failure means the backup did not happen,
    // which the caller needs to know rather than have masked. performBackup()'s
    // own caller (src/database.php) already decides, deliberately, not to let a
    // failed backup block a normal page load; backupBeforeMigration()'s caller
    // (the CHECK-constraint rebuild in migration_columns.php) deliberately does
    // NOT catch it — proceeding with a destructive table rebuild without a
    // successful pre-migration backup would be worse than stopping.
    $pdo->exec('PRAGMA wal_checkpoint(PASSIVE)');
    $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $backupFile) . "'");

    if (!file_exists($backupFile) || filesize($backupFile) === 0) {
        error_log('[SST-BACKUP] Backup file missing or empty: ' . $backupFile);
        return false;
    }

    $fingerprint = getDbFingerprint($pdo);
    setLastBackupFingerprint($fingerprint);

    rotateBackups();

    return true;
}

/**
 * Perform a backup using VACUUM INTO.
 * Skips if the DB hasn't changed since the last backup.
 *
 * @param PDO $pdo
 * @return bool True if backup was created, false if skipped or failed
 */
function performBackup(PDO $pdo): bool
{
    return performBackupInternal($pdo, 'sst', false);
}

/**
 * Force a backup before a schema migration.
 * Always creates a backup regardless of DB changes.
 *
 * @param PDO $pdo
 * @return bool
 */
function backupBeforeMigration(PDO $pdo): bool
{
    return performBackupInternal($pdo, 'sst_pre_migration', true);
}

/**
 * Rotate backup files: keep only the N most recent, delete the rest.
 * Counts both regular backups (sst_*.db) and pre-migration backups.
 */
function rotateBackups(): void
{
    $files = glob(BACKUP_DIR . '/sst_*.db');
    if ($files === false || count($files) <= BACKUP_MAX_FILES) {
        return;
    }

    // Sort by modification time, oldest first
    usort($files, fn($a, $b) => (int) filemtime($a) - (int) filemtime($b));

    // Delete the oldest files beyond the limit
    $toDelete = array_slice($files, 0, count($files) - BACKUP_MAX_FILES);
    foreach ($toDelete as $file) {
        @unlink($file);
    }
}
