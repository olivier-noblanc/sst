/**
 * Shared E2E login helper — uses the 3-form dev login page.
 *
 * Each form has hidden fields with dev credentials (admin.dev / agent.dev / chsct.dev).
 * For non-dev usernames, POSTs directly with custom credentials.
 */
import { expect } from '@playwright/test';

/**
 * Login as a specific user.
 * - 'admin.dev' / 'agent.dev' / 'chsct.dev' → form submit (hidden fields)
 * - Any other username → POST with custom credentials
 */
export async function loginAs(page, username = 'admin.dev') {
  await page.goto('/index.php?page=login');

  // Dev users: use the pre-filled form
  if (username === 'admin.dev' || username === 'agent.dev' || username === 'chsct.dev') {
    const formIndex = username === 'agent.dev' ? 1 : username === 'chsct.dev' ? 2 : 0;
    await page.locator('form').nth(formIndex).evaluate(form => form.submit());
  } else {
    // Non-dev users: POST directly
    const csrfToken = await page.locator('form').first().locator('input[name="csrf_token"]').inputValue();
    await page.request.post('/index.php?page=login', {
      form: { username, password: 'test', csrf_token: csrfToken },
    });
  }

  await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
}
