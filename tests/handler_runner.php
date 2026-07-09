<?php
/**
 * Handler test runner — executes a handler in a subprocess.
 *
 * Usage: php tests/handler_runner.php <config.json>
 *
 * The config JSON contains:
 *   - handler:   handler filename (e.g. "report_create_handler.php")
 *   - session:   $_SESSION data (must include 'user' and 'csrf_tokens')
 *   - post:      $_POST data (must include 'csrf_token')
 *   - server:    $_SERVER overrides (REQUEST_METHOD, etc.)
 *   - db_seed:   SQL statements to seed the test DB
 *   - assertions: associative array of label => SQL (results returned in output)
 *
 * Output: JSON with redirect URL, flash, form errors, and query results.
 * The handler will call exit() via redirect() — we capture results in a shutdown function.
 */

if (($configPath = $argv[1] ?? '') === '' || !file_exists($configPath)) {
    fwrite(STDERR, "Usage: php handler_runner.php <config.json>\n");
    exit(1);
}

$config = json_decode(file_get_contents($configPath), true);
if (!$config) {
    fwrite(STDERR, "Invalid config JSON\n");
    exit(1);
}

// Suppress ALL output during bootstrap loading (constant redefinition warnings, etc.)
// The callback discards all output — only our final JSON will be printed.
ob_start(function (string $buffer): string {
    return '';
});

// Suppress error reporting during bootstrap
error_reporting(0);
ini_set('display_errors', '0');

// Start session BEFORE loading bootstrap (so session is ready when code needs it)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load the test bootstrap (defines constants, getDB(), loads all source files)
require_once __DIR__ . '/bootstrap.php';

// Load middleware functions not included by bootstrap
require_once __DIR__ . '/../src/middleware/require_role.php';
require_once __DIR__ . '/../src/audit.php';

// Restore error reporting for actual test code
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Seed test data
$pdo = getDB();
$seedSql = $config['db_seed'] ?? '';
if (!empty($seedSql)) {
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $seedSql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
}

// Set up session data (session is already started above)
$_SESSION = $config['session'] ?? [];

// Set up superglobals
$_POST = $config['post'] ?? [];
$_GET = [];
$_SERVER['REQUEST_METHOD'] = $config['server']['REQUEST_METHOD'] ?? 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/../public';

// Reset globals
$GLOBALS['_PHP_REDIRECT'] = null;
$GLOBALS['_PHP_COOKIES'] = [];

// Register shutdown function to capture results BEFORE exit terminates
register_shutdown_function(function () use ($config) {
    error_reporting(0);

    $result = [
        'redirect'   => $GLOBALS['_PHP_REDIRECT'] ?? null,
        'flash'      => $_SESSION['flash'] ?? null,
        'form_errors' => $_SESSION['form_errors'] ?? null,
        'form_data'  => $_SESSION['form_data'] ?? null,
        'report_created' => $_SESSION['report_created'] ?? null,
    ];

    // Run assertion queries
    $pdo = getDB();
    $queries = $config['assertions'] ?? [];
    $results = [];
    foreach ($queries as $key => $sql) {
        try {
            $stmt = $pdo->query($sql);
            $results[$key] = $stmt->fetchColumn();
        } catch (\Exception $e) {
            $results[$key] = 'ERROR: ' . $e->getMessage();
        }
    }
    $result['queries'] = $results;

    // Clean all output buffers (discard warnings), then print ONLY the JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($result);
});

// Run the handler
$handlerFile = __DIR__ . '/../handlers/' . $config['handler'];
if (!file_exists($handlerFile)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['error' => 'Handler not found: ' . $config['handler']]);
    exit(1);
}

require $handlerFile;
