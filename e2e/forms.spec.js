/**
 * SST Application — Forms & Validation E2E Tests
 *
 * Tests form validation, CSRF protection, data persistence on error,
 * and various form interaction patterns.
 */
import { test, expect } from '@playwright/test';

async function loginAs(page, username = 'admin.dev') {
  await page.goto('/index.php?page=login');
  await page.locator('#username').fill(username);
  await page.locator('#password').fill('test');
  await page.locator('form button[type="submit"]').click();
  await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
}

test.describe('Report Form Validation', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should require date_evenement field', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    await page.locator('#date_evenement').fill('');
    await page.locator('#objet').fill('Test Objet');
    await page.locator('#description').fill('Test description');
    await page.locator('.card button[type="submit"]').click();

    await expect(page).toHaveURL(/page=report_create/, { timeout: 10000 });
  });

  test('should require objet field', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    await page.locator('#date_evenement').fill('2026-06-15');
    await page.locator('#objet').fill('');
    await page.locator('#description').fill('Test description');
    await page.locator('.card button[type="submit"]').click();

    await expect(page).toHaveURL(/page=report_create/, { timeout: 10000 });
  });

  test('should require description field', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    await page.locator('#date_evenement').fill('2026-06-15');
    await page.locator('#objet').fill('Test Objet');
    await page.locator('#description').fill('');
    await page.locator('.card button[type="submit"]').click();

    await expect(page).toHaveURL(/page=report_create/, { timeout: 10000 });
  });

  test('should reject future dates', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    await page.locator('#date_evenement').fill('2099-12-31');
    await page.locator('#objet').fill('Test Future Date');
    await page.locator('#description').fill('Test avec date future');
    await page.locator('.card button[type="submit"]').click();

    const url = page.url();
    expect(url).not.toMatch(/page=report_view/);
  });

  test('should preserve form data on validation error', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    await page.locator('#objet').fill('Objet Persisté Test');
    await page.locator('#description').fill('');
    await page.locator('.card button[type="submit"]').click();

    await expect(page).toHaveURL(/page=report_create/, { timeout: 10000 });

    const objetValue = await page.locator('#objet').inputValue();
    expect(objetValue).toContain('Objet Persisté Test');
  });

  test('should show field-specific error messages', async ({ page }) => {
    // Submit form directly via POST to test server-side validation
    // (HTML5 validation now blocks empty submissions, which is correct behavior)
    await page.goto('/index.php?page=report_create&type=rsst');
    const csrfToken = await page.locator('.card input[name="csrf_token"]').inputValue();

    const response = await page.request.post('/index.php?page=report_create&type=rsst', {
      form: {
        csrf_token: csrfToken,
        type: 'rsst',
        date_evenement: '',
        heure_evenement: '',
        lieu: '',
        objet: '',
        description: '',
        site_id: '',
      },
      maxRedirects: 0,
    });

    // Handler redirects back to form page with errors
    const location = response.headers()['location'] || '';
    expect(location).toContain('page=report_create');

    // Navigate to the form page to see errors
    await page.goto(location || '/index.php?page=report_create&type=rsst');

    const errors = page.locator('.form-error');
    const errorCount = await errors.count();
    expect(errorCount).toBeGreaterThan(0);
  });

});

test.describe('Report Form — RAMI Specific', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should show pour_compte fields when checkbox is checked', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rami');

    const pourCompteCheckbox = page.locator('#pour_compte');
    await expect(pourCompteCheckbox).toBeVisible();
    await pourCompteCheckbox.check();

    await expect(page.locator('#pour_compte_nom')).toBeVisible();
    await expect(page.locator('#pour_compte_prenom')).toBeVisible();
  });

  test('should have nature_auteur and type_acte dropdowns', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rami');

    await expect(page.locator('#nature_auteur')).toBeVisible();
    await expect(page.locator('#type_acte')).toBeVisible();

    const natureOptions = await page.locator('#nature_auteur option').allTextContents();
    expect(natureOptions.some(t => t.includes('Usager') || t.includes('usager'))).toBeTruthy();

    const typeOptions = await page.locator('#type_acte option').allTextContents();
    expect(typeOptions.some(t => t.includes('Verbal') || t.includes('verbal'))).toBeTruthy();
  });

});

