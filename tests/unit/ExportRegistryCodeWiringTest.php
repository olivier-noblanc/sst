<?php
/**
 * Export Registry Code Wiring Test — Application SST DREETS BFC
 *
 * Fiabilisation (council / audit A2) — l'export CSV des registres custom
 * était mort en production à trois niveaux :
 * 1. handlers/export_handler.php lisait $_POST['registry'], champ qui
 *    n'existait dans AUCUN formulaire (pages/export.php poste `type` +
 *    `all_registries`) → registryCode toujours null
 * 2. buildHeaders() n'ajoutait jamais les colonnes dynamiques
 * 3. buildCsvRow() n'émettait jamais les valeurs des champs custom
 *
 * Ce test exerce la chaîne complète POST → filtres → registryCode →
 * getExportData → headers → ligne CSV, avec le vrai payload du formulaire.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ExportRegistryCodeWiringTest extends TestCase
{
    private const REGISTRY_CODE = 'expowire';
    private const REGISTRY_ID = 97;
    private const TEST_UUID = 'eeeeeeee-aaaa-bbbb-cccc-111111111111';

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../src/config.php';
        require_once __DIR__ . '/../../src/helpers.php';
        require_once __DIR__ . '/../../src/Repository/StatsRepository.php';

        $pdo = getDB();

        $pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UR_EXPW', 'UR Export Wiring', 1)");
        $siteRow = $pdo->query("SELECT id FROM sites WHERE code = 'UR_EXPW'")->fetch(PDO::FETCH_COLUMN);
        $siteId = $siteRow !== false ? (int) $siteRow : 0;

        if ($siteId > 0) {
            $pdo->exec("INSERT OR IGNORE INTO users (id, username, nom, prenom, role, site_id, is_active, email) VALUES (9971, 'test.expowire', 'Export', 'Wiring', 'agent', " . $siteId . ", 1, 'fixture@dreets-bfc.gouv.fr')");
        }

        // Registre custom de test + 2 champs : 'pole' (déjà émis par les
        // colonnes standard → doit rester exclu) et 'attachment_name'
        // (colonne réelle non émise → doit apparaître en colonne dynamique).
        // is_enabled = 0 : le registre ne doit apparaître nulle part dans
        // l'UI (cartes home, formulaires) — il sert uniquement à ces tests.
        $pdo->exec("DELETE FROM registry_fields WHERE registry_id = " . self::REGISTRY_ID);
        $pdo->exec("DELETE FROM registries WHERE code = '" . self::REGISTRY_CODE . "'");
        $pdo->exec("INSERT INTO registries (id, code, label, short_label, description, icon, color_theme, is_enabled, default_visibility) VALUES (" . self::REGISTRY_ID . ", '" . self::REGISTRY_CODE . "', 'Export Wiring', 'EXPW', 'Registre de test export (désactivé)', 'icon', 'rsst', 0, 'agent_choice')");
        $pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type) VALUES (" . self::REGISTRY_ID . ", 'pole', 'Pole custom', 'text')");
        $pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type) VALUES (" . self::REGISTRY_ID . ", 'attachment_name', 'Piece jointe personnalisee', 'text')");
        // Champ NON physique (aucune colonne reports correspondante — cas réel :
        // métadonnée / case à cocher de formulaire) — oracle R1 : ne doit JAMAIS
        // être annoncé en colonne CSV, aucune donnée ne peut exister derrière.
        $pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type) VALUES (" . self::REGISTRY_ID . ", 'metadata_externe', 'Metadonnee externe', 'text')");
    }

    public static function tearDownAfterClass(): void
    {
        // Nettoyage complet — la suite partage une seule base : la moindre
        // ligne résiduelle fuirait dans PageRenderingTest / ReportTypeTest.
        $pdo = getDB();
        $pdo->exec("DELETE FROM registry_fields WHERE registry_id = " . self::REGISTRY_ID);
        $pdo->exec("DELETE FROM registries WHERE code = '" . self::REGISTRY_CODE . "'");
        $pdo->exec("DELETE FROM reports WHERE uuid = '" . self::TEST_UUID . "'");
        $pdo->exec("DELETE FROM users WHERE username = 'test.expowire'");
        $pdo->exec("DELETE FROM sites WHERE code = 'UR_EXPW'");
    }

    protected function setUp(): void
    {
        getDB()->exec("DELETE FROM reports WHERE uuid = '" . self::TEST_UUID . "'");
    }

    /** Payload fidèle au formulaire pages/export.php (case unique cochée). */
    private function realFormPost(): array
    {
        return [
            'csrf_token' => 'irrelevant',
            'type' => self::REGISTRY_CODE,
            'all_registries' => '',
            'all_sites' => '1',
            'all_agents' => '1',
            'date_from' => '',
            'date_to' => '',
            'etats' => ['nouveau'],
        ];
    }

    private function seedReport(): void
    {
        $pdo = getDB();
        $siteRow = $pdo->query("SELECT id FROM sites WHERE code = 'UR_EXPW'")->fetch(PDO::FETCH_COLUMN);
        $siteId = $siteRow !== false ? (int) $siteRow : 0;
        $pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, attachment_name, is_confidential, etat)
            VALUES ('" . self::TEST_UUID . "', 'EXPW-26-001', '" . self::REGISTRY_CODE . "', 'Objet wiring', 'Desc', '2026-01-15', 9971, 'Export', 'Wiring', " . $siteId . ", 'rapport_test.jpg', 0, 'nouveau')");
    }

    // ─── 1. Transport POST → registryCode ────────────────────────────────

    public function testResolveRegistryCodeFromRealFormPayload(): void
    {
        $service = new \App\Services\ExportService(new \App\Services\ConfigService());
        $this->assertSame(
            self::REGISTRY_CODE,
            $service->resolveRegistryCodeFromPost($this->realFormPost()),
            'Un POST réel du formulaire export (registre unique) doit résoudre le registryCode depuis type'
        );
    }

    public function testResolveRegistryCodeIsNullWhenAllRegistriesChecked(): void
    {
        $service = new \App\Services\ExportService(new \App\Services\ConfigService());
        $post = $this->realFormPost();
        $post['all_registries'] = '1';
        $this->assertNull($service->resolveRegistryCodeFromPost($post), 'all_registries=1 → pas de colonnes dynamiques');
    }

    public function testResolveRegistryCodeLegacyFieldStillWins(): void
    {
        $service = new \App\Services\ExportService(new \App\Services\ConfigService());
        $post = $this->realFormPost();
        $post['registry'] = 'ancien_champ';
        $this->assertSame('ancien_champ', $service->resolveRegistryCodeFromPost($post), 'Compatibilité ascendante du champ legacy registry');
    }

    public function testResolveRegistryCodeEmptyPostIsNull(): void
    {
        $service = new \App\Services\ExportService(new \App\Services\ConfigService());
        $this->assertNull($service->resolveRegistryCodeFromPost([]));
    }

    // ─── 2. Chaîne complète : headers + ligne CSV avec champs custom ─────

    public function testFullExportPipelineIncludesCustomFields(): void
    {
        $this->seedReport();
        $service = new \App\Services\ExportService(new \App\Services\ConfigService());

        $post = $this->realFormPost();
        $filters = $service->buildFiltersFromPost($post);
        $registryCode = $service->resolveRegistryCodeFromPost($post);
        $this->assertNotNull($registryCode);

        $repo = new \App\Repository\StatsRepository(getDB());
        $rows = $repo->getExportData($filters, $registryCode);

        $row = null;
        foreach ($rows as $r) {
            if ($r['uuid'] === self::TEST_UUID) {
                $row = $r;
                break;
            }
        }
        $this->assertNotNull($row, 'Le signalement de test doit être exporté');

        $headers = $service->buildHeaders(false, $registryCode);
        $csvRow = $service->buildCsvRow($row, [], false, $registryCode);

        $this->assertCount(count($headers), $csvRow, 'En-têtes et valeurs doivent rester alignés');

        // Le champ custom non standard doit apparaître en en-tête ET en valeur
        $idx = array_search('Piece jointe personnalisee', $headers, true);
        $this->assertNotFalse($idx, 'La colonne dynamique du registre custom doit figurer dans les en-têtes CSV');
        $this->assertSame('rapport_test.jpg', $csvRow[$idx], 'La valeur du champ custom doit figurer dans la ligne CSV');

        // Le champ 'pole' est déjà couvert par les colonnes standard → pas de doublon
        $this->assertFalse(
            array_search('Pole custom', $headers, true),
            'Un champ déjà émis par les colonnes standard ne doit pas être dupliqué'
        );
    }

    // ─── 3. Filtre colonnes physiques (oracle R1) + cache (oracle R3) ────

    public function testNonPhysicalFieldIsNeverAnnounced(): void
    {
        $this->seedReport();
        $service = new \App\Services\ExportService(new \App\Services\ConfigService());

        $headers = $service->buildHeaders(false, self::REGISTRY_CODE);
        $this->assertNotContains(
            'Metadonnee externe',
            $headers,
            'Un registry_field sans colonne physique reports ne doit jamais être annoncé en en-tête CSV (aucune donnée possible derrière)'
        );
        $this->assertContains('Piece jointe personnalisee', $headers, 'Le vrai champ physique reste annoncé');

        $repo = new \App\Repository\StatsRepository(getDB());
        $rows = $repo->getExportData(['type' => self::REGISTRY_CODE], self::REGISTRY_CODE);
        $row = null;
        foreach ($rows as $r) {
            if ($r['uuid'] === self::TEST_UUID) {
                $row = $r;
                break;
            }
        }
        $this->assertNotNull($row);

        $csvRow = $service->buildCsvRow($row, [], false, self::REGISTRY_CODE);
        $this->assertCount(count($headers), $csvRow, 'En-têtes et valeurs restent alignés');
    }

    public function testDynamicFieldsAreStableAcrossCalls(): void
    {
        $service = new \App\Services\ExportService(new \App\Services\ConfigService());

        $first = $service->getDynamicExportFields(self::REGISTRY_CODE);
        $second = $service->getDynamicExportFields(self::REGISTRY_CODE);
        $this->assertSame($first, $second, 'Le calcul des champs dynamiques doit être déterministe (base du cache par registre)');
        $this->assertNotEmpty($first);

        $codes = array_column($first, 'code');
        $this->assertContains('attachment_name', $codes, 'Champ physique non émis → colonne dynamique');
        $this->assertNotContains('metadata_externe', $codes, 'Champ non physique → exclu');
        $this->assertNotContains('pole', $codes, 'Champ déjà émis en standard → exclu (pas de doublon)');
    }

    public function testHeadersWithoutRegistryCodeAreUnchanged(): void
    {
        $service = new \App\Services\ExportService(new \App\Services\ConfigService());
        $baseline = $service->buildHeaders(false);
        $this->assertSame($baseline, $service->buildHeaders(false, null), 'registryCode null → en-têtes inchangés');

        // Avec un registre custom : la baseline est conservée en préfixe, les
        // colonnes dynamiques s'ajoutent en fin d'en-têtes.
        $withRegistry = $service->buildHeaders(false, self::REGISTRY_CODE);
        $this->assertCount(count($baseline) + 1, $withRegistry, 'Une colonne dynamique (champ non émis) doit s\'ajouter');
        $this->assertSame($baseline, array_slice($withRegistry, 0, count($baseline)), 'Les en-têtes standard ne doivent pas être altérés');

        $noSite = $service->buildHeaders(true);
        $this->assertSame($noSite, $service->buildHeaders(true, null));
    }
}
