<?php
/**
 * Router Integration Tests — Application SST DREETS BFC
 *
 * Dispatches real POST requests through App\Router\Router::dispatchPost(),
 * exercising the exact same middleware chain a real HTTP request goes
 * through (CsrfMiddleware, RoleMiddleware, ...) — unlike the *HandlerTest
 * classes elsewhere in this directory, which use handler_runner.php and
 * call the handler file directly, skipping router-level middleware
 * entirely.
 *
 * This is the layer that catches bugs living in the *interaction* between
 * router middleware and a handler's own checks — e.g. a handler
 * double-checking something the router middleware already validated,
 * silently failing on a one-time-use CSRF token consumed twice. That
 * exact bug (report_create, report_edit, choose_site all redirecting to
 * home with a generic "security error", no report ever saved, nothing
 * useful logged) went unnoticed by every other layer of this test suite,
 * including handler_runner.php-based tests and PHPUnit for the same
 * reason: none of them go through the router.
 */

use PHPUnit\Framework\TestCase;

class RouterCsrfIntegrationTest extends TestCase
{
    private function runRouter(array $config): array
    {
        $configPath = tempnam(sys_get_temp_dir(), 'sst_router_cfg_') . '.json';
        file_put_contents($configPath, json_encode($config));

        $cmd = 'php ' . escapeshellarg(__DIR__ . '/../router_runner.php') . ' ' . escapeshellarg($configPath);
        exec($cmd . ' 2>/dev/null', $output, $exitCode);

        unlink($configPath);

        $json = implode("\n", $output);
        $result = json_decode($json, true);
        $this->assertNotNull($result, "Invalid JSON from router runner: $json");

        return $result;
    }

    private function agentSession(int $userId = 1): array
    {
        return [
            'user' => [
                'id' => $userId, 'nom' => 'Martin', 'prenom' => 'Jean',
                'username' => 'jean.martin', 'role' => 'agent', 'site_id' => null,
                'email' => 'jean.martin@dreets.gouv.fr', 'is_active' => 1,
            ],
            'csrf_tokens' => ['validtoken123' => time()],
        ];
    }

    private function baseSeed(): string
    {
        return "INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email) VALUES (1, 'jean.martin', 'Martin', 'Jean', 'agent', NULL, 1, 'jean.martin@dreets.gouv.fr');";
    }

    // ─── report_create ─────────────────────────────────────────────────────

    public function testReportCreateSucceedsThroughRealRouter(): void
    {
        $result = $this->runRouter([
            'page' => 'report_create',
            'session' => $this->agentSession(),
            'post' => [
                'csrf_token' => 'validtoken123',
                'type' => 'rsst',
                'objet' => 'Test via router',
                'description' => 'Verification integration reelle via le routeur',
                'date_evenement' => '2026-07-20',
                'site_id' => '',
            ],
            'db_seed' => $this->baseSeed(),
            'assertions' => ['report_count' => 'SELECT COUNT(*) FROM reports'],
        ]);

        $this->assertEquals(1, $result['queries']['report_count'], 'Report was not saved — regression of the double CSRF-consumption bug (router middleware + handler both validating the same one-time-use token).');
        $this->assertStringContainsString('report_view', (string) $result['redirect']);
        $this->assertStringContainsString('result=success', (string) $result['redirect'], 'redirect() should append result=<flash type> automatically — pure debug aid, see HttpService::redirect().');
        $this->assertEquals('success', $result['flash']['type'] ?? null);
    }

    public function testReportCreateRejectsInvalidCsrfToken(): void
    {
        // A token that was never issued must still be rejected — this is
        // the counterpart to the test above: the router's CsrfMiddleware
        // must still do its job when the token is genuinely wrong, not
        // just when it's a legitimate token being double-checked.
        $result = $this->runRouter([
            'page' => 'report_create',
            'session' => $this->agentSession(),
            'post' => [
                'csrf_token' => 'this-token-was-never-issued',
                'type' => 'rsst',
                'objet' => 'Should not be saved',
                'description' => 'Invalid token test',
                'date_evenement' => '2026-07-20',
                'site_id' => '',
            ],
            'db_seed' => $this->baseSeed(),
            'assertions' => ['report_count' => 'SELECT COUNT(*) FROM reports'],
        ]);

        $this->assertEquals(0, $result['queries']['report_count']);
        $this->assertEquals('error', $result['flash']['type'] ?? null);
        $this->assertStringContainsString('result=error', (string) $result['redirect'], 'Two very different outcomes (valid vs invalid CSRF) must not be indistinguishable from the redirect URL alone.');
    }

    // ─── report_edit ────────────────────────────────────────────────────────

    public function testReportEditSucceedsThroughRealRouter(): void
    {
        $uuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
        $seed = $this->baseSeed() . "\n"
            . "INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat) VALUES ('$uuid', 'rsst-26-001', 'rsst', 'Original', 'Desc originale', '2026-01-01', 1, 'Martin', 'Jean', NULL, 'nouveau');";

        $result = $this->runRouter([
            'page' => 'report_edit',
            'session' => $this->agentSession(),
            'post' => [
                'csrf_token' => 'validtoken123',
                'report_uuid' => $uuid,
                'objet' => 'Objet modifie',
                'description' => 'Description modifiee via routeur',
                'date_evenement' => '2026-01-02',
                'site_id' => '',
            ],
            'db_seed' => $seed,
            'assertions' => [
                'objet_after' => "SELECT objet FROM reports WHERE uuid = '$uuid'",
            ],
        ]);

        $this->assertEquals('Objet modifie', $result['queries']['objet_after'], 'Edit was not saved — same double CSRF-consumption bug class as report_create.');
    }

    // choose_site is NOT covered here: it's handled by a special case in
    // public/index.php (bypasses Router::dispatchPost entirely, see the
    // `if ($page === 'choose_site')` block there), so this router-based
    // harness cannot dispatch it — router_runner.php calls dispatchPost()
    // directly, which finds no route for choose_site. It had the exact
    // same double CSRF-consumption bug (index.php validated once, the
    // handler validated the same token again internally), fixed the same
    // way and verified manually against a real PHP server (see the fix
    // commit), but a proper automated regression test for it would need a
    // separate harness that runs through public/index.php's own dispatch
    // logic rather than the router — not built here; noted as a gap
    // rather than covered with an assertion that can't actually fail.
}
