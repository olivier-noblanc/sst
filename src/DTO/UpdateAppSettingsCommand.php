<?php

namespace App\DTO;

use App\Enum\ReportType;
use App\Enum\VisibilityMode;

final readonly class UpdateAppSettingsCommand
{
    /**
     * @param array<string, string> $perRegistryVisibility
     */
    public function __construct(
        public readonly string $appNomOrganisation,
        public readonly string $appNomComplet,
        public readonly string $appLabelUnite,
        public readonly string $appSuperviseurUsernames,
        public readonly string $appBrandColor,
        public readonly string $appHotlineNumber,
        public readonly string $appDpoContact,
        public readonly string $appReportPreamble,
        public readonly string $appRsstDescription,
        public readonly string $appReportCreateLabel,
        public readonly string $appLinkedAgentsLabel,
        public readonly string $appBaseUrl,
        public readonly string $appAdminEmail,
        public readonly bool $appDisplayErrors,
        public readonly bool $appRegistryRamiEnabled,
        public readonly bool $appRegistryDgiEnabled,
        public readonly bool $appDgiNotifyCsa,
        public readonly string $roleLabelAgent,
        public readonly string $roleLabelSuperviseur,
        public readonly string $roleLabelChsct,
        public readonly string $appReportVisibility,
        public readonly string $chsctScope,
        public readonly array $perRegistryVisibility = [],
    ) {}

    /** @param array<string, string> $post */
    public static function fromPost(array $post): self
    {
        $appNomOrganisation = trim((string) ($post['app_nom_organisation'] ?? ''));
        $appNomComplet = trim((string) ($post['app_nom_complet'] ?? ''));
        $appLabelUnite = trim((string) ($post['app_label_unite'] ?? ''));
        $appSuperviseurUsernames = trim((string) ($post['app_superviseur_usernames'] ?? ''));

        $appBrandColor = trim((string) ($post['app_brand_color'] ?? ''));
        if ($appBrandColor === '' || preg_match('/^#[0-9a-fA-F]{6}$/', $appBrandColor) !== 1) {
            $appBrandColor = '#1e40af';
        }

        $appHotlineNumber = trim((string) ($post['app_hotline_number'] ?? ''));
        $appDpoContact = trim((string) ($post['app_dpo_contact'] ?? ''));
        $appReportPreamble = trim((string) ($post['app_report_preamble'] ?? ''));
        $appRsstDescription = trim((string) ($post['app_rsst_description'] ?? ''));

        $appReportCreateLabel = trim((string) ($post['app_report_create_label'] ?? ''));
        if ($appReportCreateLabel === '') {
            $appReportCreateLabel = 'Signaler un événement';
        }

        $appLinkedAgentsLabel = trim((string) ($post['app_linked_agents_label'] ?? ''));
        if ($appLinkedAgentsLabel === '') {
            $appLinkedAgentsLabel = 'Rattacher des collègues au signalement';
        }

        $appBaseUrl = rtrim(trim((string) ($post['app_base_url'] ?? '')), '/');
        $appAdminEmail = trim((string) ($post['app_admin_email'] ?? ''));

        $appDisplayErrors = !empty($post['app_display_errors']);
        $appRegistryRamiEnabled = !empty($post['app_registry_rami_enabled']);
        $appRegistryDgiEnabled = !empty($post['app_registry_dgi_enabled']);
        $appDgiNotifyCsa = !empty($post['app_dgi_notify_csa']);

        $roleLabelAgent = trim((string) ($post['app_role_label_agent'] ?? 'Agent'));
        if ($roleLabelAgent === '') {
            $roleLabelAgent = 'Agent';
        }
        $roleLabelSuperviseur = trim((string) ($post['app_role_label_superviseur'] ?? 'Superviseur'));
        if ($roleLabelSuperviseur === '') {
            $roleLabelSuperviseur = 'Superviseur';
        }
        $roleLabelChsct = trim((string) ($post['app_role_label_chsct'] ?? 'Membre FS/CSA'));
        if ($roleLabelChsct === '') {
            $roleLabelChsct = 'Membre FS/CSA';
        }

        $reportVisibility = (string) ($post['app_report_visibility'] ?? VisibilityMode::AgentChoice->value);
        if (!in_array($reportVisibility, [VisibilityMode::Confidential->value, VisibilityMode::AgentChoice->value, VisibilityMode::Public->value], true)) {
            $reportVisibility = VisibilityMode::AgentChoice->value;
        }

        $chsctScope = (string) ($post['app_chsct_report_scope'] ?? 'consent_only');
        if (!in_array($chsctScope, ['consent_only', 'all'], true)) {
            $chsctScope = 'consent_only';
        }

        $perRegistryVisibility = [];
        foreach (ReportType::cases() as $type) {
            $key = 'app_report_visibility_' . $type->value;
            $value = (string) ($post[$key] ?? '');
            if ($value !== '' && !in_array($value, [VisibilityMode::Confidential->value, VisibilityMode::AgentChoice->value, VisibilityMode::Public->value], true)) {
                $value = '';
            }
            if ($value !== '') {
                $perRegistryVisibility[$type->value] = $value;
            }
        }

        return new self(
            appNomOrganisation: $appNomOrganisation,
            appNomComplet: $appNomComplet,
            appLabelUnite: $appLabelUnite,
            appSuperviseurUsernames: $appSuperviseurUsernames,
            appBrandColor: $appBrandColor,
            appHotlineNumber: $appHotlineNumber,
            appDpoContact: $appDpoContact,
            appReportPreamble: $appReportPreamble,
            appRsstDescription: $appRsstDescription,
            appReportCreateLabel: $appReportCreateLabel,
            appLinkedAgentsLabel: $appLinkedAgentsLabel,
            appBaseUrl: $appBaseUrl,
            appAdminEmail: $appAdminEmail,
            appDisplayErrors: $appDisplayErrors,
            appRegistryRamiEnabled: $appRegistryRamiEnabled,
            appRegistryDgiEnabled: $appRegistryDgiEnabled,
            appDgiNotifyCsa: $appDgiNotifyCsa,
            roleLabelAgent: $roleLabelAgent,
            roleLabelSuperviseur: $roleLabelSuperviseur,
            roleLabelChsct: $roleLabelChsct,
            appReportVisibility: $reportVisibility,
            chsctScope: $chsctScope,
            perRegistryVisibility: $perRegistryVisibility,
        );
    }
}
