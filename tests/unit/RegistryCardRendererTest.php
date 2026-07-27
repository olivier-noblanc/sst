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
    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        $configService = \getConfigService();
        $configService->set('app_report_visibility_rsst', 'public');
        $configService->clearCache();
    }

    // ─── getRegistryIcon() ──────────────────────────────────────────────────

    public function testGetRegistryIconRsst(): void
    {
        $this->assertSame('📋', getRegistryIcon('rsst'));
    }

    public function testGetRegistryIconRami(): void
    {
        $this->assertSame('⚠️', getRegistryIcon('rami'));
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
        $cards = buildRegistryCards(3, 0, 0, false, false);

        $this->assertCount(1, $cards);
        $this->assertSame('rsst', $cards[0]['type']);
        $this->assertSame(3, $cards[0]['count']);
    }

    public function testBuildRegistryCardsAllEnabled(): void
    {
        $cards = buildRegistryCards(1, 2, 3, true, true);

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

        $cards = buildRegistryCards(1, 0, 0, false, false);

        $this->assertSame('Voir mes signalements', $cards[0]['listLabel'], 'An agent restricted to their own reports (confidential mode) must see "Voir mes signalements", not "Voir les signalements".');
    }

    public function testListLabelIsAllForAgentInPublicMode(): void
    {
        $configService = \getConfigService();
        $configService->set('app_report_visibility_rsst', 'public');
        $configService->clearCache();
        $_SESSION['user'] = ['role' => ROLE_AGENT];

        $cards = buildRegistryCards(1, 0, 0, false, false);

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

        $cards = buildRegistryCards(1, 0, 0, false, false);

        $this->assertSame('Voir les signalements', $cards[0]['listLabel']);
    }

    public function testBuildRegistryCardsRamiOnly(): void
    {
        $cards = buildRegistryCards(0, 5, 0, true, false);

        $this->assertCount(2, $cards);
        $this->assertSame('rsst', $cards[0]['type']);
        $this->assertSame('rami', $cards[1]['type']);
    }

    public function testBuildRegistryCardsDgiOnly(): void
    {
        $cards = buildRegistryCards(0, 0, 7, false, true);

        $this->assertCount(2, $cards);
        $this->assertSame('rsst', $cards[0]['type']);
        $this->assertSame('dgi', $cards[1]['type']);
    }

    public function testBuildRegistryCardsPreservesCounts(): void
    {
        $cards = buildRegistryCards(10, 20, 30, true, true);

        $this->assertSame(10, $cards[0]['count']);
        $this->assertSame(20, $cards[1]['count']);
        $this->assertSame(30, $cards[2]['count']);
    }

    public function testBuildRegistryCardsHasRequiredKeys(): void
    {
        $cards = buildRegistryCards(0, 0, 0, true, true);
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
        $cards = buildRegistryCards(5, 3, 1, true, true);
        $html = renderRegistryCards($cards, 'large');

        $this->assertStringContainsString('registry-cards--large', $html);
        $this->assertStringContainsString('5 signalements enregistrés', $html);
        $this->assertStringContainsString('3 signalements enregistrés', $html);
        $this->assertStringContainsString('1 signalement enregistré', $html);
    }
}
