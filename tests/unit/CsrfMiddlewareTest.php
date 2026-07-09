<?php
use PHPUnit\Framework\TestCase;
use App\Middleware\CsrfMiddleware;

class CsrfMiddlewareTest extends TestCase
{
    private CsrfMiddleware $middleware;
    private bool $nextCalled;

    protected function setUp(): void
    {
        $this->middleware = new CsrfMiddleware();
        $this->nextCalled = false;

        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SESSION = [];
        $GLOBALS['_PHP_REDIRECT'] = null;
    }

    private function callNext(): void
    {
        $this->nextCalled = true;
    }

    private function runMiddleware(array $config): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'csrf_test_');
        file_put_contents($tmpFile, json_encode($config));
        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../middleware_runner.php') . ' ' . escapeshellarg($tmpFile) . ' 2>NUL';
        exec($cmd, $output, $exitCode);
        unlink($tmpFile);
        return json_decode(implode('', $output), true) ?? ['error' => 'No output'];
    }

    // ─── GET requests ───────────────────────────────────────────────────────

    public function testGetRequestSkipsCsrfCheck(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->middleware->__invoke(fn() => $this->callNext());
        $this->assertTrue($this->nextCalled);
    }

    public function testGetRequestDoesNotRedirect(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->middleware->__invoke(fn() => $this->callNext());
        $this->assertNull($GLOBALS['_PHP_REDIRECT']);
    }

    // ─── POST with valid CSRF token ────────────────────────────────────────

    public function testPostWithValidTokenProceeds(): void
    {
        $token = 'valid-token-123';
        $result = $this->runMiddleware([
            'middleware' => 'CsrfMiddleware',
            'session' => ['csrf_tokens' => [$token => time()]],
            'post' => ['csrf_token' => $token],
            'server' => ['REQUEST_METHOD' => 'POST'],
        ]);

        $this->assertTrue($result['next_called']);
        $this->assertNull($result['redirect']);
    }

    public function testPostWithValidTokenConsumesToken(): void
    {
        // Test token consumption within a single subprocess by running
        // the middleware twice in sequence via a pipeline
        $token = 'single-use-token';
        $result = $this->runMiddleware([
            'middleware' => 'CsrfMiddleware',
            'args' => [],
            'session' => ['csrf_tokens' => [$token => time()]],
            'post' => ['csrf_token' => $token],
            'server' => ['REQUEST_METHOD' => 'POST'],
            'run_twice' => true,
        ]);

        // First call should succeed, second should fail (token consumed)
        $this->assertTrue($result['first_called']);
        $this->assertFalse($result['second_called']);
    }

    // ─── POST with invalid CSRF token ──────────────────────────────────────

    public function testPostWithInvalidTokenRedirects(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'CsrfMiddleware',
            'session' => ['csrf_tokens' => []],
            'post' => ['csrf_token' => 'bad-token'],
            'server' => ['REQUEST_METHOD' => 'POST'],
        ]);

        $this->assertFalse($result['next_called']);
        $this->assertNotNull($result['redirect']);
    }

    public function testPostWithEmptyTokenRedirects(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'CsrfMiddleware',
            'session' => ['csrf_tokens' => []],
            'post' => ['csrf_token' => ''],
            'server' => ['REQUEST_METHOD' => 'POST'],
        ]);

        $this->assertFalse($result['next_called']);
        $this->assertNotNull($result['redirect']);
    }

    public function testPostWithMissingTokenRedirects(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'CsrfMiddleware',
            'session' => ['csrf_tokens' => []],
            'post' => [],
            'server' => ['REQUEST_METHOD' => 'POST'],
        ]);

        $this->assertFalse($result['next_called']);
        $this->assertNotNull($result['redirect']);
    }

    public function testPostWithInvalidTokenSetsFlashError(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'CsrfMiddleware',
            'session' => ['csrf_tokens' => []],
            'post' => ['csrf_token' => 'invalid'],
            'server' => ['REQUEST_METHOD' => 'POST'],
        ]);

        $this->assertEquals('error', $result['flash']['type']);
        $this->assertEquals('Erreur de sécurité.', $result['flash']['message']);
    }
}
