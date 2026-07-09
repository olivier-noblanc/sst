<?php

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
 * @param array $postData  The $_POST data
 */
function handleSettingsAppTab(PDO $pdo, array $postData): void
{
    // Update application settings
    // NOTE: app_version is NOT editable here — it is read from CHANGELOG.md by getAppVersion()
    $appNomOrganisation = trim($postData['app_nom_organisation'] ?? '');
    $appNomComplet = trim($postData['app_nom_complet'] ?? '');
    $appLabelUnite = trim($postData['app_label_unite'] ?? '');
    $appSuperviseurUsernames = trim($postData['app_superviseur_usernames'] ?? '');

    // Validate: none should be empty (admin usernames can be empty)
    $errors = [];
    if (empty($appNomOrganisation)) {
        $errors[] = 'Le nom de l\'organisation est requis.';
    }
    if (empty($appNomComplet)) {
        $errors[] = 'Le nom complet est requis.';
    }
    if (empty($appLabelUnite)) {
        $errors[] = 'Le libellé des unités est requis.';
    }

    if (!empty($errors)) {
        $pdo->rollBack();
        setFlash('error', implode(' ', $errors));
        redirect(url('settings', ['tab' => 'app']));
    }

    updateConfig($pdo, 'app_nom_organisation', $appNomOrganisation);
    updateConfig($pdo, 'app_nom_complet', $appNomComplet);
    updateConfig($pdo, 'app_label_unite', $appLabelUnite);
    updateConfig($pdo, 'app_superviseur_usernames', $appSuperviseurUsernames);

    // Hotline number (displayed in help page)
    $appHotlineNumber = trim($postData['app_hotline_number'] ?? '');
    updateConfig($pdo, 'app_hotline_number', $appHotlineNumber);

    // DPO contact (displayed in RGPD preamble)
    $appDpoContact = trim($postData['app_dpo_contact'] ?? '');
    updateConfig($pdo, 'app_dpo_contact', $appDpoContact);

    // Admin email for error notifications
    $appAdminEmail = trim($postData['app_admin_email'] ?? '');
    if (!empty($appAdminEmail) && !filter_var($appAdminEmail, FILTER_VALIDATE_EMAIL)) {
        $pdo->rollBack();
        setFlash('error', 'L\'adresse e-mail de l\'administrateur technique n\'est pas valide.');
        redirect(url('settings', ['tab' => 'app']));
    }
    updateConfig($pdo, 'app_admin_email', $appAdminEmail);

    // Display PHP errors toggle (admin debug option)
    $displayErrors = !empty($postData['app_display_errors']) ? '1' : '0';
    updateConfig($pdo, 'app_display_errors', $displayErrors);

    // Registry toggles (RAMI / DGI)
    $ramiEnabled = !empty($postData['app_registry_rami_enabled']) ? '1' : '0';
    $dgiEnabled = !empty($postData['app_registry_dgi_enabled']) ? '1' : '0';
    updateConfig($pdo, 'app_registry_rami_enabled', $ramiEnabled);
    updateConfig($pdo, 'app_registry_dgi_enabled', $dgiEnabled);

    // DGI: notify CSA/CHSCT (article L4131-2 Code du travail)
    $dgiNotifyCsa = !empty($postData['app_dgi_notify_csa']) ? '1' : '0';
    updateConfig($pdo, 'app_dgi_notify_csa', $dgiNotifyCsa);

    // Customizable role labels
    $roleLabelAgent = trim($postData['app_role_label_agent'] ?? 'Agent');
    $roleLabelSuperviseur = trim($postData['app_role_label_superviseur'] ?? 'Superviseur');
    $roleLabelChsct = trim($postData['app_role_label_chsct'] ?? 'Membre FS/CSA');
    if (empty($roleLabelAgent)) {
        $roleLabelAgent = 'Agent';
    }
    if (empty($roleLabelSuperviseur)) {
        $roleLabelSuperviseur = 'Superviseur';
    }
    if (empty($roleLabelChsct)) {
        $roleLabelChsct = 'Membre FS/CSA';
    }
    updateConfig($pdo, 'app_role_label_agent', $roleLabelAgent);
    updateConfig($pdo, 'app_role_label_superviseur', $roleLabelSuperviseur);
    updateConfig($pdo, 'app_role_label_chsct', $roleLabelChsct);

    // Report visibility setting (radio: confidential / agent_choice / public)
    $reportVisibility = $postData['app_report_visibility'] ?? 'agent_choice';
    if (!in_array($reportVisibility, ['confidential', 'agent_choice', 'public'])) {
        $reportVisibility = 'agent_choice';
    }
    updateConfig($pdo, 'app_report_visibility', $reportVisibility);

    // Per-registry visibility settings
    $registryTypes = [TYPE_RSST, TYPE_RAMI, TYPE_DGI];
    foreach ($registryTypes as $type) {
        $key = 'app_report_visibility_' . $type;
        $value = $postData[$key] ?? '';
        if ($value !== '' && !in_array($value, ['confidential', 'agent_choice', 'public'])) {
            $value = '';
        }
        updateConfig($pdo, $key, $value);
    }

    // Legacy keys: keep in sync for backward compatibility
    updateConfig($pdo, 'app_agent_visibility', $reportVisibility);
    updateConfig($pdo, 'app_agent_see_only_own', $reportVisibility === 'confidential' ? '1' : '0');

    // Clear the getConfig() static cache so new values are picked up immediately
    clearConfigCache();
}
