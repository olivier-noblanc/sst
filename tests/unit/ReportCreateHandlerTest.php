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

        // Flash should be 'created' (confirmation banner trigger)
        $this->assertEquals('created', $result['flash']['type'] ?? null);
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

        // Should redirect to report_create with form errors
        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_create', $result['redirect']);
        $this->assertNotEmpty($result['form_errors'], 'Expected form validation errors for empty objet');

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

        // Should redirect to report_create with form errors
        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_create', $result['redirect']);
        $this->assertNotEmpty($result['form_errors'], 'Expected form validation errors for missing date');

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

    /**
     * Bug confirmé — une pièce jointe invalide (erreur d'upload PHP) était
     * silencieusement ignorée : le handler collectait $errors via
     * validateReportAttachment() mais ne les vérifiait jamais, créant le
     * signalement sans pièce jointe ET sans message.
     */
    public function testRejectsFailedAttachmentUploadOnCreate(): void
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
                'objet' => 'Test piece jointe en erreur',
                'description' => 'Description du test',
                'date_evenement' => '2026-01-15',
                'site_id' => '1',
            ],
            'files' => [
                'attachment' => [
                    'name' => 'photo.jpg',
                    'type' => 'image/jpeg',
                    'tmp_name' => '',
                    'error' => UPLOAD_ERR_INI_SIZE,
                    'size' => 0,
                ],
            ],
            'db_seed' => "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\nINSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('jean.martin', 'Martin', 'Jean', 'agent', 1, 1, 'jean.martin@dreets-bfc.gouv.fr');",
            'assertions' => [
                'report_count' => "SELECT COUNT(*) FROM reports",
                'attachment_count' => "SELECT COUNT(*) FROM reports WHERE attachment_blob IS NOT NULL",
            ],
        ]);

        // Le flux doit être interrompu, pas poursuivi silencieusement
        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_create', $result['redirect']);
        $this->assertNotEmpty($result['form_errors'], 'Expected a form error for the failed attachment upload');
        $this->assertArrayHasKey('attachment', $result['form_errors']);

        // Les données saisies sont préservées pour le ré-affichage du formulaire
        $this->assertSame('Test piece jointe en erreur', $result['form_data']['objet'] ?? null);

        // Aucun signalement créé sans sa pièce jointe
        $this->assertEquals(0, $result['queries']['report_count']);
        $this->assertEquals(0, $result['queries']['attachment_count']);
    }

    /**
     * Même bug, second chemin : un type MIME non autorisé doit aussi
     * interrompre la création au lieu d'être ignoré.
     */
    public function testRejectsDisallowedAttachmentMimeOnCreate(): void
    {
        $this->createTestDb();

        $tmpFile = tempnam(sys_get_temp_dir(), 'sst_att_');
        $this->assertNotFalse($tmpFile, 'Unable to create temp attachment fixture');
        file_put_contents($tmpFile, 'not an image, just text');

        $token = bin2hex(random_bytes(32));
        $session = array_merge(
            $this->makeAgentSession(1, 1),
            ['csrf_tokens' => [$token => time()]]
        );

        try {
            $result = $this->runHandler([
                'handler' => 'report_create_handler.php',
                'session' => $session,
                'post' => [
                    'csrf_token' => $token,
                    'type' => 'rsst',
                    'objet' => 'Test mime interdit',
                    'description' => 'Description du test',
                    'date_evenement' => '2026-01-15',
                    'site_id' => '1',
                ],
                'files' => [
                    'attachment' => [
                        'name' => 'document.txt',
                        'type' => 'text/plain',
                        'tmp_name' => $tmpFile,
                        'error' => UPLOAD_ERR_OK,
                        'size' => filesize($tmpFile),
                    ],
                ],
                'db_seed' => "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\nINSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('jean.martin', 'Martin', 'Jean', 'agent', 1, 1, 'jean.martin@dreets-bfc.gouv.fr');",
                'assertions' => [
                    'report_count' => "SELECT COUNT(*) FROM reports",
                ],
            ]);
        } finally {
            unlink($tmpFile);
        }

        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_create', $result['redirect']);
        $this->assertNotEmpty($result['form_errors'], 'Expected a form error for the disallowed attachment MIME type');
        $this->assertArrayHasKey('attachment', $result['form_errors']);

        $this->assertEquals(0, $result['queries']['report_count']);
    }

    /**
     * Distinction création/édition — le marqueur serveur « remove_attachment »
     * n'a de sens qu'à l'édition (suppression d'une pièce jointe déjà
     * persistée, traitée par UpdateReportCommand/report_edit_handler).
     *
     * À la création, la suppression ciblée est purement client (reset de
     * l'input file avant soumission) : si un marqueur remove_attachment
     * arrivait malgré tout dans le POST, le handler de création doit
     * l'ignorer et créer le signalement normalement, sans pièce jointe.
     */
    public function testCreateIgnoresEditOnlyRemoveAttachmentMarker(): void
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
                'objet' => 'Creation avec marqueur edition',
                'description' => 'Description du test',
                'date_evenement' => '2026-01-15',
                'lieu' => 'Bureau test',
                'site_id' => '1',
                'remove_attachment' => '1',
            ],
            'db_seed' => "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\nINSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('jean.martin', 'Martin', 'Jean', 'agent', 1, 1, 'jean.martin@dreets-bfc.gouv.fr');",
            'assertions' => [
                'report_count' => "SELECT COUNT(*) FROM reports WHERE objet = 'Creation avec marqueur edition'",
                'attachment_count' => "SELECT COUNT(*) FROM reports WHERE attachment_blob IS NOT NULL",
            ],
        ]);

        // Le marqueur d'édition est ignoré : la création aboutit normalement.
        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_view', $result['redirect']);
        $this->assertEquals(1, $result['queries']['report_count']);
        $this->assertEquals(0, $result['queries']['attachment_count']);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }
}
