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
use App\DTO\SessionUser;

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
        // Audit #85 — sous ordre aléatoire (Infection), un autre test peut
        // déjà avoir créé un site/user avec ces IDs précis (ex.
        // SessionInvalidationTest utilise aussi site_id=1) — UNIQUE
        // constraint violation sinon. DELETE explicite plutôt que
        // INSERT OR IGNORE : ce fichier a des dizaines de tests qui
        // dépendent des valeurs exactes ('Dupont'/'Jean') — IGNORE
        // garderait silencieusement les données d'un autre test.
        cleanupAllForTest($pdo);
        $pdo->exec("DELETE FROM sites WHERE code = 'UR21'");
        $pdo->exec("INSERT INTO sites (id, code, nom, is_active) VALUES (1, 'UR21', 'UR Test', 1)");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email) VALUES (1, 'test.agent', 'Dupont', 'Jean', 'agent', 1, 1, 'fixture@dreets-bfc.gouv.fr')");
        $pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email) VALUES (2, 'test.sup', 'Martin', 'Pierre', 'superviseur', 1, 1, 'fixture@dreets-bfc.gouv.fr')");
        $pdo->exec("INSERT OR IGNORE INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat, is_confidential) VALUES ('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'RSST-25-001', 'rsst', 'Test nouveau', 'Description test', '2025-01-01', 1, 'Dupont', 'Jean', 1, 'nouveau', 1)");
        $pdo->exec("INSERT OR IGNORE INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, etat, is_confidential) VALUES ('11111111-2222-3333-4444-555555555555', 'RSST-25-002', 'rsst', 'Test traite', 'Description test', '2025-01-01', 1, 'Dupont', 'Jean', 1, 'traite', 1)");

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
        setUserSession(SessionUser::fromArray([
            'id' => self::$agentUserId,
            'username' => 'test.agent',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'role' => 'agent',
            'site_id' => self::$siteId,
            'is_active' => 1,
        ]));
    }

    private function loginAsSuperviseur(): void
    {
        setUserSession(SessionUser::fromArray([
            'id' => self::$superviseurUserId,
            'username' => 'test.sup',
            'nom' => 'Martin',
            'prenom' => 'Pierre',
            'role' => 'superviseur',
            'site_id' => self::$siteId,
            'is_active' => 1,
        ]));
    }

    /**
     * Seed a durable CSA/CHSCT transmission proof in the outbox — same
     * dedup_key shape as OutboxEvent::ReportTransmitted::dedupKey()
     * ("report_transmitted:{uuid}:{recipient}"). The template reads it back via
     * reportTransmissionDate() / EmailOutboxRepository::findReportTransmissionDate().
     */
    private function seedTransmittedOutboxRow(string $reportUuid, string $createdAtUtc): void
    {
        $stmt = getDB()->prepare(
            'INSERT INTO email_outbox (dedup_key, recipient, subject, body, created_at)
             VALUES (:dedup_key, :recipient, :subject, :body, :created_at)'
        );
        $stmt->execute([
            ':dedup_key'  => 'report_transmitted:' . $reportUuid . ':csa.transmission@dreets-bfc.gouv.fr',
            ':recipient'  => 'csa.transmission@dreets-bfc.gouv.fr',
            ':subject'    => 'Signalement transmis',
            ':body'       => '<p>Transmis</p>',
            ':created_at' => $createdAtUtc,
        ]);
    }

    private function clearTransmittedOutboxRow(string $reportUuid): void
    {
        $prefix = 'report_transmitted:' . $reportUuid . ':';
        $stmt = getDB()->prepare('DELETE FROM email_outbox WHERE substr(dedup_key, 1, :len) = :prefix');
        $stmt->execute([':len' => strlen($prefix), ':prefix' => $prefix]);
    }

    /**
     * Link a user to a report as a "agent rattaché" (report_agents). The caller
     * is responsible for clearing the link afterwards (fixture isolation).
     */
    private function linkAgentToReport(string $reportUuid, int $userId): void
    {
        $stmt = getDB()->prepare('INSERT INTO report_agents (report_uuid, user_id) VALUES (:uuid, :user_id)');
        $stmt->execute([':uuid' => $reportUuid, ':user_id' => $userId]);
    }

    /**
     * Remove every "agent rattaché" link for a report — keeps the shared
     * fixture deterministic regardless of test execution order.
     */
    private function clearLinkedAgents(string $reportUuid): void
    {
        $stmt = getDB()->prepare('DELETE FROM report_agents WHERE report_uuid = :uuid');
        $stmt->execute([':uuid' => $reportUuid]);
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
            // Audit #85 — 'changelog' excluded: CHANGELOG.md legitimately
            // documents past refactors in prose using these exact strings
            // as examples (e.g. "ReportType::Rami (plus de ->value)") —
            // real content, not a leak.
            if ($page !== 'changelog') {
                $this->assertStringNotContainsString('ReportType::', $output, "[$page] output contains leaked PHP source code (ReportType::)");
                $this->assertStringNotContainsString("=> 'card--", $output, "[$page] output contains leaked match() arm");
                $this->assertStringNotContainsString('default => ', $output, "[$page] output contains leaked match default arm");
                $this->assertStringNotContainsString('}; ?>', $output, "[$page] output contains leaked PHP closing bracket+tag");
            }

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
        $exceptions = ['logout', 'impersonate', 'user_create', 'user_delete', 'user_reactivate', 'smtp_test', 'outbox_retry', 'report_transmit', 'purge_reports'];
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
     * L'information de confidentialité de la fiche signalement doit refléter
     * la politique réelle d'AccessService::canAccessReport() : un signalement
     * confidentiel est visible par le déclarant, les superviseurs et les
     * membres CSA/CHSCT — pas seulement par les superviseurs.
     *
     * Sans aucun agent rattaché ($linkedAgents vide), la phrase reste complète
     * mais ne doit PAS mentionner « les agents rattachés » : annoncer une
     * audience inexistante est faux. Le libellé du rôle reste configurable
     * (jamais « CHSCT » en dur).
     */
    public function testReportCardConfidentialHintOmitsLinkedAgentsWhenNone(): void
    {
        // Isolation de la fixture : le signalement ne doit porter aucun agent
        // rattaché, quel que soit l'ordre d'exécution des tests.
        $this->clearLinkedAgents(self::$reportUuid);

        // self::$agentUserId (1) est le déclarant du signalement confidentiel.
        $this->loginAsAgent();
        $_GET['page'] = 'report_view';
        $_GET['uuid'] = self::$reportUuid;

        ob_start();
        renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
        $output = (string) ob_get_clean();

        $roleLabel = getRoleLabelShort(\App\Enum\UserRole::Chsct->value);

        $this->assertStringNotContainsString(
            'Seuls les superviseurs peuvent voir ce signalement',
            $output,
            'Le texte faux « Seuls les superviseurs peuvent voir ce signalement » ne doit plus être rendu.'
        );
        $this->assertStringContainsString(
            '(Visible uniquement par le déclarant, les superviseurs et les membres du rôle « ' . $roleLabel . ' »)',
            $output,
            'Sans agent rattaché, la phrase de confidentialité est complète mais sans mention des agents rattachés.'
        );
        $this->assertStringNotContainsString(
            'les agents rattachés',
            $output,
            'Sans agent rattaché, la mention « les agents rattachés » doit être omise.'
        );
    }

    /**
     * Avec au moins un agent rattaché ($linkedAgents non vide), la phrase de
     * confidentialité doit nommer « les agents rattachés » — l'accès réel de
     * ces agents doit être reflété. Le lien est inséré puis retiré (try/finally)
     * pour ne pas contaminer les autres tests.
     */
    public function testReportCardConfidentialHintMentionsLinkedAgentsWhenPresent(): void
    {
        $this->clearLinkedAgents(self::$reportUuid);
        $this->linkAgentToReport(self::$reportUuid, self::$superviseurUserId);

        try {
            $this->loginAsAgent();
            $_GET['page'] = 'report_view';
            $_GET['uuid'] = self::$reportUuid;

            ob_start();
            renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
            $output = (string) ob_get_clean();
        } finally {
            $this->clearLinkedAgents(self::$reportUuid);
        }

        $roleLabel = getRoleLabelShort(\App\Enum\UserRole::Chsct->value);

        $this->assertStringContainsString(
            '(Visible uniquement par le déclarant, les superviseurs, les agents rattachés et les membres du rôle « ' . $roleLabel . ' »)',
            $output,
            'Avec au moins un agent rattaché, la phrase complète nomme « les agents rattachés ».'
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

    /**
     * Passe d'accessibilité AA — « ne plus dépendre de la couleur seule » : sur
     * la liste, l'état et la visibilité sont portés par un libellé textuel et un
     * glyphe non chromatique, jamais par la seule couleur de fond du badge.
     */
    public function testReportListBadgesConveyStateAndVisibilityWithoutColourAlone(): void
    {
        $this->loginAsSuperviseur();
        $_GET['page'] = 'report_list';
        $_GET['type'] = 'rsst';

        ob_start();
        renderPageWithLayout(getRouter(), 'report_list', 'test-csrf-token');
        $output = (string) ob_get_clean();

        // Visibilité confidentielle : glyphe (cadenas) + libellé.
        $this->assertMatchesRegularExpression(
            '/badge--confidential"[^>]*>&#128274;/',
            $output,
            'Le badge de visibilité confidentielle doit porter un glyphe + un libellé.'
        );

        // État : le libellé textuel accompagne la couleur de fond.
        $this->assertMatchesRegularExpression(
            '/badge--(?:nouveau|traite)"[^>]*>\s*(?:Nouveau|Traité)/u',
            $output,
            'Le badge d\'état doit porter son libellé, pas seulement sa couleur.'
        );

        // Le repli « public » (absent du jeu de données) porte lui aussi un
        // glyphe non chromatique dans le template.
        $source = (string) file_get_contents(__DIR__ . '/../../pages/report_list.php');
        $this->assertMatchesRegularExpression(
            '/badge--public"\s*>&#10003;\s*Public/',
            $source,
            'Le badge de visibilité publique doit porter un glyphe + un libellé.'
        );
    }

    /**
     * Feature — création d'un signalement : le composant partagé rend un
     * `<input type="file">` HTML5 natif VISIBLE, associé à son libellé, et un
     * bouton local « Annuler la sélection » (reset ciblé de l'input file côté
     * navigateur), sans faux bouton label.
     *
     * En création, la pièce jointe n'est qu'un fichier temporaire côté
     * navigateur : rien n'est envoyé au serveur tant que le formulaire n'est pas
     * soumis. Le marqueur serveur d'édition (name="remove_attachment") ne doit
     * donc PAS apparaître en création.
     */
    public function testReportCreateFormRendersVisibleNativeFileInputWithTargetedReset(): void
    {
        $this->loginAsAgent();
        $_GET['page'] = 'report_create';
        $_GET['type'] = 'rsst';

        ob_start();
        renderPageWithLayout(getRouter(), 'report_create', 'test-csrf-token');
        $output = (string) ob_get_clean();

        // Input fichier natif visible, associé à son libellé.
        $this->assertStringContainsString('id="attachment"', $output);
        $this->assertStringContainsString('for="attachment"', $output);
        $this->assertStringContainsString('class="file-upload-wrapper__input"', $output);
        $this->assertStringContainsString(
            'accept=".jpg,.jpeg,.png,.gif,.pdf"',
            $output,
            'Le champ fichier doit déclarer les extensions acceptées.'
        );

        // Le bouton de reset est un vrai bouton local, jamais un submit.
        $this->assertSame(
            1,
            preg_match('/<button\b[^>]*\bid="attachment_clear"[^>]*>/', $output, $matches),
            'Le formulaire de création doit contenir le bouton id="attachment_clear".'
        );
        $buttonTag = $matches[0];
        $this->assertStringContainsString(
            'type="button"',
            $buttonTag,
            'Le bouton « Annuler la sélection » doit être un <button type="button"> (pas de submit).'
        );
        $this->assertMatchesRegularExpression(
            '/\shidden(\s|>)/',
            $buttonTag,
            'Le bouton de reset doit être masqué tant qu\'aucun fichier n\'est sélectionné.'
        );
        $this->assertStringContainsString(
            'Annuler la sélection',
            $output,
            'Le libellé « Annuler la sélection » doit être visible.'
        );

        // Pas de marqueur serveur d'édition, pas de reset global.
        $this->assertStringNotContainsString(
            'name="remove_attachment"',
            $output,
            'La suppression à la création est purement client : aucun marqueur remove_attachment ne doit être soumis.'
        );
        $this->assertStringNotContainsString(
            'type="reset"',
            $output,
            'Aucun bouton reset global ne doit être présent (le reset doit rester ciblé sur l\'input file).'
        );
    }

    /**
     * Périmètre : l'édition conserve sa propre logique de suppression de la
     * pièce jointe stockée (case `name="remove_attachment"` traitée au submit).
     * Le bouton local « Annuler la sélection » du composant partagé ne vise que
     * la NOUVELLE sélection et ne coche jamais cette case : il n'y a donc aucun
     * risque de supprimer une pièce jointe déjà stockée en annulant un nouveau
     * choix.
     */
    public function testReportEditFormKeepsStoredAttachmentRemovalAndSharedReset(): void
    {
        $this->loginAsAgent();
        $_GET['page'] = 'report_edit';
        $_GET['uuid'] = self::$reportUuid;

        // Le fixture n'a pas de pièce jointe : on en pose une pour exercer le
        // cas « pièce jointe déjà stockée », puis on restaure.
        $pdo = getDB();
        $pdo->exec("UPDATE reports SET attachment_name = 'preuve.pdf', attachment_mime = 'application/pdf' WHERE uuid = '" . self::$reportUuid . "'");

        $output = '';
        try {
            ob_start();
            renderPageWithLayout(getRouter(), 'report_edit', 'test-csrf-token');
            $output = (string) ob_get_clean();
        } finally {
            $pdo->exec("UPDATE reports SET attachment_name = NULL, attachment_mime = NULL WHERE uuid = '" . self::$reportUuid . "'");
        }

        // Suppression de la pièce jointe stockée : la case serveur reste.
        $this->assertStringContainsString(
            'name="remove_attachment"',
            $output,
            'L\'édition doit conserver la case « Supprimer la pièce jointe actuelle ».'
        );
        $this->assertStringContainsString(
            'Supprimer la pièce jointe actuelle',
            $output,
            'Le libellé de suppression de la pièce jointe stockée doit être présent.'
        );

        // Le reset partagé ne touche que la nouvelle sélection.
        $this->assertStringContainsString(
            'id="attachment_clear"',
            $output,
            'L\'édition doit exposer le même bouton de reset (annuler une nouvelle sélection).'
        );
        $this->assertStringContainsString(
            'Annuler la sélection',
            $output,
            'Le libellé « Annuler la sélection » doit être présent en édition.'
        );
    }

    /**
     * Décision métier (Oracle) — `consent_syndicat` est une CONSIGNE pour le
     * superviseur : il ne « décide » pas, il déclenche l'envoi conformément à
     * l'instruction portée par la case. Le formulaire doit exposer cette nature
     * (aria-describedby + texte « jamais automatique » + déclenchement manuel).
     */
    public function testReportCreateFormFramesConsentAsAdvisoryConsigne(): void
    {
        $this->loginAsAgent();
        $_GET['page'] = 'report_create';
        $_GET['type'] = 'rsst';

        ob_start();
        renderPageWithLayout(getRouter(), 'report_create', 'test-csrf-token');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('id="consent_syndicat"', $output);
        $this->assertStringContainsString(
            'aria-describedby="consent_syndicat_hint"',
            $output,
            'La case de consentement doit être reliée à sa consigne via aria-describedby.'
        );
        $this->assertStringContainsString(
            'id="consent_syndicat_hint"',
            $output,
            'La consigne doit porter l\'identifiant ciblé par aria-describedby.'
        );
        $this->assertStringContainsString(
            'Cette case indique au superviseur que ce signalement doit être transmis par e-mail aux organisations syndicales.',
            $output,
            'La consigne doit présenter la case comme une instruction exécutée par le superviseur.'
        );
        $this->assertStringContainsString(
            "La transmission n'est jamais automatique : le superviseur la déclenche manuellement.",
            $output,
            'La consigne doit préciser que le superviseur déclenche seul l\'envoi.'
        );
        $this->assertStringNotContainsString(
            'le superviseur décide',
            $output,
            'Le superviseur ne décide pas : il déclenche l\'envoi conformément à la consigne.'
        );
    }

    /**
     * L'explication de la case est un tooltip accessible : role="tooltip" relié
     * par aria-describedby, sans style inline ni attribut alt/title sur la case.
     */
    public function testReportCreateConsentHintIsAccessibleTooltip(): void
    {
        $this->loginAsAgent();
        $_GET['page'] = 'report_create';
        $_GET['type'] = 'rsst';

        ob_start();
        renderPageWithLayout(getRouter(), 'report_create', 'test-csrf-token');
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression(
            '/<span\b[^>]*\bid="consent_syndicat_hint"[^>]*\brole="tooltip"[^>]*>/',
            $output,
            'L\'élément d\'aide doit porter role="tooltip".'
        );
        $this->assertMatchesRegularExpression(
            '/<input\b[^>]*\bid="consent_syndicat"[^>]*>/',
            $output,
            'La case de consentement doit être rendue.'
        );

        $inputTag = '';
        if (preg_match('/<input\b[^>]*\bid="consent_syndicat"[^>]*>/', $output, $matches) === 1) {
            $inputTag = $matches[0];
        }
        $this->assertStringNotContainsString('alt=', $inputTag, 'La case ne porte pas d\'attribut alt.');
        $this->assertStringNotContainsString('title=', $inputTag, 'La case ne porte pas d\'attribut title.');
        $this->assertStringNotContainsString('style=', $inputTag, 'La case ne porte pas de style inline.');

        $block = '';
        if (preg_match(
            '/<div class="form-group form-grid__full consent-consigne">.*?<\/div>/s',
            $output,
            $blockMatches
        ) === 1) {
            $block = $blockMatches[0];
        }
        $this->assertNotSame('', $block, 'La consigne doit être encapsulée dans le conteneur tooltip.');
        $this->assertStringNotContainsString('style="', $block, 'Aucun style inline dans le bloc consigne.');
    }

    /**
     * Décision métier (Oracle) — l'accès du CSA/CHSCT est indépendant du
     * consentement, y compris pour un signalement confidentiel : le formulaire
     * de création ne doit plus affirmer que le CSA/CHSCT ne verra le
     * signalement que si le déclarant coche la case de consentement.
     */
    public function testReportCreateConfidentialHintDoesNotGateCsaAccessOnConsent(): void
    {
        $configService = getConfigService();
        $previous = (string) $configService->get('app_report_visibility_rsst', '');
        $configService->set('app_report_visibility_rsst', \App\Enum\VisibilityMode::AgentChoice->value);
        clearConfigCache();

        try {
            $this->loginAsAgent();
            $_GET['page'] = 'report_create';
            $_GET['type'] = 'rsst';

            ob_start();
            renderPageWithLayout(getRouter(), 'report_create', 'test-csrf-token');
            $output = (string) ob_get_clean();
        } finally {
            $configService->set('app_report_visibility_rsst', $previous);
            clearConfigCache();
        }

        $this->assertStringContainsString(
            'les membres du rôle « ' . getRoleLabelShort(\App\Enum\UserRole::Chsct->value) . ' »',
            $output,
            'Le signalement confidentiel reste visible par les membres CSA/CHSCT.'
        );
        $this->assertStringNotContainsString(
            'ne le verront que si vous cochez',
            $output,
            'L\'accès du CSA/CHSCT ne dépend pas de la case de consentement.'
        );
    }

    /**
     * La transmission CSA/CHSCT est une action MANUELLE du superviseur, exposée
     * sur la fiche du signalement ; elle ne doit jamais apparaître pour un agent.
     *
     * Diagnostic CSRF (route/token/formulaire/session) : le formulaire doit
     * poster le jeton de la page (celui que le CsrfMiddleware valide), jamais un
     * jeton régénéré hors session. C'est ce que verrouille l'assertion ci-dessous
     * avec le jeton `test-csrf-token` passé à renderPageWithLayout().
     */
    public function testReportViewShowsCsaTransmissionActionForSupervisor(): void
    {
        $this->loginAsSuperviseur();
        $_GET['page'] = 'report_view';
        $_GET['uuid'] = self::$reportUuid;

        ob_start();
        renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('page=report_transmit', $output);
        $this->assertStringContainsString(
            'Transmettre aux agents du ' . getRoleLabelShort(\App\Enum\UserRole::Chsct->value),
            $output,
            'Le libellé du bouton suit le nom de rôle configurable, jamais un texte figé.'
        );
        $this->assertStringContainsString(
            'class="form-actions__group"',
            $output,
            'Réouvrir et Transmettre partagent le même groupe flex (même ligne, même hauteur).'
        );
        $this->assertStringContainsString(
            'aria-describedby="report-transmit-help"',
            $output,
            'Le bouton de transmission référence explicitement son texte d\'aide.'
        );
        $this->assertStringContainsString(
            'id="report-transmit-help"',
            $output,
            'Le texte d\'aide associé au bouton est bien rendu.'
        );
        $this->assertStringContainsString(
            'envoi manuel',
            $output,
            'L\'explication indique clairement que le clic déclenche un envoi manuel aux agents du rôle.'
        );
        $this->assertMatchesRegularExpression(
            '/<form[^>]*page=report_transmit[^>]*>.*?aria-describedby="report-transmit-help".*?id="report-transmit-help".*?<\/form>/s',
            $output,
            'Bouton et explication sont regroupés dans le même bloc action (le formulaire de transmission).'
        );

        $form = '';
        if (preg_match('/<form[^>]*page=report_transmit[^>]*>.*?<\/form>/s', $output, $matches) === 1) {
            $form = $matches[0];
        }
        $this->assertNotSame('', $form, 'La fiche doit rendre le formulaire de transmission.');

        $this->assertMatchesRegularExpression(
            '/name="csrf_token"\s+value="test-csrf-token"/',
            $form,
            'Le formulaire de transmission doit poster le jeton CSRF de la page (session), sinon « Erreur de sécurité » au clic.'
        );
        $this->assertStringContainsString(
            'name="uuid" value="' . self::$reportUuid . '"',
            $form,
            'Le formulaire de transmission doit cibler le signalement affiché.'
        );
        $this->assertStringContainsString(
            'class="btn btn--transmit"',
            $form,
            'Le bouton de transmission porte sa classe CSS dédiée (action active).'
        );
    }

    /**
     * Le libellé du bouton de transmission doit suivre le nom de rôle
     * configurable (app_role_label_chsct), même long : aucun texte figé
     * « organisations syndicales » ni « CHSCT » en dur. C'est ce libellé qui
     * peut forcer le bouton à passer sur deux lignes — d'où le groupe flex
     * `form-actions__group` qui garde Réouvrir et Transmettre alignés et de
     * même hauteur.
     */
    public function testReportViewTransmissionButtonFollowsCustomLongRoleLabel(): void
    {
        $configService = getConfigService();
        $previous = (string) $configService->get('app_role_label_chsct', '');
        $longLabel = 'Délégué interprofessionnel FS/CSA';
        $configService->set('app_role_label_chsct', $longLabel);
        clearConfigCache();

        try {
            $this->loginAsSuperviseur();
            $_GET['page'] = 'report_view';
            $_GET['uuid'] = self::$reportUuid;

            ob_start();
            renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
            $output = (string) ob_get_clean();
        } finally {
            $configService->set('app_role_label_chsct', $previous);
            clearConfigCache();
        }

        $this->assertStringContainsString(
            'Transmettre aux agents du ' . $longLabel,
            $output,
            'Le libellé long configurable est rendu tel quel dans le bouton.'
        );
        $this->assertStringContainsString(
            'class="form-actions__group"',
            $output,
            'L\'alignement reste assuré par le groupe flex même avec un libellé long.'
        );
    }

    /**
     * Après une transmission réussie, le bouton actif disparaît au profit d'un
     * indicateur non-actionnable : case cochée + désactivée et date de
     * transmission SANS heure (JJ/MM/AAAA), aligné avec Réouvrir via le groupe
     * flex. Aucune seconde transmission ne doit être proposée.
     */
    public function testReportViewShowsTransmittedIndicatorWhenAlreadyTransmitted(): void
    {
        $this->seedTransmittedOutboxRow(self::$reportUuid, '2025-03-15 10:00:00');

        try {
            $this->loginAsSuperviseur();
            $_GET['page'] = 'report_view';
            $_GET['uuid'] = self::$reportUuid;

            ob_start();
            renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
            $output = (string) ob_get_clean();
        } finally {
            $this->clearTransmittedOutboxRow(self::$reportUuid);
        }

        $this->assertStringContainsString(
            'class="transmit-status"',
            $output,
            'Une fois transmis, le bouton actif est remplacé par un indicateur non-actionnable.'
        );
        $this->assertMatchesRegularExpression(
            '/<input type="checkbox" checked disabled/',
            $output,
            'L\'indicateur porte une case cochée et désactivée (jamais actionnable).'
        );
        $this->assertStringContainsString(
            'Transmis aux agents du ' . getRoleLabelShort(\App\Enum\UserRole::Chsct->value) . ' le 15/03/2025',
            $output,
            'Le texte affiche le rôle configurable et la date SANS heure (JJ/MM/AAAA).'
        );
        $this->assertStringNotContainsString(
            'page=report_transmit',
            $output,
            'Aucune seconde transmission n\'est proposée après une transmission réussie.'
        );
        $this->assertStringNotContainsString(
            'report-transmit-help',
            $output,
            'L\'aide « envoie ce signalement » disparaît une fois la transmission faite.'
        );
    }

    /**
     * Tant qu'aucune transmission n'est enregistrée, l'action reste active :
     * le formulaire/bouton est rendu, l'indicateur non-actionnable est absent.
     */
    public function testReportViewShowsActiveTransmitButtonWhenNotTransmitted(): void
    {
        $this->clearTransmittedOutboxRow(self::$reportUuid);

        $this->loginAsSuperviseur();
        $_GET['page'] = 'report_view';
        $_GET['uuid'] = self::$reportUuid;

        ob_start();
        renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString(
            'page=report_transmit',
            $output,
            'Sans transmission enregistrée, l\'action de transmission reste disponible.'
        );
        $this->assertStringContainsString('class="btn btn--transmit"', $output);
        $this->assertStringNotContainsString(
            'class="transmit-status"',
            $output,
            'L\'indicateur non-actionnable n\'apparaît que si une transmission existe.'
        );
        $this->assertStringContainsString(
            'report-transmit-help',
            $output,
            'L\'aide contextuelle reste affichée tant que l\'action est disponible.'
        );
    }

    public function testReportViewHidesCsaTransmissionActionForAgent(): void
    {
        $this->loginAsAgent();
        $_GET['page'] = 'report_view';
        $_GET['uuid'] = self::$reportUuid;

        ob_start();
        renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
        $output = (string) ob_get_clean();

        $this->assertStringNotContainsString(
            'page=report_transmit',
            $output,
            'Seul le superviseur peut déclencher la transmission CSA/CHSCT.'
        );
    }

    /**
     * L'aide d'envoi est un tooltip CSS-only (révélé au survol/focus via
     * public/css/style.css), hors du flux flex : role="tooltip" +
     * aria-describedby, pas de <small> en flux, pas de JS inline, pas de title.
     * C'est ce qui empêche l'aide d'étirer le bloc et de désaligner les boutons
     * de la rangée (Réouvrir / Transmettre).
     */
    public function testReportViewTransmitHelpIsCssOnlyTooltip(): void
    {
        $this->clearTransmittedOutboxRow(self::$reportUuid);

        $this->loginAsSuperviseur();
        $_GET['page'] = 'report_view';
        $_GET['uuid'] = self::$reportUuid;

        ob_start();
        renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression(
            '/<span\b[^>]*\bid="report-transmit-help"[^>]*\bclass="tooltip"[^>]*\brole="tooltip"[^>]*>/',
            $output,
            'L\'aide d\'envoi doit être un tooltip (role="tooltip", classe .tooltip).'
        );
        $this->assertStringNotContainsString(
            'report-transmit__help',
            $output,
            'L\'ancienne aide en flux (.report-transmit__help) ne doit plus être rendue.'
        );
        $this->assertStringNotContainsString(
            '<small id="report-transmit-help"',
            $output,
            'L\'aide ne doit plus être un <small> qui participe au flux flex.'
        );

        $helpTag = '';
        if (preg_match('/<span\b[^>]*\bid="report-transmit-help"[^>]*>/', $output, $matches) === 1) {
            $helpTag = $matches[0];
        }
        $this->assertNotSame('', $helpTag, 'L\'élément d\'aide doit être rendu.');
        $this->assertStringNotContainsString('style=', $helpTag, 'Le tooltip ne porte pas de style inline.');
        $this->assertStringNotContainsString('title=', $helpTag, 'Le tooltip n\'utilise pas title (aria-describedby suffit).');
        $this->assertStringNotContainsString('onmouseover', $helpTag, 'Le tooltip ne doit pas embarquer de JS inline.');

        $buttonTag = '';
        if (preg_match('/<button\b[^>]*\bclass="btn btn--transmit"[^>]*>/', $output, $matches) === 1) {
            $buttonTag = $matches[0];
        }
        $this->assertNotSame('', $buttonTag, 'Le bouton de transmission doit être rendu.');
        $this->assertStringContainsString('aria-describedby="report-transmit-help"', $buttonTag);
        $this->assertStringNotContainsString('onmouseover', $buttonTag, 'La révélation doit être purement CSS, sans JS inline.');
        $this->assertStringNotContainsString('onfocus', $buttonTag, 'La révélation doit être purement CSS, sans JS inline.');
    }

    /**
     * Sur report_list, le bouton « Filtrer » doit s'aligner sur le bas des
     * contrôles (label au-dessus du champ), comme sur statistics/synthesis, et
     * non rester centré dans le flux — ce qui le faisait « flotter » au-dessus
     * des inputs.
     */
    public function testReportListFilterButtonAlignsWithControls(): void
    {
        $this->loginAsAgent();
        $_GET['page'] = 'report_list';
        $_GET['type'] = 'rsst';

        ob_start();
        renderPageWithLayout(getRouter(), 'report_list', 'test-csrf-token');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('class="filter-bar"', $output);
        $this->assertMatchesRegularExpression(
            '/<form\b[^>]*\bclass="flex flex-wrap gap-4 items-center w-full"[^>]*>/',
            $output,
            'Le formulaire de filtre conserve sa structure de contrôles.'
        );
        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*\bclass="btn btn--primary align-self-end"[^>]*>\s*Filtrer\s*<\/button>/',
            $output,
            'Le bouton Filtrer doit être aligné sur le bas des contrôles (.align-self-end), comme sur les autres pages.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Consentement transmission syndicale — paramètre d'affichage
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Renseigne la clé d'affichage du consentement. `null` remet l'état par
     * défaut (clé absente → activé, rétrocompatible).
     */
    private function setConsentSyndicatEnabled(?bool $enabled): void
    {
        $pdo = getDB();
        if ($enabled === null) {
            $pdo->exec("DELETE FROM config_app WHERE cle = 'app_consent_syndicat_enabled'");
        } else {
            $pdo->exec(
                "INSERT OR REPLACE INTO config_app (cle, valeur, type, categorie, libelle, modifiable) "
                . "VALUES ('app_consent_syndicat_enabled', '" . ($enabled ? '1' : '0') . "', 'text', 'app', '', 1)"
            );
        }
        clearConfigCache();
    }

    public function testConsentSyndicatConfigDefaultIsEnabled(): void
    {
        $this->setConsentSyndicatEnabled(null);
        $this->assertTrue(
            getConfigService()->isConsentSyndicatEnabled(),
            'Sans clé config, le consentement syndical est affiché (rétrocompatible).'
        );

        $this->setConsentSyndicatEnabled(false);
        try {
            $this->assertFalse(getConfigService()->isConsentSyndicatEnabled());
        } finally {
            $this->setConsentSyndicatEnabled(null);
        }
    }

    public function testConsentSyndicatDisabledHidesFormCheckboxButKeepsStoredValue(): void
    {
        $this->setConsentSyndicatEnabled(false);
        try {
            $this->loginAsAgent();
            $_GET['page'] = 'report_create';
            $_GET['type'] = 'rsst';

            ob_start();
            renderPageWithLayout(getRouter(), 'report_create', 'test-csrf-token');
            $output = (string) ob_get_clean();
        } finally {
            $this->setConsentSyndicatEnabled(null);
        }

        $this->assertStringNotContainsString('id="consent_syndicat"', $output, 'La case est masquée quand le réglage est désactivé.');
        $this->assertStringNotContainsString('consent_syndicat_hint', $output, 'Le libellé/tooltip de consentement est masqué.');
        $this->assertStringNotContainsString("J'accepte que mon signalement soit transmis", $output, 'Le texte de la case est masqué.');
        $this->assertStringContainsString('name="consent_syndicat"', $output, 'Un champ caché préserve la valeur (jamais réinitialisée en silence).');
    }

    public function testConsentSyndicatDisabledHidesTransmissionRowOnReportView(): void
    {
        // Activé (défaut) : la ligne de transmission est rendue.
        $this->setConsentSyndicatEnabled(null);
        $this->loginAsSuperviseur();
        $_GET['page'] = 'report_view';
        $_GET['uuid'] = self::$reportUuid;

        ob_start();
        renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
        $enabled = (string) ob_get_clean();

        $this->assertStringContainsString(transmissionLabel(), $enabled, 'La ligne « Transmission » est affichée par défaut.');

        // Désactivé : la ligne disparaît de la fiche.
        $this->setConsentSyndicatEnabled(false);
        try {
            ob_start();
            renderPageWithLayout(getRouter(), 'report_view', 'test-csrf-token');
            $disabled = (string) ob_get_clean();
        } finally {
            $this->setConsentSyndicatEnabled(null);
        }

        $this->assertStringNotContainsString(transmissionLabel(), $disabled, 'La ligne « Transmission » est masquée quand le réglage est désactivé.');
        $this->assertStringNotContainsString('❌ Refusée', $disabled);
        $this->assertStringNotContainsString('✅ Acceptée', $disabled);
    }

    public function testConsentSyndicatDisabledHidesExportColumn(): void
    {
        $service = new \App\Services\ExportService(getConfigService());

        $this->setConsentSyndicatEnabled(null);
        $enabledHeaders = $service->buildHeaders(false);
        $this->assertContains(transmissionLabel(), $enabledHeaders, 'La colonne d\'export est présente par défaut.');

        $this->setConsentSyndicatEnabled(false);
        try {
            $disabledHeaders = $service->buildHeaders(false);
        } finally {
            $this->setConsentSyndicatEnabled(null);
        }

        $this->assertNotContains(transmissionLabel(), $disabledHeaders, 'La colonne d\'export est masquée quand le réglage est désactivé.');
        $this->assertCount(
            count($enabledHeaders) - 1,
            $disabledHeaders,
            'Seule la colonne « Transmission » est retirée (alignement en-têtes/valeurs conservé).'
        );
    }
}
