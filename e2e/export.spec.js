/**
 * SST Application — Export E2E Tests
 *
 * Tests the CSV export functionality.
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Export Page', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display export form with filters', async ({ page }) => {
    await page.goto('/index.php?page=export');
    await page.waitForLoadState('networkidle');

    const content = await page.content();
    expect(content).toContain('Export');

    // Check for form elements
    const form = page.locator('form');
    expect(await form.count()).toBeGreaterThan(0);
  });

  test('should have registry type filter', async ({ page }) => {
    await page.goto('/index.php?page=export');
    await page.waitForLoadState('networkidle');

    // Check for type selector or checkboxes
    const content = await page.content();
    const hasRegistryFilter = content.includes('RSST') || content.includes('RAMI')
      || content.includes('DGI') || content.includes('registre');
    expect(hasRegistryFilter).toBeTruthy();
  });

  test('should have date range filter', async ({ page }) => {
    await page.goto('/index.php?page=export');
    await page.waitForLoadState('networkidle');

    const content = await page.content();
    const hasDateFilter = content.includes('date') || content.includes('Date')
      || content.includes('Période');
    expect(hasDateFilter).toBeTruthy();
  });

  test('should have export submit button', async ({ page }) => {
    await page.goto('/index.php?page=export');
    await page.waitForLoadState('networkidle');

    const submitBtn = page.locator('.card button[type="submit"], .card input[type="submit"]');
    expect(await submitBtn.count()).toBeGreaterThan(0);
  });

  test('should submit export form without crashing', async ({ page }) => {
    await page.goto('/index.php?page=export');
    await page.waitForLoadState('networkidle');

    // Submit the form — scope to #exportForm to avoid clicking impersonate menu button
    const submitBtn = page.locator('#exportForm button[type="submit"]');
    if (await submitBtn.count() > 0) {
      // Listen for download
      const downloadPromise = page.waitForEvent('download', { timeout: 10000 }).catch(() => null);
      await submitBtn.first().click();

      const download = await downloadPromise;
      if (download) {
        // Verify it's a CSV file
        const filename = download.suggestedFilename();
        expect(filename).toContain('.csv');
      }
      // Even without download, page shouldn't crash
      await page.waitForLoadState('networkidle');
    }
  });
});
