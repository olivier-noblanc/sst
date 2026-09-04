<?php

use App\Services\HttpService;
use App\Services\SessionService;
use App\Repository\SiteRepository;

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
 * @param array<string, string> $postData  The $_POST data
 */
function handleSettingsManageSitesTab(PDO $pdo, array $postData): void
{
    /** @var array<string, string> $postData */
    $http = new HttpService();
    $session = SessionService::getInstance();

    $action = (string) ($postData['action'] ?? '');

    if ($action === 'add_site') {
        $code = trim((string) ($postData['new_site_code'] ?? ''));
        $nom = trim((string) ($postData['new_site_nom'] ?? ''));
        $departement = trim((string) ($postData['new_site_departement'] ?? ''));

        // Fiabilisation (council) — les validations échouées appelaient
        // $pdo->rollBack() SANS transaction ouverte → PDOException fatale
        // au lieu du flash d'erreur. Aucune transaction n'est ouverte ici :
        // une validation refusée n'écrit rien, un simple flash suffit.
        if (empty($code) || empty($nom)) {
            $session->setFlash('error', 'Le code et le nom du site sont requis.');
            $http->redirect($http->url('settings', ['tab' => 'manage_sites']));
        }

        // Check for duplicate code
        $existing = SiteRepository::instance()->findByCode($code);
        if ($existing !== null) {
            $session->setFlash('error', 'Un site avec ce code existe déjà.');
            $http->redirect($http->url('settings', ['tab' => 'manage_sites']));
        }

        SiteRepository::instance()->create($code, $nom, $departement);
    }

    if ($action === 'toggle_site') {
        $siteId = (int) ($postData['site_id'] ?? 0);
        $isActive = (bool) ($postData['is_active'] ?? 0);

        if ($siteId > 0) {
            SiteRepository::instance()->toggleActive($siteId, $isActive);
        }
    }

    if ($action === 'delete_site') {
        $siteId = (int) ($postData['site_id'] ?? 0);

        if ($siteId > 0) {
            // Verify this site can be deleted (no users, no reports)
            $userCount = SiteRepository::instance()->countUsers($siteId);
            $reportCount = SiteRepository::instance()->countReports($siteId);

            if ($userCount > 0 || $reportCount > 0) {
                // Fiabilisation (council) — rollBack() sans transaction → fatal.
                // Un refus métier n'écrit rien : flash + redirect suffisent.
                $session->setFlash('error', 'Impossible de supprimer ce site : il contient ' . $userCount . ' agent(s) et ' . $reportCount . ' signalement(s). Désactivez-le plutôt.');
                $http->redirect($http->url('settings', ['tab' => 'manage_sites']));
            }

            $deleted = SiteRepository::instance()->delete($siteId);
            if (!$deleted) {
                $session->setFlash('error', 'Erreur lors de la suppression du site.');
                $http->redirect($http->url('settings', ['tab' => 'manage_sites']));
            }
        }
    }
}
