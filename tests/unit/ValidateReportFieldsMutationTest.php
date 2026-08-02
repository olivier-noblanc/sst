<?php
/**
 * Tests validateReportFields() exhaustively — kills Infection mutants on:
 *   - Empty checks (LogicalNot, LogicalAnd, Coalesce)
 *   - Regex match (Identical, preg_match mutants)
 *   - checkdate() (CastInt, substr mutants)
 *   - Future date check (GreaterThan, Identical)
 *   - mb_strlen > MAX checks (GreaterThan, CastInt)
 *   - heure regex
 */

use PHPUnit\Framework\TestCase;

class ValidateReportFieldsMutationTest extends TestCase
{
    public function testEmptyDateAddsError(): void
    {
        $errors = validateReportFields('', 'Obj', 'Desc', '', '');
        $this->assertArrayHasKey('date_evenement', $errors);
        $this->assertStringContainsString('obligatoire', $errors['date_evenement']);
    }

    public function testInvalidDateFormatAddsError(): void
    {
        $errors = validateReportFields('2026/01/15', 'Obj', 'Desc', '', '');
        $this->assertArrayHasKey('date_evenement', $errors);
        $this->assertStringContainsString('Format', $errors['date_evenement']);
    }

    public function testInvalidDateValueAddsError(): void
    {
        // Format OK but date doesn't exist — Audit #88
        $errors = validateReportFields('2026-11-45', 'Obj', 'Desc', '', '');
        $this->assertArrayHasKey('date_evenement', $errors);
        $this->assertStringContainsString('invalide', $errors['date_evenement']);
    }

    public function testFebruary30AddsError(): void
    {
        $errors = validateReportFields('2026-02-30', 'Obj', 'Desc', '', '');
        $this->assertArrayHasKey('date_evenement', $errors);
    }

    public function testValidDatePasses(): void
    {
        $errors = validateReportFields('2026-01-15', 'Obj', 'Desc', '', '');
        $this->assertArrayNotHasKey('date_evenement', $errors);
    }

    public function testFutureDateAddsError(): void
    {
        $future = date('Y-m-d', strtotime('+1 year'));
        $errors = validateReportFields($future, 'Obj', 'Desc', '', '');
        $this->assertArrayHasKey('date_evenement', $errors);
        $this->assertStringContainsString('futur', $errors['date_evenement']);
    }

    public function testTodayDatePasses(): void
    {
        $today = date('Y-m-d');
        $errors = validateReportFields($today, 'Obj', 'Desc', '', '');
        $this->assertArrayNotHasKey('date_evenement', $errors);
    }

    public function testEmptyObjetAddsError(): void
    {
        $errors = validateReportFields('2026-01-15', '', 'Desc', '', '');
        $this->assertArrayHasKey('objet', $errors);
        $this->assertStringContainsString('obligatoire', $errors['objet']);
    }

    public function testObjetTooLongAddsError(): void
    {
        $longObjet = str_repeat('a', MAX_OBJECT_LENGTH + 1);
        $errors = validateReportFields('2026-01-15', $longObjet, 'Desc', '', '');
        $this->assertArrayHasKey('objet', $errors);
        $this->assertStringContainsString((string) MAX_OBJECT_LENGTH, $errors['objet']);
    }

    public function testObjetAtMaxLengthPasses(): void
    {
        $exactObjet = str_repeat('a', MAX_OBJECT_LENGTH);
        $errors = validateReportFields('2026-01-15', $exactObjet, 'Desc', '', '');
        $this->assertArrayNotHasKey('objet', $errors);
    }

    public function testEmptyDescriptionAddsError(): void
    {
        $errors = validateReportFields('2026-01-15', 'Obj', '', '', '');
        $this->assertArrayHasKey('description', $errors);
        $this->assertStringContainsString('obligatoire', $errors['description']);
    }

    public function testDescriptionTooLongAddsError(): void
    {
        $longDesc = str_repeat('a', MAX_DESCRIPTION_LENGTH + 1);
        $errors = validateReportFields('2026-01-15', 'Obj', $longDesc, '', '');
        $this->assertArrayHasKey('description', $errors);
    }

    public function testDescriptionAtMaxLengthPasses(): void
    {
        $exactDesc = str_repeat('a', MAX_DESCRIPTION_LENGTH);
        $errors = validateReportFields('2026-01-15', 'Obj', $exactDesc, '', '');
        $this->assertArrayNotHasKey('description', $errors);
    }

    public function testLieuEmptyPasses(): void
    {
        $errors = validateReportFields('2026-01-15', 'Obj', 'Desc', '', '');
        $this->assertArrayNotHasKey('lieu', $errors);
    }

    public function testLieuTooLongAddsError(): void
    {
        $longLieu = str_repeat('a', MAX_LIEU_LENGTH + 1);
        $errors = validateReportFields('2026-01-15', 'Obj', 'Desc', $longLieu, '');
        $this->assertArrayHasKey('lieu', $errors);
    }

    public function testLieuAtMaxLengthPasses(): void
    {
        $exactLieu = str_repeat('a', MAX_LIEU_LENGTH);
        $errors = validateReportFields('2026-01-15', 'Obj', 'Desc', $exactLieu, '');
        $this->assertArrayNotHasKey('lieu', $errors);
    }

    public function testHeureEmptyPasses(): void
    {
        $errors = validateReportFields('2026-01-15', 'Obj', 'Desc', '', '');
        $this->assertArrayNotHasKey('heure_evenement', $errors);
    }

    public function testHeureValidFormatPasses(): void
    {
        $errors = validateReportFields('2026-01-15', 'Obj', 'Desc', '', '14:30');
        $this->assertArrayNotHasKey('heure_evenement', $errors);
    }

    public function testHeureInvalidFormatAddsError(): void
    {
        $errors = validateReportFields('2026-01-15', 'Obj', 'Desc', '', '14h30');
        $this->assertArrayHasKey('heure_evenement', $errors);
        $this->assertStringContainsString('heure', $errors['heure_evenement']);
    }

    public function testHeureInvalidFormatWithSecondsAddsError(): void
    {
        $errors = validateReportFields('2026-01-15', 'Obj', 'Desc', '', '14:30:45');
        $this->assertArrayHasKey('heure_evenement', $errors);
    }

    /**
     * Kill mutants on mb_strlen UTF-8 — accents count as 1 char, not bytes.
     */
    public function testObjetWithAccentsCountedAs1CharNotBytes(): void
    {
        // 'é' is 1 char in UTF-8 but 2 bytes — mb_strlen must be used, not strlen
        $objet = str_repeat('é', MAX_OBJECT_LENGTH); // Should be exactly MAX_OBJECT_LENGTH chars
        $errors = validateReportFields('2026-01-15', $objet, 'Desc', '', '');
        $this->assertArrayNotHasKey('objet', $errors, 'Accents must count as 1 char, not bytes');
    }
}
