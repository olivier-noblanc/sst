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
     * The PHP error_log destination of the E2E dev server must never be
     * hard-coded — /tmp/php-error.log is a POSIX-only path that silently
     * breaks error capture on Windows (the directory does not exist, PHP
     * falls back to stderr/syslog). This test pins the SOURCE: the literal
     * must not appear anywhere in playwright.config.js. The runtime value
     * itself is covered by the fallback test (portable os.tmpdir() oracle,
     * both branches) and the override test (SST_E2E_ERROR_LOG).
     *
     * Note: the assertion is deliberately source-only. A runtime command
     * check would false-fail on POSIX runners where os.tmpdir() legitimately
     * resolves to /tmp — making the COMPUTED fallback byte-identical to the
     * old literal, which is correct behavior, not hard-coding.
     */
    public function testErrorLogIsNotHardcodedToTmpPath(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/playwright.config.js');
        $this->assertIsString($source);

        $this->assertStringNotContainsString(
            '/tmp/php-error.log',
            $source,
            'playwright.config.js must not hard-code /tmp/php-error.log — the '
            . 'error_log value must be SST_E2E_ERROR_LOG-overridable with a '
            . 'portable os.tmpdir() fallback.'
        );
    }

    /**
     * Without SST_E2E_ERROR_LOG, both platform branches must fall back to
     * the portable value computed by Node itself: path.join(os.tmpdir(),
     * 'php-error.log'). The oracle is computed by the harness's own Node
     * runtime from OS primitives — path semantics are never re-implemented
     * in PHP.
     */
    public function testErrorLogFallsBackToPortableTempDirOnBothBranches(): void
    {
        foreach (['win32', 'linux'] as $platform) {
            $snapshot = $this->getConfigSnapshot($platform);
            $value = $this->extractErrorLogValue($snapshot['command'], $platform);

            $this->assertSame(
                $snapshot['fallback'],
                $value,
                'The ' . $platform . ' webServer command must inject the '
                . 'portable os.tmpdir()-based error_log fallback. Expected: '
                . var_export($snapshot['fallback'], true)
                . ' — found: ' . var_export($value, true)
            );
        }
    }

    /**
     * SST_E2E_ERROR_LOG must override the fallback verbatim in BOTH
     * branches, with branch-appropriate shell quoting (cmd.exe: double
     * quotes; /bin/sh: single quotes). The override contains spaces and
     * backslashes — exactly the case where unquoted interpolation splits
     * the argument on spaces or captures a trailing space.
     */
    public function testErrorLogEnvOverrideIsInjectedVerbatimWithBranchQuoting(): void
    {
        $override = 'C:\\E2E Temp Dirs\\custom log.log';

        foreach (['win32', 'linux'] as $platform) {
            $snapshot = $this->getConfigSnapshot($platform, $override);

            $this->assertStringNotContainsString(
                '/tmp/php-error.log',
                $snapshot['command'],
                'An SST_E2E_ERROR_LOG override must replace the fallback '
                . 'entirely (' . $platform . ' branch).'
            );

            $value = $this->extractErrorLogValue($snapshot['command'], $platform);
            $this->assertSame(
                $override,
                $value,
                'The ' . $platform . ' webServer command must inject the '
                . 'SST_E2E_ERROR_LOG value verbatim (spaces preserved). '
                . 'Expected: ' . var_export($override, true)
                . ' — found: ' . var_export($value, true)
            );

            // The quoted argument must be closed and followed by the next
            // `-d` flag: a value that leaked a trailing space, left an
            // unclosed quote or a shell operator would break this shape.
            $this->assertSame(
                1,
                preg_match(
                    $platform === 'win32'
                        ? '/ -d "error_log=[^"]*" -d /'
                        : "/ -d 'error_log=[^']*' -d /",
                    $snapshot['command']
                ),
                'The error_log argument must be a single, properly closed '
                . 'and space-delimited quoted argument in the ' . $platform
                . ' command. Command: ' . $snapshot['command']
            );

            $this->assertSame(
                rtrim($snapshot['command']),
                $snapshot['command'],
                'The ' . $platform . ' webServer command must not end with '
                . 'a stray space.'
            );
        }
    }

    /**
     * A value that cannot be quoted safely for both cmd.exe (double quotes:
     * `"` would close them, `%` would trigger variable expansion) and
     * /bin/sh (single quotes: `'` would terminate them), or that carries
     * control characters, must make the config fail fast with a clear error
     * naming SST_E2E_ERROR_LOG — never emit a silently broken webServer
     * command.
     */
    public function testErrorLogUnsafeOverrideFailsFast(): void
    {
        $run = $this->runNodeConfigEval('win32', 'C:\\bro"ken%PATH%\\log.log');

        $this->assertNotSame(
            0,
            $run['exitCode'],
            'An unsafe SST_E2E_ERROR_LOG value must abort the config '
            . 'evaluation (non-zero exit), not produce a broken command. '
            . 'Output: ' . implode(PHP_EOL, $run['lines'])
        );

        $this->assertStringContainsString(
            'SST_E2E_ERROR_LOG',
            implode(PHP_EOL, $run['lines']),
            'The fail-fast error must name the offending environment variable.'
        );
    }

    /**
     * D1: on Windows the error_log value is emitted inside the cmd.exe
     * double-quoted argument `-d "error_log=…"`, which the PHP process then
     * re-parses with the MSVC CRT command-line rules: a run of backslashes
     * immediately before the closing `"` is consumed as escape sequences —
     * an ODD trailing run (2n+1 backslashes) collapses to n backslashes
     * followed by a LITERAL quote, so the quote never closes, the argument
     * swallows the rest of the command line and the error_log destination
     * is lost entirely. Such an override must be rejected with a non-zero
     * exit and an error naming SST_E2E_ERROR_LOG — never emit a silently
     * broken webServer command.
     */
    public function testErrorLogOverrideEndingWithOddTrailingBackslashIsRejectedOnWin32(): void
    {
        // PHP single-quoted literal → C:\E2E Logs\ (ONE trailing backslash).
        $run = $this->runNodeConfigEval('win32', 'C:\\E2E Logs\\');

        $this->assertNotSame(
            0,
            $run['exitCode'],
            'A win32 SST_E2E_ERROR_LOG value ending with an odd number of '
            . 'trailing backslashes escapes the closing double quote of the '
            . '`-d "error_log=…"` argument (MSVC CRT parsing) and must abort '
            . 'the config evaluation, not produce a broken command. '
            . 'Output: ' . implode(PHP_EOL, $run['lines'])
        );

        $this->assertStringContainsString(
            'SST_E2E_ERROR_LOG',
            implode(PHP_EOL, $run['lines']),
            'The fail-fast error must name the offending environment variable.'
        );
    }

    /**
     * D1, even trailing run: a value ending with TWO (or any even number
     * of) backslashes keeps the command structurally sound — the MSVC CRT
     * collapses 2n trailing backslashes to n and the closing quote still
     * closes — but the destination is then silently HALVED by the same
     * parser, and a path ending with a separator names a directory, which
     * PHP can never open as an error_log file. Accepting it would trade
     * the explicit fail-fast for a silently lost log (the exact failure
     * class D1 targets), so ANY trailing-backslash run is rejected on
     * win32. The POSIX branch is deliberately unaffected: single quotes
     * keep backslashes literal (see
     * testErrorLogTrailingBackslashOverrideIsAcceptedOnPosix).
     */
    public function testErrorLogOverrideEndingWithTwoTrailingBackslashesIsRejectedOnWin32(): void
    {
        // PHP single-quoted literal → C:\E2E Logs\\ (TWO trailing backslashes).
        $run = $this->runNodeConfigEval('win32', 'C:\\E2E Logs\\\\');

        $this->assertNotSame(
            0,
            $run['exitCode'],
            'A win32 SST_E2E_ERROR_LOG value ending with an even run of '
            . 'trailing backslashes is halved by the MSVC CRT parser and '
            . 'names a directory, not a writable log file — it must abort '
            . 'the config evaluation too. Output: '
            . implode(PHP_EOL, $run['lines'])
        );

        $this->assertStringContainsString(
            'SST_E2E_ERROR_LOG',
            implode(PHP_EOL, $run['lines']),
            'The fail-fast error must name the offending environment variable.'
        );
    }

    /**
     * D1 scope guard: the trailing-backslash rejection must stay
     * Windows-only. On the POSIX branch the value is single-quoted, and
     * /bin/sh keeps every character literal — a trailing backslash cannot
     * escape anything, so such an override (harmless there) must still be
     * accepted and injected VERBATIM. This pins the POSIX branches against
     * an accidental widening of the new check.
     */
    public function testErrorLogTrailingBackslashOverrideIsAcceptedOnPosix(): void
    {
        // PHP single-quoted literal → /tmp/e2e logs\ (one trailing backslash).
        $override = '/tmp/e2e logs\\';
        $snapshot = $this->getConfigSnapshot('linux', $override);

        $this->assertSame(
            $override,
            $this->extractErrorLogValue($snapshot['command'], 'linux'),
            'The POSIX branch single-quotes the error_log value, so a '
            . 'trailing backslash is harmless there and must be injected '
            . 'verbatim. Expected: ' . var_export($override, true)
        );
    }

    /**
     * Load the real playwright.config.js with process.platform
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
     * Locate the marker-prefixed line among node's output lines and return
     * its raw JSON payload. Fails loudly (with the full output) when the
     * marker is missing. Immune to stderr warnings appearing before/after
     * the payload line.
     *
     * @param string[] $lines raw output lines (stdout+stderr interleaved)
     */
    private function findMarkerPayload(array $lines): string
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

        return substr($markerLines[0], strlen(self::NODE_OUTPUT_MARKER));
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
        $payload = $this->findMarkerPayload($lines);

        $decoded = json_decode($payload, true);

        if (!is_string($decoded)) {
            $this->fail(
                'Marker line payload was not a JSON string. Line: '
                . self::NODE_OUTPUT_MARKER . $payload
            );
        }

        return $decoded;
    }

    /**
     * Evaluate the real playwright.config.js with process.platform mocked
     * to $platform, and SST_E2E_ERROR_LOG explicitly removed (null) or set
     * to $errorLogOverride. The variable is manipulated INSIDE the node
     * process (delete / JSON-encoded assignment), so the result is
     * deterministic whatever the invoking shell exports.
     *
     * Returns the webServer.command plus `fallback`: the expected portable
     * error_log value computed by the same Node runtime from OS primitives
     * (path.join(os.tmpdir(), 'php-error.log')) — the oracle for the
     * config's own fallback, without re-implementing path semantics in PHP.
     *
     * @return array{command: string, fallback: string}
     */
    private function getConfigSnapshot(string $platform, ?string $errorLogOverride = null): array
    {
        $run = $this->runNodeConfigEval($platform, $errorLogOverride);

        $this->assertSame(
            0,
            $run['exitCode'],
            'node -e failed to evaluate playwright.config.js: ' . implode(PHP_EOL, $run['lines'])
        );

        $decoded = json_decode($this->findMarkerPayload($run['lines']), true);

        $this->assertIsArray($decoded, 'Marker payload was not a JSON object. Payload: '
            . $this->findMarkerPayload($run['lines']));
        $this->assertArrayHasKey('command', $decoded);
        $this->assertArrayHasKey('fallback', $decoded);
        $this->assertIsString($decoded['command']);
        $this->assertIsString($decoded['fallback']);

        return ['command' => $decoded['command'], 'fallback' => $decoded['fallback']];
    }

    /**
     * Build and run the instrumented `node -e` evaluation of
     * playwright.config.js (see getConfigSnapshot for the payload contract),
     * WITHOUT asserting on the exit code — callers decide what a non-zero
     * exit means (e.g. a deliberate fail-fast on an unsafe override).
     *
     * @param string|null $errorLogOverride null removes the variable from
     *        the node process; a string assigns it verbatim (passed to the
     *        script base64-encoded — a byte-safe transport that cannot be
     *        mangled by cmd.exe quoting on the `node -e` command line).
     *
     * @return array{exitCode: int, lines: string[]}
     */
    private function runNodeConfigEval(string $platform, ?string $errorLogOverride): array
    {
        $root = dirname(__DIR__, 2);
        $configPath = $root . DIRECTORY_SEPARATOR . 'playwright.config.js';
        $this->assertFileExists($configPath);

        // Forward slashes: Node resolves them fine on Windows, and they
        // avoid backslash-escaping issues inside the quoted JS one-liner.
        $jsConfigPath = str_replace('\\', '/', $configPath);

        $envStmt = $errorLogOverride === null
            ? 'delete process.env.SST_E2E_ERROR_LOG;'
            : "process.env.SST_E2E_ERROR_LOG = Buffer.from('"
                . base64_encode($errorLogOverride) . "','base64').toString('utf8');";

        $script =
            "Object.defineProperty(process,'platform',{value:'" . $platform . "'});"
            . $envStmt
            . "const os=require('os');const path=require('path');"
            . "const c=require('" . $jsConfigPath . "');"
            . "console.log('" . self::NODE_OUTPUT_MARKER . "' + JSON.stringify({"
            . 'command:c.webServer.command,'
            . "fallback:path.join(os.tmpdir(),'php-error.log')"
            . '}));';

        // `2>&1` keeps node's stderr in the captured output so failure
        // messages include it — the marker-based extraction makes the JSON
        // line's POSITION irrelevant.
        exec('node -e ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);

        return ['exitCode' => $exitCode, 'lines' => $output];
    }

    /**
     * Extract the error_log INI value from the command's `-d` argument,
     * according to the branch's shell quoting (cmd.exe: double quotes;
     * /bin/sh: single quotes). Fails loudly when no properly quoted
     * argument is found.
     */
    private function extractErrorLogValue(string $command, string $platform): string
    {
        $pattern = $platform === 'win32'
            ? '/-d "error_log=([^"]*)"/'
            : "/-d 'error_log=([^']*)'/";

        $this->assertSame(
            1,
            preg_match($pattern, $command, $m),
            'No quoted `-d error_log=…` argument found in the ' . $platform
            . ' webServer command. Command: ' . $command
        );

        return $m[1];
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