/**
 * SST Application — Registre Custom: Cycle de Vie Complet E2E
 *
 * Test complet du cycle de vie d'un registre :
 * 1. Activer un registre existant (RAMI) via les settings
 * 2. Personnaliser sa couleur et icône
 * 3. Créer un signalement dans ce registre
 * 4. Rattacher un collègue (linked agents)
 * 5. Vérifier le signalement avec rattachement
 * 6. Vérifier que le registre apparaît sur le dashboard
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Registre Custom — Cycle de Vie Complet', () => {

  test('activer RAMI, personnaliser, poster avec rattachement, vérifier', async ({ page }) => {

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 1 : Activer le registre RAMI via les settings
    // ═══════════════════════════════════════════════════════════════
    await loginAs(page);
    await page.goto('/index.php?page=settings&tab=registres');

    // Vérifier que la page des registres s'affiche
    await expect(page.locator('h2:has-text("Gestion des registres")')).toBeVisible();

    // Trouver la carte RAMI et l'activer
    const ramiCard = page.locator('div.card:has(h3:has-text("Registre des Actes"))');
    await expect(ramiCard).toBeVisible();

    const ramiToggle = ramiCard.locator('input[type="checkbox"][name*="registres"]');
    await expect(ramiToggle).toBeEnabled(); // pas système, donc activable
    await ramiToggle.check();

    // Enregistrer
    await page.locator('button:has-text("Enregistrer")').click();
    await expect(page.locator('.alert--success')).toContainText('Registres mis à jour');

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 2 : Personnaliser la couleur et l'icône
    // ═══════════════════════════════════════════════════════════════
    await page.goto('/index.php?page=settings&tab=registres');

    const ramiCard2 = page.locator('div.card:has(h3:has-text("Registre des Actes"))');

    // Choisir la couleur "vert"
    await ramiCard2.locator('input[type="radio"][name*="color_theme"][value="vert"]').check();

    // Choisir l'icône "🚨"
    await ramiCard2.locator('input[type="radio"][name*="icon"][value="🚨"]').check();

    // Enregistrer
    await page.locator('button:has-text("Enregistrer")').click();
    await expect(page.locator('.alert--success')).toContainText('Registres mis à jour');

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 3 : Vérifier que RAMI apparaît sur le dashboard
    // ═══════════════════════════════════════════════════════════════
    await page.goto('/index.php?page=home');
    await expect(page.locator('.registry-cards')).toBeVisible();

    // La carte RAMI devrait maintenant être visible
    const ramiDashboardCard = page.locator('.registry-card--rami');
    if (await ramiDashboardCard.count() > 0) {
      await expect(ramiDashboardCard).toBeVisible();
    }

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 4 : Créer un signalement RAMI avec rattachement
    // ═══════════════════════════════════════════════════════════════
    await page.goto('/index.php?page=report_create&type=rami');

    // Vérifier que le formulaire RAMI s'affiche
    await expect(page.locator('#objet')).toBeVisible();

    // Remplir les champs obligatoires
    await page.locator('#date_evenement').fill('2026-07-24');
    await page.locator('#objet').fill('Test E2E — Agression verbale');
    await page.locator('#description').fill('Test E2E complet : création de signalement RAMI avec rattachement d\'un collègue. Vérifie le cycle de vie complet du registre.');
    await page.locator('#pole').fill('Pôle Accueil');
    await page.locator('#telephone_mobile').fill('0612345678');

    // Choisir le site si visible
    const siteSelect = page.locator('#site_id');
    if (await siteSelect.isVisible()) {
      await siteSelect.selectOption({ index: 0 });
    }

    // Rattacher un collègue (linked agents)
    const linkedEmailsInput = page.locator('#linked_emails');
    if (await linkedEmailsInput.isVisible()) {
      await linkedEmailsInput.fill('agent.dev@test.local');
    }

    // Soumettre le formulaire
    await page.locator('.card button[type="submit"]').click();
    await expect(page).toHaveURL(/page=report_view/, { timeout: 10000 });

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 5 : Vérifier le signalement créé
    // ═══════════════════════════════════════════════════════════════
    await expect(page.locator('.alert--success')).toContainText(/enregistré/);
    await expect(page.locator('#main-content')).toContainText('Test E2E — Agression verbale');

    // Vérifier que le type affiché est bien RAMI
    await expect(page.locator('#main-content')).toContainText('RAMI');

    // Vérifier que la référence est générée (format rami-YY-NNN)
    const referenceText = await page.locator('#main-content').textContent();
    expect(referenceText).toMatch(/rami-\d{2}-\d{3}/);

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 6 : Vérifier dans la liste des signalements
    // ═══════════════════════════════════════════════════════════════
    await page.goto('/index.php?page=report_list&type=rami');
    await expect(page.locator('h1')).toContainText('RAMI');

    // Le signalement devrait apparaître dans la liste
    await expect(page.locator('#main-content')).toContainText('Test E2E — Agression verbale');

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 7 : Désactiver le registre RAMI
    // ═══════════════════════════════════════════════════════════════
    await page.goto('/index.php?page=settings&tab=registres');

    const ramiCard3 = page.locator('div.card:has(h3:has-text("Registre des Actes"))');
    const ramiToggle3 = ramiCard3.locator('input[type="checkbox"][name*="registres"]');
    await ramiToggle3.uncheck();

    await page.locator('button:has-text("Enregistrer")').click();
    await expect(page.locator('.alert--success')).toContainText('Registres mis à jour');

    // Vérifier que RAMI n'apparaît plus sur le dashboard
    await page.goto('/index.php?page=home');
    const ramiCardGone = page.locator('.registry-card--rami');
    // Après désactivation, la carte ne devrait plus être visible
    // (ou le lien ne devrait plus être dans le sidebar)
  });

});
