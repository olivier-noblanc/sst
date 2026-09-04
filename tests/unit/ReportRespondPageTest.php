<?php
/**
 * Report Respond Page (GET) Behavioral Tests — Application SST DREETS BFC
 *
 * Réserve R1 (revue Oracle) — pages/report_respond.php n'appelle plus
 * UserRole::from() / ReportState::from() sur des valeurs non contrôlées
 * (pattern tryFrom + refus contrôlé, symétrique au handler POST).
 *
 * Ces tests exécutent la PAGE (pas le handler) en sous-process via
 * tests/handler_runner.php en mode 'page', pour pouvoir observer les
 * refus contrôlés qui font exit() (requireRole → access_denied,
 * HttpService::redirect) — impossible in-process avec PHPUnit.
 *
 * Portée honnête : aujourd'hui, requireRole() et requireReportRespondable()
 * filtrent le rôle et l'état AVANT les tryFrom de la page. Le scénario
 * n'atteint donc pas les branches tryFrom elles-mêmes ; ces tests verrouillent
 * l'invariant comportemental « rôle/état invalide → refus contrôlé, jamais de
 * ValueError fatal » — filet de régression si l'ordre des gardes change ou si
 * un ::from() réapparaît.
 */

use PHPUnit\Framework\TestCase;

class ReportRespondPageTest extends TestCase
{
    /**
     * Exécute la page dans un sous-process et retourne le résultat JSON
     * (redirect, flash, queries, output) + le code de sortie du process.
     */
    private function runPage(array $config): array
    {
        $configPath = tempnam(sys_get_temp_dir(), 'sst_pg_') . '.json';
        file_put_contents($configPath, json_encode($config));

        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../handler_runner.php') . ' ' . escapeshellarg($configPath);
        exec($cmd . ' 2>NUL', $output, $exitCode);

        unlink($configPath);

        $json = implode("\n", $output);
        $result = json_decode($json, true);
        $this->assertNotNull($result, "Invalid JSON from page runner (fatal PHP probable): $json");
        $result['exit_code'] = $exitCode;

        return $result;
    }

    /**
     * Seed site + superviseur + signalement. Le PRAGMA ignore_check_constraints
     * est nécessaire pour semer un etat hors CHECK constraint (schema.sql) —
     * il simule une donnée corrompue/legacy qui aurait échappé à la contrainte.
     */
    private function seedWithReport(string $uuid, string $etat = 'nouveau'): string
    {
        $year = date('y');
        return "PRAGMA ignore_check_constraints = ON;\n"
            . "INSERT INTO sites (code, nom, is_active) VALUES ('UD21', 'Cote d Or', 1);\n"
            . "INSERT INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('superviseur.test', 'Sup', 'Visor', 'superviseur', 1, 1, 'superviseur.test@dreets-bfc.gouv.fr');\n"
            . "INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat, consent_syndicat) VALUES ('$uuid', 'rsst-{$year}-001', 'rsst', 'Signalement test', 'Description test', '2026-01-10', 1, 'Martin', 'Jean', 1, 0, '$etat', 0);\n"
            . "PRAGMA ignore_check_constraints = OFF;\n";
    }

    private function makeSuperviseurSession(): array
    {
        return [
            'user' => [
                'id' => 1, 'nom' => 'Sup', 'prenom' => 'Visor',
                'username' => 'superviseur.test', 'role' => 'superviseur',
                'site_id' => 1, 'site_code' => 'UD21',
                'email' => 'superviseur.test@dreets-bfc.gouv.fr', 'is_active' => 1,
            ],
        ];
    }

    private function makeUnknownRoleSession(): array
    {
        $session = $this->makeSuperviseurSession();
        $session['user']['role'] = 'direction_generale';

        return $session;
    }

    // ─── Tests ───────────────────────────────────────────────────────────

    public function testPageRendersRespondFormForSuperviseurOnNouveauReport(): void
    {
        // Sanity du mode 'page' du runner : le superviseur voit bien le
        // formulaire de réponse et les transitions offertes par la machine
        // à états (nouveau → en_cours / traite) — prouve que les refus des
        // tests suivants ne sont pas des faux positifs (page vide/cassée).
        $reportUuid = '99999999-1111-4222-8333-444444444444';

        $result = $this->runPage([
            'page' => 'report_respond.php',
            'session' => $this->makeSuperviseurSession(),
            'get' => ['uuid' => $reportUuid],
            'server' => ['REQUEST_METHOD' => 'GET'],
            'db_seed' => $this->seedWithReport($reportUuid),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertSame(0, $result['exit_code']);
        $this->assertNull($result['redirect']);
        $this->assertStringContainsString('Formuler une réponse', $result['output']);
        // Transition disponible pour un superviseur sur un signalement nouveau
        $this->assertStringContainsString('value="en_cours"', $result['output']);
        $this->assertSame('nouveau', $result['queries']['report_etat']);
    }

    public function testUnknownSessionRoleIsRefusedWithoutFatalError(): void
    {
        // Rôle de session hors UserRole (session corrompue/legacy) :
        // refus contrôlé par requireRole() (page access denied + exit) —
        // jamais de ValueError fatal (UserRole::from interdit, AGENTS.md).
        $reportUuid = '99999999-2222-4333-8444-555555555555';

        $result = $this->runPage([
            'page' => 'report_respond.php',
            'session' => $this->makeUnknownRoleSession(),
            'get' => ['uuid' => $reportUuid],
            'server' => ['REQUEST_METHOD' => 'GET'],
            'db_seed' => $this->seedWithReport($reportUuid),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertSame(0, $result['exit_code'], 'Refus contrôlé : exit propre, pas de fatal');
        $this->assertNull($result['redirect']);
        $this->assertStringContainsString('Accès refusé', $result['output']);
        $this->assertStringNotContainsString('Formuler une réponse', $result['output']);
        $this->assertSame('nouveau', $result['queries']['report_etat']);
    }

    public function testUnknownReportStateIsRefusedWithControlledRedirect(): void
    {
        // État DB hors CHECK constraint (donnée corrompue/legacy) :
        // refus contrôlé — flash error + redirection vers report_view,
        // jamais de ValueError fatal (ReportState::from interdit, AGENTS.md).
        $reportUuid = '99999999-3333-4666-8777-888888888888';

        $result = $this->runPage([
            'page' => 'report_respond.php',
            'session' => $this->makeSuperviseurSession(),
            'get' => ['uuid' => $reportUuid],
            'server' => ['REQUEST_METHOD' => 'GET'],
            'db_seed' => $this->seedWithReport($reportUuid, 'etat_inconnu'),
            'assertions' => [
                'report_etat' => "SELECT etat FROM reports WHERE uuid = '$reportUuid'",
            ],
        ]);

        $this->assertSame(0, $result['exit_code'], 'Refus contrôlé : exit propre, pas de fatal');
        $this->assertNotNull($result['redirect'], 'État inconnu → redirection contrôlée');
        $this->assertStringContainsString('page=report_view', $result['redirect']);
        $this->assertSame('error', $result['flash']['type'] ?? null);
        $this->assertStringNotContainsString('Formuler une réponse', $result['output']);
        $this->assertSame('etat_inconnu', $result['queries']['report_etat'], 'Aucune écriture : refus en lecture seule');
    }
}
