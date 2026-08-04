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
    // Ensure the form is ready before submitting
    const form = page.locator('form').nth(formIndex);
    await form.waitFor({ state: 'visible' });
    // Submit the form and wait for navigation to complete
    // form.submit() is more reliable than button click for form submissions
    await form.evaluate(formElement => formElement.submit());
    await page.waitForURL(/page=(home|choose_site)/, { timeout: 10000 });
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
    // Non-dev users: POST directly. page.request shares cookies with page's
    // browser context, so the session is set correctly — but page.request
    // is a background API call, it does NOT navigate `page` itself. Without
    // an explicit page.goto() afterwards, `page` stays on ?page=login and
    // the toHaveURL assertion below would just time out waiting for a
    // navigation that was never going to happen.
    const csrfToken = await page.locator('form').first().locator('input[name="csrf_token"]').inputValue();
    await page.request.post('/index.php?page=login', {
      form: { username, password: 'test', csrf_token: csrfToken },
    });
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
