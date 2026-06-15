<?php
/**
 * Backup — Application SST DREETS BFC
 *
 * Autonomous SQLite backup strategy for IIS/Windows.
 * No external scripts, no cron, no task scheduler.
 *
 * How it works:
 *   1. On every page load, check if the DB has changed since the last backup
 *      (compare filemtime + filesize after WAL checkpoint).
 *   2. If unchanged → skip. Zero wasted I/O.
 *   3. If changed → VACUUM INTO creates a compact, consistent snapshot.
 *   4. Rotation: keep the N most recent backups, delete the rest.
 *   5. Before schema migration: forced backup (safety net).
 *
 * Backup files are stored in data/backups/ named sst_YYYY-MMDD_HHMMSS.db
 * A marker file data/backups/.last_backup stores the DB fingerprint
 * (filemtime + filesize) of the last backed-up state.
 */

define('BACKUP_DIR', __DIR__ . '/../data/backups');
define('BACKUP_MAX_FILES', 10);
define('BACKUP_MARKER_FILE', BACKUP_DIR . '/.last_backup');

/**
 * Get a fingerprint of the current database file.
 * Forces a WAL checkpoint first so all data is in the main file,
 * then reads filemtime + filesize.
 *
 * @return array ['mtime' => int, 'size' => int]
 */
function getDbFingerprint(PDO $pdo): array {
    // Checkpoint WAL → flush pending writes into the main .db file
    try {
        $pdo->exec("PRAGMA wal_checkpoint(TRUNCATE)");
    } catch (Exception $e) {
        // Non-critical: checkpoint may fail if no WAL, just proceed
        error_log('[SST-BACKUP] WAL checkpoint warning: ' . $e->getMessage());
    }

    clearstatcache(true, DB_PATH);
    return [
        'mtime' => filemtime(DB_PATH) ?: 0,
        'size'  => filesize(DB_PATH) ?: 0,
    ];
}

/**
 * Read the fingerprint of the last backup from the marker file.
 *
 * @return array|null ['mtime' => int, 'size' => int] or null if no marker
 */
function getLastBackupFingerprint(): ?array {
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
 * @param array $fingerprint ['mtime' => int, 'size' => int]
 */
function setLastBackupFingerprint(array $fingerprint): void {
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
function shouldBackup(PDO $pdo): bool {
    $current = getDbFingerprint($pdo);
    $last = getLastBackupFingerprint();

    if ($last === null) {
        return true; // Never backed up
    }

    // Same fingerprint → no change → skip
    return ($current['mtime'] !== $last['mtime'] || $current['size'] !== $last['size']);
}

/**
 * Perform a backup using VACUUM INTO.
 * This is a pure SQL command — no external tools, works on Windows/IIS.
 * Creates a compact, consistent snapshot of the entire database.
 *
 * @param PDO $pdo
 * @return bool True if backup was created, false if skipped or failed
 */
function performBackup(PDO $pdo): bool {
    // Double-check: skip if nothing changed
    if (!shouldBackup($pdo)) {
        return false;
    }

    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0755, true);
    }

    // Protect data/backups with a .htaccess (Apache) and web.config (IIS)
    writeBackupProtection();

    $timestamp = date('Y-md_His');
    $backupFile = BACKUP_DIR . '/sst_' . $timestamp . '.db';

    // Avoid filename collision (two backups in the same second)
    if (file_exists($backupFile)) {
        $backupFile = BACKUP_DIR . '/sst_' . $timestamp . '_' . random_int(100, 999) . '.db';
    }

    try {
        // VACUUM INTO: creates a new database file with a clean copy
        // Works on Windows, no external tools, no file locking issues.
        // Requires SQLite 3.27.0+ (2019-01) — universally available.
        $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $backupFile) . "'");
    } catch (Exception $e) {
        error_log('[SST-BACKUP] VACUUM INTO failed: ' . $e->getMessage());
        return false;
    }

    // Verify the backup was created and is readable
    if (!file_exists($backupFile) || filesize($backupFile) === 0) {
        error_log('[SST-BACKUP] Backup file missing or empty: ' . $backupFile);
        return false;
    }

    // Update marker with the current fingerprint
    $fingerprint = getDbFingerprint($pdo);
    setLastBackupFingerprint($fingerprint);

    // Rotate old backups
    rotateBackups();

    return true;
}

