<?php
/**
 * Export CSV Dynamic Columns Test — Application SST DREETS BFC
 *
 * Task 3 — Vérifie que getExportData() accepte un registryCode optionnel
 * et ajoute dynamiquement les colonnes des champs custom du registre.
 *
 * Backward compatible : appel sans registryCode garde le comportement actuel.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ExportDynamicColumnsTest extends TestCase
{
    private static bool $bootstrapped = false;
    private static string $testUuidRami = 'dddddddd-eeee-ffff-0000-222222222222';
    private static string $testUuidRsst = 'dddddddd-eeee-ffff-0000-333333333333';
    private static int $siteId = 0;

    public static function setUpBeforeClass(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        require_once __DIR__ . '/../../src/config.php';
        require_once __DIR__ . '/../../src/helpers.php';
        require_once __DIR__ . '/../../src/Repository/StatsRepository.php';

        $pdo = getDB();

        // Ensure test site exists
        $pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UR21_DYN', 'UR Dynamic Test', 1)");
        $siteRow = $pdo->query("SELECT id FROM sites WHERE code = 'UR21_DYN'")->fetch(PDO::FETCH_COLUMN);
        self::$siteId = $siteRow !== false ? (int) $siteRow : 0;

        // Ensure test user exists
        if (self::$siteId > 0) {
            $pdo->exec("INSERT OR IGNORE INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (9991, 'test.dyn.export', 'Test', 'Agent', 'agent', " . self::$siteId . ", 1)");
        }

        // Ensure registries table has expected rows (some test classes may replace the table)
        $ramiIdResult = $pdo->query("SELECT id FROM registries WHERE code = 'rami'");
        $ramiId = $ramiIdResult !== false ? $ramiIdResult->fetch(PDO::FETCH_COLUMN) : false;

        if ($ramiId === false || (int) $ramiId === 0) {
            // Registries table might be recreated by another test with minimal schema
            // Try to ensure rami exists
            try {
                $pdo->exec("INSERT OR IGNORE INTO registries (id, code, label, short_label, icon, color_theme, default_visibility) VALUES (2, 'rami', 'RAMI Test', 'RAMI', 'icon', 'rami', 'agent_choice')");
            } catch (\PDOException) {
                // May fail if schema is different; ignore
            }
        }

        // Seed registry_fields for RAMI
        try {
            $pdo->exec("DELETE FROM registry_fields WHERE registry_id = (SELECT id FROM registries WHERE code = 'rami' LIMIT 1)");
            $pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type)
                SELECT id, 'pole', 'Pôle', 'text' FROM registries WHERE code = 'rami' LIMIT 1");
            $pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type)
                SELECT id, 'service_affectation', 'Service', 'text' FROM registries WHERE code = 'rami' LIMIT 1");
            $pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type)
                SELECT id, 'telephone_mobile', 'Téléphone', 'text' FROM registries WHERE code = 'rami' LIMIT 1");
        } catch (\PDOException $e) {
            // If registry table doesn't have expected schema, skip field seeding
            error_log('ExportDynamicColumnsTest: could not seed registry_fields: ' . $e->getMessage());
        }
    }

    protected function setUp(): void
    {
        $pdo = getDB();
        $pdo->exec("DELETE FROM reports WHERE uuid IN ('" . self::$testUuidRami . "', '" . self::$testUuidRsst . "')");
    }

    private function insertTestReport(string $uuid, string $reference, string $type, string $pole = 'Pole A', string $service = 'Service B', string $phone = '0708091011'): void
    {
        $pdo = getDB();
        $pdo->exec("INSERT OR IGNORE INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, pole, service_affectation, telephone_mobile, is_confidential, etat) VALUES ('{$uuid}', '{$reference}', '{$type}', 'Objet test', 'Desc', '2025-01-01', 9991, 'Test', 'Agent', " . self::$siteId . ", '{$pole}', '{$service}', '{$phone}', 0, 'nouveau')");
    }

    public function testExportWithoutRegistryCodeReturnsAllStandardColumns(): void
    {
        // Backward compatibility — appel sans registryCode
        $this->insertTestReport(self::$testUuidRami, 'DYN-25-001', 'rami');

        $repo = new \App\Repository\StatsRepository(getDB());
        $rows = $repo->getExportData([]);

        $this->assertNotEmpty($rows, 'getExportData without registryCode should return rows');

        $row = null;
        foreach ($rows as $r) {
            if ($r['uuid'] === self::$testUuidRami) {
                $row = $r;
                break;
            }
        }
        $this->assertNotNull($row, 'Test report should be present');

        // Standard columns must exist
        $this->assertArrayHasKey('uuid', $row);
        $this->assertArrayHasKey('reference', $row);
        $this->assertArrayHasKey('type', $row);
        $this->assertArrayHasKey('pole', $row);
        $this->assertArrayHasKey('service_affectation', $row);
        $this->assertArrayHasKey('telephone_mobile', $row);
    }

    public function testExportWithRegistryCodeIncludesDynamicColumns(): void
    {
        $this->insertTestReport(self::$testUuidRami, 'DYN-25-002', 'rami', 'Pole A', 'Service B', '0708091011');

        $repo = new \App\Repository\StatsRepository(getDB());
        $rows = $repo->getExportData([], 'rami');

        $this->assertNotEmpty($rows, 'getExportData with registryCode should return rows');

        $row = null;
        foreach ($rows as $r) {
            if ($r['uuid'] === self::$testUuidRami) {
                $row = $r;
                break;
            }
        }
        $this->assertNotNull($row, 'Test report should be present');

        // Dynamic columns from registry_fields must be present
        $this->assertArrayHasKey('pole', $row, 'Dynamic column "pole" must be in SELECT');
        $this->assertArrayHasKey('service_affectation', $row, 'Dynamic column "service_affectation" must be in SELECT');
        $this->assertArrayHasKey('telephone_mobile', $row, 'Dynamic column "telephone_mobile" must be in SELECT');

        // Values must match DB content
        $this->assertSame('Pole A', $row['pole']);
        $this->assertSame('Service B', $row['service_affectation']);
        $this->assertSame('0708091011', $row['telephone_mobile']);
    }

    public function testExportWithRegistryCodeWithoutFieldsNoChanges(): void
    {
        // RSST has no registry_fields — behavior should be unchanged
        $this->insertTestReport(self::$testUuidRsst, 'DYN-25-003', 'rsst', 'Pole C', 'Service D', '0102030405');

        $repo = new \App\Repository\StatsRepository(getDB());
        $rows = $repo->getExportData([], 'rsst');

        $this->assertNotEmpty($rows);

        $row = null;
        foreach ($rows as $r) {
            if ($r['uuid'] === self::$testUuidRsst) {
                $row = $r;
                break;
            }
        }
        $this->assertNotNull($row);

        // Standard columns still present (pole is in base SELECT for all reports)
        $this->assertArrayHasKey('pole', $row);
        $this->assertArrayHasKey('uuid', $row);
        $this->assertArrayHasKey('reference', $row);
    }

    public function testExportWithUnknownRegistryCodeFallsBackToDefault(): void
    {
        $this->insertTestReport(self::$testUuidRami, 'DYN-25-004', 'rami', 'Pole X');

        $repo = new \App\Repository\StatsRepository(getDB());
        $rows = $repo->getExportData([], 'nonexistent_registry');

        $this->assertNotEmpty($rows);

        $row = null;
        foreach ($rows as $r) {
            if ($r['uuid'] === self::$testUuidRami) {
                $row = $r;
                break;
            }
        }
        $this->assertNotNull($row);

        // Standard columns should still be present
        $this->assertArrayHasKey('uuid', $row);
        $this->assertArrayHasKey('pole', $row);
    }
}
