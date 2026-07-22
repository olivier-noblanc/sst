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
 * @param array<string, mixed> $postData  The $_POST data
 */
function handleSettingsAppTab(PDO $pdo, array $postData): void
{
    /** @var array<string, string> $postData */
    // Update application settings
    // NOTE: app_version is NOT editable here — it is read from CHANGELOG.md by getAppVersion()
    $appNomOrganisation = trim((string) ($postData['app_nom_organisation'] ?? ''));
    $appNomComplet = trim((string) ($postData['app_nom_complet'] ?? ''));
    $appLabelUnite = trim((string) ($postData['app_label_unite'] ?? ''));
    $appSuperviseurUsernames = trim((string) ($postData['app_superviseur_usernames'] ?? ''));

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

    // Brand color (used in email templates)
    $appBrandColor = trim((string) ($postData['app_brand_color'] ?? ''));
    if ($appBrandColor === '' || preg_match('/^#[0-9a-fA-F]{6}$/', $appBrandColor) !== 1) {
        $appBrandColor = '#1e40af';
    }
    updateConfig($pdo, 'app_brand_color', $appBrandColor);

    // Hotline number (displayed in help page)
    $appHotlineNumber = trim((string) ($postData['app_hotline_number'] ?? ''));
    updateConfig($pdo, 'app_hotline_number', $appHotlineNumber);

    // DPO contact (displayed in RGPD preamble)
    $appDpoContact = trim((string) ($postData['app_dpo_contact'] ?? ''));
    updateConfig($pdo, 'app_dpo_contact', $appDpoContact);

    // Report preamble (displayed in report form)
    $appReportPreamble = trim((string) ($postData['app_report_preamble'] ?? ''));
    updateConfig($pdo, 'app_report_preamble', $appReportPreamble);

    // RSST registry description (displayed on home page)
    $appRsstDescription = trim((string) ($postData['app_rsst_description'] ?? ''));
    updateConfig($pdo, 'app_rsst_description', $appRsstDescription);

    // Report creation label (button/heading/tab title across several pages)
    // — never save blank: an empty label would leave the primary action
    // button with no visible text anywhere it's used.
    $appReportCreateLabel = trim((string) ($postData['app_report_create_label'] ?? ''));
    updateConfig($pdo, 'app_report_create_label', $appReportCreateLabel !== '' ? $appReportCreateLabel : 'Signaler un événement');

    // Linked agents label (form field label)
    $appLinkedAgentsLabel = trim((string) ($postData['app_linked_agents_label'] ?? ''));
    updateConfig($pdo, 'app_linked_agents_label', $appLinkedAgentsLabel !== '' ? $appLinkedAgentsLabel : 'Rattacher des collègues au signalement');

    // Admin email for error notifications
    $appAdminEmail = trim((string) ($postData['app_admin_email'] ?? ''));
    if ($appAdminEmail !== '' && filter_var($appAdminEmail, FILTER_VALIDATE_EMAIL) === false) {
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
    $roleLabelAgent = trim((string) ($postData['app_role_label_agent'] ?? 'Agent'));
    $roleLabelSuperviseur = trim((string) ($postData['app_role_label_superviseur'] ?? 'Superviseur'));
    $roleLabelChsct = trim((string) ($postData['app_role_label_chsct'] ?? 'Membre FS/CSA'));
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
    $reportVisibility = (string) ($postData['app_report_visibility'] ?? 'agent_choice');
    if (!in_array($reportVisibility, ['confidential', 'agent_choice', 'public'], true)) {
        $reportVisibility = 'agent_choice';
    }
    updateConfig($pdo, 'app_report_visibility', $reportVisibility);

    // Per-registry visibility settings
    $registryTypes = [TYPE_RSST, TYPE_RAMI, TYPE_DGI];
    foreach ($registryTypes as $type) {
        $key = 'app_report_visibility_' . $type;
        $value = (string) ($postData[$key] ?? '');
        if ($value !== '' && !in_array($value, ['confidential', 'agent_choice', 'public'], true)) {
            $value = '';
        }
        updateConfig($pdo, $key, $value);
    }

    // Legacy keys: keep in sync for backward compatibility
    updateConfig($pdo, 'app_agent_visibility', $reportVisibility);
    updateConfig($pdo, 'app_agent_see_only_own', $reportVisibility === 'confidential' ? '1' : '0');

    // CHSCT report scope (consent_only / all)
    $chsctScope = (string) ($postData['app_chsct_report_scope'] ?? 'consent_only');
    if (!in_array($chsctScope, ['consent_only', 'all'], true)) {
        $chsctScope = 'consent_only';
    }
    updateConfig($pdo, 'app_chsct_report_scope', $chsctScope);

    // Clear the getConfig() static cache so new values are picked up immediately
    clearConfigCache();
}