/**
 * Force a backup before a schema migration.
 * This always creates a backup, regardless of whether the DB changed,
 * because we're about to alter the structure.
 *
 * @param PDO $pdo
 * @return bool
 */
function backupBeforeMigration(PDO $pdo): bool {
    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0755, true);
    }

    writeBackupProtection();

    $timestamp = date('Y-md_His');
    $backupFile = BACKUP_DIR . '/sst_pre_migration_' . $timestamp . '.db';

    if (file_exists($backupFile)) {
        $backupFile = BACKUP_DIR . '/sst_pre_migration_' . $timestamp . '_' . random_int(100, 999) . '.db';
    }

    try {
        $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $backupFile) . "'");
    } catch (Exception $e) {
        error_log('[SST-BACKUP] Pre-migration backup failed: ' . $e->getMessage());
        return false;
    }

    if (!file_exists($backupFile) || filesize($backupFile) === 0) {
        error_log('[SST-BACKUP] Pre-migration backup file missing or empty: ' . $backupFile);
        return false;
    }

    // Update marker
    $fingerprint = getDbFingerprint($pdo);
    setLastBackupFingerprint($fingerprint);

    rotateBackups();

    return true;
}

/**
 * Rotate backup files: keep only the N most recent, delete the rest.
 * Counts both regular backups (sst_*.db) and pre-migration backups.
 */
function rotateBackups(): void {
    $files = glob(BACKUP_DIR . '/sst_*.db');
    if ($files === false || count($files) <= BACKUP_MAX_FILES) {
        return;
    }

    // Sort by modification time, oldest first
    usort($files, function ($a, $b) {
        return filemtime($a) - filemtime($b);
    });

    // Delete the oldest files beyond the limit
    $toDelete = array_slice($files, 0, count($files) - BACKUP_MAX_FILES);
    foreach ($toDelete as $file) {
        @unlink($file);
    }
}

/**
 * Write .htaccess and web.config to protect the backups directory
 * from direct HTTP access.
 */
function writeBackupProtection(): void {
    // Apache — deny all
    if (!file_exists(BACKUP_DIR . '/.htaccess')) {
        file_put_contents(BACKUP_DIR . '/.htaccess', "Deny from all\n");
    }

    // IIS — deny all
    if (!file_exists(BACKUP_DIR . '/web.config')) {
        file_put_contents(BACKUP_DIR . '/web.config', '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<configuration>' . "\n"
            . '  <system.webServer>' . "\n"
            . '    <handlers clear="true" />' . "\n"
            . '    <authorization>' . "\n"
            . '      <deny users="*" />' . "\n"
            . '    </authorization>' . "\n"
            . '  </system.webServer>' . "\n"
            . '</configuration>' . "\n"
        );
    }
}

/**
 * List available backups for admin UI.
 *
 * @return array [{file, name, size, date}, ...]
 */
function listBackups(): array {
    if (!is_dir(BACKUP_DIR)) {
        return [];
    }

    $files = glob(BACKUP_DIR . '/sst_*.db');
    if ($files === false) {
        return [];
    }

    $backups = [];
    foreach ($files as $file) {
        $backups[] = [
            'file' => basename($file),
            'path' => $file,
            'size' => filesize($file),
            'date' => filemtime($file),
        ];
    }

    // Sort newest first
    usort($backups, function ($a, $b) {
        return $b['date'] - $a['date'];
    });

    return $backups;
}
