<?php
/**
 * Export avec les registry_fields RÉELS de production — Application SST DREETS BFC
 *
 * Non-régression : les registry_fields réels du registre RAMI (seed prod, cf.
 * src/database.php seedDefaultData()) contiennent :
 *   - 'pour_compte' (checkbox du formulaire — PAS une colonne physique de reports)
 *   - 'pour_compte_nom', 'pour_compte_prenom', 'nature_auteur', 'type_acte'
 *     (colonnes réelles, déjà présentes dans le SELECT de base de getExportData()).
 *
 * Avant le fix, getExportData($filters, 'rami') ajoutait dynamiquement
 * "r.pour_compte" au SELECT → PDOException "no such column: r.pour_compte"
 * → crash de l'export filtré sur RAMI (scénario utilisateur réel).
 *
 * Le test existant ExportDynamicColumnsTest masque ce bug : il efface les
 * registry_fields réels et les remplace par pole/service_affectation/
 * telephone_mobile (tous des colonnes existantes). Ce test utilise le seed réel.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ExportRealRegistryFieldsTest extends TestCase
{
    private static bool $bootstrapped = false;
    private static string $testUuid = 'dddddddd-eeee-ffff-0000-555555555555';
    private static int $siteId = 0;

    /**
     * Field codes RÉELS du seed production RAMI (src/database.php lignes 151-160,
     * seed/_registries.php). L'ordre du seed prod est respecté.
     */
    private const array REAL_RAMI_FIELD_CODES = [
        'pour_compte',
        'pour_compte_nom',
        'pour_compte_prenom',
        'nature_auteur',
        'type_acte',
    ];

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

        $pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UR21_REAL', 'UR Real Fields Test', 1)");
        $siteRow = $pdo->query("SELECT id FROM sites WHERE code = 'UR21_REAL'")->fetch(PDO::FETCH_COLUMN);
        self::$siteId = $siteRow !== false ? (int) $siteRow : 0;

        if (self::$siteId > 0) {
            $pdo->exec("INSERT OR IGNORE INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (9995, 'test.real.export', 'Test', 'Real', 'agent', " . self::$siteId . ", 1)");
        }

        // Seed des registry_fields RÉELS de prod sur RAMI (remplace le jeu du
        // test ExportDynamicColumnsTest s'il a tourné avant)
        try {
            $pdo->exec("DELETE FROM registry_fields WHERE registry_id = (SELECT id FROM registries WHERE code = 'rami' LIMIT 1)");
            foreach (self::REAL_RAMI_FIELD_CODES as $code) {
                $pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type)
                    SELECT id, '" . $code . "', '" . $code . "', 'text' FROM registries WHERE code = 'rami' LIMIT 1");
            }
        } catch (\PDOException $e) {
            error_log('ExportRealRegistryFieldsTest: could not seed real registry_fields: ' . $e->getMessage());
        }
    }

    protected function setUp(): void
    {
        $pdo = getDB();
        $pdo->exec("DELETE FROM reports WHERE uuid = '" . self::$testUuid . "'");
    }

    protected function tearDown(): void
    {
        $pdo = getDB();
        $pdo->exec("DELETE FROM reports WHERE uuid = '" . self::$testUuid . "'");
    }

    public function testExportWithRealRamiRegistryFieldsDoesNotCrash(): void
    {
        $pdo = getDB();
        $pdo->exec("INSERT OR IGNORE INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, pour_compte_nom, pour_compte_prenom, nature_auteur, type_acte, is_confidential, etat) VALUES ('" . self::$testUuid . "', 'REAL-25-001', 'rami', 'Objet real', 'Desc', '2025-02-02', 9995, 'Test', 'Real', " . self::$siteId . ", 'Lambert', 'Françoise', 'usager', 'verbal', 0, 'nouveau')");

        $repo = new \App\Repository\StatsRepository($pdo);

        // Avant le fix : PDOException "no such column: r.pour_compte"
        $rows = $repo->getExportData([], 'rami');

        $this->assertNotEmpty($rows, 'getExportData avec registry_fields réels doit retourner des lignes');

        $row = null;
        foreach ($rows as $r) {
            if ($r['uuid'] === self::$testUuid) {
                $row = $r;
                break;
            }
        }
        $this->assertNotNull($row, 'Le signalement de test doit être présent');

        // Colonnes de base (consommées par ExportService::buildCsvRow) intactes
        $this->assertSame('REAL-25-001', $row['reference']);
        $this->assertSame('rami', $row['type']);
        $this->assertSame('usager', $row['nature_auteur']);
        $this->assertSame('verbal', $row['type_acte']);
        $this->assertSame('Lambert', $row['pour_compte_nom']);
        $this->assertSame('Françoise', $row['pour_compte_prenom']);
    }

    public function testExportWithRealRegistryFieldsAndEtatFilterDoesNotCrash(): void
    {
        $pdo = getDB();
        $pdo->exec("INSERT OR IGNORE INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat) VALUES ('" . self::$testUuid . "', 'REAL-25-002', 'rami', 'Objet real 2', 'Desc', '2025-02-03', 9995, 'Test', 'Real', " . self::$siteId . ", 0, 'nouveau')");

        $repo = new \App\Repository\StatsRepository($pdo);

        // Combinaison réelle du handler : filtre etats (list<string>) + registryCode
        $rows = $repo->getExportData(['etats' => ['nouveau']], 'rami');

        $this->assertNotEmpty($rows);
        $uuids = array_column($rows, 'uuid');
        $this->assertContains(self::$testUuid, $uuids);
    }

    public function testExportFiltersOutOtherEtats(): void
    {
        $pdo = getDB();
        $pdo->exec("INSERT OR IGNORE INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat) VALUES ('" . self::$testUuid . "', 'REAL-25-003', 'rami', 'Objet real 3', 'Desc', '2025-02-04', 9995, 'Test', 'Real', " . self::$siteId . ", 0, 'traite')");

        $repo = new \App\Repository\StatsRepository($pdo);

        // Filtre etats sur un autre état → le report 'traite' doit être exclu
        $rows = $repo->getExportData(['etats' => ['nouveau']], 'rami');

        $uuids = array_column($rows, 'uuid');
        $this->assertNotContains(self::$testUuid, $uuids);
    }
}
