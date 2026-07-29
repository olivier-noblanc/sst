<?php
/**
 * Tests ReportType enum exhaustively — kills Infection mutants on:
 *   - Match arms (label, shortLabel, badgeClass, pdfColor, legalNote)
 *   - fromCode() fallback
 *   - ArrayItem / IncrementInteger on pdfColor RGB values
 */

use PHPUnit\Framework\TestCase;
use App\Enum\ReportType;

class ReportTypeMutationTest extends TestCase
{
    public function testEnumValuesExact(): void
    {
        $this->assertSame('rsst', ReportType::Rsst->value);
        $this->assertSame('rami', ReportType::Rami->value);
        $this->assertSame('dgi', ReportType::Dgi->value);
    }

    public function testTryFromAcceptsValidValues(): void
    {
        $this->assertSame(ReportType::Rsst, ReportType::tryFrom('rsst'));
        $this->assertSame(ReportType::Rami, ReportType::tryFrom('rami'));
        $this->assertSame(ReportType::Dgi, ReportType::tryFrom('dgi'));
    }

    public function testTryFromRejectsCustomCodes(): void
    {
        // Custom registries (violences, harassment) should return null — they're
        // not part of the 3 system ReportType values.
        $this->assertNull(ReportType::tryFrom('violences'));
        $this->assertNull(ReportType::tryFrom('harassment'));
        $this->assertNull(ReportType::tryFrom('unknown'));
        $this->assertNull(ReportType::tryFrom(''));
        $this->assertNull(ReportType::tryFrom('RSST')); // case-sensitive
    }

    public function testCasesReturnsAll3InOrder(): void
    {
        $cases = ReportType::cases();
        $this->assertCount(3, $cases);
        $this->assertSame(ReportType::Rsst, $cases[0]);
        $this->assertSame(ReportType::Rami, $cases[1]);
        $this->assertSame(ReportType::Dgi, $cases[2]);
    }

    public function testLabelExactForAllCases(): void
    {
        $this->assertSame('Santé et Sécurité au Travail', ReportType::Rsst->label());
        $this->assertSame('Agressions, Menaces et Incivilités', ReportType::Rami->label());
        $this->assertSame('Danger Grave et Imminent', ReportType::Dgi->label());
    }

    public function testShortLabelExactForAllCases(): void
    {
        $this->assertSame('RSST', ReportType::Rsst->shortLabel());
        $this->assertSame('RAMI', ReportType::Rami->shortLabel());
        $this->assertSame('DGI', ReportType::Dgi->shortLabel());
    }

    public function testBadgeClassExactForAllCases(): void
    {
        $this->assertSame('badge--rsst', ReportType::Rsst->badgeClass());
        $this->assertSame('badge--rami', ReportType::Rami->badgeClass());
        $this->assertSame('badge--dgi', ReportType::Dgi->badgeClass());
    }

    public function testPdfColorExactForAllCases(): void
    {
        // Kill ArrayItem mutants — exact RGB values
        $this->assertSame([46, 92, 138], ReportType::Rsst->pdfColor());
        $this->assertSame([108, 108, 108], ReportType::Rami->pdfColor());
        $this->assertSame([178, 34, 34], ReportType::Dgi->pdfColor());
    }

    public function testPdfColorReturnsExactly3IntsInRange(): void
    {
        foreach (ReportType::cases() as $type) {
            $color = $type->pdfColor();
            $this->assertCount(3, $color, $type->name . ' pdfColor must return 3 elements');
            foreach ($color as $i => $component) {
                $this->assertIsInt($component, $type->name . " pdfColor[$i] must be int");
                $this->assertGreaterThanOrEqual(0, $component);
                $this->assertLessThanOrEqual(255, $component);
            }
        }
    }

    public function testLegalNoteExactForAllCases(): void
    {
        $this->assertSame(
            'Décret n° 82-453 art. 3-2 : registre consultable par tout agent. La transparence est recommandée.',
            ReportType::Rsst->legalNote(),
        );
        $this->assertSame(
            "Données sensibles (art. 9 RGPD) : le mode confidentiel ou choix de l'agent est recommandé.",
            ReportType::Rami->legalNote(),
        );
        $this->assertSame(
            'Articles L4131-1 et D4132-1 du Code du travail : le formalisme du registre spécial peut justifier un mode restrictif.',
            ReportType::Dgi->legalNote(),
        );
    }

    public function testLabelsAreUniquePerCase(): void
    {
        $labels = array_map(fn($t) => $t->label(), ReportType::cases());
        $this->assertSame(count($labels), count(array_unique($labels)));
    }

    public function testShortLabelsAreUniquePerCase(): void
    {
        $labels = array_map(fn($t) => $t->shortLabel(), ReportType::cases());
        $this->assertSame(count($labels), count(array_unique($labels)));
    }

    public function testBadgeClassesAreUniquePerCase(): void
    {
        $badges = array_map(fn($t) => $t->badgeClass(), ReportType::cases());
        $this->assertSame(count($badges), count(array_unique($badges)));
    }

    public function testPdfColorsAreUniquePerCase(): void
    {
        $colors = array_map(fn($t) => implode(',', $t->pdfColor()), ReportType::cases());
        $this->assertSame(count($colors), count(array_unique($colors)));
    }

    /**
     * Kill mutants on fromCode() — must behave identically to tryFrom.
     */
    public function testFromCodeMirrorsTryFrom(): void
    {
        foreach (['rsst', 'rami', 'dgi'] as $code) {
            $this->assertSame(ReportType::tryFrom($code), ReportType::fromCode($code), "fromCode('$code') must match tryFrom");
        }
        foreach (['unknown', '', 'custom'] as $code) {
            $this->assertNull(ReportType::fromCode($code), "fromCode('$code') must return null for unknown");
        }
    }
}
