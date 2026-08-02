<?php

use App\Enum\ReportType;
use App\Enum\VisibilityMode;
use App\Services\HttpService;
use App\Services\SessionService;
use App\DTO\UpdateAppSettingsCommand;

/**
 * Settings App Tab Handler — Application SST DREETS BFC
 *
 * Handles the 'app' tab of the settings page.
 * Split from settings_handler.php for readability.
 */

/**
 * Handle the 'app' tab of settings.
 *
 * @param PDO   $pdo       Database connection
 * @param UpdateAppSettingsCommand $cmd  The validated settings command
 */
function handleSettingsAppTab(PDO $pdo, UpdateAppSettingsCommand $cmd): void
{
    $http = new HttpService();
    $session = SessionService::getInstance();

    // Validate: none should be empty (admin usernames can be empty)
    $errors = [];
    if ($cmd->appNomOrganisation === '') {
        $errors[] = 'Le nom de l\'organisation est requis.';
    }
    if ($cmd->appNomComplet === '') {
        $errors[] = 'Le nom complet est requis.';
    }
    if ($cmd->appLabelUnite === '') {
        $errors[] = 'Le libellé des unités est requis.';
    }

    if (!empty($errors)) {
        $pdo->rollBack();
        $session->setFlash('error', implode(' ', $errors));
        $http->redirect($http->url('settings', ['tab' => 'app']));
    }

    updateConfig($pdo, 'app_nom_organisation', $cmd->appNomOrganisation);
    updateConfig($pdo, 'app_nom_complet', $cmd->appNomComplet);
    updateConfig($pdo, 'app_label_unite', $cmd->appLabelUnite);
    updateConfig($pdo, 'app_superviseur_usernames', $cmd->appSuperviseurUsernames);
    updateConfig($pdo, 'app_brand_color', $cmd->appBrandColor);
    updateConfig($pdo, 'app_hotline_number', $cmd->appHotlineNumber);
    updateConfig($pdo, 'app_dpo_contact', $cmd->appDpoContact);
    updateConfig($pdo, 'app_report_preamble', $cmd->appReportPreamble);
    updateConfig($pdo, 'app_rsst_description', $cmd->appRsstDescription);
    updateConfig($pdo, 'app_report_create_label', $cmd->appReportCreateLabel);
    updateConfig($pdo, 'app_linked_agents_label', $cmd->appLinkedAgentsLabel);

    // Public base URL for email links — empty is a valid choice (means
    // "auto-detect from the request"), unlike the labels above.
    if ($cmd->appBaseUrl !== '' && filter_var($cmd->appBaseUrl, FILTER_VALIDATE_URL) === false) {
        $pdo->rollBack();
        $session->setFlash('error', 'L\'URL publique de l\'application n\'est pas valide (ex : https://sst.dreets-bfc.gouv.fr).');
        $http->redirect($http->url('settings', ['tab' => 'app']));
    }
    updateConfig($pdo, 'app_base_url', $cmd->appBaseUrl);

    // Admin email for error notifications
    if ($cmd->appAdminEmail !== '' && filter_var($cmd->appAdminEmail, FILTER_VALIDATE_EMAIL) === false) {
        $pdo->rollBack();
        $session->setFlash('error', 'L\'adresse e-mail de l\'administrateur technique n\'est pas valide.');
        $http->redirect($http->url('settings', ['tab' => 'app']));
    }
    updateConfig($pdo, 'app_admin_email', $cmd->appAdminEmail);

    // Display PHP errors toggle (admin debug option)
    updateConfig($pdo, 'app_display_errors', $cmd->appDisplayErrors ? '1' : '0');

    // Registry toggles (RAMI / DGI)
    updateConfig($pdo, 'app_registry_rami_enabled', $cmd->appRegistryRamiEnabled ? '1' : '0');
    updateConfig($pdo, 'app_registry_dgi_enabled', $cmd->appRegistryDgiEnabled ? '1' : '0');

    // DGI: notify CSA/CHSCT (article L4131-2 Code du travail)
    updateConfig($pdo, 'app_dgi_notify_csa', $cmd->appDgiNotifyCsa ? '1' : '0');

    // Customizable role labels
    updateConfig($pdo, 'app_role_label_agent', $cmd->roleLabelAgent);
    updateConfig($pdo, 'app_role_label_superviseur', $cmd->roleLabelSuperviseur);
    updateConfig($pdo, 'app_role_label_chsct', $cmd->roleLabelChsct);

    // Report visibility setting
    updateConfig($pdo, 'app_report_visibility', $cmd->appReportVisibility);

    // Per-registry visibility settings
    foreach (ReportType::cases() as $type) {
        $key = 'app_report_visibility_' . $type->value;
        $value = $cmd->perRegistryVisibility[$type->value] ?? '';
        updateConfig($pdo, $key, $value);
    }

    // Legacy keys: keep in sync for backward compatibility
    updateConfig($pdo, 'app_agent_visibility', $cmd->appReportVisibility);
    updateConfig($pdo, 'app_agent_see_only_own', $cmd->appReportVisibility === VisibilityMode::Confidential->value ? '1' : '0');

    // CHSCT report scope
    updateConfig($pdo, 'app_chsct_report_scope', $cmd->chsctScope);

    // Clear the getConfig() static cache so new values are picked up immediately
    clearConfigCache();
}
