<?php
/**
 * FormattingService Unit Tests — HTML escaping, dates, references, badges, truncate
 *
 * Tests FormattingService from src/Services/FormattingService.php:
 * - e() escapes HTML entities
 * - formatDateFR() formats ISO dates to French d/m/Y
 * - formatDateTimeFR() formats ISO datetimes to French d/m/Y à H:i
 * - generateReference() builds {type}-{YY}-{NNN} strings
 * - getRegistryColor() returns CSS variable names
 * - getEtatBadgeClass() returns badge classes for report states
 * - getRegistryBadgeClass() returns badge classes for registry types
 * - getRoleBadgeClass() returns badge classes for user roles
 * - truncate() shortens long strings with ellipsis
 * - todayISO() returns current date in Y-m-d
 * - nowTime() returns current time in H:i
 */

use PHPUnit\Framework\TestCase;
use App\Services\FormattingService;

class FormattingServiceTest extends TestCase
{
    private FormattingService $service;

    protected function setUp(): void
    {
        $this->service = new FormattingService();
        // Audit #85 — sans ça, getRegistryColor() dépend de l'ordre
        // d'exécution : elle lit d'abord registries.color_theme en DB
        // (support des registres personnalisés), et ne retombe sur le
        // fallback hardcodé que si la table est vide. reseedDefaultRegistries()
        // garantit que le chemin DB est toujours pris, comme en vraie prod.
        reseedDefaultRegistries(getDB());
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // e()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testEEscapesHtmlEntities(): void
    {
        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $this->service->e('<script>alert("xss")</script>'));
    }

    public function testEReturnsEmptyStringForNull(): void
    {
        $this->assertEquals('', $this->service->e(null));
    }

    public function testEReturnsEmptyStringForEmptyString(): void
    {
        $this->assertEquals('', $this->service->e(''));
    }

    public function testEPassesThroughPlainTextUnchanged(): void
    {
        $this->assertEquals('Hello World', $this->service->e('Hello World'));
    }

