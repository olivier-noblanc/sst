<?php
/**
 * Report Create Handler Integration Tests — Application SST DREETS BFC
 *
 * Tests the report creation flow end-to-end via subprocess execution.
 * Handlers call exit() through redirect(), so they run in child processes.
 */

use PHPUnit\Framework\TestCase;

class ReportCreateHandlerTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = tempnam(sys_get_temp_dir(), 'sst_test_') . '.db';
    }

    /**
     * Run a handler in a subprocess and return the parsed JSON result.
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

    private function makeAgentSession(int $userId, int $siteId): array
    {
        return [
            'user' => [
                'id' => $userId,
                'nom' => 'Martin',
                'prenom' => 'Jean',
                'username' => 'jean.martin',
                'role' => 'agent',
                'site_id' => $siteId,
                'site_code' => 'UD21',
                'email' => 'jean.martin@dreets-bfc.gouv.fr',
                'is_active' => 1,
            ],
        ];
    }

    private function makeCsrfSession(array $extra = []): array
    {
        $token = bin2hex(random_bytes(32));
        $session = array_merge([
            'csrf_tokens' => [$token => time()],
        ], $extra);
        $session['_csrf_token_for_test'] = $token;
        return $session;
    }

    // ─── Tests ───────────────────────────────────────────────────────────

    public function testCreateReportWithValidData(): void
    {
        $this->createTestDb();

        $token = bin2hex(random_bytes(32));
        $session = array_merge(
            $this->makeAgentSession(1, 1),
            ['csrf_tokens' => [$token => time()]]
        );

        $result = $this->runHandler([
            'handler' => 'report_create_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'type' => 'rsst',
                'objet' => 'Test signalement',
                'description' => 'Description du test',
                'date_evenement' => '2026-01-15',
                'lieu' => 'Bureau test',
                'site_id' => '1',
                'is_confidential' => '1',
            ],
            'db_seed' => "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\nINSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('jean.martin', 'Martin', 'Jean', 'agent', 1, 1, 'jean.martin@dreets-bfc.gouv.fr');",
            'assertions' => [
                'report_count' => "SELECT COUNT(*) FROM reports WHERE objet = 'Test signalement'",
                'report_type' => "SELECT type FROM reports WHERE objet = 'Test signalement'",
                'report_etat' => "SELECT etat FROM reports WHERE objet = 'Test signalement'",
                'audit_count' => "SELECT COUNT(*) FROM audit_log WHERE action = 'create' AND category = 'report'",
            ],
        ]);

        // Redirect should go to report_view
        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_view', $result['redirect']);
        $this->assertStringContainsString('uuid=', $result['redirect']);

        // Flash should be success
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertStringContainsString('enregistré', $result['flash']['message'] ?? '');

        // Report was created
        $this->assertEquals(1, $result['queries']['report_count']);
        $this->assertEquals('rsst', $result['queries']['report_type']);
        $this->assertEquals('nouveau', $result['queries']['report_etat']);

        // Audit log was written
        $this->assertEquals(1, $result['queries']['audit_count']);
    }

    public function testRejectsInvalidType(): void
    {
        $this->createTestDb();

        $token = bin2hex(random_bytes(32));
        $session = array_merge(
            $this->makeAgentSession(1, 1),
            ['csrf_tokens' => [$token => time()]]
        );

        $result = $this->runHandler([
            'handler' => 'report_create_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'type' => 'invalid_type',
                'objet' => 'Test',
                'description' => 'Test',
                'date_evenement' => '2026-01-15',
                'site_id' => '1',
            ],
            'db_seed' => "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\nINSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('jean.martin', 'Martin', 'Jean', 'agent', 1, 1, 'jean.martin@dreets-bfc.gouv.fr');",
            'assertions' => [
                'report_count' => "SELECT COUNT(*) FROM reports",
            ],
        ]);

        // Should redirect to home with error
        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);

        // No report created
        $this->assertEquals(0, $result['queries']['report_count']);
    }

    public function testRejectsEmptyObjet(): void
    {
        $this->createTestDb();

        $token = bin2hex(random_bytes(32));
        $session = array_merge(
            $this->makeAgentSession(1, 1),
            ['csrf_tokens' => [$token => time()]]
        );

        $result = $this->runHandler([
            'handler' => 'report_create_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'type' => 'rsst',
                'objet' => '',
                'description' => 'Description',
                'date_evenement' => '2026-01-15',
                'site_id' => '1',
            ],
            'db_seed' => "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\nINSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('jean.martin', 'Martin', 'Jean', 'agent', 1, 1, 'jean.martin@dreets-bfc.gouv.fr');",
            'assertions' => [
                'report_count' => "SELECT COUNT(*) FROM reports",
            ],
        ]);

        // Should redirect to report_create with error
        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_create', $result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);

        // No report created
        $this->assertEquals(0, $result['queries']['report_count']);
    }

    public function testRejectsMissingDate(): void
    {
        $this->createTestDb();

        $token = bin2hex(random_bytes(32));
        $session = array_merge(
            $this->makeAgentSession(1, 1),
            ['csrf_tokens' => [$token => time()]]
        );

        $result = $this->runHandler([
            'handler' => 'report_create_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'type' => 'rsst',
                'objet' => 'Test sans date',
                'description' => 'Description',
                'date_evenement' => '',
                'site_id' => '1',
            ],
            'db_seed' => "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\nINSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('jean.martin', 'Martin', 'Jean', 'agent', 1, 1, 'jean.martin@dreets-bfc.gouv.fr');",
            'assertions' => [
                'report_count' => "SELECT COUNT(*) FROM reports",
            ],
        ]);

        // Should redirect to report_create with error
        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_create', $result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);

        // No report created
        $this->assertEquals(0, $result['queries']['report_count']);
    }

    // Note: CSRF token and non-POST request validation are now handled by
    // CsrfMiddleware in the Router, not by the handler directly.
    // See App\Middleware\CsrfMiddleware for CSRF rejection tests.

    public function testRejectsInvalidSiteId(): void
    {
        $this->createTestDb();

        $token = bin2hex(random_bytes(32));
        $session = array_merge(
            $this->makeAgentSession(1, 1),
            ['csrf_tokens' => [$token => time()]]
        );

        $result = $this->runHandler([
            'handler' => 'report_create_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'type' => 'rsst',
                'objet' => 'Test',
                'description' => 'Description',
                'date_evenement' => '2026-01-15',
                'site_id' => '999',
            ],
            'db_seed' => "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\nINSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('jean.martin', 'Martin', 'Jean', 'agent', 1, 1, 'jean.martin@dreets-bfc.gouv.fr');",
            'assertions' => [
                'report_count' => "SELECT COUNT(*) FROM reports",
            ],
        ]);

        // Should redirect with error (form_errors, not flash — handler uses setFormErrors)
        $this->assertNotNull($result['redirect']);
        $this->assertNotNull($result['form_errors']);
        $this->assertArrayHasKey('site_id', $result['form_errors']);

        // No report created
        $this->assertEquals(0, $result['queries']['report_count']);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }
}
