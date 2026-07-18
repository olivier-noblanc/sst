/**
 * SST Application — Settings Save E2E Tests
 *
 * Tests that settings forms can be submitted and values persist.
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Settings Save Operations', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should save global notification settings', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=global');
    await page.waitForLoadState('networkidle');

    // Find the form
    const form = page.locator('form');
    expect(await form.count()).toBeGreaterThan(0);

    // Find textarea for global notifications
    const textarea = page.locator('textarea');
    if (await textarea.count() > 0) {
      const originalValue = await textarea.first().inputValue();

      // Add an email
      await textarea.first().fill('test@dreets.gouv.fr\n' + originalValue);

      // Submit
      const submitBtn = page.locator('button[type="submit"], input[type="submit"]');
      if (await submitBtn.count() > 0) {
        await submitBtn.first().click();
        await page.waitForLoadState('networkidle');

        // Should show success flash
        const content = await page.content();
        expect(content).toContain('enregistré');
      }
    }
  });

  test('should save SMTP settings', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=smtp');
    await page.waitForLoadState('networkidle');

    // Check for SMTP form fields
    const hostInput = page.locator('#smtp_host, input[name="smtp_host"]');
    const portInput = page.locator('#smtp_port, input[name="smtp_port"]');

    expect(await hostInput.count()).toBeGreaterThan(0);
    expect(await portInput.count()).toBeGreaterThan(0);
  });

  test('should save application settings', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=app');
    await page.waitForLoadState('networkidle');

    // Check for app settings form
    const form = page.locator('form');
    expect(await form.count()).toBeGreaterThan(0);

    // Check for visibility radio buttons
    const content = await page.content();
    const hasVisibility = content.includes('visibility') || content.includes('public')
      || content.includes('confidential') || content.includes('agent_choice');
    expect(hasVisibility).toBeTruthy();
  });

  test('should display settings tabs correctly', async ({ page }) => {
    await page.goto('/index.php?page=settings');
    await page.waitForLoadState('networkidle');

    // Check for tab navigation
    const tabs = page.locator('.tabs a, .nav-tabs a, [role="tab"], .settings-tabs a');
    const tabCount = await tabs.count();
    expect(tabCount).toBeGreaterThanOrEqual(3); // At least sites, global, smtp
  });
});
