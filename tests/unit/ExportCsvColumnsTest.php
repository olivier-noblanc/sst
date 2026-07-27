<?php
/**
 * Export CSV Columns Test — Application SST DREETS BFC
 *
 * Audit #7 — l'export CSV DREETS avait 5 colonnes vides (pole,
 * service_affectation, telephone_mobile, site_text) + colonne
 * "Transmission FS/CSA" toujours "Refusée" (consent_syndicat absent
 * de la SELECT).
 *
 * Ce test vérifie que getExportData() retourne bien toutes les colonnes
 * métier attendues par export_handler.php, et que les valeurs réelles
 * sont présentes (pas juste des NULL par défaut).
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ExportCsvColumnsTest extends TestCase
{
    private static bool $bootstrapped = false;
    private static string $testUuid = 'cccccccc-dddd-eeee-ffff-111111111111';

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
        $pdo->exec("INSERT OR IGNORE INTO sites (id, code, nom, is_active) VALUES (1, 'UR21', 'UR Test', 1)");
        $pdo->exec("INSERT OR IGNORE INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (1, 'test.agent', 'Dupont', 'Jean', 'agent', 1, 1)");
    }

    protected function setUp(): void
    {
        $pdo = getDB();
        $pdo->exec("DELETE FROM reports WHERE uuid = '" . self::$testUuid . "'");
    }

    public function testGetExportDataContainsAllExpectedColumns(): void
    {
        $pdo = getDB();
        $pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, site_text, pole, service_affectation, telephone_mobile, consent_syndicat, is_confidential, etat) VALUES ('" . self::$testUuid . "', 'RSST-25-001', 'rsst', 'Objet test', 'Desc', '2025-01-01', 1, 'Dupont', 'Jean', 1, 'Site text val', 'Pole S', 'Service X', '0601020304', 1, 0, 'nouveau')");

        $repo = new \App\Repository\StatsRepository($pdo);
        $rows = $repo->getExportData([]);

        $this->assertNotEmpty($rows, 'getExportData should return rows');

        $row = null;
        foreach ($rows as $r) {
            if ($r['uuid'] === self::$testUuid) {
                $row = $r;
                break;
            }
        }
        $this->assertNotNull($row, 'Test report should be present in export data');

        // Audit #7 — les 5 colonnes doivent être présentes avec valeurs réelles
        $this->assertArrayHasKey('pole', $row, 'Column "pole" must be in SELECT');
        $this->assertArrayHasKey('service_affectation', $row, 'Column "service_affectation" must be in SELECT');
        $this->assertArrayHasKey('telephone_mobile', $row, 'Column "telephone_mobile" must be in SELECT');
        $this->assertArrayHasKey('site_text', $row, 'Column "site_text" must be in SELECT');
        $this->assertArrayHasKey('consent_syndicat', $row, 'Column "consent_syndicat" must be in SELECT');

        // Les valeurs doivent être celles de la DB, pas des NULL par défaut
        $this->assertSame('Pole S', $row['pole'], 'pole value should match DB');
        $this->assertSame('Service X', $row['service_affectation'], 'service_affectation value should match DB');
        $this->assertSame('0601020304', $row['telephone_mobile'], 'telephone_mobile value should match DB');
        $this->assertSame('Site text val', $row['site_text'], 'site_text value should match DB');
        $this->assertEquals(1, (int) $row['consent_syndicat'], 'consent_syndicat value should match DB (1 = accepted)');
    }

    public function testConsentSyndicatValueIsAccurateWhenAccepted(): void
    {
        // Audit #7 — bug "consent_syndicat toujours Refusée"
        // Avant le fix : la colonne n'était pas dans la SELECT → $row['consent_syndicat'] undefined
        // → !empty(undefined) === true → "Acceptée"...
        // Wait, that's actually true (because !empty(null) is true in PHP 8 due to !empty being negation of empty).
        // But on undefined, PHP raises warning and treats as null → !empty(null) === !false === true.
        // Actually for the consent case the bug is: undefined treated as falsy → "Refusée".
        // Let me re-check the handler: !empty($row['consent_syndicat']) ? 'Acceptée' : 'Refusée'
        // undefined → empty → !empty=false → 'Refusée'
        // So when consent_syndicat=1 was set but column missing from SELECT,
        // the handler always displayed 'Refusée'.

        $pdo = getDB();
        $pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, consent_syndicat, is_confidential, etat) VALUES ('" . self::$testUuid . "', 'RSST-25-002', 'rsst', 'Objet', 'Desc', '2025-01-01', 1, 'Dupont', 'Jean', 1, 1, 0, 'nouveau')");

        $repo = new \App\Repository\StatsRepository($pdo);
        $rows = $repo->getExportData([]);

        $row = null;
        foreach ($rows as $r) {
            if ($r['uuid'] === self::$testUuid) {
                $row = $r;
                break;
            }
        }
        $this->assertNotNull($row);

        // Reproduit la logique de export_handler.php:183
        $consentLabel = !empty($row['consent_syndicat']) ? 'Acceptée' : 'Refusée';
        $this->assertSame('Acceptée', $consentLabel, 'consent_syndicat=1 should display as Acceptée');
    }

    public function testConsentSyndicatValueIsAccurateWhenRefused(): void
    {
        $pdo = getDB();
        $pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, consent_syndicat, is_confidential, etat) VALUES ('" . self::$testUuid . "', 'RSST-25-003', 'rsst', 'Objet', 'Desc', '2025-01-01', 1, 'Dupont', 'Jean', 1, 0, 0, 'nouveau')");

        $repo = new \App\Repository\StatsRepository($pdo);
        $rows = $repo->getExportData([]);

        $row = null;
        foreach ($rows as $r) {
            if ($r['uuid'] === self::$testUuid) {
                $row = $r;
                break;
            }
        }
        $this->assertNotNull($row);

        $consentLabel = !empty($row['consent_syndicat']) ? 'Acceptée' : 'Refusée';
        $this->assertSame('Refusée', $consentLabel, 'consent_syndicat=0 should display as Refusée');
    }
}
