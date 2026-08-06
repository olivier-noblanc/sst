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
    // Click the submit button and wait for navigation
    const submitButton = page.locator('form').nth(formIndex).locator('button[type="submit"]');
    await submitButton.waitFor({ state: 'visible' });
    // Use Promise.all to avoid race condition between click and URL wait
    await Promise.all([
      page.waitForURL(/page=(home|choose_site)/, { timeout: 15000 }),
      submitButton.click(),
    ]);
    // After login, handle multi-site flow if needed (same as non-dev users)
    if (page.url().includes('page=choose_site')) {
      const siteSelect = page.locator('#site_id');
      if (await siteSelect.isVisible()) {
        const chooseCsrf = await page.locator('input[name="csrf_token"]').first().inputValue();
        await siteSelect.selectOption({ index: 1 });
        await page.request.post('/index.php?page=choose_site', {
          form: { csrf_token: chooseCsrf, site_id: await siteSelect.inputValue() },
        });
        await page.goto('/index.php?page=home');
      }
    }
  } else {
    // Non-dev users: POST directly via the login handler.
    // We cannot use fill() on the hidden fields in the dev login forms.
    // Use page.evaluate() to POST from the browser context (shares cookies with the page).
    const csrfToken = await page.locator('form').first().locator('input[name="csrf_token"]').inputValue();

    await page.evaluate(async ({ csrfToken, username }) => {
      const formData = new URLSearchParams();
      formData.set('csrf_token', csrfToken);
      formData.set('username', username);
      formData.set('password', 'test');
      await fetch('/index.php?page=login', {
        method: 'POST',
        body: formData,
        redirect: 'follow',
      });
    }, { csrfToken, username });

    // Navigate to home after the login POST.
    await page.goto('/index.php?page=home');

    // In a multi-site environment (active sites configured — the case in
    // CI, unlike a production install running in no-site mode), a
    // newly-provisioned user with no site lands on choose_site instead of
    // home. Complete that flow here so every subsequent page.goto() in the
    // calling test doesn't get redirected to choose_site again and again.
    if (page.url().includes('page=choose_site')) {
      const siteSelect = page.locator('#site_id');
      if (await siteSelect.isVisible()) {
        // index 0 is the empty placeholder ("— Sélectionnez votre UR —",
        // value=""), not a real site — see pages/choose_site.php. The
        // first real site is index 1, same convention already used in
        // onboarding.spec.js.
        const chooseCsrf = await page.locator('input[name="csrf_token"]').first().inputValue();
        await siteSelect.selectOption({ index: 1 });
        await page.request.post('/index.php?page=choose_site', {
          form: { csrf_token: chooseCsrf, site_id: await siteSelect.inputValue() },
        });
        await page.goto('/index.php?page=home');
      }
    }
  }

  await expect(page).toHaveURL(/page=(home|choose_site)/, { timeout: 10000 });
}
