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

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

class PlaywrightConfigWindowsCommandTest extends TestCase
{
    /**
     * Prefix node prints before the JSON-encoded webServer.command, so the
     * PHP side can locate the command line wherever it lands in the
     * captured output — stderr noise (`2>&1`) may push it away from
     * position 0, and a positional read ($output[0]) would silently parse
     * a warning line instead.
     */
    private const NODE_OUTPUT_MARKER = 'SST_E2E_CMD_JSON:';

    /**
     * Every `set` assignment of the Windows webServer command must capture
     * a value without a trailing (or leading) space — i.e. it must use the
     * quoted `set "VAR=value"` form, not the bare `set VAR=value &&` form.
     */
    public function testWindowsWebServerSetAssignmentsCaptureNoTrailingSpace(): void
    {
        $command = $this->getWindowsWebServerCommand();

        $assignments = $this->extractSetAssignments($command);

        // N4: every `set` — known or added in the future — must use the
        // quoted form, so a new unquoted assignment cannot slip through
        // silently just because no assertion names it explicitly.
        $unquoted = $this->findUnquotedSetVariables($command);
        $this->assertSame(
            [],
            $unquoted,
            'Every `set` in the Windows webServer command must use the '
            . 'quoted `set "VAR=value"` form — an unquoted '
            . '`set VAR=value &&` makes cmd.exe capture the trailing space '
            . 'before `&&` into the value. Unquoted variables found: '
            . implode(', ', $unquoted)
        );

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
     * N4: a FUTURE `set NEWVAR=value &&` added to the Windows webServer
     * command must not pass silently. The main test above only asserts
     * SST_DB_PATH / DEV_MODE by name — so this synthetic test pins the
     * detection itself: every unquoted `set` assignment must be reported,
     * whatever the variable name.
     */
    public function testUnquotedSetVariableIsDetectedWhateverItsName(): void
    {
        $command = 'set "KNOWN=1" && set NEWVAR=value && set "OTHER=2" '
            . '&& php -S 127.0.0.1:8850 router.php';

        $this->assertSame(
            ['NEWVAR'],
            $this->findUnquotedSetVariables($command),
            'A new unquoted `set VAR=value &&` must be flagged, not silently '
            . 'accepted (cmd.exe captures a trailing space in the value).'
        );
    }

    /**
     * N4: the command must be extracted from node's output even when the
     * process emitted stderr noise first (`2>&1` interleaves warnings
     * before the JSON line) — the extraction must not depend on a fixed
     * output position like $output[0].
     */
    public function testCommandExtractionSurvivesNodeStderrNoise(): void
    {
        $expected = 'set "SST_DB_PATH=%TEMP%\\sst-e2e.db" && set "DEV_MODE=1" '
            . '&& php -S 127.0.0.1:8850 router.php';

        // json_encode mimics exactly what the instrumented script prints
        // for the command: MARKER + a JSON-quoted string on its own line.
        // The stderr warnings are interleaved BEFORE it by `2>&1`.
        $lines = [
            '(node:4242) ExperimentalWarning: Type Stripping is an '
            . 'experimental feature and might change at any time',
            '(Use `node --trace-warnings ...` to show where the warning '
            . 'was created)',
            self::NODE_OUTPUT_MARKER . json_encode($expected),
        ];

        $this->assertSame($expected, $this->extractCommandFromNodeOutput($lines));
    }

    /**
     * N4: if node's output does not contain the command at all, the
     * helper must fail loudly (with the full output in the message)
     * instead of returning null/garbage downstream.
     */
    public function testCommandExtractionFailsLoudlyWhenCommandMissing(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->extractCommandFromNodeOutput([
            'some unexpected node output',
            'with no command line in it',
        ]);
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
            . "console.log('" . self::NODE_OUTPUT_MARKER . "' + JSON.stringify(c.webServer.command));";

        // `2>&1` keeps node's stderr in the captured output so failure
        // messages include it — the marker-based extraction below makes
        // the JSON line's POSITION irrelevant.
        exec('node -e ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            'node -e failed to evaluate playwright.config.js: ' . implode(PHP_EOL, $output)
        );

        return $this->extractCommandFromNodeOutput($output);
    }

    /**
     * Locate the marker-prefixed JSON line among node's output lines and
     * decode it. Fails loudly (with the full output) when the marker is
     * missing or the payload is not a JSON string — never returns garbage
     * downstream. Immune to stderr warnings appearing before/after the
     * command line.
     *
     * @param string[] $lines raw output lines (stdout+stderr interleaved)
     */
    private function extractCommandFromNodeOutput(array $lines): string
    {
        $markerLines = array_values(array_filter(
            $lines,
            fn (string $line): bool => str_starts_with($line, self::NODE_OUTPUT_MARKER)
        ));

        if ($markerLines === []) {
            $this->fail(
                'node output did not contain the ' . self::NODE_OUTPUT_MARKER
                . ' marker line. Full output: ' . implode(PHP_EOL, $lines)
            );
        }

        $decoded = json_decode(
            substr($markerLines[0], strlen(self::NODE_OUTPUT_MARKER)),
            true
        );

        if (!is_string($decoded)) {
            $this->fail(
                'Marker line payload was not a JSON string. Line: '
                . $markerLines[0]
            );
        }

        return $decoded;
    }

    /**
     * Report every `set` assignment of the command that uses the UNQUOTED
     * `set VAR=value` form — the form for which cmd.exe assigns everything
     * after `=` up to `&&` (trailing space included) to the variable.
     *
     * The regex is disjoint from the quoted form: `set "VAR=..."` cannot
     * match because `"` is outside [A-Za-z_]. This is the single detection
     * point used both by the synthetic test (any variable name) and by the
     * real-config assertion, so a future unquoted assignment is flagged
     * whatever its name.
     *
     * @return string[] unquoted assignment variable names, in order
     */
    private function findUnquotedSetVariables(string $command): array
    {
        $names = [];
        foreach (preg_split('/&&/', $command) ?: [] as $segment) {
            if (preg_match('/^\s*set\s+([A-Za-z_]+)=/', $segment, $m) === 1) {
                $names[] = $m[1];
            }
        }

        return $names;
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