test.describe('CSRF Token Protection', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should include CSRF token in report create form', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    // Scope to the card form to avoid matching impersonate menu forms
    const csrfInput = page.locator('.card input[name="csrf_token"]').first();
    await expect(csrfInput).toBeAttached();

    const tokenValue = await csrfInput.inputValue();
    expect(tokenValue.length).toBeGreaterThan(0);
  });

  test('should include CSRF token in user create form', async ({ page }) => {
    await page.goto('/index.php?page=users&tab=create');

    const csrfInput = page.locator('.card input[name="csrf_token"]').first();
    await expect(csrfInput).toBeAttached();

    const tokenValue = await csrfInput.inputValue();
    expect(tokenValue.length).toBeGreaterThan(0);
  });

  test('should include CSRF token in settings forms', async ({ page }) => {
    await page.goto('/index.php?page=settings&tab=sites');

    // CSRF tokens are in form elements on settings pages
    const csrfInputs = page.locator('form input[name="csrf_token"]');
    const count = await csrfInputs.count();
    expect(count).toBeGreaterThan(0);
  });

});

test.describe('Form Accessibility', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should have labels for all form inputs on create report', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    const inputs = ['#date_evenement', '#objet', '#description'];
    for (const selector of inputs) {
      const input = page.locator(selector);
      if (await input.isVisible()) {
        const id = await input.getAttribute('id');
        const label = page.locator(`label[for="${id}"]`);
        await expect(label).toBeVisible();
      }
    }
  });

  test('should have aria attributes on invalid fields after error', async ({ page }) => {
    // Submit form directly via POST to test server-side validation
    await page.goto('/index.php?page=report_create&type=rsst');
    const csrfToken = await page.locator('.card input[name="csrf_token"]').inputValue();

    const response = await page.request.post('/index.php?page=report_create&type=rsst', {
      form: {
        csrf_token: csrfToken,
        type: 'rsst',
        date_evenement: '',
        heure_evenement: '',
        lieu: '',
        objet: '',
        description: '',
        site_id: '',
      },
      maxRedirects: 0,
    });

    const location = response.headers()['location'] || '';
    await page.goto(location || '/index.php?page=report_create&type=rsst');

    const invalidFields = page.locator('[aria-invalid="true"]');
    const count = await invalidFields.count();
    expect(count).toBeGreaterThan(0);
  });

  test('should have required attribute on mandatory fields', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    await expect(page.locator('#date_evenement')).toHaveAttribute('required', '');
    await expect(page.locator('#objet')).toHaveAttribute('required', '');
    await expect(page.locator('#description')).toHaveAttribute('required', '');
  });

});

test.describe('Character Counter', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('should update character counter when typing description', async ({ page }) => {
    await page.goto('/index.php?page=report_create&type=rsst');

    await page.locator('#description').fill('Test de compteur de caractères E2E');

    const counter = page.locator('#char_count_description');
    await expect(counter).toBeVisible();
    const counterText = await counter.textContent();
    expect(counterText).toMatch(/\d+/);
  });

});

test.describe('Login Form Validation', () => {

  test.use({ storageState: { cookies: [], origins: [] } });

  test('should require username field on login', async ({ page }) => {
    await page.goto('/index.php?page=login');

    // Submit empty form (login page has no impersonate menu)
    await page.locator('form button[type="submit"]').click();

    // Should stay on login page
    await expect(page).toHaveURL(/page=login/);
  });

  test('should have visible input fields on login form', async ({ page }) => {
    await page.goto('/index.php?page=login');

    const usernameInput = page.locator('#username');
    const passwordInput = page.locator('#password');

    await expect(usernameInput).toBeVisible();
    await expect(passwordInput).toBeVisible();
  });

});
