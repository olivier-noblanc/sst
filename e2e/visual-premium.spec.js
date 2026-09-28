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
      await page.goto('/index.php?page=home');
      const words = page.locator('.word-cloud__word');
      const count = await words.count();
      for (let i = 0; i < count; i++) {
        const size = await words.nth(i).evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
        expect(size).toBeGreaterThanOrEqual(12.8);
      }
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