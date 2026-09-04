<?php
/**
 * Report Abandon Handler Integration Tests — Application SST DREETS BFC
 *
 * Audit lifecycle — cohérence guards ↔ matrice ReportStateMachine :
 * la matrice autorise Nouveau/EnCours/Traite/Reouvert → Abandonne pour le
 * rôle Agent (déclarant). requireReportEditable ([Nouveau, EnCours]) était
 * PLUS restrictif que la matrice : un agent ne pouvait pas abandonner un
 * signalement Réouvert, alors que l'autorité (matrice) l'autorise et que le
 * bouton UI l'exposait.
 */

use PHPUnit\Framework\TestCase;

class ReportAbandonHandlerTest extends TestCase
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
        $this->assertNotNull($result, "JSON invalide du handler runner (fatal probable) : $json");

        return $result;
    }

    private function seedWithReport(string $uuid, string $etat): string
    {
        $year = date('y');
        return "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\n"
            . "INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('jean.martin', 'Martin', 'Jean', 'agent', 1, 1, 'jean.martin@dreets-bfc.gouv.fr');\n"
            . "INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat, consent_syndicat) VALUES ('$uuid', 'rsst-{$year}-101', 'rsst', 'Signalement test', 'Description test', '2026-01-10', 1, 'Martin', 'Jean', 1, 0, '$etat', 0);";
    }

    private function agentSession(string $token): array
    {
        return [
            'user' => [
                'id' => 1, 'nom' => 'Martin', 'prenom' => 'Jean',
                'username' => 'jean.martin', 'role' => 'agent',
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => 'jean.martin@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
            'csrf_tokens' => [$token => time()],
        ];
    }

    public function testAgentCanAbandonReouvertReport(): void
    {
        // Matrice = autorité : Reouvert→Abandonne [Agent] existe. Le guard
        // requireReportEditable ([Nouveau, EnCours]) bloquait à tort.
        $reportUuid = 'a1111111-2222-4333-a444-555555555555';
        $token = bin2hex(random_bytes(32));

        $result = $this->runHandler([
            'handler' => 'report_abandon_handler.php',
            'session' => $this->agentSession($token),
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
            ],
            'db_seed' => $this->seedWithReport($reportUuid, 'reouvert'),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertEquals('abandonne', $result['queries']['report_etat']);
    }

    public function testAbandonAlreadyAbandonedShowsControlledError(): void
    {
        // Abandonne→Abandonne n'existe pas dans la matrice → refus contrôlé
        // (flash), jamais d'exception fatale.
        $reportUuid = 'a2222222-3333-4444-b555-666666666666';
        $token = bin2hex(random_bytes(32));

        $result = $this->runHandler([
            'handler' => 'report_abandon_handler.php',
            'session' => $this->agentSession($token),
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
            ],
            'db_seed' => $this->seedWithReport($reportUuid, 'abandonne'),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals('abandonne', $result['queries']['report_etat']);
    }

    public function testAbandonByNonDeclarantShowsControlledError(): void
    {
        // Ownership : seul le déclarant abandonne (requireReportOwnership).
        $reportUuid = 'a3333333-4444-4555-c666-777777777777';
        $token = bin2hex(random_bytes(32));
        $session = $this->agentSession($token);
        $session['user']['id'] = 42; // autre utilisateur, même rôle agent

        $result = $this->runHandler([
            'handler' => 'report_abandon_handler.php',
            'session' => $session,
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
            ],
            'db_seed' => $this->seedWithReport($reportUuid, 'nouveau'),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals('nouveau', $result['queries']['report_etat']);
    }
}
