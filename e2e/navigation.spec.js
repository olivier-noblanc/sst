/**
 * SST Application — Navigation E2E Tests
 * 
 * Tests sidebar navigation, page loading, and basic page content.
 */
import { test, expect } from '@playwright/test';

async function loginAs(page, username = 'test.superviseur') {
  await page.goto('/index.php?page=login');
  await page.locator('#username').fill(username);
  await page.locator('#password').fill('test');
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
}

test.describe('Sidebar Navigation (Superviseur)', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display sidebar with navigation links', async ({ page }) => {
    await page.goto('/index.php?page=home');
    
    const sidebar = page.locator('.sidebar');
    await expect(sidebar).toBeVisible();
    
    // Should have navigation links
    await expect(sidebar.locator('a[href*="page=home"]')).toBeVisible();
    await expect(sidebar.locator('a[href*="page=report_list"]').first()).toBeVisible();
  });

  test('should navigate to report list pages', async ({ page }) => {
    await page.goto('/index.php?page=home');
    
    // Click RSST report list (sidebar link specifically)
    const rsstLink = page.locator('.sidebar a[href*="page=report_list"][href*="type=rsst"]');
    await expect(rsstLink).toBeVisible();
    await rsstLink.click();
    await expect(page).toHaveURL(/page=report_list.*type=rsst/);
    await expect(page.locator('h1, h2').first()).toContainText(/RSST|Registre/);
  });

  test('should navigate to changelog page', async ({ page }) => {
    await page.goto('/index.php?page=changelog');
    await expect(page).toHaveURL(/page=changelog/);
    await expect(page.locator('#main-content h1').first()).toContainText('Historique des modifications');
  });

  test('should navigate to help page', async ({ page }) => {
    await page.goto('/index.php?page=help');
    await expect(page).toHaveURL(/page=help/);
  });

  test('should navigate to settings page', async ({ page }) => {
    await page.goto('/index.php?page=settings');
    await expect(page).toHaveURL(/page=settings/);
  });

  test('should navigate to users page', async ({ page }) => {
    await page.goto('/index.php?page=users');
    await expect(page).toHaveURL(/page=users/);
  });

  test('should navigate to synthesis page', async ({ page }) => {
    await page.goto('/index.php?page=synthesis');
    await expect(page).toHaveURL(/page=synthesis/);
  });

  test('should navigate to export page', async ({ page }) => {
    await page.goto('/index.php?page=export');
    await expect(page).toHaveURL(/page=export/);
  });

  test('should navigate to statistics page', async ({ page }) => {
    await page.goto('/index.php?page=statistics');
    await expect(page).toHaveURL(/page=statistics/);
  });

});

test.describe('Page Layout', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should have consistent header across pages', async ({ page }) => {
    await page.goto('/index.php?page=home');
    
    const header = page.locator('.header');
    await expect(header).toBeVisible();
    
    // App name should be visible
    await expect(page.locator('.header')).toContainText(/SST|DREETS/);
  });

  test('should have footer with version on all pages', async ({ page }) => {
    const pages = ['home', 'help', 'changelog', 'settings'];
    
    for (const pageName of pages) {
      await page.goto(`/index.php?page=${pageName}`);
      const footer = page.locator('.footer');
      await expect(footer, `Footer missing on page ${pageName}`).toBeVisible();
      await expect(footer.locator('.footer-version'), `Version missing in footer on page ${pageName}`).toBeVisible();
    }
  });

  test('should have skip link for accessibility', async ({ page }) => {
    await page.goto('/index.php?page=home');
    
    const skipLink = page.locator('.skip-link');
    await expect(skipLink).toBeAttached();
  });

});

test.describe('Invalid Page Handling', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should redirect invalid page to home', async ({ page }) => {
    await page.goto('/index.php?page=nonexistent_page_xyz');
    
    // Should fall back to home page (invalid pages silently redirect to home)
    await expect(page.locator('#main-content')).toBeVisible();
  });

});
