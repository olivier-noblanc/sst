// @ts-check
const { defineConfig } = require('@playwright/test');
const path = require('path');

// Cross-platform: detect OS and build appropriate PHP command
const isWindows = process.platform === 'win32';
const phpBinary = isWindows ? 'php' : '/home/z/my-project/tools/php/bin/php';
const sessionPath = isWindows ? path.join(__dirname, 'data', 'sessions') : '/tmp/php_sessions';
const routerPath = path.join(__dirname, 'public', 'router.php').replace(/\\/g, '/');

// On Windows, use 'set DEV_MODE=1 &&' prefix; on Linux, use 'DEV_MODE=1' prefix
const devModePrefix = isWindows ? 'set DEV_MODE=1 &&' : 'DEV_MODE=1';

// Disable Xdebug for E2E tests (causes timeouts)
const xdebugFlag = isWindows ? '-d xdebug.mode=off' : '-d xdebug.mode=off';

/**
 * Playwright configuration for SST application E2E tests.
 * 
 * Uses the PHP built-in development server with our custom router.
 * The server is started automatically by Playwright before tests.
 */
module.exports = defineConfig({
  testDir: './e2e',
  fullyParallel: false,          // PHP built-in server is single-threaded
  retries: 1,
  timeout: 30000,
  expect: { timeout: 10000 },
  reporter: [['list'], ['html', { open: 'never' }]],
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
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
  webServer: {
    command: `${devModePrefix} ${phpBinary} -d session.auto_start=0 -d "session.save_path=${sessionPath}" -d display_errors=1 ${xdebugFlag} -S 127.0.0.1:8850 "${routerPath}"`,
    port: 8850,
    reuseExistingServer: true,
    timeout: 30000,
  },
});
