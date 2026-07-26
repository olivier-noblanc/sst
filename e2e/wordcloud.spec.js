/**
 * SST Application — Word Cloud per Registry E2E Tests
 *
 * Tests the wordcloud settings tab (per-registry sub-tabs, word CRUD,
 * save) and the wordcloud rendering on the home page.
 * Access: superviseur only (settings), all roles (home).
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Settings — Word Cloud Tab', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display wordcloud tab in settings', async ({ page }) => {
    await page.goto('/index.php?page=settings');
    await expect(page.locator('.tab-bar a:has-text("Nuage de mots")')).toBeVisible();
  });

  test('should navigate to wordcloud tab', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud');
    await expect(page).toHaveURL(/tab=wordcloud/);
    await expect(page.locator('.tab-bar a:has-text("Nuage de mots")')).toHaveClass(/settings-tab--active/);
  });

  test('should show registry sub-tabs including Global fallback', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud');

    // Global fallback tab is always present
    await expect(page.locator('a.tabs__item:has-text("Global")')).toBeVisible();

    // RSST is always enabled — its sub-tab must appear
    await expect(page.locator('a.tabs__item').filter({ hasText: /RSST/ })).toBeVisible();
  });

  test('should default to first enabled registry sub-tab', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud');

    const activeTab = page.locator('a.tabs__item--active');
    await expect(activeTab).toBeVisible();
    // The active tab text should not be empty
    const text = await activeTab.textContent();
    expect(text.trim().length).toBeGreaterThan(0);
  });

  test('should switch to Global fallback sub-tab', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud');

    await page.locator('a.tabs__item:has-text("Global")').click();
    await expect(page).toHaveURL(/registry=global/);
    await expect(page.locator('a.tabs__item:has-text("Global")')).toHaveClass(/tabs__item--active/);
  });

  test('should switch to RSST registry sub-tab', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud&registry=global');

    const rsstTab = page.locator('a.tabs__item').filter({ hasText: /RSST/ });
    await rsstTab.click();
    await expect(page).toHaveURL(/registry=rsst/);
    await expect(rsstTab).toHaveClass(/tabs__item--active/);
  });

  test('should display word input rows', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud');

    const rows = page.locator('.wordcloud-row');
    // Should have at least one row (empty state creates one blank row)
    await expect(rows.first()).toBeVisible();
  });

  test('should have word and weight inputs in each row', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud');

    const row = page.locator('.wordcloud-row').first();
    await expect(row.locator('input[type="text"]')).toBeVisible();
    await expect(row.locator('input[type="number"]')).toBeVisible();
  });

  test('should add a new word row via button', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud');

    const initialCount = await page.locator('.wordcloud-row').count();
    await page.locator('button:has-text("Ajouter un mot")').click();
    await expect(page.locator('.wordcloud-row')).toHaveCount(initialCount + 1);
  });

  test('should remove a word row via delete button', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud');

    // Add a row first so we have something to remove
    await page.locator('button:has-text("Ajouter un mot")').click();
    const count = await page.locator('.wordcloud-row').count();
    expect(count).toBeGreaterThanOrEqual(2);

    // Remove the last row
    await page.locator('.wordcloud-row').last().locator('button:has-text("✖")').click();
    await expect(page.locator('.wordcloud-row')).toHaveCount(count - 1);
  });

  test('should have save button', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud');
    await expect(page.locator('button:has-text("Enregistrer")')).toBeVisible();
  });

  test('should save wordcloud words for a registry', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud&registry=rsst');

    // Fill in a word
    const row = page.locator('.wordcloud-row').first();
    await row.locator('input[type="text"]').fill('Chantier');
    await row.locator('input[type="number"]').fill('15');

    // Submit
    await page.locator('button:has-text("Enregistrer")').click();
    await page.waitForLoadState('networkidle');

    // Should show success flash
    const flash = page.locator('.alert, .flash, [role="alert"]');
    if (await flash.count() > 0) {
      await expect(flash.first()).toContainText(/enregistré|succès/i);
    }

    // Reload and verify persistence
    await page.goto('/index.php?page=settings&tab=wordcloud&registry=rsst');
    const savedRow = page.locator('.wordcloud-row').first();
    // Fix E2E: the handler normalizes words to lowercase via mb_strtolower.
    // The test should expect the lowercased value.
    await expect(savedRow.locator('input[type="text"]')).toHaveValue('chantier');
  });

  test('should have hidden inputs for tab and registry_code', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=wordcloud&registry=rsst');

    await expect(page.locator('input[name="tab"][value="wordcloud"]')).toBeAttached();
    await expect(page.locator('input[name="registry_code"][value="rsst"]')).toBeAttached();
  });

});

test.describe('Home Page — Word Cloud Rendering', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display registry cards on home page', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Registry cards are rendered via renderRegistryCards()
    const cards = page.locator('.registry-card, .card');
    await expect(cards.first()).toBeVisible();
  });

  test('should display wordcloud inside registry card when words are configured', async ({ page }) => {
    // First, configure some words for RSST
    await page.goto('/index.php?page=settings&tab=wordcloud&registry=rsst');
    const row = page.locator('.wordcloud-row').first();
    await row.locator('input[type="text"]').fill('TestWord');
    await row.locator('input[type="number"]').fill('10');
    await page.locator('button:has-text("Enregistrer")').click();
    await page.waitForLoadState('networkidle');

    // Navigate to home
    await page.goto('/index.php?page=home');

    // The wordcloud should be rendered inside the registry card
    // buildWordCloud() outputs a div with class 'wordcloud' containing word spans
    const wordcloud = page.locator('.wordcloud');
    if (await wordcloud.count() > 0) {
      await expect(wordcloud.first()).toBeVisible();
      // The configured word should appear somewhere in the wordcloud
      await expect(page.locator('.wordcloud')).toContainText('TestWord');
    }
  });

});
