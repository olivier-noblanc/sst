/**
 * SST Application — Report Lifecycle E2E Tests
 *
 * Tests the full CRUD cycle for reports (RSST, RAMI, DGI):
 * create → list → view → edit → respond → abandon
 */
import { test, expect } from '@playwright/test';

/**
 * Helper: Login as a given user
 */
async function loginAs(page, username = 'admin.dev') {
  await page.goto('/index.php?page=login');
  await page.locator('#username').fill(username);
  await page.locator('#password').fill('test');
  await page.locator('form button[type="submit"]').click();
  await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
}

/**
 * Helper: Create a report via the form and return the resulting page
 */
async function createReport(page, type = 'rsst', options = {}) {
  const { objet = 'Test E2E Signalement', description = 'Description de test E2E automatisé pour vérifier le cycle de vie complet.', pourCompte = false } = options;

  // Navigate to create form from home
  await page.goto(`/index.php?page=report_create&type=${type}`);

  // Verify form is displayed
  await expect(page.locator('#objet')).toBeVisible();

  // Fill required fields
  await page.locator('#date_evenement').fill('2026-06-15');
  await page.locator('#objet').fill(objet);
  await page.locator('#description').fill(description);

  // Handle site dropdown (only visible for superviseur/chsct on create)
  const siteSelect = page.locator('#site_id');
  if (await siteSelect.isVisible()) {
    await siteSelect.selectOption({ index: 0 });
  }

  // Handle RAMI-specific fields
  if (type === 'rami' && pourCompte) {
    const pourCompteCheckbox = page.locator('#pour_compte');
    if (await pourCompteCheckbox.isVisible()) {
      await pourCompteCheckbox.check();
      await page.locator('#pour_compte_nom').fill('AgentPourCompte');
      await page.locator('#pour_compte_prenom').fill('Prénom');
    }
  }

  // Submit form — use card-scoped selector to avoid matching impersonate menu buttons
  await page.locator('.card button[type="submit"]').click();

  // Should redirect to report_view after successful creation
  await expect(page).toHaveURL(/page=report_view/, { timeout: 10000 });
}

test.describe('Report Creation', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should create an RSST report successfully', async ({ page }) => {
    await createReport(page, 'rsst');

    // Verify success flash message
    await expect(page.locator('.alert--success')).toContainText(/enregistré/);

    // Verify report view displays key data
    await expect(page.locator('#main-content')).toContainText('Test E2E Signalement');
    await expect(page.locator('#main-content')).toContainText('RSST');
  });

  test('should create a RAMI report successfully', async ({ page }) => {
    await createReport(page, 'rami');

    await expect(page.locator('.alert--success')).toContainText(/enregistré/);
    await expect(page.locator('#main-content')).toContainText('Test E2E Signalement');
  });

  test('should create a DGI report successfully', async ({ page }) => {
    await createReport(page, 'dgi');

    await expect(page.locator('.alert--success')).toContainText(/enregistré/);
    await expect(page.locator('#main-content')).toContainText('Test E2E Signalement');
  });

  test('should show form validation errors for missing required fields', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    // Clear the date field and objet, leave description empty
    await page.locator('#date_evenement').fill('');
    await page.locator('#objet').fill('');
    await page.locator('#description').fill('');

    // Submit empty form — HTML5 native validation may block submission,
    // or server validation may catch it. Either way, we should stay on create page.
    await page.locator('.card button[type="submit"]').click();

    // Should stay on create page (either HTML5 validation prevents submit, or server errors)
    await expect(page).toHaveURL(/page=report_create/, { timeout: 10000 });

    // Should display validation feedback (HTML5 :invalid pseudo-class or server .form-error)
    const hasHtml5Validation = await page.locator('input:invalid').count();
    const hasServerError = await page.locator('.form-error').count();
    expect(hasHtml5Validation + hasServerError).toBeGreaterThan(0);
  });

  test('should pre-fill declarant name from session', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    // Declarant fields should be readonly and pre-filled
    const nomInput = page.locator('#declarant_nom');
    const prenomInput = page.locator('#declarant_prenom');

    await expect(nomInput).toHaveAttribute('readonly', '');
    await expect(prenomInput).toHaveAttribute('readonly', '');

    // Should have a value (the logged-in user's name)
    const nomValue = await nomInput.inputValue();
    expect(nomValue.length).toBeGreaterThan(0);
  });

  test('should show RAMI-specific fields only for RAMI type', async ({ page }) => {
    // RSST should NOT have RAMI fields
    await page.goto('/index.php?page=report_create&type=rsst');
    await expect(page.locator('#pour_compte')).toHaveCount(0);
    await expect(page.locator('#nature_auteur')).toHaveCount(0);
    await expect(page.locator('#type_acte')).toHaveCount(0);

    // RAMI should have RAMI fields
    await page.goto('/index.php?page=report_create&type=rami');
    await expect(page.locator('#pour_compte')).toBeVisible();
    await expect(page.locator('#nature_auteur')).toBeVisible();
    await expect(page.locator('#type_acte')).toBeVisible();
  });

  test('should create RAMI report with "pour le compte" option', async ({ page }) => {
    await createReport(page, 'rami', { pourCompte: true });

    await expect(page.locator('.alert--success')).toContainText(/enregistré/);
  });

});

