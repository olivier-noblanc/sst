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

    // ── Validation COMPLÈTE avant la moindre écriture ────────────────────────
    // Fiabilisation (council) — avant ce fix :
    // 1. les validations échouées appelaient $pdo->rollBack() SANS transaction
    //    ouverte → PDOException "There is no active transaction" → fatal 500
    //    au lieu du flash d'erreur attendu ;
    // 2. app_base_url / app_admin_email étaient validés APRÈS 11 écritures de
    //    configuration → persistance partielle (les 11 premières clés écrites,
    //    les suivantes non).
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
    // Public base URL for email links — empty is a valid choice (means
    // "auto-detect from the request"), unlike the labels above.
    if ($cmd->appBaseUrl !== '' && filter_var($cmd->appBaseUrl, FILTER_VALIDATE_URL) === false) {
        $errors[] = 'L\'URL publique de l\'application n\'est pas valide (ex : https://sst.dreets-bfc.gouv.fr).';
    }
    // Admin email for error notifications
    if ($cmd->appAdminEmail !== '' && filter_var($cmd->appAdminEmail, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'L\'adresse e-mail de l\'administrateur technique n\'est pas valide.';
    }

    if (!empty($errors)) {
        $session->setFlash('error', implode(' ', $errors));
        $http->redirect($http->url('settings', ['tab' => 'app']));
    }

    // ── Écriture ATOMIQUE : une transaction, tout ou rien ────────────────────
    $values = [
        'app_nom_organisation' => $cmd->appNomOrganisation,
        'app_nom_complet' => $cmd->appNomComplet,
        'app_label_unite' => $cmd->appLabelUnite,
        'app_superviseur_usernames' => $cmd->appSuperviseurUsernames,
        'app_brand_color' => $cmd->appBrandColor,
        'app_hotline_number' => $cmd->appHotlineNumber,
        'app_dpo_contact' => $cmd->appDpoContact,
        'app_report_preamble' => $cmd->appReportPreamble,
        'app_rsst_description' => $cmd->appRsstDescription,
        'app_report_create_label' => $cmd->appReportCreateLabel,
        'app_linked_agents_label' => $cmd->appLinkedAgentsLabel,
        'app_base_url' => $cmd->appBaseUrl,
        'app_admin_email' => $cmd->appAdminEmail,
        'app_display_errors' => $cmd->appDisplayErrors ? '1' : '0',
        'app_registry_rami_enabled' => $cmd->appRegistryRamiEnabled ? '1' : '0',
        'app_registry_dgi_enabled' => $cmd->appRegistryDgiEnabled ? '1' : '0',
        // DGI: notify CSA/CHSCT (article L4131-2 Code du travail)
        'app_dgi_notify_csa' => $cmd->appDgiNotifyCsa ? '1' : '0',
        // Customizable role labels
        'app_role_label_agent' => $cmd->roleLabelAgent,
        'app_role_label_superviseur' => $cmd->roleLabelSuperviseur,
        'app_role_label_chsct' => $cmd->roleLabelChsct,
        // Report visibility setting
        'app_report_visibility' => $cmd->appReportVisibility,
    ];

    // Per-registry visibility settings
    foreach (ReportType::cases() as $type) {
        $key = 'app_report_visibility_' . $type->value;
        $values[$key] = $cmd->perRegistryVisibility[$type->value] ?? '';
    }

    // Legacy keys: keep in sync for backward compatibility
    $values['app_agent_visibility'] = $cmd->appReportVisibility;
    $values['app_agent_see_only_own'] = $cmd->appReportVisibility === VisibilityMode::Confidential->value ? '1' : '0';

    // CHSCT report scope
    $values['app_chsct_report_scope'] = $cmd->chsctScope;

    getConfigService()->setMany($values);
}
