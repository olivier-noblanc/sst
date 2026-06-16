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
    
    // Login form elements
    await expect(page.locator('#username')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
    
    // Dev mode badge
    await expect(page.locator('.login-dev-badge')).toContainText('Mode sans IIS');
    
    // Test accounts info
    await expect(page.locator('.login-dev-info')).toContainText('admin.dev');
    await expect(page.locator('.login-dev-info')).toContainText('agent.dev');
  });

  test('should require username to login', async ({ page }) => {
    await page.goto('/index.php?page=login');
    
    // Submit empty form (HTML5 validation should prevent it)
    const usernameInput = page.locator('#username');
    await usernameInput.click();
    await page.locator('button[type="submit"]').click();
    
    // Should still be on login page
    await expect(page).toHaveURL(/page=login/);
  });

  test('should login as superviseur successfully', async ({ page }) => {
    await page.goto('/index.php?page=login');
    
    // Fill login form
    await page.locator('#username').fill('admin.dev');
    await page.locator('#password').fill('test');
    await page.locator('button[type="submit"]').click();
    
    // Should redirect to home page (or choose_site if no site assigned)
    await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
    
    // Should show welcome message
    await expect(page.locator('.alert--success')).toContainText(/Bienvenue/);
    
    // Should show sidebar with superviseur role
    await expect(page.locator('.sidebar')).toBeVisible();
  });

  test('should login as agent successfully', async ({ page }) => {
    await page.goto('/index.php?page=login');
    
    await page.locator('#username').fill('agent.dev');
    await page.locator('#password').fill('test');
    await page.locator('button[type="submit"]').click();
    
    // Should redirect to choose_site (agent.dev has no site assigned)
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });
    
    await expect(page.locator('.alert--success')).toContainText(/Bienvenue/);
  });

  test('should auto-create agent account for unknown username', async ({ page }) => {
    await page.goto('/index.php?page=login');
    
    await page.locator('#username').fill('nouvel.agent.test');
    await page.locator('#password').fill('anything');
    await page.locator('button[type="submit"]').click();
    
    // Should redirect to choose_site (new agent has no site)
    await expect(page).toHaveURL(/page=choose_site/, { timeout: 10000 });
  });

});

test.describe('Logout', () => {

  test('should logout and redirect to login page', async ({ page }) => {
    // Login first
    await page.goto('/index.php?page=login');
    await page.locator('#username').fill('admin.dev');
    await page.locator('#password').fill('test');
    await page.locator('button[type="submit"]').click();
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
    await page.locator('#username').fill('admin.dev');
    await page.locator('#password').fill('test');
    await page.locator('button[type="submit"]').click();
    
    // Should be redirected to the originally requested page (changelog)
    await expect(page).toHaveURL(/page=changelog/, { timeout: 10000 });
  });

});
