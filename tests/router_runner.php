<?php
/**
 * Router test runner — dispatches a POST request through the REAL Router
 * (src/Router/routes.php createRouter()), including every middleware
 * registered for that route (CsrfMiddleware, RoleMiddleware, etc.).
 *
 * Unlike handler_runner.php, which requires the handler file directly and
 * therefore skips all router-level middleware, this runner exercises the
 * exact same dispatch path a real HTTP request goes through — this is the
 * only way to catch bugs that live in the *interaction* between router
 * middleware and a handler's own checks (e.g. a handler that duplicates a
 * check the router middleware already performs, silently double-consuming
 * a one-time-use CSRF token).
 *
 * Usage: php tests/router_runner.php <config.json>
 */

if (($configPath = $argv[1] ?? '') === '' || !file_exists($configPath)) {
    fwrite(STDERR, "Usage: php router_runner.php <config.json>\n");
    exit(1);
}

$config = json_decode(file_get_contents($configPath), true);
if (!$config) {
    fwrite(STDERR, "Invalid config JSON\n");
    exit(1);
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Middleware/require_role.php';
require_once __DIR__ . '/../src/Middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../src/Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../src/Router/Router.php';
require_once __DIR__ . '/../src/Router/routes.php';
require_once __DIR__ . '/../src/audit.php';

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

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = $config['session'] ?? [];
$_POST = $config['post'] ?? [];
$_FILES = $config['files'] ?? [];
$_GET = [];
$_SERVER['REQUEST_METHOD'] = $config['server']['REQUEST_METHOD'] ?? 'POST';
$_SERVER['CONTENT_LENGTH'] = $config['server']['CONTENT_LENGTH'] ?? '100';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/../public';
$GLOBALS['_PHP_REDIRECT'] = null;
$GLOBALS['_PHP_COOKIES'] = [];

register_shutdown_function(function () use ($config) {
    $result = [
        'redirect'    => $GLOBALS['_PHP_REDIRECT'] ?? null,
        'flash'       => $_SESSION['flash'] ?? null,
        'form_errors' => $_SESSION['form_errors'] ?? null,
        'form_data'   => $_SESSION['form_data'] ?? null,
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

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($result);
});

ob_start();

$page = $config['page'] ?? '';
if ($page === '') {
    echo json_encode(['error' => 'Missing "page" in config']);
    exit(1);
}

$router = createRouter();
$dispatched = $router->dispatchPost($page);
if (!$dispatched) {
    echo json_encode(['error' => 'No POST route registered for page: ' . $page]);
}
