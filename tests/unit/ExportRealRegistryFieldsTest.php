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
 *
 * Anti-dérive N3 : la liste des champs n'est plus codée en dur (elle
 * dupliquait le seed prod). Le fixture est DÉRIVÉ au runtime en exécutant le
 * vrai seed de production (seedDefaultData(), src/database.php) sur une base
 * SQLite vierge dans un subprocess PHP isolé (SST_DB_PATH + rebind du PDO du
 * conteneur vers la base probe → aucun effet de bord sur data/).
 */

use App\Enum\ReportType;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ExportRealRegistryFieldsTest extends TestCase
{
    private static bool $bootstrapped = false;
    private static string $testUuid = 'dddddddd-eeee-ffff-0000-555555555555';
    private static int $siteId = 0;

    /**
     * Lignes registry_fields RAMI dérivées du seed prod réel (subprocess).
     * Format : list<array{field_code: string, label: string, field_type: string,
     * options: ?string, is_required: int, sort_order: int}>
     */
    private static array $realRamiFields = [];

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
            $pdo->exec("INSERT OR IGNORE INTO users (id, username, nom, prenom, role, site_id, is_active, email) VALUES (9995, 'test.real.export', 'Test', 'Real', 'agent', " . self::$siteId . ", 1, 'fixture@dreets-bfc.gouv.fr')");
        }

        // Fixture = jeu RÉEL dérivé du seed prod. Aucun try/catch : un échec de
        // dérivation ou de seed doit faire échouer les tests (crash hard), pas
        // passer silencieusement avec un fixture vide ou incomplet.
        self::$realRamiFields = self::deriveRealRamiRegistryFields(dirname(__DIR__, 2));
        self::seedRamiRegistryFields($pdo, self::$realRamiFields);
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

    /**
     * Contrat de non-régression (anti-dérive N3) : le fixture dérivé du seed
     * prod doit toujours contenir 'pour_compte' — le field_code qui n'est PAS
     * une colonne physique de reports et qui a causé le crash "no such column:
     * r.pour_compte". Si le seed prod cesse de le produire, ce test échoue et
     * force une revue humaine : sans lui, les 3 tests d'export perdraient leur
     * pouvoir de non-régression sans que rien ne le signale.
     */
    public function testProductionSeedContractForRamiRegistryFields(): void
    {
        $this->assertNotEmpty(self::$realRamiFields, 'Le seed prod doit produire des registry_fields RAMI');
        $codes = array_column(self::$realRamiFields, 'field_code');
        $this->assertContains(
            'pour_compte',
            $codes,
            'Le seed prod RAMI doit toujours contenir pour_compte (contrat de non-régression export)'
        );
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

    /**
     * Dérive les registry_fields RAMI en exécutant le VRAI seed prod
     * (seedDefaultData(), src/database.php) sur une base SQLite vierge dans un
     * subprocess PHP isolé. La source de vérité est exécutée, pas copiée :
     * toute évolution du seed prod est suivie automatiquement par le fixture.
     *
     * Le subprocess rebind le PDO du conteneur vers la base probe avant toute
     * résolution → RegistryRepository/RegistryFieldRepository écrivent sur la
     * base probe, aucun effet de bord sur data/ (ni getDB(), ni backup).
     *
     * @return list<array{field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int}>
     */
    private static function deriveRealRamiRegistryFields(string $repoRoot): array
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sst-probe-' . uniqid('', true);
        if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
            throw new RuntimeException('Impossible de créer le dossier temporaire du probe seed : ' . $tempDir);
        }
        $probeDb = $tempDir . DIRECTORY_SEPARATOR . 'probe.db';
        $script = $tempDir . DIRECTORY_SEPARATOR . 'probe_seed.php';
        if (file_put_contents($script, self::probeScript()) === false) {
            throw new RuntimeException("Impossible d'écrire le script probe seed : " . $script);
        }

        try {
            $cmd = escapeshellarg(PHP_BINARY)
                . ' ' . escapeshellarg($script)
                . ' ' . escapeshellarg($probeDb)
                . ' ' . escapeshellarg($repoRoot);
            $pipes = [];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($proc)) {
                throw new RuntimeException('Impossible de lancer le subprocess PHP du probe seed.');
            }
            $stdout = (string) stream_get_contents($pipes[1]);
            $stderr = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($proc);

            if ($exitCode !== 0) {
                throw new RuntimeException('Le probe seed prod a échoué (exit ' . $exitCode . ') : ' . $stderr);
            }

            $payload = self::extractProbePayload($stdout);
            $decoded = $payload !== null ? json_decode($payload, true) : null;
            if (!is_array($decoded) || !is_array($decoded['fields'] ?? null) || $decoded['fields'] === []) {
                throw new RuntimeException("Le probe seed prod n'a retourné aucun registry_fields RAMI — fixture invalide.");
            }

            /** @var list<array{field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int}> $fields */
            $fields = $decoded['fields'];

            return $fields;
        } finally {
            @unlink($script);
            @unlink($probeDb);
            @rmdir($tempDir);
        }
    }

    /**
     * Script du subprocess : exécute le seed prod verbatim sur la base probe
     * et imprime les registry_fields RAMI entre deux sentinelles (les
     * sentinelles isolent le JSON de tout bruit potentiel sur STDOUT).
     */
    private static function probeScript(): string
    {
        return <<<'PHP'
<?php
/**
 * Probe seed prod — généré par ExportRealRegistryFieldsTest (temporaire).
 * Exécute le VRAI seed de production (seedDefaultData) sur une base SQLite
 * vierge puis imprime les registry_fields RAMI. Écritures confinées au
 * dossier temporaire passé en argument.
 */
ini_set('display_errors', '0');

$probeDb = $argv[1] ?? '';
$root = $argv[2] ?? '';
if ($probeDb === '' || $root === '') {
    fwrite(STDERR, "probe: arguments manquants\n");
    exit(2);
}

// Isolement : si getDB() est invoqué accidentellement, il doit rester sur la
// base probe et ne jamais toucher data/sst.db.
putenv('SST_DB_PATH=' . $probeDb);

require_once $root . '/src/autoload.php';
require_once $root . '/src/database.php';

try {
    $probe = new PDO('sqlite:' . $probeDb);
    $probe->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $probe->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $probe->exec('PRAGMA foreign_keys = ON');

    $schema = file_get_contents($root . '/schema.sql');
    if (!is_string($schema) || $schema === '') {
        throw new RuntimeException('schema.sql illisible');
    }
    $probe->exec($schema);

    // Rebind du PDO du conteneur vers la base probe AVANT toute résolution :
    // RegistryRepository::instance() / RegistryFieldRepository::instance()
    // passent par le conteneur (bootstrap_services), pas par getDB().
    getContainer()->set(PDO::class, fn () => $probe);

    // Seed prod exécuté verbatim — source de vérité du fixture.
    seedDefaultData($probe);

    $stmt = $probe->prepare(
        'SELECT rf.field_code, rf.label, rf.field_type, rf.options, rf.is_required, rf.sort_order
         FROM registry_fields rf
         JOIN registries r ON r.id = rf.registry_id
         WHERE r.code = :code
         ORDER BY rf.sort_order ASC, rf.field_code ASC'
    );
    $stmt->execute([':code' => App\Enum\ReportType::Rami->value]);
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo '###SST_PROBE###' . json_encode(['fields' => $fields]) . '###SST_PROBE_END###';
} catch (Throwable $e) {
    fwrite(STDERR, 'probe: ' . $e->getMessage() . "\n");
    exit(1);
}
PHP;
    }

    /**
     * Extrait la charge JSON du probe entre ses sentinelles.
     */
    private static function extractProbePayload(string $stdout): ?string
    {
        $start = strpos($stdout, '###SST_PROBE###');
        if ($start === false) {
            return null;
        }
        $start += strlen('###SST_PROBE###');
        $end = strrpos($stdout, '###SST_PROBE_END###');
        if ($end === false || $end < $start) {
            return null;
        }
        $payload = trim(substr($stdout, $start, $end - $start));

        return $payload !== '' ? $payload : null;
    }

    /**
     * Re-seed du DB de tests avec les lignes dérivées du seed prod
     * (remplace le jeu posé par ExportDynamicColumnsTest s'il a tourné avant).
     *
     * @param list<array{field_code: string, label: string, field_type: string, options: ?string, is_required: int, sort_order: int}> $fields
     */
    private static function seedRamiRegistryFields(PDO $pdo, array $fields): void
    {
        $pdo->exec("DELETE FROM registry_fields WHERE registry_id = (SELECT id FROM registries WHERE code = '" . ReportType::Rami->value . "' LIMIT 1)");
        $stmt = $pdo->prepare(
            'INSERT INTO registry_fields (registry_id, field_code, label, field_type, options, is_required, sort_order)
             SELECT id, :field_code, :label, :field_type, :options, :is_required, :sort_order
             FROM registries WHERE code = :registry_code LIMIT 1'
        );
        foreach ($fields as $field) {
            $stmt->execute([
                ':field_code' => $field['field_code'],
                ':label' => $field['label'],
                ':field_type' => $field['field_type'],
                ':options' => $field['options'],
                ':is_required' => (int) $field['is_required'],
                ':sort_order' => (int) $field['sort_order'],
                ':registry_code' => ReportType::Rami->value,
            ]);
        }
    }
}
