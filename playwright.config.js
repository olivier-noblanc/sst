import { defineConfig } from '@playwright/test';

/**
 * Playwright configuration for SST application E2E tests.
 * 
 * Uses the PHP built-in development server with our custom router.
 * The server is started automatically by Playwright before tests.
 */
export default defineConfig({
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
    command: '/home/z/my-project/tmp/php-root/usr/bin/php8.4 -c /home/z/my-project/tmp/php.ini -S 127.0.0.1:8850 /home/z/my-project/sst/public/router.php',
    port: 8850,
    reuseExistingServer: true,
    timeout: 10000,
  },
});
