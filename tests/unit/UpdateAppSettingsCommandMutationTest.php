<?php
/**
 * Tests UpdateAppSettingsCommand exhaustively — kills Infection mutants on:
 *   - fromPost() : trim, CastString, Coalesce on every field
 *   - Defaults : empty input → default values
 *   - Boolean flags : !empty check
 *   - Brand color validation : regex fallback
 *   - Report visibility : in_array validation
 *   - CHSCT scope : in_array validation
 *   - Role labels : empty → default
 *   - Per-registry visibility : loop, filter
 */

use PHPUnit\Framework\TestCase;
use App\DTO\UpdateAppSettingsCommand;
use App\Enum\VisibilityMode;

class UpdateAppSettingsCommandMutationTest extends TestCase
{
    public function testFromPostReturnsDefaultsForEmptyInput(): void
    {
        $cmd = UpdateAppSettingsCommand::fromPost([]);

        // Kill Coalesce/CastString/trim mutants on every field
        $this->assertSame('', $cmd->appNomOrganisation);
        $this->assertSame('', $cmd->appNomComplet);
        $this->assertSame('', $cmd->appLabelUnite);
        $this->assertSame('', $cmd->appSuperviseurUsernames);
        $this->assertSame('#1e40af', $cmd->appBrandColor, 'default brand color');
        $this->assertSame('', $cmd->appHotlineNumber);
        $this->assertSame('', $cmd->appDpoContact);
        $this->assertSame('', $cmd->appReportPreamble);
        $this->assertSame('', $cmd->appRsstDescription);
        $this->assertSame('Signaler un événement', $cmd->appReportCreateLabel, 'default create label');
        $this->assertSame('Rattacher des collègues au signalement', $cmd->appLinkedAgentsLabel, 'default linked agents label');
        $this->assertSame('', $cmd->appBaseUrl);
        $this->assertSame('', $cmd->appAdminEmail);
        $this->assertFalse($cmd->appDisplayErrors);
        $this->assertFalse($cmd->appRegistryRamiEnabled);
        $this->assertFalse($cmd->appRegistryDgiEnabled);
        $this->assertFalse($cmd->appDgiNotifyCsa);
        $this->assertSame('Agent', $cmd->roleLabelAgent, 'default role label');
        $this->assertSame('Superviseur', $cmd->roleLabelSuperviseur);
        $this->assertSame('Membre FS/CSA', $cmd->roleLabelChsct);
        $this->assertSame(VisibilityMode::AgentChoice->value, $cmd->appReportVisibility);
        $this->assertSame('consent_only', $cmd->chsctScope);
        $this->assertSame([], $cmd->perRegistryVisibility);
    }

    public function testFromPostTrimsAllStringFields(): void
    {
        // Kill UnwrapTrim mutants
        $cmd = UpdateAppSettingsCommand::fromPost([
            'app_nom_organisation' => '  DREETS  ',
            'app_nom_complet' => '  App Full  ',
            'app_label_unite' => '  UR  ',
            'app_superviseur_usernames' => '  user1, user2  ',
            'app_hotline_number' => '  01 02 03  ',
            'app_dpo_contact' => '  dpo@gouv.fr  ',
            'app_report_preamble' => '  Preamble text  ',
            'app_rsst_description' => '  RSST desc  ',
            'app_base_url' => '  https://example.com/  ',
            'app_admin_email' => '  admin@gouv.fr  ',
        ]);

        $this->assertSame('DREETS', $cmd->appNomOrganisation);
        $this->assertSame('App Full', $cmd->appNomComplet);
        $this->assertSame('UR', $cmd->appLabelUnite);
        $this->assertSame('user1, user2', $cmd->appSuperviseurUsernames);
        $this->assertSame('01 02 03', $cmd->appHotlineNumber);
        $this->assertSame('dpo@gouv.fr', $cmd->appDpoContact);
        $this->assertSame('Preamble text', $cmd->appReportPreamble);
        $this->assertSame('RSST desc', $cmd->appRsstDescription);
        $this->assertSame('https://example.com', $cmd->appBaseUrl, 'trailing / must be stripped');
        $this->assertSame('admin@gouv.fr', $cmd->appAdminEmail);
    }

    public function testFromPostBrandColorValidation(): void
    {
        // Valid hex color
        $cmd = UpdateAppSettingsCommand::fromPost(['app_brand_color' => '#ff0000']);
        $this->assertSame('#ff0000', $cmd->appBrandColor);

        // Invalid hex → default
        $cmd = UpdateAppSettingsCommand::fromPost(['app_brand_color' => 'red']);
        $this->assertSame('#1e40af', $cmd->appBrandColor, 'invalid color → default');

        // Empty → default
        $cmd = UpdateAppSettingsCommand::fromPost(['app_brand_color' => '']);
        $this->assertSame('#1e40af', $cmd->appBrandColor);

        // Uppercase hex
        $cmd = UpdateAppSettingsCommand::fromPost(['app_brand_color' => '#FF0000']);
        $this->assertSame('#FF0000', $cmd->appBrandColor);
    }

