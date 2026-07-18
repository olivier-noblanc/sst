/**
 * Shared E2E login helper — uses the 3-form dev login page.
 *
 * Each form has hidden fields with dev credentials (admin.dev / agent.dev / chsct.dev).
 * Submitting any form logs in with those credentials.
 */
import { expect } from '@playwright/test';

/**
 * Login as a specific role via form submit.
 * @param {import('@playwright/test').Page} page
 * @param {'admin.dev'|'agent.dev'|'chsct.dev'} username
 */
export async function loginAs(page, username = 'admin.dev') {
  await page.goto('/index.php?page=login');

  // Map username to form index: 0=Superviseur, 1=Agent, 2=CHSCT
  const formIndex = username === 'agent.dev' ? 1 : username === 'chsct.dev' ? 2 : 0;

  // Submit the form directly (hidden fields have the credentials)
  await page.locator('form').nth(formIndex).evaluate(form => form.submit());

  // Wait for redirect to home or choose_site
  await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
}
