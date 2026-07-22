/**
 * SST Application — Onboarding & Choose Site E2E Tests
 *
 * Tests the first-login onboarding flow where new agents must
 * select their site before accessing the application.
 */
import { test, expect } from '@playwright/test';

// Use a fresh storageState for each test
test.use({ storageState: { cookies: [], origins: [] } });

/**
 * Login with custom credentials via POST (login page has no visible fields)
 */
async function loginWithCustom(page, username, password = 'test') {
  // Get CSRF token from login page
  await page.goto('/index.php?page=login');
  const csrfToken = await page.locator('form').first().locator('input[name="csrf_token"]').inputValue();

  // POST directly with custom credentials. page.request shares cookies with
  // page's browser context (the session is set correctly), but it's a
  // background API call — it does NOT navigate `page` itself. Every caller
  // here immediately asserts on page's URL, so without this goto() they'd
  // all just time out waiting for a navigation that never happens; `page`
  // would still be sitting on ?page=login.
  await page.request.post('/index.php?page=login', {
    form: { username, password, csrf_token: csrfToken },
  });
  await page.goto('/index.php?page=home');
}

test.describe('New User Onboarding', () => {

  test('should redirect to choose_site for new users', async ({ page }) => {
    await loginWithCustom(page, 'nouvel.agent.onboarding');
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });
  });

  test('should display site selection form', async ({ page }) => {
    await loginWithCustom(page, 'nouvel.agent.onboarding2');
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    await expect(page.locator('#site_id')).toBeVisible();
    await expect(page.locator('#chooseSiteForm')).toBeVisible();
  });

  test('should show site dropdown with available sites', async ({ page }) => {
    await loginWithCustom(page, 'nouvel.agent.onboarding3');
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    const siteSelect = page.locator('#site_id');
    const options = siteSelect.locator('option');
    const optionCount = await options.count();
    expect(optionCount).toBeGreaterThan(1);
  });

  test('should show warning about irreversible choice', async ({ page }) => {
    await loginWithCustom(page, 'nouvel.agent.onboarding4');
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    await expect(page.locator('.danger-panel')).toBeVisible();
    await expect(page.locator('.danger-panel')).toContainText(/7 jours/);
  });

  test('should require site selection before submission', async ({ page }) => {
    await loginWithCustom(page, 'nouvel.agent.onboarding5');
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    const siteSelect = page.locator('#site_id');
    await expect(siteSelect).toHaveAttribute('required', '');
  });

  test('should redirect to home after choosing a site', async ({ page }) => {
    const timestamp = Date.now();
    await loginWithCustom(page, `onboarding.complete.${timestamp}`);
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    await page.locator('#site_id').selectOption({ index: 1 });
    await page.locator('button:has-text("Confirmer")').click();

    await expect(page).toHaveURL(/page=home/, { timeout: 10000 });
  });

  test('should NOT redirect to choose_site for existing users with site', async ({ page }) => {
    // Use shared helper for dev login
    const { loginAs } = await import('./helpers.js');
    await loginAs(page);

    await expect(page).toHaveURL(/page=home/, { timeout: 10000 });
  });

});

test.describe('Choose Site — CSRF Protection', () => {

  test('should include CSRF token in choose site form', async ({ page }) => {
    await loginWithCustom(page, 'nouvel.agent.csrf');
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });

    const csrfInput = page.locator('#chooseSiteForm input[name="csrf_token"]');
    await expect(csrfInput).toBeAttached();
    const tokenValue = await csrfInput.inputValue();
    expect(tokenValue.length).toBeGreaterThan(0);
  });

});
