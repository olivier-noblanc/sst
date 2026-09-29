/**
 * SST Application — Flash messages in the header-less sidebar shell.
 *
 * Regression guards for the two defects fixed alongside the sidebar shell:
 *
 *  1. Flash messages must be rendered INSIDE <main id="main-content"> so they
 *     inherit the content area's sidebar offset and padding. Rendered outside
 *     <main> (the previous behaviour), the alert sat at the document's
 *     top-left corner — underneath the fixed sidebar and on the phantom
 *     top-bar offset the shell no longer has.
 *
 *  2. index.php?page=home&result=error is the inert debug redirect target
 *     appended by HttpService::redirect() when a flash is set. Nothing reads
 *     this query parameter: it must keep serving the normal dashboard (HTTP
 *     200) and must never fall through to the production fatal-error page.
 *     No route was added for it — it is deliberately not a supported error
 *     page, so this test locks the "inert, harmless" contract instead.
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Flash messages in the sidebar shell', () => {

  test.beforeEach(async ({ page }) => {
    await loginAs(page);
  });

  test('an error flash is visible inside the main content, not behind the sidebar', async ({ page }) => {
    // Trigger the CSRF failure path: CsrfMiddleware sets a flash error and
    // redirects to index.php?page=home&result=error. We do NOT follow the
    // redirect here so the flash is still pending when the page renders.
    const csrfResponse = await page.request.post('/index.php?page=report_create', {
      form: { csrf_token: 'never-issued' },
      maxRedirects: 0,
    });
    expect(csrfResponse.status()).toBe(302);
    expect(csrfResponse.headers()['location']).toContain('result=error');

    const response = await page.goto('/index.php?page=home&result=error');
    expect(response.status()).toBe(200);

    const alert = page.locator('#main-content .alert.alert--error');
    await expect(alert).toBeVisible();
    await expect(alert).toContainText('Erreur de sécurité.');

    // Not hidden underneath the fixed sidebar: the alert's left edge must
    // start at or after the sidebar's right edge.
    const sidebarBox = await page.locator('.sidebar').boundingBox();
    const alertBox = await alert.boundingBox();
    expect(sidebarBox).not.toBeNull();
    expect(alertBox).not.toBeNull();
    expect(alertBox.x).toBeGreaterThanOrEqual(sidebarBox.x + sidebarBox.width - 1);
  });

  test('home with the inert result=error param still renders the dashboard', async ({ page }) => {
    const response = await page.goto('/index.php?page=home&result=error');
    expect(response.status()).toBe(200);
    await expect(page.locator('#main-content')).toBeVisible();
    await expect(page.locator('.home-hero__title')).toBeVisible();
    // The production fatal-error page must never be served for this URL.
    await expect(page.getByText('Une erreur est survenue')).toHaveCount(0);
  });

});