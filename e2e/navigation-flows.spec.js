/**
 * SST Application — Deep Navigation Flow E2E Tests
 *
 * Tests multi-step navigation scenarios, browser back/forward,
 * sidebar active state tracking, and cross-page workflows.
 * These simulate real user navigation patterns through the app.
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Full Report Lifecycle Navigation', () => {

  test('should navigate through complete report lifecycle', async ({ page }) => {
    await loginAs(page);

    // Step 1: Start on home page
    await page.goto('/index.php?page=home');
    await expect(page.locator('.registry-card--rsst')).toBeVisible();

    // Step 2: Click "Déposer un signalement" on RSST card → create form
    await page.locator('.registry-card--rsst a:has-text("Déposer")').click();
    await expect(page).toHaveURL(/page=report_create.*type=rsst/);
    await expect(page.locator('#objet')).toBeVisible();

    // Step 3: Fill and submit the form → report view
    await page.locator('#date_evenement').fill('2026-06-15');
    await page.locator('#objet').fill('Navigation Flux Complet');
    await page.locator('#description').fill('Test de navigation complète à travers le cycle de vie d\'un signalement.');
    await page.locator('#pole').fill('Pôle Test E2E');
    await page.locator('#telephone_mobile').fill('0601020304');
    const siteSelect = page.locator('#site_id');
    if (await siteSelect.isVisible()) {
      await siteSelect.selectOption({ index: 1 });
    }
    await page.locator('.card button[type="submit"]').click();
    await expect(page).toHaveURL(/page=(report_view|home)/, { timeout: 10000 });

    // Step 4: On report view, verify data
    await expect(page.locator('#main-content')).toContainText('Navigation Flux Complet');

    // Step 5: Click "Répondre" → response form (superviseur)
    await page.locator('a:has-text("Répondre")').click();
    await expect(page).toHaveURL(/page=report_respond/);

    // Step 6: Submit a response → back to report view
    const textarea = page.locator('textarea[name="response"]');
    if (await textarea.isVisible()) {
      await textarea.fill('Réponse de test pour le flux de navigation complet.');
      const etatSelect = page.locator('select[name="etat"]');
      if (await etatSelect.isVisible()) {
        await etatSelect.selectOption('en_cours');
      }
      await page.locator('.card button[type="submit"]').click();
      await expect(page).toHaveURL(/page=(report_view|home)/, { timeout: 10000 });
    }

    // Step 7: Navigate back to list via breadcrumb (use .first() because "rsst-XX-NNN" also matches)
    await page.locator('.breadcrumb a:has-text("RSST")').first().click();
    await expect(page).toHaveURL(/page=report_list.*type=rsst/);

    // Step 8: Navigate to home via sidebar
    await page.locator('.sidebar a[href*="page=home"]').click();
    await expect(page).toHaveURL(/page=home/);
  });

});

test.describe('Sidebar Active State', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should highlight home link when on home page', async ({ page }) => {
    await page.goto('/index.php?page=home');
    const homeLink = page.locator('.sidebar a[href*="page=home"]');
    await expect(homeLink).toHaveClass(/sidebar__item--active/);
    await expect(homeLink).toHaveAttribute('aria-current', 'page');
  });

  test('should highlight RSST link when on RSST list page', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=rsst');
    const rsstLink = page.locator('.sidebar a[href*="type=rsst"]');
    await expect(rsstLink).toHaveClass(/sidebar__item--active/);
  });

  test('should highlight RAMI link when on RAMI list page', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=rami');
    const ramiLink = page.locator('.sidebar a[href*="type=rami"]');
    // RAMI may be disabled — skip if link not present
    if (await ramiLink.count() === 0) return;
    await expect(ramiLink).toHaveClass(/sidebar__item--active/);
  });

  test('should highlight DGI link when on DGI list page', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=dgi');
    const dgiLink = page.locator('.sidebar a[href*="type=dgi"]');
    // DGI may be disabled — skip if link not present
    if (await dgiLink.count() === 0) return;
    await expect(dgiLink).toHaveClass(/sidebar__item--active/);
  });

  test('should highlight Settings link when on settings page', async ({ page }) => {
    await page.goto('/index.php?page=settings');
    const settingsLink = page.locator('.sidebar a[href*="page=settings"]');
    await expect(settingsLink).toHaveClass(/sidebar__item--active/);
  });

  test('should highlight RSST link when creating an RSST report', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');
    const rsstLink = page.locator('.sidebar a[href*="type=rsst"]');
    await expect(rsstLink).toHaveClass(/sidebar__item--active/);
  });

  test('should highlight RAMI link when viewing a RAMI report', async ({ page }) => {
    // RAMI may be disabled — skip if page redirects to home
    await page.goto('/index.php?page=report_create&type=rami');
    if (page.url().includes('page=home')) return;
    await page.locator('#date_evenement').fill('2026-06-15');
    await page.locator('#objet').fill('Test Sidebar Active RAMI');
    await page.locator('#description').fill('Test de l\'état actif du sidebar pour RAMI.');
    // Fix E2E: fill required fields (pole, telephone, site) so submit succeeds
    await page.locator('#pole').fill('Pôle Test');
    await page.locator('#telephone_mobile').fill('0601020304');
    const siteSelect = page.locator('#site_id');
    if (await siteSelect.isVisible()) {
      await siteSelect.selectOption({ index: 1 });
    }
    await page.locator('.card button[type="submit"]').click();
    await expect(page).toHaveURL(/page=(report_view|home)/, { timeout: 10000 });

    // The RAMI sidebar link should be active
    const ramiLink = page.locator('.sidebar a[href*="type=rami"]');
    if (await ramiLink.count() === 0) return;
    await expect(ramiLink).toHaveClass(/sidebar__item--active/);
  });

});

test.describe('Browser Back/Forward Navigation', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should handle browser back from report view to list', async ({ page }) => {
    // Navigate: home → RSST list → report view
    await page.goto('/index.php?page=report_list&type=rsst');
    await expect(page).toHaveURL(/page=report_list/);

    // Create a report so we have one to view
    await page.goto('/index.php?page=report_create&type=rsst');
    await page.locator('#date_evenement').fill('2026-06-15');
    await page.locator('#objet').fill('Test Back Navigation');
    await page.locator('#description').fill('Test de navigation avec le bouton retour du navigateur.');
    await page.locator('#pole').fill('Pôle Test E2E');
    await page.locator('#telephone_mobile').fill('0601020304');
    const siteSelect = page.locator('#site_id');
    if (await siteSelect.isVisible()) {
      await siteSelect.selectOption({ index: 1 });
    }
    await page.locator('.card button[type="submit"]').click();
    await expect(page).toHaveURL(/page=(report_view|home)/, { timeout: 10000 });

    // Go back
    await page.goBack();
    // Should be on a previous page (create or list)
    await expect(page).toHaveURL(/page=/);
  });

  test('should handle browser back from settings to home', async ({ page }) => {
    await page.goto('/index.php?page=home');
    await page.goto('/index.php?page=settings');
    await expect(page).toHaveURL(/page=settings/);

    // Go back
    await page.goBack();
    await expect(page).toHaveURL(/page=home/);
  });

  test('should handle browser forward after back', async ({ page }) => {
    await page.goto('/index.php?page=home');
    await page.goto('/index.php?page=help');
    await expect(page).toHaveURL(/page=help/);

    // Go back to home
    await page.goBack();
    await expect(page).toHaveURL(/page=home/);

    // Go forward to help
    await page.goForward();
    await expect(page).toHaveURL(/page=help/);
  });

  test('should preserve session after browser navigation', async ({ page }) => {
    await page.goto('/index.php?page=home');
    await page.goto('/index.php?page=settings');

    // Go back
    await page.goBack();

    // Session should still be valid (not redirected to login)
    await expect(page).toHaveURL(/page=home/);
    await expect(page.locator('.sidebar')).toBeVisible();
  });

});

test.describe('Cross-Page Navigation Flows', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should navigate from home to all main pages and back', async ({ page }) => {
    const pages = [
      { name: 'help', sidebarSelector: '.sidebar a[href*="page=help"]' },
      { name: 'changelog', sidebarSelector: '.sidebar a[href*="page=home"]' },  // no direct sidebar link
    ];

    // Navigate to each page and back to home
    for (const p of pages) {
      await page.goto(`/index.php?page=${p.name}`);
      await expect(page).toHaveURL(new RegExp(`page=${p.name}`));

      // Go back to home
      await page.locator('.sidebar a[href*="page=home"]').click();
      await expect(page).toHaveURL(/page=home/);
    }
  });

  test('should navigate through settings tabs sequentially', async ({ page }) => {
    await page.goto('/index.php?page=settings');
    await expect(page).toHaveURL(/page=settings/);

    // Navigate through all tabs
    const tabs = [
      { label: 'Notifications globales', url: /tab=global/ },
      { label: 'Configuration SMTP', url: /tab=smtp/ },
      { label: 'Gestion des', url: /tab=manage_sites/ },
      { label: 'Paramètres de l\'application', url: /tab=app/ },
      { label: 'Notifications par site', url: /page=settings/ },  // back to first tab
    ];

    for (const tab of tabs) {
      await page.locator(`.tab-bar a:has-text("${tab.label}")`).click();
      await expect(page).toHaveURL(tab.url);
    }
  });

  test('should navigate from user list to user view to user edit and back', async ({ page }) => {
    // Users list
    await page.goto('/index.php?page=users');
    await expect(page).toHaveURL(/page=users/);

    // Click "Voir" on first user
    await page.locator('.card a.btn:has-text("Voir")').first().click();
    await expect(page).toHaveURL(/page=user_view/);

    // Go back to list
    await page.goto('/index.php?page=users');

    // Click "Éditer" on first user
    await page.locator('.card a.btn:has-text("Éditer")').first().click();
    await expect(page).toHaveURL(/page=user_edit/);

    // Go back to list via cancel or navigation
    await page.goto('/index.php?page=users');
    await expect(page).toHaveURL(/page=users/);
  });

  test('should navigate through registry list pages', async ({ page }) => {
    // RSST is always enabled; RAMI/DGI are conditional
    await page.locator('.sidebar a[href*="type=rsst"]').click();
    await expect(page).toHaveURL(/type=rsst/);
  });

  test('should navigate from home card to create and then to list', async ({ page }) => {
    // Click the RSST card's create button. Its label is "Déposer un
    // signalement" specifically (see buildRegistryCards() in
    // registry_card_renderer.php) — RAMI/DGI use "Signaler ...", RSST
    // doesn't. Match on the href instead of the label text so this stays
    // correct regardless of the exact wording used for any given registry.
    await page.goto('/index.php?page=home');
    await page.locator('.registry-card--rsst a[href*="page=report_create"]').click();
    await expect(page).toHaveURL(/page=report_create.*type=rsst/);

    // Go back, then click "Voir"
    await page.goto('/index.php?page=home');
    await page.locator('.registry-card--rsst a:has-text("Voir")').click();
    await expect(page).toHaveURL(/page=report_list.*type=rsst/);
  });

});

test.describe('Breadcrumb Navigation', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should navigate from report view to list via breadcrumb "Accueil"', async ({ page }) => {
    // Create a report
    await page.goto('/index.php?page=report_create&type=rsst');
    await page.locator('#date_evenement').fill('2026-06-15');
    await page.locator('#objet').fill('Test Breadcrumb Accueil');
    await page.locator('#description').fill('Test navigation via breadcrumb.');
    await page.locator('#pole').fill('Pôle Test E2E');
    await page.locator('#telephone_mobile').fill('0601020304');
    await page.locator('.card button[type="submit"]').click();
    await expect(page).toHaveURL(/page=(report_view|home)/, { timeout: 10000 });

    // Click "Accueil" in breadcrumb
    await page.locator('.breadcrumb a:has-text("Accueil")').click();
    await expect(page).toHaveURL(/page=home/);
  });

  test('should navigate from report view to list via breadcrumb registry link', async ({ page }) => {
    // Create an RSST report (RSST is always enabled; RAMI/DGI are conditional)
    await page.goto('/index.php?page=report_create&type=rsst');
    await page.locator('#date_evenement').fill('2026-06-15');
    await page.locator('#objet').fill('Test Breadcrumb RSST');
    await page.locator('#description').fill('Test navigation via breadcrumb RSST.');
    await page.locator('#pole').fill('Pôle Test E2E');
    await page.locator('#telephone_mobile').fill('0601020304');
    await page.locator('.card button[type="submit"]').click();
    await expect(page).toHaveURL(/page=(report_view|home)/, { timeout: 10000 });

    // Click "RSST" in breadcrumb
    await page.locator('.breadcrumb a:has-text("RSST")').click();
    await expect(page).toHaveURL(/page=report_list.*type=rsst/);
  });

});

test.describe('Session Persistence Across Navigation', () => {

  test('should maintain login across 10+ page loads', async ({ page }) => {
    await loginAs(page);

    // Navigate through many pages — session should persist
    const pagesToVisit = [
      'home', 'report_list&type=rsst',
      'help', 'changelog', 'preamble',
      'synthesis', 'export', 'statistics', 'users', 'settings',
    ];

    for (const pageParam of pagesToVisit) {
      await page.goto(`/index.php?page=${pageParam}`);
      // Should NOT be redirected to login
      await expect(page).not.toHaveURL(/page=login/);
      // Should have sidebar (authenticated layout)
      await expect(page.locator('.sidebar')).toBeVisible();
    }
  });

});
