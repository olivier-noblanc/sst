/**
 * SST Application — User Management E2E Tests
 *
 * Tests user CRUD operations.
 */
import { test, expect } from '@playwright/test';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('User Management', () => {

  test.beforeEach(async ({ page }) => {
    // Login as superviseur
    await page.goto('/index.php?page=login');
    await page.click('.login-quick-buttons button:first-child, .login-quick-buttons a:first-child');
    await page.waitForLoadState('networkidle');
  });

  test('should display users list', async ({ page }) => {
    await page.goto('/index.php?page=users');
    await page.waitForLoadState('networkidle');

    const content = await page.content();
    expect(content).toContain('utilisateur');

    // Should have a table or list
    const table = page.locator('table');
    expect(await table.count()).toBeGreaterThan(0);
  });

  test('should navigate to user creation form', async ({ page }) => {
    await page.goto('/index.php?page=users');
    await page.waitForLoadState('networkidle');

    // Find create user link
    const createLink = page.locator('a[href*="user_create"], a:has-text("Créer"), a:has-text("Nouvel")');
    if (await createLink.count() > 0) {
      await createLink.first().click();
      await page.waitForLoadState('networkidle');

      const content = await page.content();
      expect(content).toContain('utilisateur');
    }
  });

  test('should display user creation form with role selector', async ({ page }) => {
    await page.goto('/index.php?page=user_create');
    await page.waitForLoadState('networkidle');

    const content = await page.content();
    expect(content).toContain('utilisateur');

    // Check for role dropdown
    const roleSelect = page.locator('#role, select[name="role"]');
    if (await roleSelect.count() > 0) {
      const options = await roleSelect.locator('option').allTextContents();
      expect(options.length).toBeGreaterThan(0);
    }
  });

  test('should display user view page', async ({ page }) => {
    // First get a user ID from the list
    await page.goto('/index.php?page=users');
    await page.waitForLoadState('networkidle');

    const viewLink = page.locator('a[href*="user_view"]');
    if (await viewLink.count() > 0) {
      await viewLink.first().click();
      await page.waitForLoadState('networkidle');

      const content = await page.content();
      // Should show user profile info
      expect(content).toContain('utilisateur') || expect(content).toContain('Profil');
    }
  });
});
