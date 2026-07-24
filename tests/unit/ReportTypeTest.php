<?php
/**
 * ReportType Enum Tests — Application SST DREETS BFC
 *
 * Tests the ReportType enum for correctness and consistency.
 */

use PHPUnit\Framework\TestCase;
use App\Enum\ReportType;

class ReportTypeTest extends TestCase
{
    /**
     * ReportType::cases() must contain exactly 3 values.
     */
    public function testCasesContainsExactlyThreeValues(): void
    {
        $cases = ReportType::cases();
        $this->assertCount(3, $cases, 'ReportType must have exactly 3 cases');

        $values = array_map(fn(ReportType $t) => $t->value, $cases);
        $this->assertEqualsCanonicalizing(
            ['rsst', 'rami', 'dgi'],
            $values,
            'ReportType values must match the expected set'
        );
    }

    /**
     * Each ReportType case must produce a unique label.
     */
    public function testLabelsAreUnique(): void
    {
        $labels = array_map(fn(ReportType $t) => $t->label(), ReportType::cases());
        $this->assertCount(3, array_unique($labels), 'All ReportType labels must be unique');
    }

    /**
     * Each ReportType case must produce a unique short label.
     */
    public function testShortLabelsAreUnique(): void
    {
        $shortLabels = array_map(fn(ReportType $t) => $t->shortLabel(), ReportType::cases());
        $this->assertCount(3, array_unique($shortLabels), 'All ReportType short labels must be unique');
    }

    /**
     * Each ReportType case must produce a unique badge class.
     */
    public function testBadgeClassesAreUnique(): void
    {
        $classes = array_map(fn(ReportType $t) => $t->badgeClass(), ReportType::cases());
        $this->assertCount(3, array_unique($classes), 'All ReportType badge classes must be unique');
    }

    /**
     * Each ReportType case must produce a unique PDF color.
     */
    public function testPdfColorsAreUnique(): void
    {
        $colors = array_map(fn(ReportType $t) => serialize($t->pdfColor()), ReportType::cases());
        $this->assertCount(3, array_unique($colors), 'All ReportType PDF colors must be unique');
    }

    /**
     * TYPE_* constants must match the enum values.
     */
    public function testEnumValuesAreStrings(): void
    {
        $this->assertIsString(ReportType::Rsst->value);
        $this->assertIsString(ReportType::Rami->value);
        $this->assertIsString(ReportType::Dgi->value);
    }

    /**
     * REGISTRY_LABELS must be derived from the enum and contain all 3 types.
     */
    public function testRegistryLabelsMatchEnum(): void
    {
        $expected = array_combine(
            array_map(fn(ReportType $t) => $t->value, ReportType::cases()),
            array_map(fn(ReportType $t) => $t->label(), ReportType::cases())
        );
        $this->assertEquals($expected, REGISTRY_LABELS);
    }

    /**
     * REGISTRY_SHORT_LABELS must be derived from the enum and contain all 3 types.
     */
    public function testRegistryShortLabelsMatchEnum(): void
    {
        $expected = array_combine(
            array_map(fn(ReportType $t) => $t->value, ReportType::cases()),
            array_map(fn(ReportType $t) => $t->shortLabel(), ReportType::cases())
        );
        $this->assertEquals($expected, REGISTRY_SHORT_LABELS);
    }

    /**
     * from() must reject invalid values.
     */
    public function testFromRejectsInvalidValues(): void
    {
        $this->expectException(ValueError::class);
        ReportType::from('invalid_type');
    }

    /**
     * tryFrom() must return null for invalid values.
     */
    public function testTryFromReturnsNullForInvalid(): void
    {
        $this->assertNull(ReportType::tryFrom('invalid_type'));
    }

    /**
     * tryFrom() must return the correct case for valid values.
     */
    public function testTryFromReturnsCorrectCase(): void
    {
        $this->assertSame(ReportType::Rsst, ReportType::tryFrom('rsst'));
        $this->assertSame(ReportType::Rami, ReportType::tryFrom('rami'));
        $this->assertSame(ReportType::Dgi, ReportType::tryFrom('dgi'));
    }
}