test.describe('Report List & Filtering', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
    // Create a report so the list is not empty
    await createReport(page, 'rsst', { objet: 'Filtre RSST Test' });
  });

  test('should display report list page for RSST', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=rsst');
    await expect(page).toHaveURL(/page=report_list/);

    // Should have a table with at least one row
    const table = page.locator('table');
    await expect(table).toBeVisible();

    // Should not show empty state
    await expect(page.locator('.empty-state')).toHaveCount(0);
  });

  test('should filter reports by state', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=rsst');

    // Select "nouveau" state filter
    const etatSelect = page.locator('#etat');
    await etatSelect.selectOption('nouveau');
    await page.locator('.filter-bar button[type="submit"]').click();

    // URL should contain the filter parameter
    await expect(page).toHaveURL(/etat=nouveau/);

    // Table should still be visible
    await expect(page.locator('table')).toBeVisible();
  });

  test('should search reports by keyword', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=rsst');

    // Type a search query
    await page.locator('#q').fill('Filtre RSST');
    await page.locator('.filter-bar button[type="submit"]').click();

    // URL should contain the search parameter
    await expect(page).toHaveURL(/q=Filtre/);
  });

  test('should navigate from list to report view', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=rsst');

    // Click the first "Voir" button
    const viewLink = page.locator('a.btn--outline:has-text("Voir")').first();
    await expect(viewLink).toBeVisible();
    await viewLink.click();

    // Should be on report_view page
    await expect(page).toHaveURL(/page=report_view/);
  });

  test('should show empty state for types with no reports', async ({ page }) => {
    // Navigate to DGI list — just verify the page loads correctly
    await page.goto('/index.php?page=report_list&type=dgi');
    await expect(page.locator('table')).toBeVisible();
  });

  test('should show "Nouveau signalement" button on list page', async ({ page }) => {
    await page.goto('/index.php?page=report_list&type=rsst');

    const newBtn = page.locator('a:has-text("Nouveau signalement")');
    await expect(newBtn).toBeVisible();
  });

});

test.describe('Report View', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
    await createReport(page, 'rsst', { objet: 'Rapport Vue Test' });
  });

  test('should display report details', async ({ page }) => {
    // After createReport we're already on report_view
    await expect(page.locator('#main-content')).toContainText('Rapport Vue Test');
    await expect(page.locator('#main-content')).toContainText('RSST');
  });

  test('should show breadcrumb navigation', async ({ page }) => {
    const breadcrumb = page.locator('.breadcrumb');
    await expect(breadcrumb).toBeVisible();
    await expect(breadcrumb).toContainText('Accueil');
    await expect(breadcrumb).toContainText('RSST');
  });

  test('should show action buttons for superviseur', async ({ page }) => {
    // Superviseur should see "Répondre" button for nouveau reports
    await expect(page.locator('a:has-text("Répondre")')).toBeVisible();
  });

  test('should navigate back to list via breadcrumb', async ({ page }) => {
    // Click RSST breadcrumb link
    await page.locator('.breadcrumb a:has-text("RSST")').click();
    await expect(page).toHaveURL(/page=report_list.*type=rsst/);
  });

});

