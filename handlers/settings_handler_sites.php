<?php

/**
 * Settings Manage Sites Tab Handler — Application SST DREETS BFC
 *
 * Handles the 'manage_sites' tab of the settings page.
 * Split from settings_handler.php for readability.
 */

/**
 * Handle the 'manage_sites' tab of settings.
 *
 * @param PDO   $pdo       Database connection
 * @param array<string, mixed> $postData  The $_POST data
 */
function handleSettingsManageSitesTab(PDO $pdo, array $postData): void
{
    /** @var array<string, string> $postData */
    $action = (string) ($postData['action'] ?? '');

    if ($action === 'add_site') {
        $code = trim((string) ($postData['new_site_code'] ?? ''));
        $nom = trim((string) ($postData['new_site_nom'] ?? ''));
        $departement = trim((string) ($postData['new_site_departement'] ?? ''));

        if (empty($code) || empty($nom)) {
            $pdo->rollBack();
            setFlash('error', 'Le code et le nom du site sont requis.');
            redirect(url('settings', ['tab' => 'manage_sites']));
        }

        // Check for duplicate code
        $existing = getSiteByCode($pdo, $code);
        if ($existing !== null) {
            $pdo->rollBack();
            setFlash('error', 'Un site avec ce code existe déjà.');
            redirect(url('settings', ['tab' => 'manage_sites']));
        }

        createSite($pdo, $code, $nom, $departement);
    }

    if ($action === 'toggle_site') {
        $siteId = (int) ($postData['site_id'] ?? 0);
        $isActive = (bool) ($postData['is_active'] ?? 0);

        if ($siteId > 0) {
            toggleSiteActive($pdo, $siteId, $isActive);
        }
    }

    if ($action === 'delete_site') {
        $siteId = (int) ($postData['site_id'] ?? 0);

        if ($siteId > 0) {
            // Verify this site can be deleted (no users, no reports)
            $userCount = countUsersBySite($pdo, $siteId);
            $reportCount = countReportsBySite($pdo, $siteId);

            if ($userCount > 0 || $reportCount > 0) {
                $pdo->rollBack();
                setFlash('error', 'Impossible de supprimer ce site : il contient ' . $userCount . ' agent(s) et ' . $reportCount . ' signalement(s). Désactivez-le plutôt.');
                redirect(url('settings', ['tab' => 'manage_sites']));
            }

            $deleted = deleteSite($pdo, $siteId);
            if (!$deleted) {
                $pdo->rollBack();
                setFlash('error', 'Erreur lors de la suppression du site.');
                redirect(url('settings', ['tab' => 'manage_sites']));
            }
        }
    }
}
