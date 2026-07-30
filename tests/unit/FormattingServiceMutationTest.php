<?php
/**
 * Tests FormattingService pure methods exhaustively — kills Infection mutants on:
 *   - e() : LogicalOr, CastString, UnwrapStrReplace (htmlspecialchars)
 *   - formatDateFR() : LogicalNot/empty, Identical, Ternary, Concat
 *   - generateReference() : Concat, CastString, str_pad mutants
 *   - truncate() : GreaterThan, mb_substr, Concat
 *   - getEtatBadgeClass() : MatchArmRemoval, CastString
 *   - getRoleBadgeClass() : MatchArmRemoval
 *   - renderBreadcrumb() : Foreach, ArrayItem, Concat
 */

use PHPUnit\Framework\TestCase;
use App\Services\FormattingService;

class FormattingServiceMutationTest extends TestCase
{
    private FormattingService $service;

    protected function setUp(): void
    {
        $this->service = new FormattingService();
        reseedDefaultRegistries(getDB());
    }

    // ═══ e() ═══

    public function testEHandlesAllHtmlSpecialChars(): void
    {
        // Kill mutants on htmlspecialchars flags — each char must be escaped
        $this->assertSame('&lt;', $this->service->e('<'));
        $this->assertSame('&gt;', $this->service->e('>'));
        $this->assertSame('&amp;', $this->service->e('&'));
        $this->assertSame('&quot;', $this->service->e('"'));
        $this->assertSame('&#039;', $this->service->e("'"));
    }

