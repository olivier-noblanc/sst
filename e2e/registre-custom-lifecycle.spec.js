/**
 * SST Application — Registre Custom: Cycle de Vie Complet E2E
 *
 * Test du cycle de vie complet d'un registre CUSTOM (pas RSST/RAMI/DGI) :
 * 1. Ajouter un registre via les settings
 * 2. Vérifier qu'il apparaît sur le dashboard et le sidebar
 * 3. Créer un signalement dans ce registre
 * 4. Vérifier le signalement dans la liste
 * 5. Modifier le signalement
 * 6. Supprimer le registre
 * 7. Vérifier qu'il n'apparaît plus
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './helpers.js';

test.describe('Registre Custom — Cycle de Vie Complet', () => {

  test('ajouter, utiliser, personnaliser et supprimer un registre custom', async ({ page }) => {

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 1 : Ajouter un registre custom via les settings
    // ═══════════════════════════════════════════════════════════════
    await loginAs(page);
    await page.goto('/index.php?page=settings&tab=registres');

    // Vérifier que la page des registres s'affiche
    await expect(page.locator('h2:has-text("Gestion des registres")')).toBeVisible();

    // Remplir le formulaire d'ajout
    await page.locator('#new_code').fill('violences');
    await page.locator('#new_label').fill('Registre des Violences');
    await page.locator('#new_short_label').fill('VIOL');

    // Soumettre l'ajout
    await page.locator('button:has-text("Ajouter ce registre")').click();
    await page.waitForLoadState('networkidle');

    // Vérifier le message de succès
    await expect(page.locator('.alert--success')).toContainText('ajouté');

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 2 : Vérifier que le registre apparaît dans les settings
    // ═══════════════════════════════════════════════════════════════
    await page.goto('/index.php?page=settings&tab=registres');
    await expect(page.locator('h2:has-text("Gestion des registres")')).toBeVisible();

    // Le registre VIOL devrait être visible
    const violCard = page.locator('div.card:has(h3:has-text("Registre des Violences"))');
    await expect(violCard).toBeVisible();

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 3 : Personnaliser couleur et icône
    // ═══════════════════════════════════════════════════════════════
    const violCard2 = page.locator('div.card:has(h3:has-text("Registre des Violences"))');

    // Choisir la couleur "violet"
    await violCard2.locator('input[type="radio"][name*="color_theme"][value="violet"]').check();

    // Choisir l'icône "🚨"
    await violCard2.locator('input[type="radio"][name*="icon"][value="🚨"]').check();

    // Enregistrer
    await page.locator('button:has-text("Enregistrer les modifications")').click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.alert--success')).toContainText('Registres mis à jour');

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 4 : Vérifier sur le dashboard
    // ═══════════════════════════════════════════════════════════════
    await page.goto('/index.php?page=home');
    await expect(page.locator('.registry-cards')).toBeVisible();

    // La carte VIOL devrait être visible
    const violDashboardCard = page.locator('.registry-card--violences');
    await expect(violDashboardCard).toBeVisible();

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 5 : Vérifier dans le sidebar
    // ═══════════════════════════════════════════════════════════════
    await expect(page.locator('.sidebar__item:has-text("VIOL")')).toBeVisible();

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 6 : Créer un signalement dans le registre custom
    // ═══════════════════════════════════════════════════════════════
    await page.goto('/index.php?page=report_create&type=violences');

    // Vérifier que le formulaire s'affiche (pas de redirect "registre invalide")
    await expect(page.locator('#objet')).toBeVisible({ timeout: 5000 });

    // Remplir les champs obligatoires
    await page.locator('#date_evenement').fill('2026-07-24');
    await page.locator('#objet').fill('Test E2E — Violence verbale');
    await page.locator('#description').fill('Test E2E complet : cycle de vie d\'un registre custom violences.');
    await page.locator('#pole').fill('Pôle Test');
    await page.locator('#telephone_mobile').fill('0611111111');

    // Choisir le site si visible
    const siteSelect = page.locator('#site_id');
    if (await siteSelect.isVisible()) {
      await siteSelect.selectOption({ index: 1 });
    }

    // Soumettre
    await page.locator('.card button[type="submit"]').click();
    await expect(page).toHaveURL(/page=report_view/, { timeout: 10000 });

    // Vérifier le signalement créé
    await expect(page.locator('#main-content')).toContainText('Test E2E — Violence verbale');

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 7 : Vérifier dans la liste
    // ═══════════════════════════════════════════════════════════════
    await page.goto('/index.php?page=report_list&type=violences');
    await expect(page.locator('#main-content')).toContainText('Test E2E — Violence verbale');

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 8 : Supprimer le registre custom
    // ═══════════════════════════════════════════════════════════════
    await page.goto('/index.php?page=settings&tab=registres');

    const violCard3 = page.locator('div.card:has(h3:has-text("Registre des Violences"))');
    await expect(violCard3).toBeVisible();

    // Le bouton supprimer ne devrait pas être disabled (pas système)
    const deleteBtn = violCard3.locator('button:has-text("Supprimer")');
    await expect(deleteBtn).toBeEnabled();

    // Accepter la confirmation
    page.on('dialog', dialog => dialog.accept());
    await deleteBtn.click();
    await page.waitForLoadState('networkidle');

    // Vérifier le message de suppression
    await expect(page.locator('.alert--success')).toContainText('supprimé');

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 9 : Vérifier qu'il n'apparaît plus
    // ═══════════════════════════════════════════════════════════════
    await page.goto('/index.php?page=settings&tab=registres');
    const violCardGone = page.locator('div.card:has(h3:has-text("Registre des Violences"))');
    await expect(violCardGone).toHaveCount(0);

    // Plus sur le dashboard
    await page.goto('/index.php?page=home');
    const violDashboardGone = page.locator('.registry-card--violences');
    await expect(violDashboardGone).toHaveCount(0);

    // Plus dans le sidebar
    await expect(page.locator('.sidebar__item:has-text("VIOL")')).toHaveCount(0);
  });

});
