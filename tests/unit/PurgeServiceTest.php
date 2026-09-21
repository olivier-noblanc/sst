<?php

/**
 * PurgeServiceTest — Application SST DREETS BFC
 *
 * TDD : le service de purge supervisée (bouton « Purger les signalements »)
 * n'efface les signalements et leurs données liées + l'outbox + les sessions
 * que lorsque le fichier sentinelle `erase.txt` est présent à la racine.
 *
 * Contrat verrouillé ici :
 *   - sentinelle absente → purge refusée (PurgeNotArmedException), données intactes ;
 *   - sentinelle présente → signalements, données liées, audit, outbox et
 *     sessions purgés ; users, sites, config_app et registries CONSERVÉS ;
 *   - sentinelle supprimée UNIQUEMENT après un succès complet ;
 *   - sentinelle conservée si la purge échoue.
 *
 * Chaque test s'exécute sur une base SQLite mémoire isolée (schema.sql +
 * migrateTables) : la purge réelle ne doit pas toucher la base partagée du
 * reste de la suite.
 */

use App\Repository\AuditRepository;
use App\Repository\PurgeRepository;
use App\Services\PurgeNotArmedException;
use App\Services\PurgeService;
use PHPUnit\Framework\TestCase;

class PurgeServiceTest extends TestCase
{
    private PDO $pdo;
    private string $markerPath;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $schema = file_get_contents(__DIR__ . '/../../schema.sql');
        $pdo->exec((string) $schema);
        require_once __DIR__ . '/../../src/migration_tables.php';
        migrateTables($pdo);
        $this->pdo = $pdo;

