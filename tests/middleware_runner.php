<?php
/**
 * Middleware test runner — executes a middleware in a subprocess.
 *
 * Usage: php tests/middleware_runner.php <config.json>
 */

if (($configPath = $argv[1] ?? '') === '' || !file_exists($configPath)) {
    fwrite(STDERR, "Usage: php middleware_runner.php <config.json>\n");
    exit(1);
}

$config = json_decode(file_get_contents($configPath), true);
if (!$config) {
    fwrite(STDERR, "Invalid config JSON\n");
    exit(1);
}

// Load bootstrap (includes Composer autoloader)
require_once __DIR__ . '/bootstrap.php';

// Load middleware classes
require_once __DIR__ . '/../src/Middleware/require_role.php';
require_once __DIR__ . '/../src/Middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../src/Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../src/Middleware/AuthMiddleware.php';

// Suppress errors only during middleware execution (redirect/exit may trigger warnings)
error_reporting(0);
ini_set('display_errors', '0');

// Start session before setting $_SESSION data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set up environment
$_SESSION = $config['session'] ?? [];
$_POST = $config['post'] ?? [];
$_GET = [];
$_SERVER['REQUEST_METHOD'] = $config['server']['REQUEST_METHOD'] ?? 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = $config['server']['REQUEST_URI'] ?? '/';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/../public';
$GLOBALS['_PHP_REDIRECT'] = null;
$GLOBALS['_PHP_COOKIES'] = [];

$nextCalled = false;
$firstCalled = false;
$secondCalled = false;

register_shutdown_function(function () use (&$nextCalled, &$firstCalled, &$secondCalled, $config) {
    $result = [
        'next_called' => $nextCalled,
        'redirect'    => $GLOBALS['_PHP_REDIRECT'] ?? null,
        'flash'       => $_SESSION['flash'] ?? null,
        'intended_url' => $_SESSION['intended_url'] ?? null,
    ];
    if (!empty($config['run_twice'])) {
        $result['first_called'] = $firstCalled;
        $result['second_called'] = $secondCalled;
    }
    // Clean all output buffers (discard middleware HTML output), then print ONLY the JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($result);
});

// Start output buffer to capture middleware HTML output (e.g. prod mode error)
ob_start();

$middlewareClass = 'App\\Middleware\\' . $config['middleware'];
$args = $config['args'] ?? [];

if (!class_exists($middlewareClass)) {
    echo json_encode(['error' => 'Middleware not found: ' . $middlewareClass]);
    exit(1);
}

if (!empty($args)) {
    $reflection = new ReflectionClass($middlewareClass);
    $middleware = $reflection->newInstanceArgs($args);
} else {
    $middleware = new $middlewareClass();
}

if (!empty($config['run_twice'])) {
    $middleware(function () use (&$firstCalled) { $firstCalled = true; });
    $GLOBALS['_PHP_REDIRECT'] = null;
    $middleware(function () use (&$secondCalled) { $secondCalled = true; });
} else {
    $middleware(function () use (&$nextCalled) { $nextCalled = true; });
}