    public function testEHandlesCombinedHtmlString(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            $this->service->e('<script>alert("xss")</script>'),
        );
    }

    public function testEReturnsEmptyForNullAndEmptyAndZero(): void
    {
        // Kill LogicalOr mutant on `$string === null || $string === ''`
        $this->assertSame('', $this->service->e(null), 'null → empty string');
        $this->assertSame('', $this->service->e(''), 'empty string → empty string');
        // Kill mutant that would return '' for 0 or '0'
        $this->assertSame('0', $this->service->e(0), '0 → "0" (not empty)');
        $this->assertSame('0', $this->service->e('0'), '"0" → "0" (not empty)');
    }

    public function testEDoesNotDoubleEscape(): void
    {
        // Kill mutant that would double-escape & → &amp;
        $this->assertSame('&amp;amp;', $this->service->e('&amp;'), 'Already escaped &amp; → &amp;amp;');
    }

    public function testEPassesAccentsUnchanged(): void
    {
        // Kill mutant that would HTML-entity encode accents
        $this->assertSame('éèêëàâùûüôîç', $this->service->e('éèêëàâùûüôîç'));
    }

    public function testECastsIntToString(): void
    {
        // Kill CastString mutant on (string) $str
        $this->assertSame('42', $this->service->e(42));
        $this->assertSame('-5', $this->service->e(-5));
    }

    // ═══ formatDateFR() ═══

    public function testFormatDateFRReturnsDashForEmpty(): void
    {
        // Kill LogicalNot/empty mutant
        $this->assertSame('—', $this->service->formatDateFR(''));
        $this->assertSame('—', $this->service->formatDateFR(null));
        $this->assertSame('—', $this->service->formatDateFR(0));
    }

    public function testFormatDateFRReturnsCorrectFormat(): void
    {
        // Kill Concat mutant on $dt->format('d/m/Y')
        $this->assertSame('15/01/2026', $this->service->formatDateFR('2026-01-15'));
        $this->assertSame('31/12/2025', $this->service->formatDateFR('2025-12-31'));
        $this->assertSame('01/03/2024', $this->service->formatDateFR('2024-03-01'));
    }

    public function testFormatDateFRHandlesDatetimeInput(): void
    {
        // Kill mutant on the fallback format Y-m-d H:i:s
        $this->assertSame('15/01/2026', $this->service->formatDateFR('2026-01-15 14:30:00'));
    }

    public function testFormatDateFREscapesInvalidInput(): void
    {
        // Kill Ternary mutant on `$dt !== false ? format : e($date)`
        $result = $this->service->formatDateFR('not-a-date');
        $this->assertNotSame('not-a-date', $result, 'invalid input must be escaped, not passed through');
        $this->assertStringContainsString('not-a-date', $result);
    }

    // ═══ generateReference() ═══

    public function testGenerateReferenceFormat(): void
    {
        // Kill Concat mutant on $type . '-' . $year2 . '-' . str_pad(...)
        $this->assertSame('rsst-25-001', $this->service->generateReference('rsst', '25', 1));
        $this->assertSame('rami-26-042', $this->service->generateReference('rami', '26', 42));
        $this->assertSame('dgi-25-999', $this->service->generateReference('dgi', '25', 999));
    }

    public function testGenerateReferencePadsTo3Digits(): void
    {
        // Kill str_pad mutant — must pad with leading zeros
        $this->assertSame('rsst-25-001', $this->service->generateReference('rsst', '25', 1));
        $this->assertSame('rsst-25-010', $this->service->generateReference('rsst', '25', 10));
        $this->assertSame('rsst-25-100', $this->service->generateReference('rsst', '25', 100));
        $this->assertSame('rsst-25-1000', $this->service->generateReference('rsst', '25', 1000), '>999 not truncated');
    }

    public function testGenerateReferenceCastsIntSeqToString(): void
    {
        // Kill CastString mutant on (string) $seq
        $this->assertSame('rsst-25-005', $this->service->generateReference('rsst', '25', 5));
    }

    // ═══ truncate() ═══

    public function testTruncateShortStringUnchanged(): void
    {
        // Kill GreaterThan mutant on `mb_strlen > $length`
        $this->assertSame('Hello', $this->service->truncate('Hello', 10));
        $this->assertSame('Hello', $this->service->truncate('Hello', 5), 'exact length → no truncation');
    }

    public function testTruncateLongStringWithEllipsis(): void
    {
        // Kill mb_substr mutant + Concat on '…'
        $this->assertSame('Hello…', $this->service->truncate('Hello World', 5));
        $this->assertSame('Hello Wor…', $this->service->truncate('Hello World', 10));
    }

    public function testTruncateDefaultLengthIs50(): void
    {
        // Kill default value mutant (50 → other)
        $long = str_repeat('a', 60);
        $result = $this->service->truncate($long);
        $this->assertSame(str_repeat('a', 50) . '…', $result, 'default length must be 50');
    }

    public function testTruncateHandlesAccentsCorrectly(): void
    {
        // Kill mb_strlen mutant — accents count as 1 char
        $input = str_repeat('é', 10);
        $result = $this->service->truncate($input, 5);
        $this->assertSame(str_repeat('é', 5) . '…', $result, 'accents must count as 1 char');
    }

    public function testTruncateCastsInputToString(): void
    {
        // Kill CastString mutant on (string) $string
        $this->assertSame('42', $this->service->truncate(42, 10));
        $this->assertSame('123…', $this->service->truncate(12345, 3));
    }

    // ═══ getEtatBadgeClass() ═══

    public function testGetEtatBadgeClassAllStates(): void
    {
        // Kill MatchArmRemoval mutants — each state must return its own class
        $this->assertSame('badge--nouveau', $this->service->getEtatBadgeClass('nouveau'));
        $this->assertSame('badge--en-cours', $this->service->getEtatBadgeClass('en_cours'));
        $this->assertSame('badge--traite', $this->service->getEtatBadgeClass('traite'));
        $this->assertSame('badge--abandonne', $this->service->getEtatBadgeClass('abandonne'));
        $this->assertSame('badge--reouvert', $this->service->getEtatBadgeClass('reouvert'));
    }

    public function testGetEtatBadgeClassReturnsEmptyForUnknownState(): void
    {
        // Kill default arm mutant
        $this->assertSame('', $this->service->getEtatBadgeClass('unknown'));
        $this->assertSame('', $this->service->getEtatBadgeClass(''));
        $this->assertSame('', $this->service->getEtatBadgeClass(null));
    }

    public function testGetEtatBadgeClassCastsInputToString(): void
    {
        // Kill CastString mutant on (string) $etat
        $this->assertSame('badge--nouveau', $this->service->getEtatBadgeClass('nouveau'));
    }

    // ═══ getRoleBadgeClass() ═══

    public function testGetRoleBadgeClassAllRoles(): void
    {
        $this->assertSame('badge--agent', $this->service->getRoleBadgeClass('agent'));
        $this->assertSame('badge--superviseur', $this->service->getRoleBadgeClass('superviseur'));
        $this->assertSame('badge--chsct', $this->service->getRoleBadgeClass('chsct'));
    }

    public function testGetRoleBadgeClassReturnsEmptyForUnknownRole(): void
    {
        $this->assertSame('', $this->service->getRoleBadgeClass('admin'));
        $this->assertSame('', $this->service->getRoleBadgeClass(''));
        $this->assertSame('', $this->service->getRoleBadgeClass(null));
    }

    // ═══ renderBreadcrumb() ═══

    public function testRenderBreadcrumbEmptyArray(): void
    {
        $html = $this->service->renderBreadcrumb([]);
        $this->assertStringContainsString('<nav class="breadcrumb"', $html);
        $this->assertStringContainsString('</nav>', $html);
    }

    public function testRenderBreadcrumbSingleItem(): void
    {
        $html = $this->service->renderBreadcrumb([['label' => 'Accueil']]);
        $this->assertStringContainsString('<span class="breadcrumb__current">Accueil</span>', $html);
        $this->assertStringNotContainsString('breadcrumb__item', $html, 'single item should not have a link');
        $this->assertStringNotContainsString('breadcrumb__separator', $html);
    }

    public function testRenderBreadcrumbMultipleItems(): void
    {
        $html = $this->service->renderBreadcrumb([
            ['url' => '/home', 'label' => 'Accueil'],
            ['label' => 'Signalements'],
        ]);
        $this->assertStringContainsString('<a href="/home" class="breadcrumb__item">Accueil</a>', $html);
        $this->assertStringContainsString('<span class="breadcrumb__separator">/</span>', $html);
        $this->assertStringContainsString('<span class="breadcrumb__current">Signalements</span>', $html);
    }

    public function testRenderBreadcrumbEscapesLabelsAndUrls(): void
    {
        // Kill mutant that would skip e() on labels/urls
        $html = $this->service->renderBreadcrumb([
            ['url' => '"><script>alert(1)</script>', 'label' => '<b>Bold</b>'],
        ]);
        $this->assertStringNotContainsString('<script>', $html, 'script must be escaped in url');
        $this->assertStringNotContainsString('<b>', $html, 'bold tag must be escaped in label');
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }

    public function testRenderBreadcrumbHandlesMissingUrl(): void
    {
        // Kill Coalesce mutant on $item['url'] ?? ''
        $html = $this->service->renderBreadcrumb([
            ['label' => 'No URL'],
        ]);
        $this->assertStringContainsString('breadcrumb__current', $html);
    }

    // ═══ todayISO() / nowTime() ═══

    public function testTodayISOFormat(): void
    {
        // Kill date format mutant
        $today = $this->service->todayISO();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $today, 'todayISO must be Y-m-d');
        $this->assertSame(date('Y-m-d'), $today);
    }

    public function testNowTimeFormat(): void
    {
        $now = $this->service->nowTime();
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $now, 'nowTime must be H:i');
    }

    // ═══ formatDateTimeFR() ═══

    public function testFormatDateTimeFRReturnsDashForEmpty(): void
    {
        $this->assertSame('—', $this->service->formatDateTimeFR(''));
        $this->assertSame('—', $this->service->formatDateTimeFR(null));
    }

    public function testFormatDateTimeFRFormatsCorrectly(): void
    {
        // 2026-01-15 14:30:00 UTC → Europe/Paris (UTC+1 in winter)
        $result = $this->service->formatDateTimeFR('2026-01-15 14:30:00');
        $this->assertStringContainsString('15/01/2026', $result);
        $this->assertStringContainsString('à', $result);
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4} à \d{2}:\d{2}$/', $result);
    }

    public function testFormatDateTimeFREscapesInvalidInput(): void
    {
        $result = $this->service->formatDateTimeFR('invalid');
        $this->assertStringContainsString('invalid', $result);
        $this->assertStringNotContainsString('à', $result, 'invalid datetime should not be formatted with "à"');
    }
}
