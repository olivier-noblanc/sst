/**
 * SST Application — Refonte visuelle premium (seconde passe)
 *
 * Matrice laptop contractuelle : 1024×768 (plancher), 1280×800 (priorité de
 * design), 1440×900 (contrôle). Vérifie la tenue du layout (O1), la grille de
 * registres (O2), la dominance du compteur (O3), la lisibilité du nuage (O5)
 * et la compacité de la légende (O6), puis produit les captures de contrôle.
 */
import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { loginAs } from './helpers.js';

const VIEWPORTS = [
  { label: 'laptop-1024', width: 1024, height: 768 },
  { label: 'laptop-1280', width: 1280, height: 800 },
  { label: 'laptop-1440', width: 1440, height: 900 },
];

const PREMIUM_DIR = path.join(process.cwd(), 'docs', 'screenshots', 'premium');

/**
 * Deterministic prerequisite for the O5 readability test: configure at least
 * one word for the RSST registry so the home page actually renders a
 * `.word-cloud[data-words]`.
 *
 * Without this, a fresh E2E database has no words → the home page contains no
 * cloud → the old O5 loop iterated zero times and passed vacuously, which
 * masked the real regression (`public/router.php` did not route `/js.php`, so
 * `wordcloud.js` never loaded and never rendered a single word).
 */
async function ensureWordCloudConfigured(page) {
  await page.goto('/index.php?page=settings&tab=wordcloud&registry=rsst');
  const row = page.locator('.wordcloud-row').first();
  await row.locator('input[type="text"]').fill('Chantier');
  await row.locator('input[type="number"]').fill('15');
  await page
    .locator('form:has(input[name="tab"][value="wordcloud"]) button[type="submit"]')
    .click();
  await page.waitForLoadState('networkidle');
}

for (const vp of VIEWPORTS) {
  test.describe(`Refonte premium — ${vp.label}`, () => {
    test.use({ viewport: { width: vp.width, height: vp.height } });

    test.beforeEach(async ({ page }) => {
      await loginAs(page);
    });

    test('accueil — pas de scroll horizontal (O1)', async ({ page }) => {
      await page.goto('/index.php?page=home');
      const ok = await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth
      );
      expect(ok).toBe(true);
    });

    test('accueil — grille de registres conforme (O2)', async ({ page }) => {
      await page.goto('/index.php?page=home');
      const grid = page.locator('.registry-cards').first();
      await expect(grid).toBeVisible();
      const columns = await grid.evaluate(
        (el) => getComputedStyle(el).gridTemplateColumns.split(' ').length
      );
      const expected = vp.width >= 1440 ? [3, 4] : [3];
      expect(expected).toContain(columns);
    });

    test('accueil — compteur dominant (O3)', async ({ page }) => {
      await page.goto('/index.php?page=home');
      const value = page.locator('.registry-card__stat-value').first();
      await expect(value).toBeVisible();
      const valueSize = await value.evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
      const descSize = await page
        .locator('.registry-card__desc')
        .first()
        .evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
      expect(valueSize).toBeGreaterThan(descSize);
    });

    test('accueil — nuage de mots lisible (O5)', async ({ page }) => {
      // Guarantee a configured cloud exists before asserting anything, so this
      // test can never pass because there is nothing to render.
      await ensureWordCloudConfigured(page);
      await page.goto('/index.php?page=home');

      const clouds = page.locator('.word-cloud[data-words]');
      const cloudCount = await clouds.count();
      expect(cloudCount).toBeGreaterThan(0);

      let cloudsWithConfiguredWords = 0;

      for (let c = 0; c < cloudCount; c++) {
        const raw = await clouds.nth(c).getAttribute('data-words');
        let configured = null;
        try {
          configured = JSON.parse(raw);
        } catch {
          configured = null;
        }
        // Only clouds that carry actual words are held to the contract.
        if (!Array.isArray(configured) || configured.length === 0) continue;

        cloudsWithConfiguredWords++;

        // Hardened: a cloud with a non-empty data-words payload MUST render at
        // least one placed word. Zero words means the `wordcloud.js` script was
        // not executed (e.g. not served by the dev router) — a silent failure
        // the previous `count === 0` loop happily ignored.
        const rendered = clouds.nth(c).locator('.word-cloud__word');
        const count = await rendered.count();
        expect(
          count,
          `nuage #${c} : ${configured.length} mot(s) configuré(s) mais aucun mot rendu `
            + `— wordcloud.js n'a pas été exécuté (servi par le router ?).`
        ).toBeGreaterThan(0);

        for (let i = 0; i < count; i++) {
          const size = await rendered.nth(i).evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
          expect(size).toBeGreaterThanOrEqual(12.8);
        }
      }

      // At least one cloud with configured words must have been exercised.
      expect(cloudsWithConfiguredWords).toBeGreaterThan(0);
    });

    test('accueil — légende compacte (O6)', async ({ page }) => {
      test.skip(vp.width < 1280, 'légende sur une ligne garantie à 1280/1440 seulement');
      await page.goto('/index.php?page=home');
      const legend = page.locator('.workflow-legend');
      if ((await legend.count()) === 0) return;
      const height = await legend.first().evaluate((el) => el.getBoundingClientRect().height);
      expect(height).toBeLessThanOrEqual(44);
    });

    test('accueil — capture de contrôle', async ({ page }) => {
      await page.goto('/index.php?page=home');
      fs.mkdirSync(PREMIUM_DIR, { recursive: true });
      await page.screenshot({ path: path.join(PREMIUM_DIR, `accueil-${vp.width}.png`) });
    });

    test('report_list — pas de scroll horizontal (O1)', async ({ page }) => {
      await page.goto('/index.php?page=report_list&type=rsst');
      const ok = await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth
      );
      expect(ok).toBe(true);
    });

    test('report_view — pas de scroll horizontal (O1)', async ({ page }) => {
      await page.goto('/index.php?page=report_list&type=rsst');
      const viewLink = page.locator('a.btn--outline:has-text("Voir")').first();
      if ((await viewLink.count()) === 0) {
        test.skip(true, 'aucun signalement RSST disponible pour ouvrir une fiche');
        return;
      }
      await viewLink.click();
      await expect(page).toHaveURL(/page=report_view/);
      const ok = await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth
      );
      expect(ok).toBe(true);
    });
  });
}