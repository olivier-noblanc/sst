<?php
/**
 * Report Edit Handler Integration Tests — Application SST DREETS BFC
 *
 * Tests the report editing flow end-to-end via subprocess execution.
 */

use PHPUnit\Framework\TestCase;

class ReportEditHandlerTest extends TestCase
{
    private function runHandler(array $config): array
    {
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

    private function baseSeed(): string
    {
        return "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\nINSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('jean.martin', 'Martin', 'Jean', 'agent', 1, 1, 'jean.martin@dreets-bfc.gouv.fr');";
    }

    private function seedWithReport(string $uuid, int $declarantId = 1, int $siteId = 1, string $etat = 'nouveau'): string
    {
        $year = date('y');
        return $this->baseSeed()
            . "\nINSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat, consent_syndicat) VALUES ('$uuid', 'rsst-{$year}-001', 'rsst', 'Original objet', 'Original description', '2026-01-10', $declarantId, 'Martin', 'Jean', $siteId, 0, '$etat', 0);";
    }

    private function makeAgentSession(int $userId = 1): array
    {
        return [
            'user' => [
                'id' => $userId, 'nom' => 'Martin', 'prenom' => 'Jean',
                'username' => 'jean.martin', 'role' => 'agent',
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => 'jean.martin@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
        ];
    }

    // ─── Tests ───────────────────────────────────────────────────────────

    public function testEditReportWithValidData(): void
    {
        $reportUuid = '11111111-2222-4333-a444-555555555555';
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->makeAgentSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'report_edit_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'objet' => 'Objet modifie',
                'description' => 'Description modifiee',
                'date_evenement' => '2026-01-15',
                'lieu' => 'Bureau modifie',
                'is_confidential' => '0',
            ],
            'db_seed' => $this->seedWithReport($reportUuid),
            'assertions' => [
                'report_objet' => "SELECT objet FROM reports WHERE uuid = '$reportUuid'",
                'report_description' => "SELECT description FROM reports WHERE uuid = '$reportUuid'",
                'report_lieu' => "SELECT lieu FROM reports WHERE uuid = '$reportUuid'",
                'audit_count' => "SELECT COUNT(*) FROM audit_log WHERE action = 'edit' AND category = 'report'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_view', $result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertEquals('Objet modifie', $result['queries']['report_objet']);
        $this->assertEquals('Description modifiee', $result['queries']['report_description']);
        $this->assertEquals('Bureau modifie', $result['queries']['report_lieu']);
        $this->assertEquals(1, $result['queries']['audit_count']);
    }

    public function testRejectsEditByNonOwner(): void
    {
        $reportUuid = '11111111-2222-4333-a444-555555555555';
        $token = bin2hex(random_bytes(32));
        // User ID 2 is NOT the declarant (which is 1)
        $session = [
            'user' => [
                'id' => 2, 'nom' => 'Dupont', 'prenom' => 'Marie',
                'username' => 'marie.dupont', 'role' => 'agent',
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => 'marie.dupont@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
            'csrf_tokens' => [$token => time()],
        ];

        $result = $this->runHandler([
            'handler' => 'report_edit_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'objet' => 'Tentative de modification',
                'description' => 'Description',
                'date_evenement' => '2026-01-15',
            ],
            'db_seed' => $this->seedWithReport($reportUuid) . "\nINSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('marie.dupont', 'Dupont', 'Marie', 'agent', 1, 1, 'marie.dupont@dreets-bfc.gouv.fr');",
            'assertions' => [
                'report_objet' => "SELECT objet FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals('Original objet', $result['queries']['report_objet']);
    }

    public function testRejectsEditOfTreatedReport(): void
    {
        $reportUuid = '11111111-2222-4333-a444-555555555555';
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->makeAgentSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'report_edit_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'objet' => 'Modification refusee',
                'description' => 'Test',
                'date_evenement' => '2026-01-15',
            ],
            'db_seed' => $this->seedWithReport($reportUuid, 1, 1, 'traite'),
            'assertions' => [
                'report_objet' => "SELECT objet FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals('Original objet', $result['queries']['report_objet']);
    }

    public function testRejectsEditWithInvalidUuid(): void
    {
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->makeAgentSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'report_edit_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => 'not-a-valid-uuid',
                'objet' => 'Test',
                'description' => 'Test',
                'date_evenement' => '2026-01-15',
            ],
            'db_seed' => $this->baseSeed(),
            'assertions' => [],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
    }

    public function testEditReportWithEmptyObjetFails(): void
    {
        // The edit handler validates objet emptiness — empty objet returns form errors.
        $reportUuid = '11111111-2222-4333-a444-555555555555';
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->makeAgentSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'report_edit_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'objet' => '',
                'description' => 'Description',
                'date_evenement' => '2026-01-15',
            ],
            'db_seed' => $this->seedWithReport($reportUuid),
            'assertions' => [
                'report_objet' => "SELECT objet FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_edit', $result['redirect']);
        $this->assertNotEmpty($result['form_errors'], 'Expected form validation errors for empty objet');
        // Report should NOT be modified
        $this->assertNotEquals('', $result['queries']['report_objet']);
    }
}