    public function testEEscapesAmpersandAndQuotes(): void
    {
        $result = $this->service->e("a & b 'c' \"d\"");
        $this->assertEquals('a &amp; b &#039;c&#039; &quot;d&quot;', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // formatDateFR()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFormatDateFRConvertsIsoToFrench(): void
    {
        $this->assertEquals('15/03/2025', $this->service->formatDateFR('2025-03-15'));
    }

    public function testFormatDateFRReturnsDashForEmpty(): void
    {
        $this->assertEquals('—', $this->service->formatDateFR(''));
    }

    public function testFormatDateFRReturnsDashForNull(): void
    {
        $this->assertEquals('—', $this->service->formatDateFR(null));
    }

    public function testFormatDateFRHandlesDatetimeFormat(): void
    {
        $this->assertEquals('01/01/2025', $this->service->formatDateFR('2025-01-01 10:30:00'));
    }

    public function testFormatDateFREscapesInvalidFormat(): void
    {
        $result = $this->service->formatDateFR('not-a-date');
        $this->assertStringContainsString('not-a-date', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // formatDateTimeFR()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testFormatDateTimeFRConvertsIsoToFrench(): void
    {
        // 2025-03-15 14:30:00 UTC → 15:30:00 CET (winter, +1h)
        $this->assertEquals('15/03/2025 à 15:30', $this->service->formatDateTimeFR('2025-03-15 14:30:00'));
    }

    public function testFormatDateTimeFRReturnsDashForEmpty(): void
    {
        $this->assertEquals('—', $this->service->formatDateTimeFR(''));
    }

    public function testFormatDateTimeFRReturnsDashForNull(): void
    {
        $this->assertEquals('—', $this->service->formatDateTimeFR(null));
    }

    public function testFormatDateTimeFRHandlesTFormat(): void
    {
        // 2025-01-01T00:00:00 UTC → 01:00:00 CET (winter, +1h)
        $this->assertEquals('01/01/2025 à 01:00', $this->service->formatDateTimeFR('2025-01-01T00:00:00'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // generateReference()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGenerateReferenceBuildsCorrectFormat(): void
    {
        $this->assertEquals('RSST-25-001', $this->service->generateReference('RSST', '25', 1));
    }

    public function testGenerateReferencePadsSequenceWithZeros(): void
    {
        $this->assertEquals('RAMI-25-042', $this->service->generateReference('RAMI', '25', 42));
    }

    public function testGenerateReferenceWithLargeSequence(): void
    {
        $this->assertEquals('DGI-26-1000', $this->service->generateReference('DGI', '26', 1000));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // getRegistryColor()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetRegistryColorRsst(): void
    {
        // Audit #85 — getRegistryColor() lit registries.color_theme en DB
        // depuis le "Modular-audit P2.2" (support des registres
        // personnalisés) — var(--theme-X), pas l'ancien var(--X-color)
        // hardcodé qui n'existe plus dans public/css/style.css.
        $this->assertEquals('var(--theme-rsst)', $this->service->getRegistryColor('rsst'));
    }

    public function testGetRegistryColorRami(): void
    {
        $this->assertEquals('var(--theme-rami)', $this->service->getRegistryColor('rami'));
    }

    public function testGetRegistryColorDgi(): void
    {
        $this->assertEquals('var(--theme-dgi)', $this->service->getRegistryColor('dgi'));
    }

    public function testGetRegistryColorUnknownFallsBackToRsstTheme(): void
    {
        // Audit #85 — le commentaire de getRegistryColor() documente
        // explicitement que match(ReportType::from($type)) (qui levait un
        // ValueError) a été remplacé par fromCode()/tryFrom() (jamais
        // d'exception) précisément pour ne plus planter sur un code
        // personnalisé inconnu. Ce test attendait encore l'ancien
        // comportement — jamais mis à jour après le refactor.
        $this->assertEquals('var(--theme-rsst)', $this->service->getRegistryColor('unknown'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // getEtatBadgeClass()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetEtatBadgeClassNouveau(): void
    {
        $this->assertEquals('badge--nouveau', $this->service->getEtatBadgeClass('nouveau'));
    }

    public function testGetEtatBadgeClassEnCours(): void
    {
        $this->assertEquals('badge--en-cours', $this->service->getEtatBadgeClass('en_cours'));
    }

    public function testGetEtatBadgeClassTraite(): void
    {
        $this->assertEquals('badge--traite', $this->service->getEtatBadgeClass('traite'));
    }

    public function testGetEtatBadgeClassAbandonne(): void
    {
        $this->assertEquals('badge--abandonne', $this->service->getEtatBadgeClass('abandonne'));
    }

    public function testGetEtatBadgeClassReouvert(): void
    {
        $this->assertEquals('badge--reouvert', $this->service->getEtatBadgeClass('reouvert'));
    }

    public function testGetEtatBadgeClassUnknownReturnsEmpty(): void
    {
        $this->assertEquals('', $this->service->getEtatBadgeClass('unknown'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // getRegistryBadgeClass()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetRegistryBadgeClassRsst(): void
    {
        $this->assertEquals('badge--rsst', $this->service->getRegistryBadgeClass('rsst'));
    }

    public function testGetRegistryBadgeClassRami(): void
    {
        $this->assertEquals('badge--rami', $this->service->getRegistryBadgeClass('rami'));
    }

    public function testGetRegistryBadgeClassDgi(): void
    {
        $this->assertEquals('badge--dgi', $this->service->getRegistryBadgeClass('dgi'));
    }

    public function testGetRegistryBadgeClassUnknownThrowsValueError(): void
    {
        $this->expectException(ValueError::class);
        $this->service->getRegistryBadgeClass('unknown');
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // getRoleBadgeClass()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetRoleBadgeClassAgent(): void
    {
        $this->assertEquals('badge--agent', $this->service->getRoleBadgeClass('agent'));
    }

    public function testGetRoleBadgeClassSuperviseur(): void
    {
        $this->assertEquals('badge--superviseur', $this->service->getRoleBadgeClass('superviseur'));
    }

    public function testGetRoleBadgeClassChsct(): void
    {
        $this->assertEquals('badge--chsct', $this->service->getRoleBadgeClass('chsct'));
    }

    public function testGetRoleBadgeClassUnknownReturnsEmpty(): void
    {
        $this->assertEquals('', $this->service->getRoleBadgeClass('unknown'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // truncate()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testTruncateReturnsSameStringIfShortEnough(): void
    {
        $this->assertEquals('Hello', $this->service->truncate('Hello', 50));
    }

    public function testTruncateAddsEllipsisWhenTooLong(): void
    {
        $result = $this->service->truncate('This is a very long sentence that should be truncated', 20);
        $this->assertStringEndsWith('…', $result);
        $this->assertEquals(21, mb_strlen($result, 'UTF-8'));
    }

    public function testTruncateDefaultLengthIs50(): void
    {
        $short = str_repeat('a', 50);
        $this->assertEquals($short, $this->service->truncate($short));
        $long = str_repeat('a', 51);
        $this->assertStringEndsWith('…', $this->service->truncate($long));
    }

    public function testTruncateHandlesUtf8Characters(): void
    {
        $input = 'Rémi est à la maison — très bien!';
        $result = $this->service->truncate($input, 15);
        $this->assertStringEndsWith('…', $result);
        $this->assertEquals(16, mb_strlen($result, 'UTF-8'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // todayISO()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testTodayISOReturnsYMDFormat(): void
    {
        $result = $this->service->todayISO();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
    }

    public function testTodayISOMatchesCurrentDate(): void
    {
        $this->assertEquals(date('Y-m-d'), $this->service->todayISO());
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // nowTime()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testNowTimeReturnsHIFormat(): void
    {
        $result = $this->service->nowTime();
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result);
    }

    public function testNowTimeMatchesCurrentTime(): void
    {
        $this->assertEquals(date('H:i'), $this->service->nowTime());
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Service instantiation
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testServiceCanBeInstantiated(): void
    {
        $service = new FormattingService();
        $this->assertInstanceOf(FormattingService::class, $service);
    }
}