    public function testFromPostBrandColorRejectsJunkAroundValidHex(): void
    {
        // Tue les mutants PregMatchRemoveCaret / PregMatchRemoveDollar :
        // sans l'ancre ^, 'X#ff0000' matcherait (le # est en position 2) ;
        // sans l'ancre $, '#ff0000X' matcherait (6 hexa présents en tête).
        // Dans les deux cas le mutant accepterait la valeur polluée au lieu
        // de retomber sur la couleur par défaut.
        $cmd = UpdateAppSettingsCommand::fromPost(['app_brand_color' => 'X#ff0000']);
        $this->assertSame('#1e40af', $cmd->appBrandColor, 'junk prefix must invalidate the color');

        $cmd = UpdateAppSettingsCommand::fromPost(['app_brand_color' => '#ff0000X']);
        $this->assertSame('#1e40af', $cmd->appBrandColor, 'junk suffix must invalidate the color');

        // 7 hexa : trop long même avec les deux ancres
        $cmd = UpdateAppSettingsCommand::fromPost(['app_brand_color' => '#ff0000f']);
        $this->assertSame('#1e40af', $cmd->appBrandColor, '7 hex digits must invalidate the color');
    }

    public function testFromPostBrandColorRequiresAnchors(): void
    {
        // Kill PregMatchRemoveCaret (^) and PregMatchRemoveDollar ($) on line 48.
        // 'xx#ff0000' matches WITHOUT the ^ anchor → must fall back to default (kills RemoveCaret).
        $cmd = UpdateAppSettingsCommand::fromPost(['app_brand_color' => 'xx#ff0000']);
        $this->assertSame('#1e40af', $cmd->appBrandColor, 'leading chars must fail ^ anchor → default');

        // '#ff0000xx' matches WITHOUT the $ anchor → must fall back to default (kills RemoveDollar).
        $cmd = UpdateAppSettingsCommand::fromPost(['app_brand_color' => '#ff0000xx']);
        $this->assertSame('#1e40af', $cmd->appBrandColor, 'trailing chars must fail $ anchor → default');

        // Short hex ('#ff000') and long hex ('#ff00000') are invalid anchored; without
        // bounds they'd wrongly pass a loosened regex — guard the {6} quantifier too.
        $cmd = UpdateAppSettingsCommand::fromPost(['app_brand_color' => '#ff000']);
        $this->assertSame('#1e40af', $cmd->appBrandColor, '5 hex chars → default');
    }

    public function testFromPostReportCreateLabelDefaultWhenEmpty(): void
    {
        $cmd = UpdateAppSettingsCommand::fromPost(['app_report_create_label' => '']);
        $this->assertSame('Signaler un événement', $cmd->appReportCreateLabel);

        $cmd = UpdateAppSettingsCommand::fromPost(['app_report_create_label' => 'Custom Label']);
        $this->assertSame('Custom Label', $cmd->appReportCreateLabel);
    }

    public function testFromPostLinkedAgentsLabelDefaultWhenEmpty(): void
    {
        $cmd = UpdateAppSettingsCommand::fromPost(['app_linked_agents_label' => '']);
        $this->assertSame('Rattacher des collègues au signalement', $cmd->appLinkedAgentsLabel);

        $cmd = UpdateAppSettingsCommand::fromPost(['app_linked_agents_label' => 'Custom']);
        $this->assertSame('Custom', $cmd->appLinkedAgentsLabel);
    }

    public function testFromPostBaseUrlStripsTrailingSlash(): void
    {
        // Kill rtrim mutant
        $cmd = UpdateAppSettingsCommand::fromPost(['app_base_url' => 'https://app.gouv.fr/']);
        $this->assertSame('https://app.gouv.fr', $cmd->appBaseUrl);

        $cmd = UpdateAppSettingsCommand::fromPost(['app_base_url' => 'https://app.gouv.fr///']);
        $this->assertSame('https://app.gouv.fr', $cmd->appBaseUrl, 'all trailing slashes stripped');

        $cmd = UpdateAppSettingsCommand::fromPost(['app_base_url' => 'https://app.gouv.fr/path/']);
        $this->assertSame('https://app.gouv.fr/path', $cmd->appBaseUrl);
    }

    public function testFromPostBooleanFlagsTruthTable(): void
    {
        // Kill !empty mutants on each boolean flag
        $flags = [
            'app_display_errors' => 'appDisplayErrors',
            'app_registry_rami_enabled' => 'appRegistryRamiEnabled',
            'app_registry_dgi_enabled' => 'appRegistryDgiEnabled',
            'app_dgi_notify_csa' => 'appDgiNotifyCsa',
        ];
        foreach ($flags as $key => $prop) {
            $cmd = UpdateAppSettingsCommand::fromPost([$key => '1']);
            $this->assertTrue($cmd->$prop, "$key='1' → true");

            $cmd = UpdateAppSettingsCommand::fromPost([$key => '0']);
            $this->assertFalse($cmd->$prop, "$key='0' → false");

            $cmd = UpdateAppSettingsCommand::fromPost([]);
            $this->assertFalse($cmd->$prop, "$key missing → false");
        }
    }

