<?php
/**
 * Page Rendering Integration Test — Application SST DREETS BFC
 *
 * Verifies every page in the router renders without fatal PHP errors,
 * produces valid HTML structure (when rendered with layout), and
 * displays the correct page title from getPageTitle().
 *
 * Uses the in-memory SQLite database from tests/bootstrap.php with
 * minimal test data: one site, one agent, one superviseur, two reports.
 */

use PHPUnit\Framework\TestCase;

class PageRenderingTest extends TestCase
{
    private static bool $bootstrapped = false;
    private static int $siteId = 1;
    private static int $agentUserId = 1;
    private static int $superviseurUserId = 2;
    private static string $reportUuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    private static string $reportUuidTraite = '11111111-2222-3333-4444-555555555555';

    // ═══════════════════════════════════════════════════════════════════════════════
    // Bootstrap
    // ═══════════════════════════════════════════════════════════════════════════════

    public static function setUpBeforeClass(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        // Load application files needed for page rendering
        require_once __DIR__ . '/../../src/config.php';
        require_once __DIR__ . '/../../src/helpers.php';
        require_once __DIR__ . '/../../src/session.php';
        require_once __DIR__ . '/../../src/user_context.php';
        require_once __DIR__ . '/../../src/auth.php';
        require_once __DIR__ . '/../../src/Middleware/require_role.php';
        require_once __DIR__ . '/../../src/Router/Renderer.php';
        require_once __DIR__ . '/../../src/audit.php';
        require_once __DIR__ . '/../../src/Router/routes.php';

        // Seed the in-memory SQLite database with minimal test data
        $pdo = getDB();
        $pdo->exec("INSERT INTO sites (id, code, nom, is_active) VALUES (1, 'UR21', 'UR Test', 1)");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (1, 'test.agent', 'Dupont', 'Jean', 'agent', 1, 1)");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active) VALUES (2, 'test.sup', 'Martin', 'Pierre', 'superviseur', 1, 1)");
        $pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat, is_confidential) VALUES ('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'RSST-25-001', 'rsst', 'Test nouveau', 'Description test', '2025-01-01', 1, 'Dupont', 'Jean', 1, 'nouveau', 1)");
        $pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat, is_confidential) VALUES ('11111111-2222-3333-4444-555555555555', 'RSST-25-002', 'rsst', 'Test traite', 'Description test', '2025-01-01', 1, 'Dupont', 'Jean', 1, 'traite', 1)");

        // Tables created by migrations (not in schema.sql)
        $pdo->exec("CREATE TABLE IF NOT EXISTS report_agents (report_uuid TEXT NOT NULL, user_id INTEGER NOT NULL, PRIMARY KEY (report_uuid, user_id))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS report_agent_invites (id INTEGER PRIMARY KEY AUTOINCREMENT, report_uuid TEXT NOT NULL, email TEXT NOT NULL, token TEXT, confirmed INTEGER DEFAULT 0, created_at TEXT DEFAULT (datetime('now')))");
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════════════

    private function loginAsAgent(): void
    {
        setUserSession([
            'id' => self::$agentUserId,
            'username' => 'test.agent',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'role' => 'agent',
            'site_id' => self::$siteId,
            'is_active' => 1,
        ]);
    }

    private function loginAsSuperviseur(): void
    {
        setUserSession([
            'id' => self::$superviseurUserId,
            'username' => 'test.sup',
            'nom' => 'Martin',
            'prenom' => 'Pierre',
            'role' => 'superviseur',
            'site_id' => self::$siteId,
            'is_active' => 1,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Data providers
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Pages rendered through renderPageWithLayout() — full HTML with header/sidebar/footer.
     *
     * Each entry: [page, role, extraGetParams]
     */
    public static function layoutPageProvider(): array
    {
        return [
            ['home', 'agent', []],
            ['preamble', 'agent', []],
            ['help', 'agent', []],
            ['guide', 'agent', []],
            ['access_denied', 'agent', []],
            ['choose_site', 'agent', []],
            ['report_list', 'agent', ['type' => 'rsst']],
            ['report_create', 'agent', ['type' => 'rsst']],
            ['report_view', 'agent', ['uuid' => self::$reportUuid]],
            ['report_edit', 'agent', ['uuid' => self::$reportUuid]],
            ['report_abandon', 'agent', ['uuid' => self::$reportUuid]],
            ['agent_confirm', 'agent', ['token' => '']],
            ['changelog', 'superviseur', []],
            ['synthesis', 'superviseur', []],
            ['export', 'superviseur', []],
            ['statistics', 'superviseur', []],
            ['settings', 'superviseur', []],
            ['users', 'superviseur', []],
            ['logs', 'superviseur', []],
            ['user_edit', 'superviseur', ['id' => 1]],
            ['user_view', 'superviseur', ['id' => 1]],
            ['site_edit', 'superviseur', ['id' => 1]],
            ['report_respond', 'superviseur', ['uuid' => self::$reportUuid]],
            ['report_reopen', 'superviseur', ['uuid' => self::$reportUuidTraite]],
            ['impersonate', 'superviseur', []],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Tests
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Each layout page must:
     *  1. Not produce any PHP fatal/parse error.
     *  2. Output valid HTML (DOCTYPE, <html>, <title>).
     *  3. Contain the title returned by getPageTitle().
     */
    public function testAllLayoutPagesRenderValidHtml(): void
    {
        $pages = [
            ['home', 'agent', []],
            ['preamble', 'agent', []],
            ['help', 'agent', []],
            ['guide', 'agent', []],
            ['access_denied', 'agent', []],
            ['report_list', 'agent', ['type' => 'rsst']],
            ['report_create', 'agent', ['type' => 'rsst']],
            ['report_view', 'agent', ['uuid' => self::$reportUuid]],
            ['report_edit', 'agent', ['uuid' => self::$reportUuid]],
            ['report_abandon', 'agent', ['uuid' => self::$reportUuid]],
            ['agent_confirm', 'agent', ['token' => '']],
            ['changelog', 'superviseur', []],
            ['synthesis', 'superviseur', []],
            ['export', 'superviseur', []],
            ['statistics', 'superviseur', []],
            ['settings', 'superviseur', []],
            ['users', 'superviseur', []],
            ['logs', 'superviseur', []],
            ['user_edit', 'superviseur', ['id' => 1]],
            ['user_view', 'superviseur', ['id' => 1]],
            ['site_edit', 'superviseur', ['id' => 1]],
            ['report_respond', 'superviseur', ['uuid' => self::$reportUuid]],
            ['report_reopen', 'superviseur', ['uuid' => self::$reportUuidTraite]],
            ['impersonate', 'superviseur', []],
        ];

        foreach ($pages as [$page, $role, $getParams]) {
            // Reset state
            $_SESSION = [];
            $_GET = [];
            $_POST = [];
            $_SERVER['REQUEST_METHOD'] = 'GET';

            // Authenticate
            if ($role === 'superviseur') {
                $this->loginAsSuperviseur();
            } else {
                $this->loginAsAgent();
            }

            // Simulate GET request
            $_GET['page'] = $page;
            foreach ($getParams as $key => $value) {
                $_GET[$key] = $value;
            }

            // Capture rendered output
            ob_start();
            try {
                renderPageWithLayout(getRouter(), $page, 'test-csrf-token');
            } catch (\Throwable $e) {
                ob_end_clean();
                $this->fail("Page '$page' threw an exception: " . $e->getMessage());
            }
            $output = (string) ob_get_clean();

            // 1. No fatal PHP errors (check for actual PHP error patterns, not content text)
            $this->assertStringNotContainsString('<b>Fatal error</b>', $output, "[$page] output contains PHP Fatal error");
            $this->assertStringNotContainsString('Uncaught Error:', $output, "[$page] output contains Uncaught Error");
            $this->assertStringNotContainsString('Parse error:', $output, "[$page] output contains Parse error");
            $this->assertStringNotContainsString('allowed memory size', $output, "[$page] output contains memory exhausted");

            // 1.b. No leaked PHP source code (audit #5/#6 — pages must not show ReportType::Xsrt => 'card--rsst' etc.)
            $this->assertStringNotContainsString('ReportType::', $output, "[$page] output contains leaked PHP source code (ReportType::)");
            $this->assertStringNotContainsString("=> 'card--", $output, "[$page] output contains leaked match() arm");
            $this->assertStringNotContainsString('default => ', $output, "[$page] output contains leaked match default arm");
            $this->assertStringNotContainsString('}; ?>', $output, "[$page] output contains leaked PHP closing bracket+tag");

            // 1.c. No double-escaped HTML entity (audit — sidebar icons stored as
            // '&#12345;' and re-escaped via e()/htmlspecialchars() produced literal
            // '&amp;#12345;' text instead of rendering the emoji. See templates/sidebar.php.
            $this->assertStringNotContainsString('&amp;#', $output, "[$page] output contains a double-escaped HTML entity (regression: sidebar icons must be raw UTF-8 emoji, not '&#...;' strings passed through e())");

            // 2. Valid HTML structure
            $this->assertStringContainsString('<!DOCTYPE html>', $output, "[$page] missing <!DOCTYPE html>");
            $this->assertStringContainsString('<html', $output, "[$page] missing <html> tag");
            $this->assertStringContainsString('<title>', $output, "[$page] missing <title> tag");

            // 3. Title matches getPageTitle()
            $router = getRouter();
            $expectedTitle = $router->getPageTitle($page);
            $escapedTitle = e($expectedTitle);
            $this->assertStringContainsString(
                $escapedTitle,
                $output,
                "[$page] <title> does not contain getPageTitle() value '$expectedTitle'"
            );
        }
    }

    /**
     * Verify that getPageTitle() returns a non-empty string for every valid page.
     */
    public function testGetPageTitleReturnsNonEmptyForAllValidPages(): void
    {
        $router = getRouter();
        foreach ($router->getValidPages() as $page) {
            $title = $router->getPageTitle($page);
            $this->assertNotEmpty($title, "getPageTitle('$page') returns empty string");
        }
    }

    /**
     * Verify that getValidPages() returns the expected page count.
     * This is a guard against accidental page removal.
     */
    public function testGetValidPagesCount(): void
    {
        $router = getRouter();
        $pages = $router->getValidPages();
        $this->assertGreaterThanOrEqual(25, count($pages), 'getValidPages() should contain at least 25 pages');
    }

    /**
     * Verify that every valid page has a corresponding PHP file in pages/.
     * Pages handled entirely by index.php (logout) or handlers (impersonate) are excluded.
     */
    public function testEveryValidPageHasFile(): void
    {
        $pagesDir = __DIR__ . '/../../pages';
        // These pages are handled by index.php or handlers, not by a page file
        $exceptions = ['logout', 'impersonate', 'user_create', 'user_delete', 'user_reactivate', 'smtp_test'];
        $missing = [];

        $router = getRouter();
        foreach ($router->getValidPages() as $page) {
            if (in_array($page, $exceptions, true)) {
                continue;
            }
            $file = $pagesDir . '/' . $page . '.php';
            if (!file_exists($file)) {
                $missing[] = $page;
            }
        }

        $this->assertEmpty(
            $missing,
            'Valid pages without a page file: ' . implode(', ', $missing)
        );
    }

    /**
     * Regression test — templates/report_card.php used to build $canEdit from
     * a naive (array) cast of the ReportData DTO instead of $report->toArray().
     * DTO properties are camelCase (declarantId) but AccessService::canEditReport()
     * expects a snake_case 'declarant_id' key, so the cast silently produced an
     * "Undefined array key" PHP warning in production and $canEdit was always
     * false — the declarant could never see the "Modifier" button on their own
     * editable report.
     */
    public function testReportViewShowsEditButtonForDeclarant(): void
    {
        // self::$agentUserId (1) is the declarant of self::$reportUuid, etat=nouveau (editable).
        $this->loginAsAgent();
        $_GET['page'] = 'report_view';
        $_GET['uuid'] = self::$reportUuid;

        ob_start();
        renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString(
            '>Modifier<',
            $output,
            'The declarant of an editable report must see the "Modifier" button. ' .
            'If this fails, check that report_card.php passes $report->toArray() ' .
            '(not (array) $report) to AccessService::canEditReport().'
        );
    }

    /**
     * Regression test — Audit #79. Same user-facing symptom as
     * testReportViewShowsEditButtonForDeclarant above (declarant never sees
     * "Modifier"), different root cause: ReportListItem — the lighter DTO
     * used by report_list.php's paginated list — never had a declarantId
     * property at all, so its toArray() had no 'declarant_id' key.
     * AccessService::canEditReport() read an undefined array key (PHP
     * warning in prod), (int) null cast to 0, and $isDeclarant was always
     * false. Fixed by adding declarantId to the DTO's constructor and
     * toArray(), and passing $row['declarant_id'] when ReportRepository
     * builds it in findPaginated().
     */
    public function testReportListShowsEditButtonForDeclarant(): void
    {
        // self::$agentUserId (1) is the declarant of self::$reportUuid, etat=nouveau (editable).
        $this->loginAsAgent();
        $_GET['page'] = 'report_list';
        $_GET['type'] = 'rsst';

        ob_start();
        renderPageWithLayout(getRouter(), 'report_list', 'test-csrf-token');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString(
            '>Modifier<',
            $output,
            'The declarant of an editable report must see the "Modifier" button ' .
            'on the report list too. If this fails, check that ReportListItem ' .
            'carries declarantId through to toArray().'
        );
    }
}
