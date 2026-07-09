<?php
/**
 * Middleware test runner — executes a middleware in a subprocess.
 *
 * Usage: php tests/middleware_runner.php <config.json>
 *
 * The config JSON contains:
 *   - middleware: middleware class name (e.g. "CsrfMiddleware")
 *   - args:      constructor arguments array (e.g. [["superviseur"]])
 *   - session:   $_SESSION data
 *   - post:      $_POST data
 *   - server:    $_SERVER overrides
 *
 * Output: JSON with redirect URL, flash, and whether next() was called.
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

// Suppress ALL output during bootstrap loading
ob_start(function (string $buffer): string {
    return '';
});

error_reporting(0);
ini_set('display_errors', '0');

// Start session BEFORE loading bootstrap
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load the test bootstrap
require_once __DIR__ . '/bootstrap.php';

// Load middleware functions not included by bootstrap
require_once __DIR__ . '/../src/middleware/require_role.php';
require_once __DIR__ . '/../src/middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../src/middleware/RoleMiddleware.php';
require_once __DIR__ . '/../src/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../src/middleware/Pipeline.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set up session data
$_SESSION = $config['session'] ?? [];

// Set up superglobals
$_POST = $config['post'] ?? [];
$_GET = [];
$_SERVER['REQUEST_METHOD'] = $config['server']['REQUEST_METHOD'] ?? 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = $config['server']['REQUEST_URI'] ?? '/';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/../public';

// Reset globals
$GLOBALS['_PHP_REDIRECT'] = null;
$GLOBALS['_PHP_COOKIES'] = [];

// Track if next() was called
$nextCalled = false;
$firstCalled = false;
$secondCalled = false;
$nextFn = function () use (&$nextCalled) {
    $nextCalled = true;
};

// Register shutdown function to capture results BEFORE exit terminates
register_shutdown_function(function () use (&$nextCalled, &$firstCalled, &$secondCalled, $config) {
    error_reporting(0);

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

    // Clean all output buffers, then print ONLY the JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($result);
});

// Create and run the middleware
$middlewareClass = 'App\\Middleware\\' . $config['middleware'];
$args = $config['args'] ?? [];

if (!class_exists($middlewareClass)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
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
    // Run middleware twice to test token consumption
    $middleware(function () use (&$firstCalled) {
        $firstCalled = true;
    });

    // Reset redirect for second call
    $GLOBALS['_PHP_REDIRECT'] = null;

    $middleware(function () use (&$secondCalled) {
        $secondCalled = true;
    });
} else {
    $middleware($nextFn);
}
