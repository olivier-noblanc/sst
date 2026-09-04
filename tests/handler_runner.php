<?php
/**
 * Handler test runner — executes a handler in a subprocess.
 *
 * Usage: php tests/handler_runner.php <config.json>
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

// Load bootstrap (includes Composer autoloader)
require_once __DIR__ . '/bootstrap.php';

// Load middleware + audit
require_once __DIR__ . '/../src/Middleware/require_role.php';
require_once __DIR__ . '/../src/audit.php';

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

// Suppress errors only during handler execution
error_reporting(0);
ini_set('display_errors', '0');

// Start session before setting $_SESSION data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set up environment
$_SESSION = $config['session'] ?? [];
$_POST = $config['post'] ?? [];
// Support GET pages (page mode) — handlers keep the previous behavior ($_GET = []).
$_GET = $config['get'] ?? [];
$_SERVER['REQUEST_METHOD'] = $config['server']['REQUEST_METHOD'] ?? 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/../public';
$GLOBALS['_PHP_REDIRECT'] = null;
$GLOBALS['_PHP_COOKIES'] = [];

register_shutdown_function(function () use ($config) {
    $result = [
        'redirect'   => $GLOBALS['_PHP_REDIRECT'] ?? null,
        'flash'      => $_SESSION['flash'] ?? null,
        'form_errors' => $_SESSION['form_errors'] ?? null,
        'form_data'  => $_SESSION['form_data'] ?? null,
        'report_created' => $_SESSION['report_created'] ?? null,
    ];
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

    // Capture rendered output (pages) instead of discarding it — lets page
    // tests assert on visible markers (e.g. access denied). Additive: handler
    // tests that ignore the extra 'output' key are unaffected.
    $renderedOutput = '';
    while (ob_get_level() > 0) {
        $renderedOutput = (string) ob_get_clean() . $renderedOutput;
    }
    $result['output'] = $renderedOutput;

    echo json_encode($result);
});

// Start output buffer to capture handler output (e.g. CSV from export)
ob_start();

// Page mode (GET pages): run pages/<page>.php instead of a POST handler.
// 'handler' remains the default for backward compatibility.
if (isset($config['page'])) {
    $pageFile = __DIR__ . '/../pages/' . $config['page'];
    if (!file_exists($pageFile)) {
        echo json_encode(['error' => 'Page not found: ' . $config['page']]);
        exit(1);
    }
    require $pageFile;
} else {
    $handlerFile = __DIR__ . '/../handlers/' . $config['handler'];
    if (!file_exists($handlerFile)) {
        echo json_encode(['error' => 'Handler not found: ' . $config['handler']]);
        exit(1);
    }

    require $handlerFile;
}
