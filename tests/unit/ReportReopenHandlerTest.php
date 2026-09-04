<?php
/**
 * Report Reopen Handler Integration Tests — Application SST DREETS BFC
 *
 * Audit lifecycle :
 * - validateTransition() lève InvalidArgumentException (transition absente,
 *   ex. Nouveau→Reouvert) que le catch(RuntimeException) du handler
 *   n'interceptait pas → fatal. Gestion contrôlée symétrique à respond.
 * - La matrice autorise Traite/Abandonne → Reouvert pour [Superviseur, Chsct]
 *   : le CHSCT est un acteur légitime de la réouverture (matrice = autorité).
 */

use PHPUnit\Framework\TestCase;

class ReportReopenHandlerTest extends TestCase
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
            . "INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('superviseur.test', 'Sup', 'Visor', 'superviseur', 1, 1, 'superviseur.test@dreets-bfc.gouv.fr');\n"
            . "INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('chsct.test', 'Mem', 'Bere', 'chsct', 1, 1, 'chsct.test@dreets-bfc.gouv.fr');\n"
            . "INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat, consent_syndicat) VALUES ('$uuid', 'rsst-{$year}-201', 'rsst', 'Signalement test', 'Description test', '2026-01-10', 1, 'Martin', 'Jean', 1, 0, '$etat', 0);";
    }

    private function sessionFor(int $id, string $role, string $token): array
    {
        return [
            'user' => [
                'id' => $id, 'nom' => 'Test', 'prenom' => 'Role',
                'username' => $role . '.test', 'role' => $role,
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => $role . '.test@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
            'csrf_tokens' => [$token => time()],
        ];
    }

    public function testReopenNouveauReportShowsControlledError(): void
    {
        // Nouveau→Reouvert n'existe pas dans la matrice → InvalidArgumentException
        // du service → l'ancien catch(RuntimeException) le laissait fatal.
        $reportUuid = 'b1111111-2222-4333-a444-555555555555';
        $token = bin2hex(random_bytes(32));

        $result = $this->runHandler([
            'handler' => 'report_reopen_handler.php',
            'session' => $this->sessionFor(2, 'superviseur', $token),
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'motif_reouverture' => 'Elements nouveaux apres expertise.',
            ],
            'db_seed' => $this->seedWithReport($reportUuid, 'nouveau'),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect'], 'Transition absente → refus contrôlé, pas de fatal');
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertEquals('nouveau', $result['queries']['report_etat']);
    }

    public function testChsctCanReopenTraiteReport(): void
    {
        // Matrice : Traite→Reouvert [Superviseur, Chsct] — le CHSCT est
        // autorisé par la matrice (l'UI expose déjà le bouton).
        $reportUuid = 'b2222222-3333-4444-b555-666666666666';
        $token = bin2hex(random_bytes(32));

        $result = $this->runHandler([
            'handler' => 'report_reopen_handler.php',
            'session' => $this->sessionFor(3, 'chsct', $token),
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'motif_reouverture' => 'Elements nouveaux apres expertise.',
            ],
            'db_seed' => $this->seedWithReport($reportUuid, 'traite'),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertEquals('reouvert', $result['queries']['report_etat']);
    }

    public function testSuperviseurCanReopenTraiteReport(): void
    {
        // Garde de régression du flux nominal.
        $reportUuid = 'b3333333-4444-4555-c666-777777777777';
        $token = bin2hex(random_bytes(32));

        $result = $this->runHandler([
            'handler' => 'report_reopen_handler.php',
            'session' => $this->sessionFor(2, 'superviseur', $token),
            'post' => [
                'csrf_token' => $token,
                'report_uuid' => $reportUuid,
                'motif_reouverture' => 'Elements nouveaux apres expertise.',
            ],
            'db_seed' => $this->seedWithReport($reportUuid, 'traite'),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertNotNull($result['redirect']);
        $this->assertEquals('success', $result['flash']['type'] ?? null);
        $this->assertEquals('reouvert', $result['queries']['report_etat']);
    }
}
