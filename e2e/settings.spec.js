/**
 * SST Application — Settings & Configuration E2E Tests
 *
 * Tests the settings page tabs, site management, SMTP config,
 * and application parameter navigation.
 * Access: superviseur only
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Settings Page — Tab Navigation', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display settings page with tab bar', async ({ page }) => {
    await page.goto('/index.php?page=settings');
    await expect(page).toHaveURL(/page=settings/);
    await expect(page.locator('h1')).toContainText('Paramètres');

    await expect(page.locator('.tab-bar a:has-text("Notifications par site")')).toBeVisible();
    await expect(page.locator('.tab-bar a:has-text("Notifications globales")')).toBeVisible();
    await expect(page.locator('.tab-bar a:has-text("Configuration SMTP")')).toBeVisible();
    // The label is dynamic — "Gestion des {app_label_unite}s", "URs" by
    // default, not literally "sites" (see pages/settings.php) — match the
    // stable prefix instead of assuming a specific unit label.
    await expect(page.locator('.tab-bar a:has-text("Gestion des")')).toBeVisible();
    await expect(page.locator('.tab-bar a:has-text("Paramètres de l\'application")')).toBeVisible();
  });

  test('should default to sites tab', async ({ page }) => {
    await page.goto('/index.php?page=settings');

    const sitesTab = page.locator('.tab-bar a:has-text("Notifications par site")');
    await expect(sitesTab).toHaveClass(/settings-tab--active/);
  });

  test('should switch to global notifications tab', async ({ page }) => {
    await page.goto('/index.php?page=settings');

    await page.locator('.tab-bar a:has-text("Notifications globales")').click();
    await expect(page).toHaveURL(/tab=global/);

    const globalTab = page.locator('.tab-bar a:has-text("Notifications globales")');
    await expect(globalTab).toHaveClass(/settings-tab--active/);
  });

  test('should switch to SMTP configuration tab', async ({ page }) => {
    await page.goto('/index.php?page=settings');

    await page.locator('.tab-bar a:has-text("Configuration SMTP")').click();
    await expect(page).toHaveURL(/tab=smtp/);
  });

  test('should switch to site management tab', async ({ page }) => {
    await page.goto('/index.php?page=settings');

    // "Gestion des {app_label_unite}s" — dynamic, "URs" by default
    await page.locator('.tab-bar a:has-text("Gestion des")').click();
    await expect(page).toHaveURL(/tab=manage_sites/);
  });

  test('should switch to application settings tab', async ({ page }) => {
    await page.goto('/index.php?page=settings');

    await page.locator('.tab-bar a:has-text("Paramètres de l\'application")').click();
    await expect(page).toHaveURL(/tab=app/);
  });

  test('should navigate to each tab via direct URL', async ({ page }) => {
    const tabs = ['sites', 'global', 'smtp', 'manage_sites', 'app'];

    for (const tab of tabs) {
      await page.goto(`/index.php?page=settings&tab=${tab}`);
      await expect(page).toHaveURL(new RegExp(`tab=${tab}`));
      await expect(page.locator('.tab-bar')).toBeVisible();
    }
  });

});

test.describe('Settings — Per-Site Notifications Tab', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should render notification form with textareas for each site', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=sites');

    // The tab content must include at least one form with textarea
    const form = page.locator('#settingsForm');
    await expect(form).toBeVisible();

    // Each site card should have a textarea for emails
    const textareas = page.locator('textarea[name^="site_emails["]');
    await expect(textareas.first()).toBeVisible();
  });

  test('should have submit button on sites notification tab', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=sites');

    await expect(page.locator('button:has-text("Enregistrer les modifications")')).toBeVisible();
  });

  test('should display site cards with badges on sites notification tab', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=sites');

    const cards = page.locator('.card');
    await expect(cards.first()).toBeVisible();
    // Each card should have a site code badge
    const badges = page.locator('.card .badge');
    await expect(badges.first()).toBeVisible();
  });

});

test.describe('Settings — Global Notifications Tab', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should render global notification form with textarea', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=global');

    // The tab content must include the form with global emails textarea
    const form = page.locator('#settingsForm');
    await expect(form).toBeVisible();

    await expect(page.locator('#global_emails')).toBeVisible();
  });

  test('should have submit button on global notification tab', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=global');

    await expect(page.locator('button:has-text("Enregistrer les modifications")')).toBeVisible();
  });

  test('should display help text for global emails', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=global');

    await expect(page.locator('#hint_global_emails')).toBeVisible();
  });

});

test.describe('Settings — Site Management Tab', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display site management tab content', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=manage_sites');

    await expect(page.locator('#main-content')).toBeVisible();
    const cards = page.locator('.card');
    await expect(cards.first()).toBeVisible();
  });

  test('should show site edit form', async ({ page }) => {
    await page.goto('/index.php?page=site_edit&id=1');
    await expect(page).toHaveURL(/page=site_edit/);
    await expect(page.locator('#main-content')).toBeVisible();
  });

});

test.describe('Settings — SMTP Tab', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display SMTP configuration form', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=smtp');

    // Should have SMTP form fields
    await expect(page.locator('#smtp_host')).toBeVisible();
    await expect(page.locator('#smtp_port')).toBeVisible();
  });

  test('should have SMTP test section', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=smtp');

    await expect(page.locator('#smtp_test_to')).toBeVisible();
  });

});

test.describe('Settings — Application Tab', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display application settings form', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=app');

    // Should have app configuration fields
    await expect(page.locator('#app_nom_organisation')).toBeVisible();
    await expect(page.locator('#app_label_unite')).toBeVisible();
  });

  test('should display version as read-only', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=app');

    // Version should be displayed (as read-only, not an input)
    await expect(page.locator('#main-content')).toContainText(/\d+\.\d+\.\d+/);
  });

  test('should display visibility settings for each registry', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=app');

    // Visibility radios for RSST (always enabled)
    await expect(page.locator('#visibility-radios-rsst')).toBeVisible();
    // RAMI/DGI visibility radios are conditional on registry being enabled
  });

});

test.describe('Logs Page', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display logs page with tab bar', async ({ page }) => {
    await page.goto('/index.php?page=logs');
    await expect(page).toHaveURL(/page=logs/);

    await expect(page.locator('a:has-text("Erreurs PHP")')).toBeVisible();
    await expect(page.locator('a:has-text("Journal d\'audit")')).toBeVisible();
  });

  test('should default to audit tab', async ({ page }) => {
    await page.goto('/index.php?page=logs');

    await expect(page.locator('a.tab--active:has-text("Journal d\'audit")')).toBeVisible();
  });

  test('should switch to errors tab', async ({ page }) => {
    await page.goto('/index.php?page=logs');

    await page.locator('a:has-text("Erreurs PHP")').click();
    await expect(page).toHaveURL(/tab=errors/);
  });

  test('should display audit log with filters', async ({ page }) => {
    await page.goto('/index.php?page=logs&tab=audit');

    await expect(page.locator('#audit-category')).toBeVisible();
    await expect(page.locator('#audit-q')).toBeVisible();
  });

  test('should filter audit log by category', async ({ page }) => {
    await page.goto('/index.php?page=logs&tab=audit');

    await page.locator('#audit-category').selectOption('auth');
    await page.locator('button:has-text("Filtrer")').click();

    await expect(page).toHaveURL(/category=auth/);
  });

});

test.describe('Synthesis Page', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display synthesis page', async ({ page }) => {
    await page.goto('/index.php?page=synthesis');
    await expect(page).toHaveURL(/page=synthesis/);
    await expect(page.locator('h1')).toContainText('Synthèse');
  });

  test('should show filter bar with year and site selectors', async ({ page }) => {
    await page.goto('/index.php?page=synthesis');

    await expect(page.locator('#year')).toBeVisible();
    await expect(page.locator('#site')).toBeVisible();
  });

  test('should display synthesis table', async ({ page }) => {
    await page.goto('/index.php?page=synthesis');

    const table = page.locator('table');
    await expect(table).toBeVisible();

    // RSST is always enabled; RAMI/DGI are conditional
    // (app_registry_rami_enabled/app_registry_dgi_enabled, disabled by
    // default — see pages/synthesis.php) and only get a column when on.
    await expect(table.locator('th:has-text("RSST")')).toBeVisible();
  });

  test('should filter by year', async ({ page }) => {
    await page.goto('/index.php?page=synthesis');

    await page.locator('#year').selectOption('2026');
    await page.locator('button:has-text("Filtrer")').click();

    await expect(page).toHaveURL(/year=2026/);
  });

});

test.describe('Export Page', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display export page', async ({ page }) => {
    await page.goto('/index.php?page=export');
    await expect(page).toHaveURL(/page=export/);
  });

});

test.describe('Statistics Page', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display statistics page', async ({ page }) => {
    await page.goto('/index.php?page=statistics');
    await expect(page).toHaveURL(/page=statistics/);
  });

});
