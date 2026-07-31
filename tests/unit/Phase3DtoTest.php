<?php
/**
 * Phase 3 DTO Tests — TDD tests for new DTOs before implementation.
 *
 * Tests:
 *   - AttachmentData: construction and isEmpty()
 *   - UpdateAppSettingsCommand::fromPost(): valid data and defaults
 *   - getAdjacentUuids() with scalar params
 */

use PHPUnit\Framework\TestCase;
use App\DTO\AttachmentData;
use App\DTO\UpdateAppSettingsCommand;
use App\DTO\AdjacentUuids;
use App\Repository\ReportRepository;

class Phase3DtoTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM registries');
        \App\Repository\RegistryRepository::instance()->seedDefaults();
    }

    protected function tearDown(): void
    {
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM registries');
    }

    // ═══ AttachmentData ═══

    public function testAttachmentDataDefaultsToNull(): void
    {
        $att = new AttachmentData();
        $this->assertNull($att->blob);
        $this->assertNull($att->name);
        $this->assertNull($att->mime);
    }

    public function testAttachmentDataIsNotEmptyWhenBlobSet(): void
    {
        $att = new AttachmentData(blob: 'base64data', name: 'file.pdf', mime: 'application/pdf');
        $this->assertFalse($att->isEmpty());
    }

    public function testAttachmentDataIsEmptyWhenAllNull(): void
    {
        $att = new AttachmentData();
        $this->assertTrue($att->isEmpty());
    }

    public function testAttachmentDataIsEmptyWhenOnlyBlobSet(): void
    {
        $att = new AttachmentData(blob: 'data');
        $this->assertFalse($att->isEmpty());
    }

    // ═══ UpdateAppSettingsCommand::fromPost() ═══

    public function testFromPostExtractsAllFields(): void
    {
        $post = [
            'app_nom_organisation' => ' DREETS BFC ',
            'app_nom_complet' => ' SST Test ',
            'app_label_unite' => ' Unité ',
            'app_superviseur_usernames' => ' sup1, sup2 ',
            'app_brand_color' => '#ff0000',
            'app_hotline_number' => '03 80 00 00 00',
            'app_dpo_contact' => 'dpo@test.fr',
            'app_report_preamble' => ' Préambule ',
            'app_rsst_description' => ' Description RSST ',
            'app_report_create_label' => ' Signaler ',
            'app_linked_agents_label' => ' Rattacher ',
            'app_base_url' => 'https://sst.test.fr/',
            'app_admin_email' => 'admin@test.fr',
            'app_display_errors' => '1',
            'app_registry_rami_enabled' => '1',
            'app_registry_dgi_enabled' => '0',
            'app_dgi_notify_csa' => '1',
            'app_role_label_agent' => ' Agent ',
            'app_role_label_superviseur' => ' Superviseur ',
            'app_role_label_chsct' => ' Membre CSA ',
            'app_report_visibility' => 'confidential',
            'app_chsct_report_scope' => 'all',
            'app_report_visibility_rsst' => 'public',
            'app_report_visibility_rami' => 'confidential',
            'app_report_visibility_dgi' => 'agent_choice',
        ];

        $cmd = UpdateAppSettingsCommand::fromPost($post);

        $this->assertSame('DREETS BFC', $cmd->appNomOrganisation);
        $this->assertSame('SST Test', $cmd->appNomComplet);
        $this->assertSame('Unité', $cmd->appLabelUnite);
        $this->assertSame('sup1, sup2', $cmd->appSuperviseurUsernames);
        $this->assertSame('#ff0000', $cmd->appBrandColor);
        $this->assertSame('03 80 00 00 00', $cmd->appHotlineNumber);
        $this->assertSame('dpo@test.fr', $cmd->appDpoContact);
        $this->assertSame('Préambule', $cmd->appReportPreamble);
        $this->assertSame('Description RSST', $cmd->appRsstDescription);
        $this->assertSame('Signaler', $cmd->appReportCreateLabel);
        $this->assertSame('Rattacher', $cmd->appLinkedAgentsLabel);
        $this->assertSame('https://sst.test.fr', $cmd->appBaseUrl);
        $this->assertSame('admin@test.fr', $cmd->appAdminEmail);
        $this->assertTrue($cmd->appDisplayErrors);
        $this->assertTrue($cmd->appRegistryRamiEnabled);
        $this->assertFalse($cmd->appRegistryDgiEnabled);
        $this->assertTrue($cmd->appDgiNotifyCsa);
        $this->assertSame('Agent', $cmd->roleLabelAgent);
        $this->assertSame('Superviseur', $cmd->roleLabelSuperviseur);
        $this->assertSame('Membre CSA', $cmd->roleLabelChsct);
        $this->assertSame('confidential', $cmd->appReportVisibility);
        $this->assertSame('all', $cmd->chsctScope);
        $this->assertSame([
            'rsst' => 'public',
            'rami' => 'confidential',
            'dgi' => 'agent_choice',
        ], $cmd->perRegistryVisibility);
    }

    public function testFromPostUsesDefaultsForEmptyInput(): void
    {
        $cmd = UpdateAppSettingsCommand::fromPost([]);

        $this->assertSame('', $cmd->appNomOrganisation);
        $this->assertSame('', $cmd->appNomComplet);
        $this->assertSame('', $cmd->appLabelUnite);
        $this->assertSame('', $cmd->appSuperviseurUsernames);
        $this->assertSame('#1e40af', $cmd->appBrandColor);
        $this->assertSame('', $cmd->appHotlineNumber);
        $this->assertSame('', $cmd->appDpoContact);
        $this->assertSame('', $cmd->appReportPreamble);
        $this->assertSame('', $cmd->appRsstDescription);
        $this->assertSame('Signaler un événement', $cmd->appReportCreateLabel);
        $this->assertSame('Rattacher des collègues au signalement', $cmd->appLinkedAgentsLabel);
        $this->assertSame('', $cmd->appBaseUrl);
        $this->assertSame('', $cmd->appAdminEmail);
        $this->assertFalse($cmd->appDisplayErrors);
        $this->assertFalse($cmd->appRegistryRamiEnabled);
        $this->assertFalse($cmd->appRegistryDgiEnabled);
        $this->assertFalse($cmd->appDgiNotifyCsa);
        $this->assertSame('Agent', $cmd->roleLabelAgent);
        $this->assertSame('Superviseur', $cmd->roleLabelSuperviseur);
        $this->assertSame('Membre FS/CSA', $cmd->roleLabelChsct);
        $this->assertSame('agent_choice', $cmd->appReportVisibility);
        $this->assertSame('consent_only', $cmd->chsctScope);
        $this->assertSame([], $cmd->perRegistryVisibility);
    }

    public function testFromPostNormalizesInvalidColor(): void
    {
        $cmd = UpdateAppSettingsCommand::fromPost(['app_brand_color' => 'not-a-color']);
        $this->assertSame('#1e40af', $cmd->appBrandColor);
    }

    public function testFromPostNormalizesInvalidVisibility(): void
    {
        $cmd = UpdateAppSettingsCommand::fromPost(['app_report_visibility' => 'invalid']);
        $this->assertSame('agent_choice', $cmd->appReportVisibility);
    }

    public function testFromPostNormalizesInvalidChsctScope(): void
    {
        $cmd = UpdateAppSettingsCommand::fromPost(['app_chsct_report_scope' => 'invalid']);
        $this->assertSame('consent_only', $cmd->chsctScope);
    }

    public function testFromPostEmptyLabelUsesDefaults(): void
    {
        $cmd = UpdateAppSettingsCommand::fromPost([
            'app_report_create_label' => '',
            'app_linked_agents_label' => '',
        ]);
        $this->assertSame('Signaler un événement', $cmd->appReportCreateLabel);
        $this->assertSame('Rattacher des collègues au signalement', $cmd->appLinkedAgentsLabel);
    }

    public function testFromPostEmptyRoleLabelsUseDefaults(): void
    {
        $cmd = UpdateAppSettingsCommand::fromPost([
            'app_role_label_agent' => '',
            'app_role_label_superviseur' => '',
            'app_role_label_chsct' => '',
        ]);
        $this->assertSame('Agent', $cmd->roleLabelAgent);
        $this->assertSame('Superviseur', $cmd->roleLabelSuperviseur);
        $this->assertSame('Membre FS/CSA', $cmd->roleLabelChsct);
    }

    // ═══ getAdjacentUuids with scalar params ═══

    public function testGetAdjacentUuidsWithScalarParams(): void
    {
        $repo = new ReportRepository($this->pdo);

        // Create a user for FK
        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, is_active) VALUES ('test.phase3', 'Dupont', 'Jean', 'agent', 1)");
        $userId = (int) $this->pdo->lastInsertId();

        // Seed reports
        $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement,
                declarant_id, declarant_nom, declarant_prenom, site_id, etat, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            'uuid-old', 'RSST-old', 'rsst', 'Old', 'Desc', '2026-01-15',
            $userId, 'Dupont', 'Jean', null, 'nouveau', '2026-01-01 10:00:00',
        ]);

        $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement,
                declarant_id, declarant_nom, declarant_prenom, site_id, etat, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            'uuid-new', 'RSST-new', 'rsst', 'New', 'Desc', '2026-02-15',
            $userId, 'Dupont', 'Jean', null, 'nouveau', '2026-02-01 10:00:00',
        ]);

        $result = $repo->getAdjacentUuids('rsst', '2026-01-01 10:00:00', 'uuid-old');
        $this->assertInstanceOf(AdjacentUuids::class, $result);
        $this->assertSame('uuid-new', $result->prev, 'prev = newer report');
        $this->assertNull($result->next, 'oldest has no next');
    }

    public function testGetAdjacentUuidsEmptyScalarsReturnsNulls(): void
    {
        $repo = new ReportRepository($this->pdo);
        $result = $repo->getAdjacentUuids('rsst', '', '');
        $this->assertNull($result->prev);
        $this->assertNull($result->next);
    }
}