        $this->markerPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR . 'sst_erase_' . bin2hex(random_bytes(8)) . '.txt';
    }

    protected function tearDown(): void
    {
        if (is_file($this->markerPath)) {
            unlink($this->markerPath);
        }
    }

    private function service(?PurgeRepository $repository = null): PurgeService
    {
        return new PurgeService(
            $repository ?? new PurgeRepository($this->pdo),
            new AuditRepository($this->pdo),
            $this->markerPath,
        );
    }

    private function arm(): void
    {
        file_put_contents($this->markerPath, 'armed');
    }

    private function countRows(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }

    private function seedReportGraph(): void
    {
        $pdo = $this->pdo;
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email) VALUES (10, 'purge.sup', 'Sup', 'Visor', 'superviseur', NULL, 1, 'sup@dreets-bfc.gouv.fr')");
        $pdo->exec("INSERT INTO sites (id, code, nom, is_active) VALUES (10, 'PURGE', 'UR Purge', 1)");
        $pdo->exec("INSERT OR IGNORE INTO config_app (cle, valeur) VALUES ('purge_test_marker', '1')");
        $pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat, is_confidential) VALUES ('purge-u1', 'RSST-25-900', 'rsst', 'Objet purge', 'Description purge', '2025-01-01', 10, 'Sup', 'Visor', NULL, 'nouveau', 0)");
        $pdo->exec("INSERT INTO report_responses (report_uuid, user_id, reponse) VALUES ('purge-u1', 10, 'Réponse')");
        $pdo->exec("INSERT INTO report_agents (report_uuid, user_id) VALUES ('purge-u1', 10)");
        $pdo->exec("INSERT INTO report_agent_invites (report_uuid, email, token) VALUES ('purge-u1', 'invite@dreets-bfc.gouv.fr', 'tok-purge')");
        $pdo->exec("INSERT INTO report_access_log (report_uuid, user_id, role) VALUES ('purge-u1', 10, 'superviseur')");
        $pdo->exec("INSERT INTO report_state_history (report_uuid, etat_precedent, etat_suivant, user_id) VALUES ('purge-u1', 'nouveau', 'en_cours', 10)");
        $pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label) VALUES (1, 'purge_field', 'Champ purge')");
        $pdo->exec("INSERT INTO registry_field_values (report_uuid, registry_id, field_code, value) VALUES ('purge-u1', 1, 'purge_field', 'valeur')");
        $pdo->exec("INSERT INTO report_sequence (type, year, last_sequence) VALUES ('rsst', 2099, 7)");
        $pdo->exec("INSERT INTO audit_log (username, category, action, details) VALUES ('purge.sup', 'report', 'create', 'seed purge')");
        $pdo->exec("INSERT INTO email_outbox (dedup_key, recipient, subject, body, status) VALUES ('purge-mail', 'agent@dreets-bfc.gouv.fr', 'Sujet', 'Corps', 'pending')");
        $pdo->exec("INSERT INTO sessions (id, data, last_accessed) VALUES ('purge-sess', 'x', 1)");
    }

    // ═══ Sentinelle absente → refus ═══════════════════════════════════════════

    public function testIsNotArmedWhenMarkerAbsent(): void
    {
        $this->assertFalse($this->service()->isArmed());
    }

    public function testPurgeRefusedWhenMarkerAbsent(): void
    {
        $this->seedReportGraph();

        $this->expectException(PurgeNotArmedException::class);

        try {
            $this->service()->purgeAll();
        } finally {
            $this->assertSame(1, $this->countRows('reports'), 'Sentinelle absente : aucune donnée ne doit être supprimée');
            $this->assertSame(1, $this->countRows('email_outbox'), 'Sentinelle absente : l\'outbox reste intacte');
            $this->assertSame(1, $this->countRows('sessions'), 'Sentinelle absente : les sessions restent intactes');
        }
    }

    // ══ Sentinelle présente → purge applicative ══════════════════════════════

    public function testPurgeClearsReportDataOutboxAndSessionsButKeepsUsersSitesConfigAndRegistries(): void
    {
        $this->arm();
        $this->seedReportGraph();

        $counts = $this->service()->purgeAll();

        foreach ([
            'reports',
            'report_responses',
            'report_agents',
            'report_agent_invites',
            'report_access_log',
            'report_state_history',
            'registry_field_values',
            'report_sequence',
            'email_outbox',
            'sessions',
        ] as $table) {
            $this->assertSame(0, $this->countRows($table), "$table doit être vidée");
        }

        $this->assertGreaterThan(0, $counts['reports'] ?? 0, 'Le compte des signalements purgés est remonté');

        // Données préservées par contrat
        $this->assertSame(1, $this->countRows('users'), 'users est conservée');
        $this->assertSame(1, $this->countRows('sites'), 'sites est conservée');
        $this->assertSame(1, $this->countRows("config_app WHERE cle = 'purge_test_marker'"), 'config_app est conservée');
        $this->assertSame(1, $this->countRows('registry_fields'), 'registry_fields (définition des registres) est conservée');
        $this->assertGreaterThanOrEqual(3, $this->countRows('registries'), 'registries est conservée');
    }

    public function testAuditLogIsPurgedThenRecordsThePurgeAction(): void
    {
        $this->arm();
        $this->seedReportGraph();

        $this->service()->purgeAll();

        // audit_log était purgé (seed), puis l'action de purge a été journalisée.
        $this->assertSame(1, $this->countRows('audit_log'), 'Seule l\'entrée de purge subsiste dans audit_log');
        $row = $this->pdo->query("SELECT category, action FROM audit_log LIMIT 1")->fetch();
        $this->assertSame('maintenance', $row['category']);
        $this->assertSame('purge_reports', $row['action']);
    }

    // ═══ Sentinelle : supprimée après succès, conservée après échec ═══════════

    public function testMarkerDeletedAfterCompleteSuccess(): void
    {
        $this->arm();
        $this->seedReportGraph();
        $this->assertFileExists($this->markerPath);

        $this->service()->purgeAll();

        $this->assertFileDoesNotExist($this->markerPath, 'erase.txt doit être supprimé après un succès complet');
    }

    public function testMarkerKeptWhenPurgeFails(): void
    {
        $this->arm();
        $this->seedReportGraph();

        $failing = new class ($this->pdo) extends PurgeRepository {
            public function purgeReportData(): array
            {
                throw new RuntimeException('échec simulé de la purge');
            }
        };

        try {
            $this->service($failing)->purgeAll();
            $this->fail('La purge devait propager l\'échec');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('échec simulé', $e->getMessage());
        }

        $this->assertFileExists($this->markerPath, 'erase.txt doit être conservé si la purge échoue');
    }
}