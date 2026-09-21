<?php
/**
 * Report Transmit Handler Integration Test — Application SST DREETS BFC
 *
 * L'action « Transmettre au rôle … » (superviseur, libellé dérivé du rôle
 * CSA/CHSCT configurable) met en file les messages CSA/CHSCT via l'outbox et
 * trace l'action dans l'audit.
 * Le contrôle de rôle/CSRF est porté par les middlewares de route (testés dans
 * RouterRoleMappingTest) ; ce test exerce le handler en subprocess.
 */

use PHPUnit\Framework\TestCase;

class ReportTransmitHandlerTest extends TestCase
{
    private string $dbPath;

    private const UUID = 'abcd1234-1111-2222-3333-444444444444';
    private const CSA_EMAIL = 'csa.handler@dreets-bfc.gouv.fr';

    protected function setUp(): void
    {
        $this->dbPath = tempnam(sys_get_temp_dir(), 'sst_trans_') . '.db';
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function runHandler(array $config): array
    {
        $config['db_path'] = $this->dbPath;

        $configPath = tempnam(sys_get_temp_dir(), 'sst_cfg_') . '.json';
        file_put_contents($configPath, json_encode($config));

        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../handler_runner.php') . ' ' . escapeshellarg($configPath);
        exec($cmd . ' 2>NUL', $output, $exitCode);

        unlink($configPath);

        $json = implode("\n", $output);
        $result = json_decode($json, true);
        $this->assertNotNull($result, "Invalid JSON from handler runner: $json");

        return $result;
    }

    private function createTestDb(): void
    {
        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schema = file_get_contents(__DIR__ . '/../../schema.sql');
        $pdo->exec($schema);
        $pdo = null;
    }

    private function seedSql(): string
    {
        return "INSERT INTO sites (id, code, nom, is_active) VALUES (1, 'UDH', 'Site Handler', 1);\n"
            . "INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email) VALUES (50, 'test.h.decl', 'Martin', 'Jean', 'agent', 1, 1, 'declarant.handler@dreets-bfc.gouv.fr');\n"
            . "INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email) VALUES (51, 'test.h.csa', 'Csa', 'Membre', 'chsct', 1, 1, '" . self::CSA_EMAIL . "');\n"
            . "INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email) VALUES (52, 'test.h.sup', 'Sup', 'Erviseur', 'superviseur', 1, 1, 'sup.handler@dreets-bfc.gouv.fr');\n"
            . "INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, consent_syndicat, etat) VALUES ('" . self::UUID . "', 'RSST-26-HTR', 'rsst', 'Objet handler transmission', 'Description', '2026-02-01', 50, 'Martin', 'Jean', 1, 0, 0, 'nouveau')";
    }

    private function superviseurSession(): array
    {
        $token = bin2hex(random_bytes(32));
        return [
            'user' => [
                'id' => 52,
                'nom' => 'Sup',
                'prenom' => 'Erviseur',
                'username' => 'test.h.sup',
                'role' => 'superviseur',
                'site_id' => 1,
                'site_code' => 'UDH',
                'email' => 'sup.handler@dreets-bfc.gouv.fr',
                'is_active' => 1,
            ],
            'csrf_tokens' => [$token => time()],
        ];
    }

    private function dedupKey(): string
    {
        return 'report_transmitted:' . self::UUID . ':' . self::CSA_EMAIL;
    }

    public function testSupervisorTransmissionEnqueuesAndAudits(): void
    {
        $this->createTestDb();
        $token = bin2hex(random_bytes(32));
        $session = $this->superviseurSession();
        $session['csrf_tokens'] = [$token => time()];

        $result = $this->runHandler([
            'handler' => 'report_transmit_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'uuid' => self::UUID,
            ],
            'db_seed' => $this->seedSql(),
            'assertions' => [
                'outbox_count' => "SELECT COUNT(*) FROM email_outbox WHERE dedup_key = '" . $this->dedupKey() . "'",
                'outbox_recipient' => "SELECT recipient FROM email_outbox WHERE dedup_key = '" . $this->dedupKey() . "'",
                'outbox_subject' => "SELECT subject FROM email_outbox WHERE dedup_key = '" . $this->dedupKey() . "'",
                'audit_count' => "SELECT COUNT(*) FROM audit_log WHERE action = 'transmit' AND category = 'report'",
                'audit_uuid' => "SELECT target_uuid FROM audit_log WHERE action = 'transmit' AND category = 'report'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_view', (string) $result['redirect']);
        $this->assertStringContainsString('uuid=' . self::UUID, (string) $result['redirect']);

        $this->assertEquals(1, $result['queries']['outbox_count'], 'La transmission doit être mise en file pour le membre CSA');
        $this->assertSame(self::CSA_EMAIL, $result['queries']['outbox_recipient']);
        $this->assertStringContainsString('RSST-26-HTR', (string) $result['queries']['outbox_subject']);

        $this->assertEquals(1, $result['queries']['audit_count'], 'L\'action de transmission doit être auditée');
        $this->assertSame(self::UUID, $result['queries']['audit_uuid']);

        $this->assertSame('success', $result['flash']['type'] ?? null);
    }

    public function testTransmissionOnUnknownReportIsRejected(): void
    {
        $this->createTestDb();
        $token = bin2hex(random_bytes(32));
        $session = $this->superviseurSession();
        $session['csrf_tokens'] = [$token => time()];

        $result = $this->runHandler([
            'handler' => 'report_transmit_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'uuid' => 'not-a-uuid',
            ],
            'db_seed' => $this->seedSql(),
            'assertions' => [
                'outbox_count' => 'SELECT COUNT(*) FROM email_outbox',
                'audit_count' => "SELECT COUNT(*) FROM audit_log WHERE action = 'transmit'",
            ],
        ]);

        $this->assertEquals(0, $result['queries']['outbox_count'], 'Aucune transmission pour un signalement introuvable');
        $this->assertEquals(0, $result['queries']['audit_count'], 'Aucun audit pour un signalement introuvable');
        $this->assertSame('error', $result['flash']['type'] ?? null);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }
}