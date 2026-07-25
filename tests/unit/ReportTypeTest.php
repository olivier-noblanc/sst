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
     * Modular-audit P1.4 — Test mis à jour.
     * Avant : testRegistryLabelsMatchEnum assertait que REGISTRY_LABELS (constante
     * globale) matchait exactement ReportType::cases(). Or cette constante a été
     * supprimée dans P25d (remplacée par getRegistryLabel() qui lit depuis la DB).
     * Le test échouait en silence.
     * Maintenant : vérifie que getRegistryLabel() retourne bien le label de l'enum
     * pour les 3 codes système (rsst/rami/dgi).
     */
    public function testRegistryLabelsMatchEnum(): void
    {
        foreach (ReportType::cases() as $type) {
            $label = getRegistryLabel($type->value);
            $this->assertNotEmpty($label, "Label for {$type->value} should not be empty");
            $this->assertSame($type->label(), $label, "Label for {$type->value} should match enum");
        }
    }

    /**
     * Modular-audit P1.4 — Test mis à jour.
     * Idem que testRegistryLabelsMatchEnum mais pour shortLabel.
     */
    public function testRegistryShortLabelsMatchEnum(): void
    {
        foreach (ReportType::cases() as $type) {
            $short = getRegistryShortLabel($type->value);
            $this->assertNotEmpty($short, "Short label for {$type->value} should not be empty");
            $this->assertSame($type->shortLabel(), $short, "Short label for {$type->value} should match enum");
        }
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
