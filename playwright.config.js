// @ts-check
const { defineConfig } = require('@playwright/test');
const path = require('path');
const os = require('os');

// Cross-platform: detect OS and build appropriate PHP command
const isWindows = process.platform === 'win32';
// PHP binary resolved via PATH (portable across machines/CI runners).
// Override with the SST_PHP_BINARY env var if a specific build must be targeted.
const phpBinary = process.env.SST_PHP_BINARY || 'php';
const sessionPath = isWindows ? path.join(__dirname, 'data', 'sessions') : '/tmp/php_sessions';
const routerPath = path.join(__dirname, 'public', 'router.php').replace(/\\/g, '/');

// E2E tests use an isolated temp DB, never the real data/sst.db
const e2eDbPath = isWindows
  ? '%TEMP%\\sst-e2e-test.db'
  : '/tmp/sst-e2e-test.db';

// Disable Xdebug for E2E tests (causes timeouts)
const xdebugFlag = isWindows ? '-d xdebug.mode=off' : '-d xdebug.mode=off';

// PHP error_log destination for the E2E dev server.
// - Overridable per environment through SST_E2E_ERROR_LOG (empty/blank
//   counts as unset).
// - Portable fallback computed by Node: the OS temp dir + a fixed file
//   name — never a hard-coded /tmp path (POSIX-only, silently breaks error
//   capture on Windows).
// - A value that cannot be quoted safely for BOTH target shells is a
//   configuration error: fail fast with a clear message instead of
//   emitting a broken webServer command. Rejected: `"` (terminates the
//   cmd.exe double-quoted argument), `%` (cmd.exe variable expansion
//   fires even inside double quotes), `'` (terminates the /bin/sh
//   single-quoted argument) and control characters.
const errorLogOverride = process.env.SST_E2E_ERROR_LOG ?? '';
const errorLogValue =
  errorLogOverride.trim() !== ''
    ? errorLogOverride
    : path.join(os.tmpdir(), 'php-error.log');
