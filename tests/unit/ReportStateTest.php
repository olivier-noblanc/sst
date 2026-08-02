<?php
/**
 * ReportState Enum Tests — Application SST DREETS BFC
 *
 * Tests the ReportState enum for correctness and consistency.
 */

use PHPUnit\Framework\TestCase;
use App\Enum\ReportState;

class ReportStateTest extends TestCase
{
    /**
     * ReportState::cases() must contain exactly 5 values.
     * This is a guard against someone adding a case without updating
     * label()/badgeClass()/pdfColor() — PHPStan should already block it,
     * but this is a second runtime defense.
     */
    public function testCasesContainsExactlyFiveValues(): void
    {
        $cases = ReportState::cases();
        $this->assertCount(5, $cases, 'ReportState must have exactly 5 cases');

        $values = array_map(fn(ReportState $s) => $s->value, $cases);
        $this->assertEqualsCanonicalizing(
            ['nouveau', 'en_cours', 'traite', 'reouvert', 'abandonne'],
            $values,
            'ReportState values must match the expected set'
        );
    }

    /**
     * Each ReportState case must produce a unique label.
     */
    public function testLabelsAreUnique(): void
    {
        $labels = array_map(fn(ReportState $s) => $s->label(), ReportState::cases());
        $this->assertCount(5, array_unique($labels), 'All ReportState labels must be unique');
    }

    /**
     * Each ReportState case must produce a unique badge class.
     */
    public function testBadgeClassesAreUnique(): void
    {
        $classes = array_map(fn(ReportState $s) => $s->badgeClass(), ReportState::cases());
        $this->assertCount(5, array_unique($classes), 'All ReportState badge classes must be unique');
    }

    /**
     * Each ReportState case must produce a unique PDF color.
     * Regression test for the 'reouvert' bug where it shared 'nouveau' color.
     */
    public function testPdfColorsAreUnique(): void
    {
        $colors = array_map(fn(ReportState $s) => serialize($s->pdfColor()), ReportState::cases());
        $this->assertCount(5, array_unique($colors), 'All ReportState PDF colors must be unique');
    }

    /**
     * ETAT_* constants must match the enum values.
     */
    public function testConstantsMatchEnumValues(): void
    {
        $this->assertSame(ETAT_NOUVEAU, ReportState::Nouveau->value);
        $this->assertSame(ETAT_EN_COURS, ReportState::EnCours->value);
        $this->assertSame(ETAT_TRAITE, ReportState::Traite->value);
        $this->assertSame(ETAT_ABANDONNE, ReportState::Abandonne->value);
        $this->assertSame(ETAT_REOUVERT, ReportState::Reouvert->value);
    }

    /**
     * ETAT_LABELS must be derived from the enum and contain all 5 states.
     */
    public function testEtatLabelsMatchEnum(): void
    {
        $expected = array_combine(
            array_map(fn(ReportState $s) => $s->value, ReportState::cases()),
            array_map(fn(ReportState $s) => $s->label(), ReportState::cases())
        );
        $this->assertEquals($expected, ETAT_LABELS);
    }

    /**
     * from() must reject invalid values.
     */
    public function testFromRejectsInvalidValues(): void
    {
        $this->expectException(ValueError::class);
        ReportState::from('invalid_state');
    }

    /**
     * tryFrom() must return null for invalid values.
     */
    public function testTryFromReturnsNullForInvalid(): void
    {
        $this->assertNull(ReportState::tryFrom('invalid_state'));
    }

    /**
     * tryFrom() must return the correct case for valid values.
     */
    public function testTryFromReturnsCorrectCase(): void
    {
        $this->assertSame(ReportState::Nouveau, ReportState::tryFrom('nouveau'));
        $this->assertSame(ReportState::EnCours, ReportState::tryFrom('en_cours'));
        $this->assertSame(ReportState::Traite, ReportState::tryFrom('traite'));
        $this->assertSame(ReportState::Reouvert, ReportState::tryFrom('reouvert'));
        $this->assertSame(ReportState::Abandonne, ReportState::tryFrom('abandonne'));
    }
}
