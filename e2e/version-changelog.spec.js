/**
 * SST Application — Version & Changelog E2E Tests
 * 
 * Tests that the version displayed in the footer matches CHANGELOG.md,
 * and that the changelog page correctly parses and displays the changelog.
 */
import { test, expect } from '@playwright/test';

/**
 * Helper: Login as superviseur before each test
 */
async function loginAs(page, username = 'admin.dev') {
  await page.goto('/index.php?page=login');
  await page.locator('#username').fill(username);
  await page.locator('#password').fill('test');
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
}

test.describe('Footer Version Display', () => {

  test('should display version from CHANGELOG.md in footer', async ({ page }) => {
    await loginAs(page);
    await page.goto('/index.php?page=home');
    
    // The footer should contain a version link like v3.13.0
    const versionLink = page.locator('.footer-version');
    await expect(versionLink).toBeVisible();
    
    // Version should match semver pattern (not vx.y.z placeholder)
    const versionText = await versionLink.textContent();
    expect(versionText).toMatch(/^v\d+\.\d+\.\d+$/);
    
    // Should NOT show the placeholder vx.y.z
    expect(versionText).not.toBe('vx.y.z');
    expect(versionText).not.toBe('v0');
  });

  test('version link should navigate to changelog page', async ({ page }) => {
    await loginAs(page);
    await page.goto('/index.php?page=home');
    
    // Click the version link in the footer
    await page.locator('.footer-version').click();
    
    // Should navigate to changelog page
    await expect(page).toHaveURL(/page=changelog/);
    await expect(page.locator('#main-content h1').first()).toContainText('Historique des modifications');
  });

  test('version should be consistent across all pages', async ({ page }) => {
    await loginAs(page);
    
    // Check version on home page
    await page.goto('/index.php?page=home');
    const homeVersion = await page.locator('.footer-version').textContent();
    
    // Check version on settings page
    await page.goto('/index.php?page=help');
    const helpVersion = await page.locator('.footer-version').textContent();
    
    // Should be the same version
    expect(homeVersion).toBe(helpVersion);
  });

});

test.describe('Changelog Page', () => {

  test('should display changelog with parsed markdown', async ({ page }) => {
    await loginAs(page);
    await page.goto('/index.php?page=changelog');
    
    // Page title
    await expect(page.locator('#main-content h1').first()).toContainText('Historique des modifications');
    
    // Should have at least one version heading (h2)
    const versionHeadings = page.locator('h2');
    const count = await versionHeadings.count();
    expect(count).toBeGreaterThan(0);
    
    // First h2 should be the latest version
    const firstVersion = await versionHeadings.first().textContent();
    expect(firstVersion).toMatch(/\d+\.\d+\.\d+/);
  });

  test('should show the latest version prominently', async ({ page }) => {
    await loginAs(page);
    await page.goto('/index.php?page=changelog');
    
    // The first h2 should contain the latest version (3.13.0 or higher)
    const firstHeading = await page.locator('h2').first().textContent();
    expect(firstHeading).toMatch(/3\.\d+\.\d+/);
    
    // Should have sub-sections (Added, Changed, Fixed, etc.)
    const h3Headings = page.locator('h3');
    const h3Count = await h3Headings.count();
    expect(h3Count).toBeGreaterThan(0);
  });

  test('should not show diagnostic details by default', async ({ page }) => {
    await loginAs(page);
    await page.goto('/index.php?page=changelog');
    
    // Diagnostics should be in a <details> element (collapsed by default)
    const detailsElements = page.locator('details');
    const count = await detailsElements.count();
    
    // If there are diagnostic details, they should be collapsed
    if (count > 0) {
      const isOpen = await detailsElements.first().getAttribute('open');
      expect(isOpen).toBeNull(); // Not open by default
    }
  });

  test('changelog version should match footer version', async ({ page }) => {
    await loginAs(page);
    
    // Get footer version from home page
    await page.goto('/index.php?page=home');
    const footerVersion = await page.locator('.footer-version').textContent();
    // Extract version number (remove 'v' prefix)
    const footerVersionNum = footerVersion.replace(/^v/, '');
    
    // Get changelog first version
    await page.goto('/index.php?page=changelog');
    const changelogFirstVersion = await page.locator('h2').first().textContent();
    // Extract version from heading like "[3.13.0] — 2026-06-15"
    const versionMatch = changelogFirstVersion.match(/\[(\d+\.\d+\.\d+)\]/);
    expect(versionMatch).not.toBeNull();
    const changelogVersion = versionMatch[1];
    
    // They should match
    expect(footerVersionNum).toBe(changelogVersion);
  });

});
