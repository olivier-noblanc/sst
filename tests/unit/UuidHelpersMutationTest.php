<?php
/**
 * Tests UUID helpers — kills Infection mutants on:
 *   - isValidUuid regex match (Identical, preg_match mutants)
 *   - generateUuid format (Concat, substr mutants)
 *   - Case insensitivity (i flag)
 */

use PHPUnit\Framework\TestCase;

class UuidHelpersMutationTest extends TestCase
{
    public function testIsValidUuidAcceptsValidLowercaseUuid(): void
    {
        $this->assertTrue(isValidUuid('550e8400-e29b-41d4-a716-446655440000'));
    }

    public function testIsValidUuidAcceptsValidUppercaseUuid(): void
    {
        // Kill mutant that would remove the /i flag
        $this->assertTrue(isValidUuid('550E8400-E29B-41D4-A716-446655440000'));
    }

    public function testIsValidUuidAcceptsMixedCaseUuid(): void
    {
        $this->assertTrue(isValidUuid('550e8400-E29b-41D4-a716-446655440000'));
    }

    public function testIsValidUuidRejectsEmptyString(): void
    {
        $this->assertFalse(isValidUuid(''));
    }

    public function testIsValidUuidRejectsRandomString(): void
    {
        $this->assertFalse(isValidUuid('not-a-uuid'));
    }

    public function testIsValidUuidRejectsTruncatedUuid(): void
    {
        $this->assertFalse(isValidUuid('550e8400-e29b-41d4-a716-44665544000'));
        $this->assertFalse(isValidUuid('550e8400-e29b-41d4-a716'));
    }

    public function testIsValidUuidRejectsUuidWithExtraChars(): void
    {
        $this->assertFalse(isValidUuid('550e8400-e29b-41d4-a716-446655440000-extra'));
        $this->assertFalse(isValidUuid('extra-550e8400-e29b-41d4-a716-446655440000'));
    }

    public function testIsValidUuidRejectsUuidWithWrongSeparators(): void
    {
        $this->assertFalse(isValidUuid('550e8400_e29b-41d4-a716-446655440000'));
        $this->assertFalse(isValidUuid('550e8400-e29b_41d4-a716-446655440000'));
    }

    public function testIsValidUuidRejectsUuidWithNonHexChars(): void
    {
        $this->assertFalse(isValidUuid('550g8400-e29b-41d4-a716-446655440000')); // 'g' is not hex
        $this->assertFalse(isValidUuid('550e8400-z29b-41d4-a716-446655440000'));
    }

    public function testIsValidUuidRejectsUuidWithWrongSegmentLengths(): void
    {
        // First segment too short
        $this->assertFalse(isValidUuid('550e840-e29b-41d4-a716-446655440000'));
        // Second segment too long
        $this->assertFalse(isValidUuid('550e8400-e29b1-41d4-a716-446655440000'));
    }

    /**
     * Kill mutants on generateUuid() — must produce a valid UUID v4 format.
     */
    public function testGenerateUuidReturnsValidUuidFormat(): void
    {
        $uuid = generateUuid();
        $this->assertTrue(isValidUuid($uuid), 'generateUuid() must return a valid UUID format');
    }

    public function testGenerateUuidReturnsDifferentValuesEachCall(): void
    {
        $uuid1 = generateUuid();
        $uuid2 = generateUuid();
        $this->assertNotSame($uuid1, $uuid2, 'generateUuid() must return different values (random)');
    }

    public function testGenerateUuidHasVersion4Indicator(): void
    {
        // UUID v4 has '4' as the first char of the 3rd segment
        $uuid = generateUuid();
        // 3rd segment starts at position 14 (0-indexed): xxxxxxxx-xxxx-4xxx-...
        $this->assertSame('4', $uuid[14], 'UUID v4 must have "4" at position 14');
    }

    public function testGenerateUuidHasValidVariantIndicator(): void
    {
        // UUID v4 variant: 1st char of 4th segment must be 8, 9, a, or b
        $uuid = generateUuid();
        $variantChar = strtolower($uuid[19]);
        $this->assertContains($variantChar, ['8', '9', 'a', 'b'], "Variant char must be 8/9/a/b, got $variantChar");
    }

    public function testGenerateUuidAlwaysLowercase(): void
    {
        // Kill mutant that would uppercase the hex
        for ($i = 0; $i < 10; $i++) {
            $uuid = generateUuid();
            $this->assertSame(strtolower($uuid), $uuid, 'generateUuid() must return lowercase');
        }
    }
}
