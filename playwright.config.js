// @ts-check
const { defineConfig } = require('@playwright/test');
const path = require('path');

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
  retries: 1,
  timeout: 30000,
  expect: { timeout: 10000 },
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
    command: isWindows
      ? `set SST_DB_PATH=${e2eDbPath} && set DEV_MODE=1 && mkdir -p "${sessionPath}" && ${phpBinary} -d session.auto_start=0 -d "session.save_path=${sessionPath}" -d display_errors=1 ${xdebugFlag} -S 127.0.0.1:8850 "${routerPath}"`
      : `mkdir -p "${sessionPath}" && SST_DB_PATH=${e2eDbPath} DEV_MODE=1 ${phpBinary} -d session.auto_start=0 -d "session.save_path=${sessionPath}" -d display_errors=1 ${xdebugFlag} -S 127.0.0.1:8850 "${routerPath}"`,
    port: 8850,
    reuseExistingServer: true,
    timeout: 30000,
  },
});