if (/["'%\r\n\u0000]/.test(errorLogValue)) {
  throw new Error(
    'SST_E2E_ERROR_LOG contains a character that cannot be quoted safely ' +
      'for the webServer command (double quote, single quote, % or a ' +
      'control character): ' +
      JSON.stringify(errorLogValue)
  );
}
// D1: on Windows the value lands inside the double-quoted cmd.exe argument
// `-d "error_log=…"`, which the PHP process then re-parses with the MSVC
// CRT command-line rules: a run of backslashes directly before the closing
// `"` is consumed as escape sequences (2n → n backslashes and the quote
// closes; 2n+1 → n backslashes and a LITERAL quote that stays open). An
// odd trailing run therefore escapes the closing quote — the argument
// swallows the rest of the command line and the error_log destination is
// lost entirely. An even run keeps the command structurally sound, but the
// same parser silently halves the value AND a path ending with a separator
// names a directory, which PHP can never open as a log file — so ANY
// trailing backslash loses the log one way or the other and is rejected
// here. Paths that merely CONTAIN backslashes (any normal Windows path)
// and the os.tmpdir() fallback (path.join never emits a trailing
// separator) are unaffected. The POSIX branch is out of scope: single
// quotes keep every backslash literal, so nothing can escape there.
if (isWindows && /\\+$/.test(errorLogValue)) {
  throw new Error(
    'SST_E2E_ERROR_LOG ends with a backslash, which cannot be quoted ' +
      'safely for the Windows webServer command: an odd trailing run of ' +
      'backslashes escapes the closing double quote of the -d ' +
      '"error_log=…" argument (the rest of the command line is swallowed ' +
      'and the log is lost), while an even run is halved by the Windows ' +
      'command-line parser and names a directory, not a writable log ' +
      'file. Point SST_E2E_ERROR_LOG at a log FILE path without trailing ' +
      'backslashes: ' +
      JSON.stringify(errorLogValue)
  );
}
// Quoted for the target shell so spaces survive: cmd.exe keeps everything
// literal inside double quotes except `"` and `%` (both rejected above);
// /bin/sh single quotes keep every character literal except `'`.
const phpErrorLogFlag = isWindows
  ? `-d "error_log=${errorLogValue}"`
  : `-d 'error_log=${errorLogValue}'`;

// Force strictly sequential test files only in CI (process.env.CI, the
// de-facto standard env var set by GitHub Actions and virtually every CI
// provider — also Playwright's own documented convention for this exact
// setting). That's deliberately NOT based on CPU count: the GitHub Actions
// Linux runner this project's workflow uses actually has several cores, not
// one — a CPU-count check would have also undone this fix there. In CI,
// several spec files running in separate workers, all hammering the single-
// threaded PHP dev server and the same shared SQLite database at once,
// caused timeouts and cross-test state leakage. On a real dev/test/prod
// machine (which is what CI is NOT), leave Playwright's own default worker
// count — don't force everyone down to 1 worker for a problem that's
// specific to the ephemeral CI runner.
const inCi = !!process.env.CI;

/**
 * Playwright configuration for SST application E2E tests.
 * 
 * Uses the PHP built-in development server with our custom router.
 * The server is started automatically by Playwright before tests.
 */
module.exports = defineConfig({
  testDir: './e2e',
  fullyParallel: false,          // PHP built-in server is single-threaded
  // fullyParallel only serializes tests WITHIN a file — different spec
  // files can still be dispatched to separate workers running at the same
  // time. Safe in practice on a real dev/test/prod machine; in CI (see
  // inCi above), several files hammering the same single-threaded PHP dev
  // server AND the same shared SQLite database concurrently caused
  // timeouts and tests seeing state left behind by another worker.
  workers: inCi ? 1 : undefined,
  retries: 0,
  timeout: 30000,
  expect: { timeout: 10000 },
  failOnFirstFailure: true,
  // 'github' adds inline failure annotations on the commit/PR — visible via
  // GET /repos/.../check-runs/{id}/annotations without downloading the full
  // HTML report artifact (which can run tens of MB and isn't reachable from
  // every environment that might need to diagnose a CI failure).
  reporter: process.env.CI
    ? [['list'], ['github'], ['html', { open: 'never' }]]
    : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: 'http://127.0.0.1:8850',
    headless: true,
    viewport: { width: 1280, height: 720 },
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'firefox',
      use: { browserName: 'firefox' },
    },
    {
      // channel: 'msedge' launches the real, system-installed Microsoft Edge
      // (not Playwright's bundled Chromium) — required for Windows Integrated
      // Authentication (NTLM/Kerberos) SSO against IIS to work automatically,
      // since that relies on the OS credential cache that only a genuine,
      // domain-aware Edge process picks up. Only meaningful when run on the
      // actual Windows/IIS machine, against a domain-joined session — not
      // reproducible in this sandbox. See update_sst.ps1 for how this project
      // is invoked in the quality gate.
      name: 'msedge',
      use: { browserName: 'chromium', channel: 'msedge' },
    },
  ],
  webServer: {
    // Use PHP default session path (/tmp) — no need to create custom directory.
    // Session files are automatically cleaned up by PHP's GC.
    command: isWindows
      ? `set "SST_DB_PATH=${e2eDbPath}" && set "DEV_MODE=1" && ${phpBinary} -d session.auto_start=0 -d display_errors=1 ${phpErrorLogFlag} ${xdebugFlag} -S 127.0.0.1:8850 "${routerPath}"`
      : `SST_DB_PATH=${e2eDbPath} DEV_MODE=1 ${phpBinary} -d session.auto_start=0 -d display_errors=1 ${phpErrorLogFlag} ${xdebugFlag} -S 127.0.0.1:8850 "${routerPath}"`,
    port: 8850,
    reuseExistingServer: true,
    timeout: 30000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
});
