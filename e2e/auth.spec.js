/**
 * SST Application — Authentication E2E Tests
 * 
 * Tests the mock login form (dev mode), session management,
 * and logout functionality.
 */
import { test, expect } from '@playwright/test';

// Use a fresh storageState for each test
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Login Page', () => {

  test('should display the login form in dev mode', async ({ page }) => {
    await page.goto('/index.php?page=login');
    
    // Page title
    await expect(page).toHaveTitle(/Connexion/);
    
    // Quick-login buttons visible
    await expect(page.locator('.login-quick-buttons')).toBeVisible();
    
    // Dev mode badge
    await expect(page.locator('.login-dev-badge')).toContainText('Connexion');
    
    // Profile buttons
    await expect(page.locator('.login-quick-buttons')).toContainText('Superviseur');
    await expect(page.locator('.login-quick-buttons')).toContainText('Agent');
  });

  test('should login with dev credentials from any form', async ({ page }) => {
    // Each form has hidden dev credentials — submitting any form logs in
    await page.goto('/index.php?page=login');
    await page.locator('form').first().evaluate(form => form.submit());
    // Should redirect to home (dev credentials are pre-filled in hidden fields)
    await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
  });

  test('should login as superviseur successfully', async ({ page }) => {
    await page.goto('/index.php?page=login');
    
    // Fill login form
    await page.locator('button:has-text("Superviseur")').click();
    
    // Should redirect to home page (or choose_site if no site assigned)
    await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
    
    // Should show welcome message
    await expect(page.locator('.alert--success')).toContainText(/Bienvenue/);
    
    // Should show sidebar with superviseur role
    await expect(page.locator('.sidebar')).toBeVisible();
  });

  test('should login as agent successfully', async ({ page }) => {
    await page.goto('/index.php?page=login');
    
    await page.locator('button:has-text("Agent")').click();
    
    // Should redirect to choose_site or home (if site already assigned)
    await expect(page).toHaveURL(/page=(choose_site|home)/, { timeout: 10000 });
    
    await expect(page.locator('.alert--success')).toContainText(/Bienvenue/);
  });

  test('should auto-create agent account for unknown username', async ({ page }) => {
    await page.goto('/index.php?page=login');
    
    // Get CSRF token from the Agent form (second form)
    const csrfToken = await page.locator('form').nth(1).locator('input[name="csrf_token"]').inputValue();
    
    // POST directly to create a new agent
    const response = await page.request.post('/index.php?page=login', {
      form: { username: 'nouvel.agent.test', password: 'test', csrf_token: csrfToken },
      maxRedirects: 0,
    });
    
    // Should redirect (302) after successful creation
    expect(response.status()).toBe(302);
  });

});

test.describe('Logout', () => {

  test('should logout and redirect to login page', async ({ page }) => {
    // Login first
    await page.goto('/index.php?page=login');
    await page.locator('button:has-text("Superviseur")').click();
    await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
    
    // Click logout
    await page.goto('/index.php?page=logout');
    
    // Should redirect to login page (dev mode)
    await expect(page).toHaveURL(/page=login/, { timeout: 10000 });
  });

});

test.describe('Authentication Protection', () => {

  test('should redirect unauthenticated users to login', async ({ page }) => {
    // Try to access a protected page without login
    await page.goto('/index.php?page=home');
    
    // Should be redirected to login
    await expect(page).toHaveURL(/page=login/, { timeout: 10000 });
  });

  test('should redirect to intended URL after login', async ({ page }) => {
    // Try to access changelog directly
    await page.goto('/index.php?page=changelog');
    
    // Should be redirected to login
    await expect(page).toHaveURL(/page=login/, { timeout: 10000 });
    
    // Login
    await page.locator('button:has-text("Superviseur")').click();
    
    // Should be redirected to the originally requested page (changelog)
    await expect(page).toHaveURL(/page=changelog/, { timeout: 10000 });
  });

});