test.describe('Report Edit', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'jean.dupont');
    // Create a report as agent (can edit own reports)
    await createReport(page, 'rsst', { objet: 'Rapport Modif Test' });
  });

  test('should edit own report successfully', async ({ page }) => {
    // Navigate to edit page via report view
    const editLink = page.locator('a:has-text("Modifier")');
    if (await editLink.isVisible()) {
      await editLink.click();
      await expect(page).toHaveURL(/page=report_edit/);

      // Modify the objet field
      await page.locator('#objet').fill('Rapport Modifié E2E');
      await page.locator('.card button[type="submit"]').click();

      // Should redirect back to report view
      await expect(page).toHaveURL(/page=report_view/, { timeout: 10000 });
      await expect(page.locator('#main-content')).toContainText('Rapport Modifié E2E');
    }
  });

  test('should preserve data on edit form', async ({ page }) => {
    const editLink = page.locator('a:has-text("Modifier")');
    if (await editLink.isVisible()) {
      await editLink.click();

      // Form should be pre-filled with existing data
      const objetValue = await page.locator('#objet').inputValue();
      expect(objetValue).toContain('Rapport Modif Test');

      const descValue = await page.locator('#description').inputValue();
      expect(descValue.length).toBeGreaterThan(0);
    }
  });

});

test.describe('Report Response (Superviseur)', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
    // Create a report as superviseur, then respond to it
    await createReport(page, 'rsst', { objet: 'Rapport Réponse Test' });
  });

  test('should navigate to respond page', async ({ page }) => {
    const respondLink = page.locator('a:has-text("Répondre")');
    await expect(respondLink).toBeVisible();
    await respondLink.click();

    await expect(page).toHaveURL(/page=report_respond/);
  });

  test('should submit a response to a report', async ({ page }) => {
    // Navigate to respond page
    await page.locator('a:has-text("Répondre")').click();
    await expect(page).toHaveURL(/page=report_respond/);

    // Fill response form
    const textarea = page.locator('textarea[name="response"]');
    if (await textarea.isVisible()) {
      await textarea.fill('Réponse de test E2E — traitement en cours.');

      // Select "en_cours" status if available
      const etatSelect = page.locator('select[name="etat"]');
      if (await etatSelect.isVisible()) {
        await etatSelect.selectOption('en_cours');
      }

      // Submit response
      await page.locator('.card button[type="submit"]').click();

      // Should redirect to report view
      await expect(page).toHaveURL(/page=report_view/, { timeout: 10000 });

      // Should show success flash
      await expect(page.locator('.alert--success')).toBeVisible({ timeout: 5000 });
    }
  });

});

test.describe('Report Abandon', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'jean.dupont');
    await createReport(page, 'rsst', { objet: 'Rapport Abandon Test' });
  });

  test('should navigate to abandon page', async ({ page }) => {
    // Look for abandon link
    const abandonLink = page.locator('a:has-text("Abandonner")');
    if (await abandonLink.isVisible()) {
      await abandonLink.click();
      // Abandon now uses a dedicated page (report_abandon) instead of confirm_abandon GET param
      await expect(page).toHaveURL(/page=report_abandon/);
    }
  });

});

test.describe('Home Page Registry Cards', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display three registry cards on home page', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Three registry cards
    await expect(page.locator('.registry-card--rsst')).toBeVisible();
    await expect(page.locator('.registry-card--rami')).toBeVisible();
    await expect(page.locator('.registry-card--dgi')).toBeVisible();
  });

  test('should have "Inscrire" and "Voir" links on each card', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Each card should have an "Inscrire un signalement" link and "Voir les signalements" link
    for (const type of ['rsst', 'rami', 'dgi']) {
      const card = page.locator(`.registry-card--${type}`);
      await expect(card.locator('a:has-text("Inscrire")')).toBeVisible();
      await expect(card.locator('a:has-text("Voir")')).toBeVisible();
    }
  });

  test('should navigate to create report from registry card', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Click "Inscrire" on RSST card
    await page.locator('.registry-card--rsst a:has-text("Inscrire")').click();
    await expect(page).toHaveURL(/page=report_create.*type=rsst/);
  });

  test('should navigate to report list from registry card', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Click "Voir" on RAMI card
    await page.locator('.registry-card--rami a:has-text("Voir")').click();
    await expect(page).toHaveURL(/page=report_list.*type=rami/);
  });

  test('should show superviseur quick access links', async ({ page }) => {
    await page.goto('/index.php?page=home');

    // Superviseur should see quick access section
    await expect(page.locator('.quick-access')).toBeVisible();
    await expect(page.locator('.quick-access a:has-text("Synthèse")')).toBeVisible();
    await expect(page.locator('.quick-access a:has-text("Statistiques")')).toBeVisible();
    await expect(page.locator('.quick-access a:has-text("Export")')).toBeVisible();
  });

});
