<?php
/**
 * Tests ReportState enum exhaustively — kills Infection mutants on:
 *   - All match arms (each case tested independently)
 *   - Return values exact (label, badgeClass, pdfColor)
 *   - Mutants like Identical, MatchArmRemoval, ArrayItem, etc.
 */

use PHPUnit\Framework\TestCase;
use App\Enum\ReportState;

class ReportStateMutationTest extends TestCase
{
    /**
     * Kill mutants on the enum values themselves.
     */
    public function testEnumValuesExact(): void
    {
        $this->assertSame('nouveau', ReportState::Nouveau->value);
        $this->assertSame('en_cours', ReportState::EnCours->value);
        $this->assertSame('traite', ReportState::Traite->value);
        $this->assertSame('reouvert', ReportState::Reouvert->value);
        $this->assertSame('abandonne', ReportState::Abandonne->value);
    }

    public function testTryFromAcceptsValidValues(): void
    {
        $this->assertSame(ReportState::Nouveau, ReportState::tryFrom('nouveau'));
        $this->assertSame(ReportState::EnCours, ReportState::tryFrom('en_cours'));
        $this->assertSame(ReportState::Traite, ReportState::tryFrom('traite'));
        $this->assertSame(ReportState::Reouvert, ReportState::tryFrom('reouvert'));
        $this->assertSame(ReportState::Abandonne, ReportState::tryFrom('abandonne'));
    }

    public function testTryFromRejectsInvalidValues(): void
    {
        $this->assertNull(ReportState::tryFrom('inconnu'));
        $this->assertNull(ReportState::tryFrom('Nouveau')); // case-sensitive
        $this->assertNull(ReportState::tryFrom(''));
    }

    public function testCasesReturnsAll5InOrder(): void
    {
        $cases = ReportState::cases();
        $this->assertCount(5, $cases);
        $this->assertSame(ReportState::Nouveau, $cases[0]);
        $this->assertSame(ReportState::EnCours, $cases[1]);
        $this->assertSame(ReportState::Traite, $cases[2]);
        $this->assertSame(ReportState::Reouvert, $cases[3]);
        $this->assertSame(ReportState::Abandonne, $cases[4]);
    }

    /**
     * Kill mutants on label() — every match arm tested with exact value.
     * Accents matter: Traité has é, Abandonné has é, Réouvert has é.
     */
    public function testLabelExactValueForAllCases(): void
    {
        $this->assertSame('Nouveau', ReportState::Nouveau->label());
        $this->assertSame('En cours', ReportState::EnCours->label(), 'En cours with space, not "EnCours"');
        $this->assertSame('Traité', ReportState::Traite->label(), 'Traité with é');
        $this->assertSame('Réouvert', ReportState::Reouvert->label(), 'Réouvert with é');
        $this->assertSame('Abandonné', ReportState::Abandonne->label(), 'Abandonné with é');
    }

    /**
     * Kill mutants on badgeClass() — every CSS class exact.
     */
    public function testBadgeClassExactForAllCases(): void
    {
        $this->assertSame('badge--nouveau', ReportState::Nouveau->badgeClass());
        $this->assertSame('badge--en-cours', ReportState::EnCours->badgeClass(), 'en-cours with hyphen');
        $this->assertSame('badge--traite', ReportState::Traite->badgeClass());
        $this->assertSame('badge--reouvert', ReportState::Reouvert->badgeClass());
        $this->assertSame('badge--abandonne', ReportState::Abandonne->badgeClass());
    }

    /**
     * Kill mutants on pdfColor() — every RGB exact, and returns array of 3 ints.
     */
    public function testPdfColorExactForAllCases(): void
    {
        $this->assertSame([46, 92, 138], ReportState::Nouveau->pdfColor());
        $this->assertSame([230, 126, 34], ReportState::EnCours->pdfColor());
        $this->assertSame([39, 174, 96], ReportState::Traite->pdfColor());
        $this->assertSame([142, 68, 173], ReportState::Reouvert->pdfColor());
        $this->assertSame([149, 165, 166], ReportState::Abandonne->pdfColor());
    }

    /**
     * Kill ArrayItem mutants — pdfColor must return exactly 3 elements.
     */
    public function testPdfColorReturnsExactly3Ints(): void
    {
        foreach (ReportState::cases() as $state) {
            $color = $state->pdfColor();
            $this->assertCount(3, $color, $state->name . ' pdfColor must return 3 elements');
            foreach ($color as $component) {
                $this->assertIsInt($component, $state->name . ' pdfColor components must be ints');
            }
        }
    }

    /**
     * Kill IncrementInteger/DecrementInteger mutants on RGB values.
     * Verify each component is in a sensible range (0-255).
     */
    public function testPdfColorComponentsInValidRange(): void
    {
        foreach (ReportState::cases() as $state) {
            foreach ($state->pdfColor() as $i => $component) {
                $this->assertGreaterThanOrEqual(0, $component, $state->name . " pdfColor[$i] >= 0");
                $this->assertLessThanOrEqual(255, $component, $state->name . " pdfColor[$i] <= 255");
            }
        }
    }

    /**
     * Kill mutants that would mix up labels between cases.
     * (e.g. Nouveau->label() returning 'Traité' due to swapped match arms)
     */
    public function testLabelsAreUniquePerCase(): void
    {
        $labels = array_map(fn($s) => $s->label(), ReportState::cases());
        $this->assertSame(count($labels), count(array_unique($labels)), 'All labels must be unique');
    }

    /**
     * Kill mutants that would mix up badge classes between cases.
     */
    public function testBadgeClassesAreUniquePerCase(): void
    {
        $badges = array_map(fn($s) => $s->badgeClass(), ReportState::cases());
        $this->assertSame(count($badges), count(array_unique($badges)), 'All badge classes must be unique');
    }

    /**
     * Kill mutants that would mix up PDF colors between cases.
     */
    public function testPdfColorsAreUniquePerCase(): void
    {
        $colors = array_map(fn($s) => implode(',', $s->pdfColor()), ReportState::cases());
        $this->assertSame(count($colors), count(array_unique($colors)), 'All PDF colors must be unique');
    }
}