    public function testFromPostRoleLabelDefaultsWhenEmpty(): void
    {
        // Kill empty → default mutants
        $cmd = UpdateAppSettingsCommand::fromPost([
            'app_role_label_agent' => '',
            'app_role_label_superviseur' => '',
            'app_role_label_chsct' => '',
        ]);
        $this->assertSame('Agent', $cmd->roleLabelAgent);
        $this->assertSame('Superviseur', $cmd->roleLabelSuperviseur);
        $this->assertSame('Membre FS/CSA', $cmd->roleLabelChsct);
    }

    public function testFromPostRoleLabelUsesProvidedValues(): void
    {
        $cmd = UpdateAppSettingsCommand::fromPost([
            'app_role_label_agent' => 'Agent SST',
            'app_role_label_superviseur' => 'Manager',
            'app_role_label_chsct' => 'Représentant',
        ]);
        $this->assertSame('Agent SST', $cmd->roleLabelAgent);
        $this->assertSame('Manager', $cmd->roleLabelSuperviseur);
        $this->assertSame('Représentant', $cmd->roleLabelChsct);
    }

    public function testFromPostRoleLabelUsesPostDefaultWhenKeyMissing(): void
    {
        // Kill ?? 'Agent' / ?? 'Superviseur' / ?? 'Membre FS/CSA' Coalesce mutants
        $cmd = UpdateAppSettingsCommand::fromPost([]);
        $this->assertSame('Agent', $cmd->roleLabelAgent);
        $this->assertSame('Superviseur', $cmd->roleLabelSuperviseur);
        $this->assertSame('Membre FS/CSA', $cmd->roleLabelChsct);
    }

    public function testFromPostReportVisibilityValidation(): void
    {
        // Valid values
        foreach ([VisibilityMode::Confidential->value, VisibilityMode::AgentChoice->value, VisibilityMode::Public->value] as $mode) {
            $cmd = UpdateAppSettingsCommand::fromPost(['app_report_visibility' => $mode]);
            $this->assertSame($mode, $cmd->appReportVisibility);
        }

        // Invalid → default
        $cmd = UpdateAppSettingsCommand::fromPost(['app_report_visibility' => 'invalid']);
        $this->assertSame(VisibilityMode::AgentChoice->value, $cmd->appReportVisibility);

        // Missing → default
        $cmd = UpdateAppSettingsCommand::fromPost([]);
        $this->assertSame(VisibilityMode::AgentChoice->value, $cmd->appReportVisibility);
    }

    public function testFromPostChsctScopeValidation(): void
    {
        // Valid values
        $cmd = UpdateAppSettingsCommand::fromPost(['app_chsct_report_scope' => 'consent_only']);
        $this->assertSame('consent_only', $cmd->chsctScope);

        $cmd = UpdateAppSettingsCommand::fromPost(['app_chsct_report_scope' => 'all']);
        $this->assertSame('all', $cmd->chsctScope);

        // Invalid → default
        $cmd = UpdateAppSettingsCommand::fromPost(['app_chsct_report_scope' => 'invalid']);
        $this->assertSame('consent_only', $cmd->chsctScope);

        // Missing → default
        $cmd = UpdateAppSettingsCommand::fromPost([]);
        $this->assertSame('consent_only', $cmd->chsctScope);
    }

    public function testFromPostPerRegistryVisibility(): void
    {
        // Kill Foreach mutant — per-registry visibility must be populated
        $cmd = UpdateAppSettingsCommand::fromPost([
            'app_report_visibility_rsst' => 'public',
            'app_report_visibility_rami' => 'confidential',
        ]);

        $this->assertSame('public', $cmd->perRegistryVisibility['rsst']);
        $this->assertSame('confidential', $cmd->perRegistryVisibility['rami']);
        $this->assertArrayNotHasKey('dgi', $cmd->perRegistryVisibility, 'dgi not set → not in array');
    }

    public function testFromPostPerRegistryVisibilityFiltersInvalidValues(): void
    {
        // Kill in_array validation mutant
        $cmd = UpdateAppSettingsCommand::fromPost([
            'app_report_visibility_rsst' => 'invalid_value',
        ]);
        $this->assertArrayNotHasKey('rsst', $cmd->perRegistryVisibility, 'invalid value filtered out');
    }

    public function testFromPostPerRegistryVisibilityFiltersEmptyValues(): void
    {
        // Kill empty check mutant
        $cmd = UpdateAppSettingsCommand::fromPost([
            'app_report_visibility_rsst' => '',
        ]);
        $this->assertArrayNotHasKey('rsst', $cmd->perRegistryVisibility, 'empty value filtered out');
    }
}
