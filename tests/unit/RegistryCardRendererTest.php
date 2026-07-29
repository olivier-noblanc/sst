<?php
/**
 * Registry Card Renderer Tests — Application SST DREETS BFC
 *
 * Tests for src/helpers/registry_card_renderer.php.
 * These tests would have caught the buildRegistryCards() fatal error
 * if helpers.php hadn't loaded the file — because the functions
 * wouldn't be callable even in the test bootstrap.
 */

use PHPUnit\Framework\TestCase;

class RegistryCardRendererTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        // Audit #80 — buildRegistryCards() no longer takes parameters; it
        // reads registries.is_enabled + real report counts from the DB.
        // Start every test from a known-clean state: all 3 system registries
        // enabled, no reports, no custom registries from other test classes.
        reseedDefaultRegistries($this->pdo);
        $this->pdo->exec("DELETE FROM registries WHERE code NOT IN ('rsst', 'rami', 'dgi')");
        foreach (['rsst', 'rami', 'dgi'] as $code) {
            $this->setRegistryEnabled($code, true);
        }
        $this->pdo->exec("INSERT OR IGNORE INTO sites (id, code, nom) VALUES (9001, 'TEST-RCR', 'Site Test RCR')");
        $this->pdo->exec("INSERT OR IGNORE INTO users (id, username, nom, prenom, role, site_id) VALUES (9001, 'test.rcr', 'Test', 'RCR', 'agent', 9001)");
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        $configService = \getConfigService();
        $configService->set('app_report_visibility_rsst', 'public');
        $configService->clearCache();
        // getDB() is a process-wide singleton shared by the whole PHPUnit
        // run (see tests/bootstrap.php) — every other test class that
        // touches `registries` expects rsst/rami/dgi enabled by default.
        // Restore it, don't just set it at the start of THIS test.
        foreach (['rsst', 'rami', 'dgi'] as $code) {
            $this->setRegistryEnabled($code, true);
        }
        cleanupAllForTest($this->pdo);
    }

    private function setRegistryEnabled(string $code, bool $enabled): void
    {
        $registry = \App\Repository\RegistryRepository::instance()->findByCode($code);
        if ($registry !== null) {
            \App\Repository\RegistryRepository::instance()->toggleEnabled((int) $registry['id'], $enabled);
        }
    }

    /** Seeds $count active, public (non-confidential) reports of $type at the test site. */
    private function seedReports(string $type, int $count): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement,
                declarant_id, declarant_nom, declarant_prenom, site_id, etat, is_confidential)
            VALUES (:uuid, :reference, :type, 'Test', 'Test', '2026-01-01', 9001, 'RCR', 'Test', 9001, 'nouveau', 0)
        ");
        for ($i = 0; $i < $count; $i++) {
            $stmt->execute([
                ':uuid' => 'test-rcr-' . $type . '-' . $i . '-' . uniqid(),
                ':reference' => strtoupper($type) . '-TEST-' . $i . '-' . uniqid(),
                ':type' => $type,
            ]);
        }
    }

    // ─── getRegistryIcon() ──────────────────────────────────────────────────

    public function testGetRegistryIconRsst(): void
    {
        $this->assertSame('📋', getRegistryIcon('rsst'));
    }

    public function testGetRegistryIconRami(): void
    {
        $this->assertSame('🚨', getRegistryIcon('rami'));
    }

    public function testGetRegistryIconDgi(): void
    {
        $this->assertSame('🔴', getRegistryIcon('dgi'));
    }

    public function testGetRegistryIconDefault(): void
    {
        $this->assertSame('📋', getRegistryIcon('unknown'));
    }

    // ─── renderRegistryCard() ───────────────────────────────────────────────

    public function testRenderRegistryCardContainsAllElements(): void
    {
        $card = [
            'type' => 'rsst', 'title' => 'Registre RSST', 'subtitle' => 'RSST',
            'desc' => 'Description test', 'count' => 5,
            'btnLabel' => 'Déposer', 'btnUrl' => '/create', 'listUrl' => '/list', 'listLabel' => 'Voir les signalements',
        ];

        $html = renderRegistryCard($card);

        $this->assertStringContainsString('registry-card registry-card--rsst', $html);
        $this->assertStringContainsString('registry-card__icon', $html);
        $this->assertStringContainsString('📋', $html);
        $this->assertStringContainsString('Registre RSST', $html);
        $this->assertStringContainsString('5 signalements enregistrés', $html);
        $this->assertStringContainsString('href="/create"', $html);
        $this->assertStringContainsString('href="/list"', $html);
    }

    public function testRenderRegistryCardSingularCount(): void
    {
        $card = [
            'type' => 'rami', 'title' => 'Test', 'subtitle' => 'T',
            'desc' => 'Desc', 'count' => 1,
            'btnLabel' => 'Btn', 'btnUrl' => '/a', 'listUrl' => '/b', 'listLabel' => 'Voir les signalements',
        ];

        $html = renderRegistryCard($card);

        $this->assertStringContainsString('1 signalement enregistré', $html);
        // No plural 's' on count label
        $this->assertStringNotContainsString('1 signalements', $html);
        $this->assertStringNotContainsString('1 enregistrés', $html);
    }

    public function testRenderRegistryCardExtraClass(): void
    {
        $card = [
            'type' => 'dgi', 'title' => 'T', 'subtitle' => 'S',
            'desc' => 'D', 'count' => 0,
            'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b', 'listLabel' => 'Voir les signalements',
        ];

        $html = renderRegistryCard($card, 'home-action--large');

        $this->assertStringContainsString('home-action--large', $html);
    }

    /**
     * Regression test — renderRegistryCard() used to build the card's CSS class
     * from the registry's own code ('registry-card--{code}') instead of its
     * configured color_theme. For the 3 system registries (rsst/rami/dgi) the
     * code happens to match a theme, hiding the bug. For any custom registry
     * (code != theme, e.g. code 'incident-electrique' with theme 'violet'
     * chosen in the admin color picker), the generated class didn't exist in
     * CSS and no color was applied on the home page.
     */
    public function testRenderRegistryCardUsesCardClassNotType(): void
    {
        $card = [
            'type' => 'incident-electrique', 'cardClass' => 'registry-card--violet',
            'title' => 'Incident électrique', 'subtitle' => 'IE',
            'desc' => 'D', 'count' => 0,
            'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b', 'listLabel' => 'Voir les signalements',
        ];

        $html = renderRegistryCard($card);

        $this->assertStringContainsString('registry-card registry-card--violet', $html);
        $this->assertStringNotContainsString('registry-card--incident-electrique', $html);
    }

    public function testRenderRegistryCardEscapesHtml(): void
    {
        $card = [
            'type' => 'rsst', 'title' => '<script>alert(1)</script>', 'subtitle' => 'S',
            'desc' => 'D', 'count' => 0,
            'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b', 'listLabel' => 'Voir les signalements',
        ];

        $html = renderRegistryCard($card);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ─── renderRegistryCards() ──────────────────────────────────────────────

    public function testRenderRegistryCardsCompact(): void
    {
        $cards = [
            ['type' => 'rsst', 'title' => 'RSST', 'subtitle' => 'R', 'desc' => 'D', 'count' => 0, 'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b', 'listLabel' => 'Voir les signalements'],
        ];

        $html = renderRegistryCards($cards, 'compact');

        $this->assertStringContainsString('class="registry-cards"', $html);
        $this->assertStringNotContainsString('registry-cards--large', $html);
        $this->assertStringContainsString('RSST', $html);
    }

    public function testRenderRegistryCardsLarge(): void
    {
        $cards = [
            ['type' => 'rsst', 'title' => 'RSST', 'subtitle' => 'R', 'desc' => 'D', 'count' => 0, 'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b', 'listLabel' => 'Voir les signalements'],
        ];

        $html = renderRegistryCards($cards, 'large');

        $this->assertStringContainsString('registry-cards--large', $html);
        $this->assertStringContainsString('home-action--large', $html);
    }

    public function testRenderRegistryCardsMultiple(): void
    {
        $cards = [
            ['type' => 'rsst', 'title' => 'RSST', 'subtitle' => 'R', 'desc' => 'D', 'count' => 0, 'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b', 'listLabel' => 'Voir les signalements'],
            ['type' => 'rami', 'title' => 'RAMI', 'subtitle' => 'R', 'desc' => 'D', 'count' => 0, 'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b', 'listLabel' => 'Voir les signalements'],
        ];

        $html = renderRegistryCards($cards);

        $this->assertStringContainsString('RSST', $html);
        $this->assertStringContainsString('RAMI', $html);
    }

    public function testRenderRegistryCardsEmpty(): void
    {
        $html = renderRegistryCards([]);

        $this->assertStringContainsString('registry-cards', $html);
        // Empty grid still renders the wrapper
        $this->assertStringContainsString('</div>', $html);
    }

    // ─── buildRegistryCards() ───────────────────────────────────────────────

    public function testBuildRegistryCardsRsstOnly(): void
    {
        $this->setRegistryEnabled('rami', false);
        $this->setRegistryEnabled('dgi', false);
        $this->seedReports('rsst', 3);

        $cards = buildRegistryCards();

        $this->assertCount(1, $cards);
        $this->assertSame('rsst', $cards[0]['type']);
        $this->assertSame(3, $cards[0]['count']);
    }

    public function testBuildRegistryCardsAllEnabled(): void
    {
        $this->seedReports('rsst', 1);
        $this->seedReports('rami', 2);
        $this->seedReports('dgi', 3);

        $cards = buildRegistryCards();

        $this->assertCount(3, $cards);
        $this->assertSame('rsst', $cards[0]['type']);
        $this->assertSame('rami', $cards[1]['type']);
        $this->assertSame('dgi', $cards[2]['type']);
    }

    // ─── listLabel ("Voir mes signalements" vs "Voir les signalements") ────

    public function testListLabelIsMineForAgentInConfidentialMode(): void
    {
        $configService = \getConfigService();
        $configService->set('app_report_visibility_rsst', 'confidential');
        $configService->clearCache();
        $_SESSION['user'] = ['role' => ROLE_AGENT];
        $this->seedReports('rsst', 1);

        $cards = buildRegistryCards();

        $this->assertSame('Voir mes signalements', $cards[0]['listLabel'], 'An agent restricted to their own reports (confidential mode) must see "Voir mes signalements", not "Voir les signalements".');
    }

    public function testListLabelIsAllForAgentInPublicMode(): void
    {
        $configService = \getConfigService();
        $configService->set('app_report_visibility_rsst', 'public');
        $configService->clearCache();
        $_SESSION['user'] = ['role' => ROLE_AGENT];
        $this->seedReports('rsst', 1);

        $cards = buildRegistryCards();

        $this->assertSame('Voir les signalements', $cards[0]['listLabel']);
    }

    public function testListLabelIsAllForSuperviseurEvenInConfidentialMode(): void
    {
        // A superviseur sees every report regardless of the agent-facing
        // visibility config — the label must reflect what THIS user sees.
        $configService = \getConfigService();
        $configService->set('app_report_visibility_rsst', 'confidential');
        $configService->clearCache();
        $_SESSION['user'] = ['role' => ROLE_SUPERVISEUR];
        $this->seedReports('rsst', 1);

        $cards = buildRegistryCards();

        $this->assertSame('Voir les signalements', $cards[0]['listLabel']);
    }

    public function testBuildRegistryCardsRamiOnly(): void
    {
        // "RamiOnly" (original name) meant: rsst (always on) + rami enabled,
        // dgi disabled — matching the old buildRegistryCards(0, 5, 0, true, false).
        $this->setRegistryEnabled('dgi', false);
        $this->seedReports('rami', 5);

        $cards = buildRegistryCards();

        $this->assertCount(2, $cards);
        $this->assertSame('rsst', $cards[0]['type']);
        $this->assertSame('rami', $cards[1]['type']);
    }

    public function testBuildRegistryCardsDgiOnly(): void
    {
        $this->setRegistryEnabled('rami', false);
        $this->seedReports('dgi', 7);

        $cards = buildRegistryCards();

        $this->assertCount(2, $cards);
        $this->assertSame('rsst', $cards[0]['type']);
        $this->assertSame('dgi', $cards[1]['type']);
    }

    public function testBuildRegistryCardsPreservesCounts(): void
    {
        $this->seedReports('rsst', 10);
        $this->seedReports('rami', 20);
        $this->seedReports('dgi', 30);

        $cards = buildRegistryCards();

        $this->assertSame(10, $cards[0]['count']);
        $this->assertSame(20, $cards[1]['count']);
        $this->assertSame(30, $cards[2]['count']);
    }

    public function testBuildRegistryCardsHasRequiredKeys(): void
    {
        $this->seedReports('rsst', 1);
        $cards = buildRegistryCards();
        $requiredKeys = ['type', 'cardClass', 'title', 'subtitle', 'desc', 'count', 'btnLabel', 'btnUrl', 'listUrl', 'listLabel'];

        foreach ($cards as $card) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $card, "Missing key '$key' in card '{$card['type']}'");
            }
        }
    }

    public function testBuildRegistryCardsIsCallable(): void
    {
        // This is the EXACT test that would have caught the prod bug:
        // if registry_card_renderer.php wasn't loaded, this would fail
        // with "Call to undefined function buildRegistryCards()"
        $this->assertTrue(function_exists('buildRegistryCards'));
        $this->assertTrue(function_exists('renderRegistryCards'));
        $this->assertTrue(function_exists('renderRegistryCard'));
        $this->assertTrue(function_exists('getRegistryIcon'));
    }

    // ─── Full pipeline: build → render ──────────────────────────────────────

    public function testBuildAndRenderPipeline(): void
    {
        $this->seedReports('rsst', 5);
        $this->seedReports('rami', 3);
        $this->seedReports('dgi', 1);

        $cards = buildRegistryCards();
        $html = renderRegistryCards($cards, 'large');

        $this->assertStringContainsString('registry-cards--large', $html);
        $this->assertStringContainsString('5 signalements enregistrés', $html);
        $this->assertStringContainsString('3 signalements enregistrés', $html);
        $this->assertStringContainsString('1 signalement enregistré', $html);
    }
}
