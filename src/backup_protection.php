<?php

/**
 * Backup Protection — Application SST DREETS BFC
 *
 * Directory protection and listing for the backup folder.
 * Split from backup.php to keep file size under 250 lines.
 */

/**
 * Write .htaccess and web.config to protect the backups directory
 * from direct HTTP access.
 */
function writeBackupProtection(): void
{
    // Apache 2.4+ — deny all
    if (!file_exists(BACKUP_DIR . '/.htaccess')) {
        file_put_contents(BACKUP_DIR . '/.htaccess', "<RequireAll>\n    Require all denied\n</RequireAll>\n");
    }

    // IIS — deny all
    if (!file_exists(BACKUP_DIR . '/web.config')) {
        file_put_contents(
            BACKUP_DIR . '/web.config',
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
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
 * @return array<int, array{file: string, path: string, size: int|false, date: int|false}>
 */
function listBackups(): array
{
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
    usort($backups, fn($a, $b) => (int) $b['date'] - (int) $a['date']);

    return $backups;
}
