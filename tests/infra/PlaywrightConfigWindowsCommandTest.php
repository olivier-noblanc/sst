<?php
/**
 * Playwright Config Test — Windows webServer command env assignments
 *
 * Infrastructure test for playwright.config.js (webServer.command, Windows
 * branch). On Windows, Playwright spawns this command through cmd.exe,
 * where the unquoted `set VAR=value &&` form assigns EVERYTHING after `=`
 * up to `&&` — including the trailing space before the operator — to the
 * variable value. A space-polluted SST_DB_PATH then reaches
 * src/config.php:66 (getenv('SST_DB_PATH') → DB_PATH) and breaks SQLite /
 * open_basedir, because the stored path no longer matches the real file.
 *
 * The command must therefore use the quoted cmd form `set "VAR=value"`,
 * which assigns exactly the value between the quotes.
 *
 * MUST STAY OUT OF tests/unit: this test evaluates the real
 * playwright.config.js with `node -e`, which requires a runnable node
 * binary AND node_modules/@playwright/test in the repo. The CI PHPUnit
 * job installs neither node nor npm — its suite (phpunit.xml) scans
 * tests/unit only, precisely so this file is never collected there.
 * It is instead executed by the CI `e2e` job (shard 1/3), which does
 * npm-install Node and the Playwright module before running it.
 */

use PHPUnit\Framework\TestCase;

class PlaywrightConfigWindowsCommandTest extends TestCase
{
    /**
     * Every `set` assignment of the Windows webServer command must capture
     * a value without a trailing (or leading) space — i.e. it must use the
     * quoted `set "VAR=value"` form, not the bare `set VAR=value &&` form.
     */
    public function testWindowsWebServerSetAssignmentsCaptureNoTrailingSpace(): void
    {
        $command = $this->getWindowsWebServerCommand();

        $assignments = $this->extractSetAssignments($command);

        $this->assertArrayHasKey(
            'SST_DB_PATH',
            $assignments,
            'Windows webServer command must assign SST_DB_PATH. Command: ' . $command
        );
        $this->assertNotEmpty(
            $assignments['SST_DB_PATH'],
            'SST_DB_PATH value must not be empty. Command: ' . $command
        );
        $this->assertSame(
            rtrim($assignments['SST_DB_PATH']),
            $assignments['SST_DB_PATH'],
            'SST_DB_PATH would capture a trailing space in cmd.exe (unquoted '
            . '`set VAR=value &&` form) → SQLite/open_basedir failure. '
            . 'Use `set "SST_DB_PATH=..."`. Captured value: '
            . var_export($assignments['SST_DB_PATH'], true)
        );
        $this->assertSame(
            ltrim($assignments['SST_DB_PATH']),
            $assignments['SST_DB_PATH'],
            'SST_DB_PATH value must not start with a space. Captured value: '
            . var_export($assignments['SST_DB_PATH'], true)
        );

        $this->assertArrayHasKey(
            'DEV_MODE',
            $assignments,
            'Windows webServer command must assign DEV_MODE. Command: ' . $command
        );
        $this->assertSame(
            rtrim($assignments['DEV_MODE']),
            $assignments['DEV_MODE'],
            'DEV_MODE would capture a trailing space in cmd.exe (unquoted '
            . '`set VAR=value &&` form). Use `set "DEV_MODE=..."`. '
            . 'Captured value: ' . var_export($assignments['DEV_MODE'], true)
        );
    }

    /**
     * Load the real playwright.config.js module with process.platform
     * mocked to 'win32', and return its webServer.command string. This
     * exercises the actual code under test (the config file), on any host
     * OS — the Windows branch is selected deterministically via the mock.
     */
    private function getWindowsWebServerCommand(): string
    {
        $root = dirname(__DIR__, 2);
        $configPath = $root . DIRECTORY_SEPARATOR . 'playwright.config.js';
        $this->assertFileExists($configPath);

        // Forward slashes: Node resolves them fine on Windows, and they
        // avoid backslash-escaping issues inside the quoted JS one-liner.
        $jsConfigPath = str_replace('\\', '/', $configPath);

        $script =
            "Object.defineProperty(process,'platform',{value:'win32'});"
            . "const c=require('" . $jsConfigPath . "');"
            . "console.log(JSON.stringify(c.webServer.command));";

        exec('node -e ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            'node -e failed to evaluate playwright.config.js: ' . implode(PHP_EOL, $output)
        );
        $command = json_decode($output[0] ?? '', true);
        $this->assertIsString(
            $command,
            'Unexpected node output (expected JSON string of webServer.command): '
            . implode(PHP_EOL, $output)
        );

        return $command;
    }

    /**
     * Parse `set` assignments out of a cmd.exe command string, the way
     * cmd.exe would capture them: splitting on `&&` WITHOUT trimming the
     * segments, so that the unquoted form keeps the trailing space in the
     * captured value (which is exactly the bug being tested for).
     *
     * @return array<string, string> variable name => captured value
     */
    private function extractSetAssignments(string $command): array
    {
        $assignments = [];
        foreach (preg_split('/&&/', $command) ?: [] as $segment) {
            // Quoted form: set "VAR=value" → cmd assigns exactly the value
            // between the quotes; surrounding whitespace is irrelevant.
            if (preg_match('/^\s*set\s+"([A-Za-z_]+)=([^"]*)"\s*$/', $segment, $m) === 1) {
                $assignments[$m[1]] = $m[2];
                continue;
            }
            // Unquoted form: set VAR=value → cmd assigns everything after
            // `=` up to `&&`, trailing space included.
            if (preg_match('/^\s*set\s+([A-Za-z_]+)=(.*)$/s', $segment, $m) === 1) {
                $assignments[$m[1]] = $m[2];
            }
        }

        return $assignments;
    }
}