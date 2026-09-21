<?php
/**
 * Report Respond Handler Integration Tests — Application SST DREETS BFC
 *
 * Tests the report response flow end-to-end via subprocess execution.
 * Only superviseur users can respond to reports.
 */

use PHPUnit\Framework\TestCase;

class ReportRespondHandlerTest extends TestCase
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
        return "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\n"
            . "INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('jean.martin', 'Martin', 'Jean', 'agent', 1, 1, 'jean.martin@dreets-bfc.gouv.fr');\n"
            . "INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('superviseur.test', 'Sup', 'Visor', 'superviseur', 1, 1, 'superviseur.test@dreets-bfc.gouv.fr');";
    }

    private function seedWithReport(string $uuid, int $declarantId = 1, int $siteId = 1, string $etat = 'nouveau'): string
    {
        $year = date('y');
        return $this->baseSeed()
            . "\nINSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat, consent_syndicat) VALUES ('$uuid', 'rsst-{$year}-001', 'rsst', 'Signalement test', 'Description test', '2026-01-10', $declarantId, 'Martin', 'Jean', $siteId, 0, '$etat', 0);";
    }

    private function makeSuperviseurSession(): array
    {
        return [
            'user' => [
                'id' => 2, 'nom' => 'Sup', 'prenom' => 'Visor',
                'username' => 'superviseur.test', 'role' => 'superviseur',
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => 'superviseur.test@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
        ];
    }

    // ─── Tests ───────────────────────────────────────────────────────────

    public function testRespondToReportWithValidData(): void
    {
        $reportUuid = '11111111-2222-4333-a444-555555555555';
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->makeSuperviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'report_respond_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'nouvel_etat' => 'en_cours',
                'reponse' => 'Nous avons pris en compte votre signalement.',
            ],
            'db_seed' => $this->seedWithReport($reportUuid),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
                'report_reponse' => "SELECT reponse FROM reports WHERE uuid = '$reportUuid'",
                'response_count' => "SELECT COUNT(*) FROM report_responses WHERE report_uuid = '$reportUuid'",
                'audit_count' => "SELECT COUNT(*) FROM audit_log WHERE action = 'respond' AND category = 'report'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_view', $result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertEquals('en_cours', $result['queries']['report_etat']);
        $this->assertEquals('Nous avons pris en compte votre signalement.', $result['queries']['report_reponse']);
        $this->assertEquals(1, $result['queries']['response_count']);
        $this->assertEquals(1, $result['queries']['audit_count']);
    }

    public function testRespondMarksReportAsTreated(): void
    {
        $reportUuid = '22222222-3333-4444-a555-666666666666';
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->makeSuperviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'report_respond_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'nouvel_etat' => 'traite',
                'reponse' => 'Signalement traite et clos.',
            ],
            'db_seed' => $this->seedWithReport($reportUuid, 1, 1, 'en_cours'),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertEquals('traite', $result['queries']['report_etat']);
    }

    public function testRejectsEmptyResponse(): void
    {
        $reportUuid = '33333333-4444-4555-a666-777777777777';
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->makeSuperviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'report_respond_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'nouvel_etat' => 'en_cours',
                'reponse' => '',
            ],
            'db_seed' => $this->seedWithReport($reportUuid),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals('nouveau', $result['queries']['report_etat']);
    }

    public function testRejectsInvalidEtat(): void
    {
        $reportUuid = '44444444-5555-4666-a777-888888888888';
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->makeSuperviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'report_respond_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'nouvel_etat' => 'invalid_etat',
                'reponse' => 'Reponse',
            ],
            'db_seed' => $this->seedWithReport($reportUuid),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals('nouveau', $result['queries']['report_etat']);
    }

    public function testRejectsResponseByAgent(): void
    {
        $reportUuid = '55555555-6666-4777-a888-999999999999';
        $token = bin2hex(random_bytes(32));
        $session = [
            'user' => [
                'id' => 1, 'nom' => 'Martin', 'prenom' => 'Jean',
                'username' => 'jean.martin', 'role' => 'agent',
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => 'jean.martin@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
            'csrf_tokens' => [$token => time()],
        ];

        $result = $this->runHandler([
            'handler' => 'report_respond_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'nouvel_etat' => 'en_cours',
                'reponse' => 'Reponse agent non autorisee',
            ],
            'db_seed' => $this->seedWithReport($reportUuid),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals('nouveau', $result['queries']['report_etat']);
    }

    public function testRespondToEnCoursWithSameStateAppendsResponse(): void
    {
        // Bug corrigé : un signalement déjà « en_cours » n'offrait plus que
        // « traite », alors qu'une nouvelle réponse peut rester « en_cours »
        // pour poursuivre les échanges (EnCours→EnCours désormais valide dans
        // la matrice pour le superviseur). Attendu : réponse enregistrée,
        // état inchangé, historique incrémenté.
        $reportUuid = '77777777-8888-4999-baaa-bbbbbbbbbbbb';
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->makeSuperviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'report_respond_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'nouvel_etat' => 'en_cours',
                'reponse' => 'Mise a jour sans changement d etat.',
            ],
            'db_seed' => $this->seedWithReport($reportUuid, 1, 1, 'en_cours'),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
                'report_reponse' => "SELECT reponse FROM reports WHERE uuid = '$reportUuid'",
                'response_count' => "SELECT COUNT(*) FROM report_responses WHERE report_uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertStringContainsString('page=report_view', $result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertEquals('en_cours', $result['queries']['report_etat']);
        $this->assertEquals('Mise a jour sans changement d etat.', $result['queries']['report_reponse']);
        $this->assertEquals(1, $result['queries']['response_count']);
    }

    public function testMultipleResponsesOnEnCoursAreArchivedAndKeepLastStateConsistent(): void
    {
        // Scénario « poursuite des échanges » : le signalement a déjà une
        // première réponse (colonne reports.reponse + ligne report_responses),
        // et un superviseur en ajoute une seconde en restant « en_cours ».
        // Attendu : les DEUX réponses sont archivées, la colonne reponse
        // reflète la dernière, l'état reste en_cours (pas d'écrasement).
        $reportUuid = '99999999-aaaa-4bbb-8ccc-dddddddddddd';
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->makeSuperviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $seed = $this->seedWithReport($reportUuid, 1, 1, 'en_cours')
            . "\nUPDATE reports SET reponse = 'Premiere reponse', repondant_id = 2, date_reponse = datetime('now') WHERE uuid = '$reportUuid';"
            . "\nINSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat) VALUES ('$reportUuid', 2, 'Premiere reponse', 'en_cours');";

        $result = $this->runHandler([
            'handler' => 'report_respond_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'nouvel_etat' => 'en_cours',
                'reponse' => 'Deuxieme reponse de poursuite.',
            ],
            'db_seed' => $seed,
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
                'report_reponse' => "SELECT reponse FROM reports WHERE uuid = '$reportUuid'",
                'response_count' => "SELECT COUNT(*) FROM report_responses WHERE report_uuid = '$reportUuid'",
                'archived_content' => "SELECT group_concat(reponse, '|') FROM report_responses WHERE report_uuid = '$reportUuid' ORDER BY id",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertEquals('en_cours', $result['queries']['report_etat']);
        $this->assertEquals('Deuxieme reponse de poursuite.', $result['queries']['report_reponse']);
        $this->assertEquals(2, $result['queries']['response_count']);
        $this->assertStringContainsString('Premiere reponse', (string) $result['queries']['archived_content']);
        $this->assertStringContainsString('Deuxieme reponse de poursuite.', (string) $result['queries']['archived_content']);
    }

    public function testRejectsUnknownSessionRoleWithControlledError(): void
    {
        // Fiabilisation (oracle) — UserRole::from(currentUserRole()) levait une
        // ValueError fatale si le rôle de session n'était pas un UserRole connu
        // (session corrompue/legacy) — interdit par AGENTS.md/NoForbiddenEnumMethodRule.
        // Attendu : tryFrom + refus contrôlé (flash + redirection), jamais de fatal.
        $reportUuid = '88888877-9999-4aaa-bbbb-cccccccccccc';
        $token = bin2hex(random_bytes(32));
        $session = [
            'user' => [
                'id' => 9, 'nom' => 'Legacy', 'prenom' => 'Role',
                'username' => 'legacy.role', 'role' => 'direction_generale',
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => 'legacy.role@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
            'csrf_tokens' => [$token => time()],
        ];

        $result = $this->runHandler([
            'handler' => 'report_respond_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'nouvel_etat' => 'en_cours',
                'reponse' => 'Tentative avec role de session inconnu.',
            ],
            'db_seed' => $this->seedWithReport($reportUuid),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
                'response_count' => "SELECT COUNT(*) FROM report_responses WHERE report_uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect'], 'Rôle inconnu → refus contrôlé, pas de fatal ValueError');
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals('nouveau', $result['queries']['report_etat']);
        $this->assertEquals(0, $result['queries']['response_count']);
    }

    public function testRejectsResponseToTreatedReport(): void
    {
        $reportUuid = '66666666-7777-4888-a999-aaaaaaaaaaaa';
        $token = bin2hex(random_bytes(32));
        $session = array_merge($this->makeSuperviseurSession(), ['csrf_tokens' => [$token => time()]]);

        $result = $this->runHandler([
            'handler' => 'report_respond_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'nouvel_etat' => 'en_cours',
                'reponse' => 'Reponse tardive',
            ],
            'db_seed' => $this->seedWithReport($reportUuid, 1, 1, 'traite'),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals('traite', $result['queries']['report_etat']);
    }
}
