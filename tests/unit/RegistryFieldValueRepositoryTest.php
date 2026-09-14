<?php
/**
 * RegistryFieldValueRepository Tests — Application SST DREETS BFC
 *
 * TDD: tests written BEFORE implementation (persistance des champs
 * dynamiques des registres personnalisés).
 *
 * Modèle de stockage : table registry_field_values (report_uuid,
 * registry_id, field_code, value) avec FK composites — la suppression
 * d'un signalement, d'un champ ou d'un registre cascade les valeurs.
 */

use App\Repository\RegistryFieldValueRepository;
use PHPUnit\Framework\TestCase;

class RegistryFieldValueRepositoryTest extends TestCase
{
    private PDO $pdo;
    private int $registryId;
    private string $reportUuid = 'cfv00000-1111-2222-3333-444444444444';

    protected function setUp(): void
    {
        $this->pdo = getDB();

        // Nettoyage ciblé (jamais les 3 registres par défaut)
        $this->pdo->exec('DELETE FROM registry_field_values');
        $this->pdo->exec("DELETE FROM registry_fields WHERE registry_id IN (SELECT id FROM registries WHERE code LIKE 'cfv_t%')");
        $this->pdo->exec("DELETE FROM registries WHERE code LIKE 'cfv_t%'");
        $this->pdo->exec("DELETE FROM reports WHERE uuid LIKE 'cfv%'");
        $this->pdo->exec("DELETE FROM users WHERE username LIKE 'test.cfv%'");

        // Registre + définitions de champs
        $this->pdo->exec("INSERT INTO registries (code, label, short_label, color_theme, is_enabled, default_visibility)
            VALUES ('cfv_t', 'Registre test valeurs', 'CTV', 'rsst', 1, 'agent_choice')");
        $this->registryId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type, is_required, sort_order) VALUES
            ({$this->registryId}, 'ctv_texte', 'Texte', 'text', 0, 1),
            ({$this->registryId}, 'ctv_select', 'Select', 'select', 0, 2)");

        // Site + utilisateur + signalement (FK reports.declarant_id → users)
        // INSERT OR IGNORE : le getDB() singleton conserve les sites entre tests.
        $this->pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UR21_CTV', 'UR CTV', 1)");
        $siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UR21_CTV'")->fetchColumn();
        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active, email)
            VALUES ('test.cfv.user', 'Test', 'Cfv', 'agent', {$siteId}, 1, 'fixture@dreets-bfc.gouv.fr')");
        $userId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat)
            VALUES ('{$this->reportUuid}', 'CTV-26-001', 'cfv_t', 'Objet', 'Description', '2026-01-15', {$userId}, 'Test', 'Cfv', {$siteId}, 0, 'nouveau')");
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM registry_field_values');
        $this->pdo->exec("DELETE FROM registry_fields WHERE registry_id IN (SELECT id FROM registries WHERE code LIKE 'cfv_t%')");
        $this->pdo->exec("DELETE FROM registries WHERE code LIKE 'cfv_t%'");
        $this->pdo->exec("DELETE FROM reports WHERE uuid LIKE 'cfv%'");
        $this->pdo->exec("DELETE FROM users WHERE username LIKE 'test.cfv%'");
    }

    private function insertValue(string $uuid, string $fieldCode, ?string $value): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO registry_field_values (report_uuid, registry_id, field_code, value) VALUES (?, ?, ?, ?)');
        $stmt->execute([$uuid, $this->registryId, $fieldCode, $value]);
    }

    // ─── READ ────────────────────────────────────────────────────────────────

    public function testFindByReportReturnsFieldValueMap(): void
    {
        $this->insertValue($this->reportUuid, 'ctv_texte', 'Risque électrique');
        $this->insertValue($this->reportUuid, 'ctv_select', 'usager');

        $values = RegistryFieldValueRepository::instance()->findByReport($this->reportUuid);

        // Ordre déterministe : field_code ASC
        $this->assertSame([
            'ctv_select' => 'usager',
            'ctv_texte' => 'Risque électrique',
        ], $values);
    }

    public function testFindByReportReturnsEmptyForUnknownUuid(): void
    {
        $this->insertValue($this->reportUuid, 'ctv_texte', 'x');
        $this->assertSame([], RegistryFieldValueRepository::instance()->findByReport('unknown-uuid'));
    }

    public function testFindByReportSkipsNullStoredValues(): void
    {
        $this->insertValue($this->reportUuid, 'ctv_texte', null);
        $this->insertValue($this->reportUuid, 'ctv_select', 'usager');
        $values = RegistryFieldValueRepository::instance()->findByReport($this->reportUuid);
        $this->assertSame(['ctv_select' => 'usager'], $values);
    }

    // ─── CASCADE (FK composites) ─────────────────────────────────────────────

    public function testDeleteReportCascadesValues(): void
    {
        $this->insertValue($this->reportUuid, 'ctv_texte', 'valeur');
        $this->pdo->exec("DELETE FROM reports WHERE uuid = '{$this->reportUuid}'");
        $this->assertSame([], RegistryFieldValueRepository::instance()->findByReport($this->reportUuid));
    }

    public function testDeleteRegistryFieldCascadesValues(): void
    {
        $this->insertValue($this->reportUuid, 'ctv_texte', 'valeur');
        $this->insertValue($this->reportUuid, 'ctv_select', 'usager');
        $this->pdo->exec("DELETE FROM registry_fields WHERE registry_id = {$this->registryId} AND field_code = 'ctv_texte'");
        $values = RegistryFieldValueRepository::instance()->findByReport($this->reportUuid);
        $this->assertSame(['ctv_select' => 'usager'], $values);
    }

    public function testDeleteRegistryCascadesValues(): void
    {
        $this->insertValue($this->reportUuid, 'ctv_texte', 'valeur');
        $this->pdo->exec("DELETE FROM registries WHERE id = {$this->registryId}");
        $this->assertSame([], RegistryFieldValueRepository::instance()->findByReport($this->reportUuid));
    }

    // ─── CONTRAINTE D'UNICITÉ ────────────────────────────────────────────────

    public function testUniqueConstraintOnReportAndFieldCode(): void
    {
        $this->insertValue($this->reportUuid, 'ctv_texte', 'premiere');
        try {
            $this->insertValue($this->reportUuid, 'ctv_texte', 'doublon');
            $this->fail('UNIQUE(report_uuid, field_code) attendu — le doublon aurait dû être rejeté.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('UNIQUE', $e->getMessage());
        }
    }
}
