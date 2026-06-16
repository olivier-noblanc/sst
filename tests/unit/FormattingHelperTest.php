<?php
/**
 * Formatting Helper Unit Tests — Application SST DREETS BFC
 *
 * Tests formatting functions from src/helpers/formatting.php:
 * - formatDateTimeFR()
 * - getRegistryBadgeClass()
 * - renderBreadcrumb()
 * - getNextSequence()
 * - e() (HTML escaping edge cases)
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/helpers/formatting.php';

class FormattingHelperTest extends TestCase
{
    // ─── formatDateTimeFR ──────────────────────────────────────────────────

    public function testFormatDateTimeFRWithFullDatetime(): void
    {
        $result = formatDateTimeFR('2025-06-15 14:30:00');
        $this->assertStringContainsString('15/06/2025', $result);
        $this->assertStringContainsString('14:30', $result);
    }

    public function testFormatDateTimeFRWithISOFormat(): void
    {
        $result = formatDateTimeFR('2025-06-15T14:30:00');
        $this->assertStringContainsString('15/06/2025', $result);
        $this->assertStringContainsString('14:30', $result);
    }

    public function testFormatDateTimeFRReturnsDashForEmpty(): void
    {
        $this->assertEquals('—', formatDateTimeFR(''));
        $this->assertEquals('—', formatDateTimeFR(null));
    }

    public function testFormatDateTimeFRReturnsEscapedForInvalid(): void
    {
        $result = formatDateTimeFR('not-a-date');
        $this->assertEquals('not-a-date', $result);
    }

    // ─── getRegistryBadgeClass ──────────────────────────────────────────────

    public function testGetRegistryBadgeClassRsst(): void
    {
        $this->assertEquals('badge--rsst', getRegistryBadgeClass('rsst'));
    }

    public function testGetRegistryBadgeClassRami(): void
    {
        $this->assertEquals('badge--rami', getRegistryBadgeClass('rami'));
    }

    public function testGetRegistryBadgeClassDgi(): void
    {
        $this->assertEquals('badge--dgi', getRegistryBadgeClass('dgi'));
    }

    public function testGetRegistryBadgeClassUnknownReturnsEmpty(): void
    {
        $this->assertEquals('', getRegistryBadgeClass('unknown'));
    }

    // ─── renderBreadcrumb ───────────────────────────────────────────────────

    public function testRenderBreadcrumbWithSingleItem(): void
    {
        $html = renderBreadcrumb([['label' => 'Accueil']]);
        $this->assertStringContainsString('<nav class="breadcrumb"', $html);
        $this->assertStringContainsString('<span class="breadcrumb__current">Accueil</span>', $html);
    }

    public function testRenderBreadcrumbWithMultipleItems(): void
    {
        $html = renderBreadcrumb([
            ['url' => '/home', 'label' => 'Accueil'],
            ['label' => 'Signalements'],
        ]);
        $this->assertStringContainsString('<a href="/home"', $html);
        $this->assertStringContainsString('Accueil</a>', $html);
        $this->assertStringContainsString('<span class="breadcrumb__separator">/</span>', $html);
        $this->assertStringContainsString('<span class="breadcrumb__current">Signalements</span>', $html);
    }

    public function testRenderBreadcrumbEscapesHtmlInLabels(): void
    {
        $html = renderBreadcrumb([['label' => '<script>alert("xss")</script>']]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderBreadcrumbEscapesHtmlInUrls(): void
    {
        $html = renderBreadcrumb([
            ['url' => '"><script>alert(1)</script>', 'label' => 'Test'],
            ['label' => 'Fin'],
        ]);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderBreadcrumbEmptyArray(): void
    {
        $html = renderBreadcrumb([]);
        $this->assertStringContainsString('<nav class="breadcrumb"', $html);
        $this->assertStringContainsString('</nav>', $html);
    }

    // ─── e() edge cases ─────────────────────────────────────────────────────

    public function testEscapingNullReturnsEmpty(): void
    {
        $this->assertEquals('', e(null));
    }

    public function testEscapingSpecialChars(): void
    {
        $this->assertEquals('&amp;', e('&'));
        $this->assertEquals('&lt;', e('<'));
        $this->assertEquals('&gt;', e('>'));
        $this->assertEquals('&quot;', e('"'));
        $this->assertEquals('&#039;', e("'"));
    }

    public function testEscapingFrenchAccents(): void
    {
        // Accents should NOT be escaped (UTF-8, not HTML entities)
        $this->assertEquals('éèêëàâùûüôîç', e('éèêëàâùûüôîç'));
    }

    // ─── getNextSequence (DB-dependent) ─────────────────────────────────────

    public function testGetNextSequenceStartsAtOne(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM report_sequence');

        $seq = getNextSequence($pdo, 'rsst', 2025);
        $this->assertEquals(1, $seq);
    }

    public function testGetNextSequenceIncrements(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM report_sequence');

        $seq1 = getNextSequence($pdo, 'rami', 2025);
        $seq2 = getNextSequence($pdo, 'rami', 2025);
        $seq3 = getNextSequence($pdo, 'rami', 2025);

        $this->assertEquals(1, $seq1);
        $this->assertEquals(2, $seq2);
        $this->assertEquals(3, $seq3);
    }

    public function testGetNextSequencePerTypeAndYear(): void
    {
        $pdo = getDB();
        $pdo->exec('DELETE FROM report_sequence');

        $seq1 = getNextSequence($pdo, 'rsst', 2025);
        $seq2 = getNextSequence($pdo, 'dgi', 2025);
        $seq3 = getNextSequence($pdo, 'rsst', 2026);

        $this->assertEquals(1, $seq1);
        $this->assertEquals(1, $seq2);
        $this->assertEquals(1, $seq3);
    }
}
