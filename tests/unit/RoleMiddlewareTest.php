<?php
use PHPUnit\Framework\TestCase;
use App\Middleware\RoleMiddleware;

class RoleMiddlewareTest extends TestCase
{
    private bool $nextCalled;

    protected function setUp(): void
    {
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
        $tmpFile = tempnam(sys_get_temp_dir(), 'role_test_');
        file_put_contents($tmpFile, json_encode($config));
        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../middleware_runner.php') . ' ' . escapeshellarg($tmpFile) . ' 2>NUL';
        exec($cmd, $output, $exitCode);
        unlink($tmpFile);
        return json_decode(implode('', $output), true) ?? ['error' => 'No output'];
    }

    // ─── User has matching role ─────────────────────────────────────────────

    public function testUserWithMatchingRoleProceeds(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'RoleMiddleware',
            'args' => [['superviseur']],
            'session' => ['user' => ['role' => 'superviseur']],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertTrue($result['next_called']);
        $this->assertNull($result['redirect']);
    }

    public function testUserWithOneOfMultipleRolesProceeds(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'RoleMiddleware',
            'args' => [['superviseur', 'chsct']],
            'session' => ['user' => ['role' => 'chsct']],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertTrue($result['next_called']);
        $this->assertNull($result['redirect']);
    }

    public function testUserWithFirstRoleInListProceeds(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'RoleMiddleware',
            'args' => [['agent', 'superviseur']],
            'session' => ['user' => ['role' => 'agent']],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertTrue($result['next_called']);
        $this->assertNull($result['redirect']);
    }

    // ─── User does NOT have matching role ───────────────────────────────────

    public function testUserWithWrongRoleRedirects(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'RoleMiddleware',
            'args' => [['superviseur']],
            'session' => ['user' => ['role' => 'agent']],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertFalse($result['next_called']);
        $this->assertNotNull($result['redirect']);
    }

    public function testUserWithWrongRoleSetsFlashError(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'RoleMiddleware',
            'args' => [['superviseur']],
            'session' => ['user' => ['role' => 'agent']],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertEquals('error', $result['flash']['type']);
        $this->assertEquals('Accès refusé.', $result['flash']['message']);
    }

    // ─── No user session ────────────────────────────────────────────────────

    public function testNoSessionRedirects(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'RoleMiddleware',
            'args' => [['agent']],
            'session' => [],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertFalse($result['next_called']);
        $this->assertNotNull($result['redirect']);
    }

    public function testEmptyRoleArrayAllowsNoOne(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'RoleMiddleware',
            'args' => [[]],
            'session' => ['user' => ['role' => 'superviseur']],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertFalse($result['next_called']);
        $this->assertNotNull($result['redirect']);
    }

    // ─── Role comparison is strict ──────────────────────────────────────────

    public function testRoleComparisonIsStrict(): void
    {
        $result = $this->runMiddleware([
            'middleware' => 'RoleMiddleware',
            'args' => [['Agent']],
            'session' => ['user' => ['role' => 'agent']],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertFalse($result['next_called']);
        $this->assertNotNull($result['redirect']);
    }
}
