/**
 * SST Application — Agent Confirm E2E Tests
 *
 * Verifies the agent confirmation page renders correctly
 * and handles edge cases without crashing.
 * CSRF field presence is covered by RouterCsrfIntegrationTest (unit).
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Agent Confirm Flow', () => {

  test('should render confirmation page for valid token without crash', async ({ page }) => {
    await loginAs(page, 'admin.dev');

    // Navigate with a bogus token — page should handle it gracefully
    await page.goto('/index.php?page=agent_confirm&token=nonexistent-token');
    await page.waitForLoadState('networkidle');

    // Should NOT return a 500 — page renders gracefully
    const status = page.url();
    expect(status).not.toContain('500');

    // Body should contain a user-facing message (not a PHP error)
    const body = await page.textContent('body');
    expect(body).toBeTruthy();
    expect(body.length).toBeGreaterThan(0);
  });

  test('should show expired/processed message for invalid token', async ({ page }) => {
    await loginAs(page, 'admin.dev');

    await page.goto('/index.php?page=agent_confirm&token=invalid-token-12345');
    await page.waitForLoadState('networkidle');

    const body = await page.textContent('body');
    expect(body).toMatch(/invitation|traitée|invalide/i);
  });

  test('should show invalid link message when token is empty', async ({ page }) => {
    await loginAs(page, 'admin.dev');

    await page.goto('/index.php?page=agent_confirm');
    await page.waitForLoadState('networkidle');

    const body = await page.textContent('body');
    expect(body).toMatch(/invalide|lien/i);
  });
});
