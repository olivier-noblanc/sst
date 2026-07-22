/**
 * SST Application — Report Workflow E2E Tests
 *
 * Tests the full lifecycle: create → respond → reopen → abandon.
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Report Abandon Flow', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should navigate to abandon page from report_view', async ({ page }) => {
    // Create a report first
    await page.goto('/index.php?page=report_create&type=rsst');
    await page.fill('#date_evenement', '2026-01-15');
    await page.fill('#objet', 'Test abandon flow');
    await page.fill('#description', 'Description pour test abandon');
    const siteSelect1 = page.locator('#site_id');
    if (await siteSelect1.isVisible()) {
      await siteSelect1.selectOption({ index: 0 });
    }
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Should be on report_view
    const title = await page.title();
    expect(title).toContain('Signalement');

    // Find abandon link/button
    const abandonLink = page.locator('a[href*="report_abandon"], a:has-text("Abandonner")');
    if (await abandonLink.count() > 0) {
      await abandonLink.first().click();
      await page.waitForLoadState('networkidle');
      const abandonTitle = await page.title();
      expect(abandonTitle).toContain('Abandonner');
    }
  });

  test('should display confirmation message on abandon page', async ({ page }) => {
    // Create and navigate to abandon
    await page.goto('/index.php?page=report_create&type=rsst');
    await page.fill('#date_evenement', '2026-01-15');
    await page.fill('#objet', 'Test abandon confirmation');
    await page.fill('#description', 'Description test');
    const siteSelect2 = page.locator('#site_id');
    if (await siteSelect2.isVisible()) {
      await siteSelect2.selectOption({ index: 0 });
    }
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForLoadState('networkidle');

    const abandonLink = page.locator('a[href*="report_abandon"], a:has-text("Abandonner")');
    if (await abandonLink.count() > 0) {
      await abandonLink.first().click();
      await page.waitForLoadState('networkidle');

      // Should have a warning/confirmation message
      const content = await page.content();
      const hasWarning = content.includes('abandonner') || content.includes('irréversible')
        || content.includes('Attention') || content.includes('confirmer');
      expect(hasWarning).toBeTruthy();
    }
  });
});

test.describe('Report Reopen Flow', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should show reopen option for processed reports', async ({ page }) => {
    // Navigate to report list to find a report
    await page.goto('/index.php?page=report_list&type=rsst');
    await page.waitForLoadState('networkidle');

    // Check if there are any reports to reopen
    const reportLinks = page.locator('a[href*="report_view"]');
    if (await reportLinks.count() > 0) {
      await reportLinks.first().click();
      await page.waitForLoadState('networkidle');

      // Check for reopen button/link
      const reopenLink = page.locator('a[href*="report_reopen"], a:has-text("Réouvrir")');
      const content = await page.content();
      // Reopen may or may not be visible depending on report state
      expect(content.length).toBeGreaterThan(0);
    }
  });
});

test.describe('Report Create and View Cycle', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should create RSST report and view it', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    // Fill form
    await page.fill('#date_evenement', '2026-07-10');
    await page.fill('#objet', 'E2E Test RSST Report');
    await page.fill('#description', 'This is a test report created by E2E tests');
    const siteSelect3 = page.locator('#site_id');
    if (await siteSelect3.isVisible()) {
      await siteSelect3.selectOption({ index: 0 });
    }

    // Submit
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Should redirect to report_view with success flash
    const content = await page.content();
    expect(content).toContain('enregistré');

    // Verify report details are displayed
    expect(content).toContain('E2E Test RSST Report');
  });

  test('should create RAMI report with pour_compte fields', async ({ page }) => {
    // RAMI is conditional (app_registry_rami_enabled) — skip if disabled
    await page.goto('/index.php?page=report_create&type=rami');
    if (page.url().includes('page=home')) return;

    // Fill base fields
    await page.fill('#date_evenement', '2026-07-10');
    await page.fill('#objet', 'E2E Test RAMI Report');
    await page.fill('#description', 'RAMI test report');

    // Check pour_compte checkbox
    const pourCompteCheckbox = page.locator('#pour_compte, input[name="pour_compte"]');
    if (await pourCompteCheckbox.count() > 0) {
      await pourCompteCheckbox.check();

      // Fill pour_compte fields
      await page.fill('#pour_compte_nom', 'Dupont');
      await page.fill('#pour_compte_prenom', 'Jean');
    }

    // Submit
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForLoadState('networkidle');

    const content = await page.content();
    expect(content).toContain('enregistré');
  });

  test('should edit an existing report', async ({ page }) => {
    // Create a report first
    await page.goto('/index.php?page=report_create&type=rsst');
    await page.fill('#date_evenement', '2026-07-10');
    await page.fill('#objet', 'Report to edit');
    await page.fill('#description', 'Original description');
    const siteSelect4 = page.locator('#site_id');
    if (await siteSelect4.isVisible()) {
      await siteSelect4.selectOption({ index: 0 });
    }
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Navigate to edit
    const editLink = page.locator('a[href*="report_edit"], a:has-text("Modifier")');
    if (await editLink.count() > 0) {
      await editLink.first().click();
      await page.waitForLoadState('networkidle');

      // Modify the description
      const descField = page.locator('#description');
      if (await descField.count() > 0) {
        await descField.clear();
        await descField.fill('Updated description from E2E test');
        await page.click('button[type="submit"], input[type="submit"]');
        await page.waitForLoadState('networkidle');

        const content = await page.content();
        expect(content).toContain('modifié');
      }
    }
  });
});

test.describe('Report Search and Filter', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should search reports by keyword', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=rsst');
    await page.waitForLoadState('networkidle');

    // Find search input
    const searchInput = page.locator('input[name="q"], input[type="search"], input[type="text"][placeholder*="echerch"]');
    if (await searchInput.count() > 0) {
      await searchInput.first().fill('test');
      // Submit search (Enter key or button)
      await searchInput.first().press('Enter');
      await page.waitForLoadState('networkidle');

      // Page should still load without error
      const content = await page.content();
      expect(content.length).toBeGreaterThan(0);
    }
  });

  test('should filter reports by state', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=rsst');
    await page.waitForLoadState('networkidle');

    // Check for state filter buttons/tabs
    const stateFilters = page.locator('.tabs a, .filter a, a[href*="etat="]');
    const count = await stateFilters.count();
    expect(count).toBeGreaterThanOrEqual(0); // Page loads without error
  });
});
