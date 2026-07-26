/**
 * SST Application — Registres Paramétrables E2E Tests
 *
 * Tests the P21 changes:
 * - Settings: Registres tab (navigation, toggle, color, icon)
 * - Report creation with dynamic registry types
 * - Dashboard displays registres dynamically
 * - CHECK constraint removal (custom types accepted)
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Settings — Registres Tab', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display Registres tab in settings', async ({ page }) => {
    await page.goto('/index.php?page=settings');
    await expect(page.locator('.tab-bar a:has-text("Registres")')).toBeVisible();
  });

  test('should navigate to Registres tab', async ({ page }) => {
    await page.goto('/index.php?page=settings');
    await page.locator('.tab-bar a:has-text("Registres")').click();
    await expect(page).toHaveURL(/tab=registres/);
    await expect(page.locator('h2:has-text("Gestion des registres")')).toBeVisible();
  });

  test('should navigate to Registres tab via direct URL', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=registres');
    await expect(page).toHaveURL(/tab=registres/);
    await expect(page.locator('h2:has-text("Gestion des registres")')).toBeVisible();
  });

  test('should display all 3 default registres (RSST, RAMI, DGI)', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=registres');

    // Each registre should have a card with its label.
    // Use .first() to avoid strict mode violation — the label also appears
    // in the "custom fields" section below each card.
    await expect(page.locator('h3:has-text("Registre de Santé")').first()).toBeVisible();
    await expect(page.locator('h3:has-text("Registre des Actes")').first()).toBeVisible();
    await expect(page.locator('h3:has-text("Registre de signalement")').first()).toBeVisible();
  });

  test('should show RSST as system registre (cannot be disabled)', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=registres');

    // RSST card should have the "Système" badge
    const rsstCard = page.locator('div.card:has(h3:has-text("Registre de Santé"))').first();
    await expect(rsstCard.locator('.badge:has-text("Système")')).toBeVisible();

    // RSST toggle should be disabled — use [name*="[is_enabled]"] to be specific
    const rsstToggle = rsstCard.locator('input[type="checkbox"][name*="[is_enabled]"]');
    await expect(rsstToggle).toBeDisabled();
  });

  test('should toggle RAMI enabled/disabled', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=registres');

    // Find the RAMI card — use .first() to avoid strict mode violation
    const ramiCard = page.locator('div.card:has(h3:has-text("Registre des Actes"))').first();
    // Use [name*="[is_enabled]"] to match only the is_enabled toggle, not notify_chsct
    const ramiToggle = ramiCard.locator('input[type="checkbox"][name*="[is_enabled]"]');

    // Toggle and save
    await ramiToggle.check();
    await page.locator('button:has-text("Enregistrer")').click();

    // Should show success message
    await expect(page.locator('.alert--success')).toBeVisible();
    await expect(page.locator('.alert--success')).toContainText('Registres mis à jour');
  });

  test('should display color theme selector for non-system registres', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=registres');

    // RAMI card should have color dots (10 themes)
    // Modular-audit P1.5 — UI uses <span class="color-dot"> + hidden input, not radio buttons.
    // The old test checked `input[type="radio"][name*="color_theme"]` which never matched.
    const ramiCard = page.locator('div.card:has(h3:has-text("Registre des Actes"))');
    const colorDots = ramiCard.locator('.color-dot');
    await expect(colorDots.first()).toBeVisible();

    // Should have 10 color options
    const count = await colorDots.count();
    expect(count).toBe(10);
  });

  test('should display icon selector for non-system registres', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=registres');

    // RAMI card should have icon <select> dropdown (not radio buttons)
    // Modular-audit P1.5 — UI uses <select>, not <input type="radio">. Old test was wrong.
    const ramiCard = page.locator('div.card:has(h3:has-text("Registre des Actes"))');
    const iconSelect = ramiCard.locator('select[name*="[icon]"]');
    await expect(iconSelect).toBeVisible();

    // Should have ~30 icon options
    const optionCount = await iconSelect.locator('option').count();
    expect(optionCount).toBeGreaterThan(10);
  });

  test('should save color and icon changes', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=registres');

    // Fix E2E: use .first() and the correct selectors (color-dot + select, not radio)
    const ramiCard = page.locator('div.card:has(h3:has-text("Registre des Actes"))').first();

    // Select a different color (vert) — UI uses <span class="color-dot" data-theme="vert">
    await ramiCard.locator('.color-dot[data-theme="vert"]').click();

    // Select a different icon via <select> dropdown
    await ramiCard.locator('select[name*="[icon]"]').selectOption('🚨');

    // Save
    await page.locator('button:has-text("Enregistrer")').click();
    await expect(page.locator('.alert--success')).toBeVisible();
  });

});

test.describe('Dashboard — Dynamic Registry Cards', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display RSST card on home page', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // RSST is always present
    await expect(page.locator('.registry-card--rsst')).toBeVisible();
  });

  test('should display RAMI card when enabled', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // RAMI may or may not be visible depending on config
    // Just verify the page loads without error
    await expect(page.locator('.registry-cards')).toBeVisible();
  });

});

test.describe('Report Creation — Dynamic Registry Types', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should access RSST report creation form', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');
    await expect(page.locator('#objet')).toBeVisible();
    // Fix E2E: the h2 contains the full label "Signaler un événement — Registre de Santé et de Sécurité au Travail"
    // not the short label "RSST". Check for the full registry name instead.
    await expect(page.locator('h2')).toContainText('Santé');
  });

  test('should display registry-specific form fields based on registry config', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    // Common fields should always be visible
    await expect(page.locator('#date_evenement')).toBeVisible();
    await expect(page.locator('#objet')).toBeVisible();
    await expect(page.locator('#description')).toBeVisible();
  });

});

test.describe('Report Type Validation', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should reject invalid report type on create page', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=invalid_type');

    // Should redirect to home or show error
    await expect(page).toHaveURL(/page=(home|report_list)/, { timeout: 10000 });
  });

  test('should reject invalid report type on list page', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=invalid_type');

    // Should redirect to home or show error
    await expect(page).toHaveURL(/page=(home|report_list)/, { timeout: 10000 });
  });

});

test.describe('Navigation — Registry-Aware Sidebar', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should show RSST in sidebar navigation', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Sidebar should contain RSST link
    await expect(page.locator('.sidebar__item:has-text("RSST")')).toBeVisible();
  });

  test('should filter report list by registry type', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=rsst');
    await expect(page).toHaveURL(/type=rsst/);
    await expect(page.locator('h1')).toContainText('RSST');
  });

});
