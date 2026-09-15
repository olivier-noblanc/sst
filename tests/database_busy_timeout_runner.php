<?php
/**
 * Subprocess runner — production getDB() contract probe.
 *
 * tests/bootstrap.php redefines getDB() with an in-memory PDO, so the real
 * implementation in src/database.php can never be loaded in-process
 * (redeclaration is a fatal error). DatabaseBusyTimeoutTest therefore spawns
 * THIS script through the PHP CLI: it boots the unmodified production
 * autoloader, calls the production getDB() against a real file-backed SQLite
 * database, and reports the live connection PRAGMAs.
 *
 * Output protocol: a single line prefixed with SST_BUSY_PRAGMA_JSON: carrying
 * the JSON payload. The marker lets the test locate the payload even if a
 * stray PHP notice lands on stdout.
 *
 * Usage: php database_busy_timeout_runner.php <path-to-sqlite-file>
 */

declare(strict_types=1);

const SST_BUSY_MARKER = 'SST_BUSY_PRAGMA_JSON:';

$dbPath = $argv[1] ?? '';
if ($dbPath === '') {
    fwrite(STDERR, "usage: php database_busy_timeout_runner.php <sqlite-file>\n");
    exit(2);
}

// prod mode keeps PHP diagnostics off stdout (config.php honours APP_ENV);
// SST_DB_PATH makes the production config target our disposable temp file.
putenv('APP_ENV=prod');
putenv('SST_DB_PATH=' . $dbPath);

/**
 * @return string  the raw PRAGMA value for the connection
 */
function readPragma(PDO $pdo, string $name): string
{
    $stmt = $pdo->query('PRAGMA ' . $name . ';');
    if ($stmt === false) {
        throw new RuntimeException('PRAGMA query failed: ' . $name);
    }
    return (string) $stmt->fetchColumn();
}

try {
    require_once __DIR__ . '/../src/autoload.php';
    require_once __DIR__ . '/../src/database.php';

    $pdo = getDB();

    echo SST_BUSY_MARKER . json_encode([
        'busy_timeout' => (int) readPragma($pdo, 'busy_timeout'),
        'journal_mode' => readPragma($pdo, 'journal_mode'),
        'foreign_keys' => (int) readPragma($pdo, 'foreign_keys'),
    ], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    echo SST_BUSY_MARKER . json_encode([
        'error' => $e::class . ': ' . $e->getMessage(),
    ], JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n";
    exit(1);
}