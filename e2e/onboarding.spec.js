/**
 * SST Application — Onboarding & Choose Site E2E Tests
 *
 * Tests the first-login onboarding flow where new agents must
 * select their site before accessing the application.
 */
import { test, expect } from '@playwright/test';

// Use a fresh storageState for each test
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('New User Onboarding', () => {

  test('should redirect to choose_site for new users', async ({ page }) => {
    // Login with a completely new username
    await page.goto('/index.php?page=login');
    await page.locator('#username').fill('nouvel.agent.onboarding');
    await page.locator('#password').fill('test');
    await page.locator('form button[type="submit"]').click();

    // Should redirect to choose_site
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });
  });

  test('should display site selection form', async ({ page }) => {
    await page.goto('/index.php?page=login');
    await page.locator('#username').fill('nouvel.agent.onboarding2');
    await page.locator('#password').fill('test');
    await page.locator('form button[type="submit"]').click();
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    // Should have the form elements
    await expect(page.locator('#site_id')).toBeVisible();
    await expect(page.locator('#chooseSiteForm')).toBeVisible();
  });

  test('should show site dropdown with available sites', async ({ page }) => {
    await page.goto('/index.php?page=login');
    await page.locator('#username').fill('nouvel.agent.onboarding3');
    await page.locator('#password').fill('test');
    await page.locator('form button[type="submit"]').click();
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    // Site dropdown should have options
    const siteSelect = page.locator('#site_id');
    const options = siteSelect.locator('option');
    const optionCount = await options.count();
    // Should have at least 1 site option + the placeholder
    expect(optionCount).toBeGreaterThan(1);
  });

  test('should show warning about irreversible choice', async ({ page }) => {
    await page.goto('/index.php?page=login');
    await page.locator('#username').fill('nouvel.agent.onboarding4');
    await page.locator('#password').fill('test');
    await page.locator('form button[type="submit"]').click();
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    // Should have danger panel warning
    await expect(page.locator('.danger-panel')).toBeVisible();
    await expect(page.locator('.danger-panel')).toContainText(/définitif/);
  });

  test('should require site selection before submission', async ({ page }) => {
    await page.goto('/index.php?page=login');
    await page.locator('#username').fill('nouvel.agent.onboarding5');
    await page.locator('#password').fill('test');
    await page.locator('form button[type="submit"]').click();
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    // Try to submit without selecting a site
    // HTML5 validation should prevent it (required attribute)
    const siteSelect = page.locator('#site_id');
    await expect(siteSelect).toHaveAttribute('required', '');
  });

  test('should redirect to home after choosing a site', async ({ page }) => {
    const timestamp = Date.now();
    await page.goto('/index.php?page=login');
    await page.locator('#username').fill(`onboarding.complete.${timestamp}`);
    await page.locator('#password').fill('test');
    await page.locator('form button[type="submit"]').click();
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    // Select a site and submit
    await page.locator('#site_id').selectOption({ index: 1 });
    await page.locator('button:has-text("Confirmer")').click();

    // Should redirect to home
    await expect(page).toHaveURL(/page=home/, { timeout: 10000 });
  });

  test('should NOT redirect to choose_site for existing users with site', async ({ page }) => {
    // Login with an existing user that already has a site
    await page.goto('/index.php?page=login');
    await page.locator('#username').fill('admin.dev');
    await page.locator('#password').fill('test');
    await page.locator('form button[type="submit"]').click();

    // Should go directly to home, NOT choose_site
    await expect(page).toHaveURL(/page=home/, { timeout: 10000 });
  });

});

test.describe('Choose Site — CSRF Protection', () => {

  test('should include CSRF token in choose site form', async ({ page }) => {
    await page.goto('/index.php?page=login');
    await page.locator('#username').fill('nouvel.agent.csrf');
    await page.locator('#password').fill('test');
    await page.locator('form button[type="submit"]').click();
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    const csrfInput = page.locator('#chooseSiteForm input[name="csrf_token"]');
    await expect(csrfInput).toBeAttached();
    const tokenValue = await csrfInput.inputValue();
    expect(tokenValue.length).toBeGreaterThan(0);
  });

});
