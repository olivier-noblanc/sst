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
            'btnLabel' => 'Déposer', 'btnUrl' => '/create', 'listUrl' => '/list',
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
            'btnLabel' => 'Btn', 'btnUrl' => '/a', 'listUrl' => '/b',
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
            'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b',
        ];

        $html = renderRegistryCard($card, 'home-action--large');

        $this->assertStringContainsString('home-action--large', $html);
    }

    public function testRenderRegistryCardEscapesHtml(): void
    {
        $card = [
            'type' => 'rsst', 'title' => '<script>alert(1)</script>', 'subtitle' => 'S',
            'desc' => 'D', 'count' => 0,
            'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b',
        ];

        $html = renderRegistryCard($card);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ─── renderRegistryCards() ──────────────────────────────────────────────

    public function testRenderRegistryCardsCompact(): void
    {
        $cards = [
            ['type' => 'rsst', 'title' => 'RSST', 'subtitle' => 'R', 'desc' => 'D', 'count' => 0, 'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b'],
        ];

        $html = renderRegistryCards($cards, 'compact');

        $this->assertStringContainsString('class="registry-cards"', $html);
        $this->assertStringNotContainsString('registry-cards--large', $html);
        $this->assertStringContainsString('RSST', $html);
    }

    public function testRenderRegistryCardsLarge(): void
    {
        $cards = [
            ['type' => 'rsst', 'title' => 'RSST', 'subtitle' => 'R', 'desc' => 'D', 'count' => 0, 'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b'],
        ];

        $html = renderRegistryCards($cards, 'large');

        $this->assertStringContainsString('registry-cards--large', $html);
        $this->assertStringContainsString('home-action--large', $html);
    }

    public function testRenderRegistryCardsMultiple(): void
    {
        $cards = [
            ['type' => 'rsst', 'title' => 'RSST', 'subtitle' => 'R', 'desc' => 'D', 'count' => 0, 'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b'],
            ['type' => 'rami', 'title' => 'RAMI', 'subtitle' => 'R', 'desc' => 'D', 'count' => 0, 'btnLabel' => 'B', 'btnUrl' => '/a', 'listUrl' => '/b'],
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
        $requiredKeys = ['type', 'title', 'subtitle', 'desc', 'count', 'btnLabel', 'btnUrl', 'listUrl'];

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
