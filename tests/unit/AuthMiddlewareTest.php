<?php
use PHPUnit\Framework\TestCase;
use App\Middleware\AuthMiddleware;

class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $middleware;
    private bool $nextCalled;

    protected function setUp(): void
    {
        $this->middleware = new AuthMiddleware();
        $this->nextCalled = false;
        $_SESSION = [];
        $GLOBALS['_PHP_REDIRECT'] = null;
    }

    private function callNext(): void
    {
        $this->nextCalled = true;
    }

    private function runMiddleware(array $config): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'auth_test_');
        file_put_contents($tmpFile, json_encode($config));
        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../middleware_runner.php') . ' ' . escapeshellarg($tmpFile) . ' 2>NUL';
        exec($cmd, $output, $exitCode);
        unlink($tmpFile);
        return json_decode(implode('', $output), true) ?? ['error' => 'No output'];
    }

    // ─── User is logged in ──────────────────────────────────────────────────

    public function testLoggedInUserProceeds(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'AuthMiddleware',
            'session' => ['user' => ['id' => 1, 'role' => 'agent']],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertTrue($result['next_called']);
        $this->assertNull($result['redirect']);
    }

    public function testLoggedInUserWithMinimalSessionProceeds(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'AuthMiddleware',
            'session' => ['user' => ['id' => 1]],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertTrue($result['next_called']);
        $this->assertNull($result['redirect']);
    }

    // ─── User is NOT logged in (DEV_MODE = true) ────────────────────────────

    public function testUnauthenticatedUserRedirectsToLoginInDevMode(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'AuthMiddleware',
            'session' => [],
            'server' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/index.php?page=report_view&id=42',
            ],
        ]);

        $this->assertFalse($result['next_called']);
        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('login', $result['redirect']);
    }

    public function testUnauthenticatedUserSetsIntendedUrlInDevMode(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'AuthMiddleware',
            'session' => [],
            'server' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/index.php?page=report_view&id=42',
            ],
        ]);

        $this->assertEquals('/index.php?page=report_view&id=42', $result['intended_url']);
    }

    // ─── User is NOT logged in (DEV_MODE = false) ───────────────────────────

    public function testUnauthenticatedUserShowsErrorInProdMode(): void
    {
        // Override DEV_MODE for this test
        $result = $this->runMiddlewareWithDevMode(false);

        $this->assertFalse($result['next_called']);
        // In prod mode, the middleware outputs an error message and exits
        // The runner captures this as output, not redirect
        $this->assertNull($result['redirect']);
    }

    private function runMiddlewareWithDevMode(bool $devMode): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'auth_test_');
        $config = [
            'middleware' => 'AuthMiddleware',
            'session' => [],
            'server' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
            ],
        ];
        file_put_contents($tmpFile, json_encode($config));

        $devModeValue = $devMode ? 'true' : 'false';
        $runnerPath = __DIR__ . '/../middleware_runner.php';
        $wrapper = "<?php\n";
        $wrapper .= "define('DEV_MODE', $devModeValue);\n";
        $wrapper .= "require '$runnerPath';\n";

        $wrapperFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('auth_wrapper_') . '.php';
        file_put_contents($wrapperFile, $wrapper);

        $cmd = 'php ' . escapeshellarg($wrapperFile) . ' ' . escapeshellarg($tmpFile) . ' 2>NUL';
        exec($cmd, $output, $exitCode);
        unlink($tmpFile);
        unlink($wrapperFile);
        return json_decode(implode('', $output), true) ?? ['error' => 'No output'];
    }

    // ─── Empty session user ─────────────────────────────────────────────────

    public function testEmptyUserArrayIsConsideredLoggedIn(): void
    {
        // isUserLoggedIn() checks isset($_SESSION['user']), so an empty array
        // is still considered "logged in" — the middleware proceeds
        $result = $this->runMiddleware([
            'middleware' => 'AuthMiddleware',
            'session' => ['user' => []],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertTrue($result['next_called']);
        $this->assertNull($result['redirect']);
    }
}
