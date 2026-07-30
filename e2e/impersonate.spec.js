/**
 * SST Application — Impersonation E2E Tests
 *
 * Tests the role impersonation feature: superviseurs can temporarily
 * adopt agent or chsct roles to see the app from their perspective.
 * Includes: start impersonation, banner display, role restrictions,
 * and stop impersonation (restore real role).
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Impersonate — Start', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display impersonate dropdown for superviseur', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // The impersonate toggle should be visible
    const impersonateToggle = page.locator('#impersonate-toggle');
    await expect(impersonateToggle).toBeAttached();

    // The label/button should be visible
    await expect(page.locator('.impersonate-btn')).toBeVisible();
  });

  test('should open impersonate menu on click', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Click the impersonate toggle to open the dropdown
    await page.locator('.impersonate-btn').click();

    // Menu should be visible with Agent and CHSCT options
    await expect(page.locator('.impersonate-menu')).toBeVisible();
    await expect(page.locator('.impersonate-menu button:has-text("Agent")')).toBeVisible();
    await expect(page.locator('.impersonate-menu button:has-text("Membre FS/CSA")')).toBeVisible();
  });

  test('should impersonate Agent role successfully', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Open impersonate menu and click Agent
    await page.locator('.impersonate-btn').click();
    await page.locator('.impersonate-menu button:has-text("Agent")').click();

    // Should still be on a page (redirected back)
    await expect(page).toHaveURL(/page=/);

    // Should display the impersonation banner
    await expect(page.locator('.impersonate-banner')).toBeVisible();
    await expect(page.locator('.impersonate-banner')).toContainText('incarnez');
  });

  test('should impersonate CHSCT role successfully', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Open impersonate menu and click CHSCT
    await page.locator('.impersonate-btn').click();
    await page.locator('.impersonate-menu button:has-text("Membre FS/CSA")').click();

    // Should display the impersonation banner
    await expect(page.locator('.impersonate-banner')).toBeVisible();
    await expect(page.locator('.impersonate-banner')).toContainText(/CHSCT|CSA/);
  });

});

test.describe('Impersonate — Banner', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
    // Start impersonating Agent
    await page.goto('/index.php?page=home');
    await page.locator('.impersonate-btn').click();
    await page.locator('.impersonate-menu button:has-text("Agent")').click();
    await expect(page.locator('.impersonate-banner').first()).toBeVisible({ timeout: 10000 });
  });

  test('should show impersonation banner on all pages', async ({ page }) => {
    // Check banner persists across different pages
    const pages = ['home', 'report_list&type=rsst', 'changelog'];
    for (const pageParam of pages) {
      await page.goto(`/index.php?page=${pageParam}`);
      await expect(page.locator('.impersonate-banner').first()).toBeVisible();
    }
  });

  test('banner should indicate the impersonated role', async ({ page }) => {
    await page.goto('/index.php?page=home');
    await expect(page.locator('.impersonate-banner')).toContainText(/Agent/);
  });

  test('banner should have "Reprendre mon rôle" button', async ({ page }) => {
    await page.goto('/index.php?page=home');
    await expect(page.locator('.impersonate-banner__btn')).toBeVisible();
    await expect(page.locator('.impersonate-banner__btn')).toContainText('Reprendre mon rôle');
  });

});

test.describe('Impersonate — Role Restrictions', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
    // Start impersonating Agent
    await page.goto('/index.php?page=home');
    await page.locator('.impersonate-btn').click();
    await page.locator('.impersonate-menu button:has-text("Agent")').click();
    await expect(page.locator('.impersonate-banner').first()).toBeVisible({ timeout: 10000 });
  });

  test('impersonated Agent should NOT see superviseur sidebar items', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Agent should NOT see these sidebar links
    await expect(page.locator('.sidebar a[href*="page=synthesis"]')).toHaveCount(0);
    await expect(page.locator('.sidebar a[href*="page=users"]')).toHaveCount(0);
    await expect(page.locator('.sidebar a[href*="page=settings"]')).toHaveCount(0);
    await expect(page.locator('.sidebar a[href*="page=logs"]')).toHaveCount(0);
  });

  test('impersonated Agent should NOT access settings page', async ({ page }) => {
    await page.goto('/index.php?page=settings');
    // Should show access denied
    await expect(page.locator('#main-content')).toBeVisible();
    await expect(page.locator('.tab-bar')).toHaveCount(0);
  });

  test('impersonated Agent should NOT access users page', async ({ page }) => {
    await page.goto('/index.php?page=users');
    await expect(page.locator('#main-content')).toBeVisible();
    // Should NOT show user table
    await expect(page.locator('table[aria-label*="utilisateurs"]')).toHaveCount(0);
  });

  test('impersonated Agent should access report create page', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');
    await expect(page).toHaveURL(/page=report_create/);
    await expect(page.locator('#objet')).toBeVisible();
  });

  test('impersonated Agent should NOT see "Répondre" button on reports', async ({ page }) => {
    // Create a report first as the impersonated agent
    await page.goto('/index.php?page=report_create&type=rsst');
    await page.locator('#date_evenement').fill('2026-06-15');
    await page.locator('#objet').fill('Test Impersonation Agent');
    await page.locator('#description').fill('Test pour vérifier les restrictions en mode incognito.');
    await page.locator('#pole').fill('Pôle Test E2E');
    await page.locator('#telephone_mobile').fill('0601020304');
    await page.locator('.card button[type="submit"]').click();
    await expect(page).toHaveURL(/page=(report_view|home)/, { timeout: 10000 });

    // Agent should NOT see "Répondre" button
    await expect(page.locator('a:has-text("Répondre")')).toHaveCount(0);
  });

});

test.describe('Impersonate — Stop', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
    // Start impersonating Agent
    await page.goto('/index.php?page=home');
    await page.locator('.impersonate-btn').click();
    await page.locator('.impersonate-menu button:has-text("Agent")').click();
    await expect(page.locator('.impersonate-banner').first()).toBeVisible({ timeout: 10000 });
  });

  test('should restore superviseur role when clicking "Reprendre mon rôle"', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Click the stop impersonation button
    await page.locator('.impersonate-banner__btn').click();

    // Should redirect back to a page
    await expect(page).toHaveURL(/page=/);

    // Banner should be gone
    await expect(page.locator('.impersonate-banner')).toHaveCount(0);

    // Should see superviseur sidebar items again
    await expect(page.locator('.sidebar a[href*="page=settings"]')).toBeVisible();
    await expect(page.locator('.sidebar a[href*="page=users"]')).toBeVisible();
  });

  test('should see "Répondre" button again after stopping impersonation', async ({ page }) => {
    // Create a report while impersonating
    await page.goto('/index.php?page=report_create&type=rsst');
    await page.locator('#date_evenement').fill('2026-06-15');
    await page.locator('#objet').fill('Test Stop Impersonation');
    await page.locator('#description').fill('Test pour vérifier le retour au rôle superviseur.');
    await page.locator('#pole').fill('Pôle Test E2E');
    await page.locator('#telephone_mobile').fill('0601020304');
    await page.locator('.card button[type="submit"]').click();
    await expect(page).toHaveURL(/page=(report_view|home)/, { timeout: 10000 });

    // Stop impersonation
    await page.locator('.impersonate-banner__btn').click();
    await expect(page).toHaveURL(/page=/, { timeout: 10000 });

    // Navigate to the report again and check "Répondre" is visible
    await page.goto('/index.php?page=report_list&type=rsst');
    const viewLink = page.locator('a.btn--outline:has-text("Voir")').first();
    if (await viewLink.isVisible()) {
      await viewLink.click();
      await expect(page).toHaveURL(/page=report_view/);
      await expect(page.locator('a:has-text("Répondre")')).toBeVisible();
    }
  });

  test('should NOT show impersonate dropdown while already impersonating', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // While impersonating, the impersonate dropdown should NOT be visible
    // (only the stop button in the banner should be visible)
    await expect(page.locator('.impersonate-btn')).toHaveCount(0);
  });

});

test.describe('Impersonate — Agent Cannot Impersonate', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'agent.dev');
  });

  test('should NOT show impersonate dropdown for agent role', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Agent should NOT see impersonate button
    await expect(page.locator('.impersonate-btn')).toHaveCount(0);
    await expect(page.locator('.impersonate-banner')).toHaveCount(0);
  });

});
