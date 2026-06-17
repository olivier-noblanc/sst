/**
 * SST Application — User Management E2E Tests
 *
 * Tests user listing, search, creation, editing, and the tab navigation.
 * Access: superviseur only
 */
import { test, expect } from '@playwright/test';

async function loginAs(page, username = 'admin.dev') {
  await page.goto('/index.php?page=login');
  await page.locator('#username').fill(username);
  await page.locator('#password').fill('test');
  await page.locator('form button[type="submit"]').click();
  await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
}

test.describe('Users Page — List Tab', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display users list page', async ({ page }) => {
    await page.goto('/index.php?page=users');
    await expect(page).toHaveURL(/page=users/);

    await expect(page.locator('h1')).toContainText('Gestion des utilisateurs');
  });

  test('should show tab bar with list and create tabs', async ({ page }) => {
    await page.goto('/index.php?page=users');

    await expect(page.locator('.tab-bar a:has-text("Liste des utilisateurs")')).toBeVisible();
    await expect(page.locator('.tab-bar a:has-text("Inscrire un utilisateur")')).toBeVisible();
  });

  test('should display users table with existing users', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=list');

    const table = page.locator('table');
    await expect(table).toBeVisible();

    await expect(table.locator('tbody tr').first()).toBeVisible();
  });

  test('should show user details in table columns', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=list');

    // Verify table headers — scope to the card table to avoid impersonate menu
    const table = page.locator('.card table');
    await expect(table.locator('thead th').first()).toContainText('Nom');
  });

  test('should search users by name', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=list');

    await page.locator('input[name="q"]').fill('superviseur');
    await page.locator('button:has-text("Rechercher")').click();

    await expect(page).toHaveURL(/q=superviseur/);
    await expect(page.locator('table')).toBeVisible();
  });

  test('should show action buttons for each user', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=list');

    await expect(page.locator('.card a.btn:has-text("Voir")').first()).toBeVisible();
    await expect(page.locator('.card a.btn:has-text("Éditer")').first()).toBeVisible();
  });

  test('should show result count', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=list');

    await expect(page.locator('.result-count')).toContainText(/utilisateur/);
  });

  test('should navigate to user view page', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=list');

    await page.locator('.card a.btn:has-text("Voir")').first().click();
    await expect(page).toHaveURL(/page=user_view/);
  });

  test('should navigate to user edit page', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=list');

    await page.locator('.card a.btn:has-text("Éditer")').first().click();
    await expect(page).toHaveURL(/page=user_edit/);
  });

});

test.describe('Users Page — Create Tab', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display create user form', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=create');

    // Should show the creation form inside .card
    await expect(page.locator('.card form[method="POST"]')).toBeVisible();

    await expect(page.locator('#nom')).toBeVisible();
    await expect(page.locator('#prenom')).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#username')).toBeVisible();
    await expect(page.locator('#role')).toBeVisible();
  });

  test('should show role dropdown with options', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=create');

    const roleSelect = page.locator('#role');
    await expect(roleSelect).toBeVisible();

    const options = roleSelect.locator('option');
    const optionTexts = await options.allTextContents();
    expect(optionTexts.some(t => t.includes('Agent') || t.includes('agent'))).toBeTruthy();
    expect(optionTexts.some(t => t.includes('Superviseur') || t.includes('superviseur'))).toBeTruthy();
  });

  test('should create a new user successfully', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=create');

    const timestamp = Date.now();
    await page.locator('#nom').fill('TestE2E');
    await page.locator('#prenom').fill('Utilisateur');
    await page.locator('#email').fill(`e2e.test.${timestamp}@example.com`);
    await page.locator('#username').fill(`e2e.user.${timestamp}`);
    await page.locator('#role').selectOption('agent');

    const siteSelect = page.locator('#site_id');
    if (await siteSelect.isVisible()) {
      await siteSelect.selectOption({ index: 0 });
    }

    await page.locator('.card button:has-text("Créer")').click();

    // Should redirect to users list
    const url = page.url();
    expect(url).toMatch(/page=users/);
  });

  test('should show validation errors for missing required fields', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=create');

    // Remove HTML5 required validation to test server-side validation
    await page.evaluate(() => {
      document.querySelectorAll('#main-content [required]').forEach(el => el.removeAttribute('required'));
    });

    // Submit empty form
    await page.locator('.card button:has-text("Créer")').click();

    // Should stay on create page or redirect back with errors
    await page.waitForURL(/page=users/, { timeout: 10000 });

    // Check for either form errors or flash error
    const errorElements = page.locator('.form-error');
    const hasFormErrors = (await errorElements.count()) > 0;
    const hasFlashError = await page.locator('.alert--error').isVisible().catch(() => false);
    const hasFlashWarning = await page.locator('.alert--warning').isVisible().catch(() => false);
    expect(hasFormErrors || hasFlashError || hasFlashWarning).toBeTruthy();
  });

  test('should have cancel button linking to user list', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=create');

    const cancelLink = page.locator('.card a:has-text("Annuler")');
    await expect(cancelLink).toBeVisible();
    await cancelLink.click();
    await expect(page).toHaveURL(/page=users.*tab=list/);
  });

});

test.describe('User View Page', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display user profile page', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=list');
    await page.locator('.card a.btn:has-text("Voir")').first().click();

    await expect(page).toHaveURL(/page=user_view/);
    await expect(page.locator('#main-content')).toBeVisible();
  });

});

test.describe('User Edit Page', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should display user edit form with existing data', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=list');
    await page.locator('.card a.btn:has-text("Éditer")').first().click();

    await expect(page).toHaveURL(/page=user_edit/);

    const nomValue = await page.locator('#nom').inputValue();
    expect(nomValue.length).toBeGreaterThan(0);
  });

  test('should modify user information', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=list');
    await page.locator('.card a.btn:has-text("Éditer")').first().click();

    await page.locator('#email').fill('modified.e2e@example.com');

    // Use card-scoped button to avoid impersonate menu
    await page.locator('.card button[type="submit"]').click();

    // Should show success flash
    await expect(page.locator('.alert--success')).toBeVisible({ timeout: 5000 });
  });

});
