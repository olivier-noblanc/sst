<?php
/**
 * Runner Smoke Tests — Infrastructure tests for subprocess runners.
 *
 * These tests verify that middleware_runner.php and handler_runner.php
 * produce valid JSON output. If the autoloader or bootstrap breaks,
 * these tests fail first with a clear message instead of 38 cryptic
 * "null is not true" failures scattered across middleware/handler tests.
 */

use PHPUnit\Framework\TestCase;

class RunnerSmokeTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir();
    }

    private function runRunner(string $runnerFile, array $config): array
    {
        $configFile = $this->tmpDir . DIRECTORY_SEPARATOR . uniqid('smoke_') . '.json';
        file_put_contents($configFile, json_encode($config));

        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../' . $runnerFile) . ' ' . escapeshellarg($configFile) . ' 2>NUL';
        exec($cmd, $output, $exitCode);
        unlink($configFile);

        $raw = implode('', $output);
        $decoded = json_decode($raw, true);

        return [
            'raw' => $raw,
            'decoded' => $decoded,
            'exit_code' => $exitCode,
        ];
    }

    public function testMiddlewareRunnerProducesValidJson(): void
    {
        $result = $this->runRunner('middleware_runner.php', [
            'middleware' => 'CsrfMiddleware',
            'session' => [],
            'post' => [],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertNotNull(
            $result['decoded'],
            "middleware_runner.php output was not valid JSON. Raw output: " . var_export($result['raw'], true)
        );
        $this->assertArrayHasKey('next_called', $result['decoded']);
    }

    public function testMiddlewareRunnerHandlesRoleMiddleware(): void
    {
        $result = $this->runRunner('middleware_runner.php', [
            'middleware' => 'RoleMiddleware',
            'args' => [['agent', 'superviseur']],
            'session' => ['user' => ['id' => 1, 'role' => 'agent']],
            'server' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $this->assertNotNull(
            $result['decoded'],
            "middleware_runner.php output was not valid JSON. Raw output: " . var_export($result['raw'], true)
        );
        $this->assertTrue($result['decoded']['next_called']);
    }

    public function testHandlerRunnerProducesValidJson(): void
    {
        $result = $this->runRunner('handler_runner.php', [
            'handler' => 'report_reopen_handler.php',
            'session' => ['user' => ['id' => 1, 'role' => 'superviseur', 'site_id' => 1]],
            'post' => ['csrf_token' => 'test', 'report_uuid' => 'nonexistent-uuid', 'motif_reouverture' => 'Test motif'],
            'server' => ['REQUEST_METHOD' => 'POST'],
            'db_seed' => '',
            'assertions' => [],
        ]);

        $this->assertNotNull(
            $result['decoded'],
            "handler_runner.php output was not valid JSON. Raw output: " . var_export($result['raw'], true)
        );
    }
}
