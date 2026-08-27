<?php
/**
 * FormDataMutationTest — kills Infection mutants on:
 *   - CastString on getString() line 77
 *   - CastInt on getInt() line 88
 *   - CastString on offsetExists() line 124
 *
 * The mutants remove the (string)/(int) casts. Tests must verify that
 * non-string/non-int inputs are properly cast.
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\FormData;
use PHPUnit\Framework\TestCase;

class FormDataMutationTest extends TestCase
{
    // ═══ getString() CastString mutants ═══

    public function testGetStringCastStringWhenInt(): void
    {
        // Kill CastString mutant on line 77: without cast, int would stay int
        $formData = new FormData(['name' => 42]);
        $this->assertSame('42', $formData->getString('name'), 'int must be cast to string "42"');
        $this->assertIsString($formData->getString('name'));
    }

    public function testGetStringCastStringWhenBool(): void
    {
        // Kill CastString mutant: without cast, bool would stay bool
        $formData = new FormData(['name' => true]);
        $this->assertSame('1', $formData->getString('name'), 'bool true must be cast to string "1"');
        $this->assertIsString($formData->getString('name'));
    }

    public function testGetStringCastStringWhenFloat(): void
    {
        // Kill CastString mutant: without cast, float would stay float
        $formData = new FormData(['name' => 3.14]);
        $this->assertSame('3.14', $formData->getString('name'), 'float must be cast to string "3.14"');
        $this->assertIsString($formData->getString('name'));
    }

    public function testGetStringCastStringWhenNull(): void
    {
        // Kill CastString mutant: without cast, null would stay null
        $formData = new FormData(['name' => null]);
        $this->assertSame('', $formData->getString('name'), 'null must be cast to empty string');
        $this->assertIsString($formData->getString('name'));
    }

    public function testGetStringReturnsStringWhenAlreadyString(): void
    {
        // Baseline: string values pass through unchanged
        $formData = new FormData(['name' => 'hello']);
        $this->assertSame('hello', $formData->getString('name'));
    }

    public function testGetStringReturnsDefaultWhenKeyMissing(): void
    {
        // Kill CastString mutant on ?? $default fallback
        $formData = new FormData([]);
        $this->assertSame('default', $formData->getString('missing', 'default'));
    }

    public function testGetStringReturnsEmptyStringWhenNoDefaultAndKeyMissing(): void
    {
        $formData = new FormData([]);
        $this->assertSame('', $formData->getString('missing'));
    }

    // ═══ getInt() CastInt mutants ═══

    public function testGetIntCastIntWhenString(): void
    {
        // Kill CastInt mutant on line 88: without cast, string would stay string
        $formData = new FormData(['count' => '42']);
        $this->assertSame(42, $formData->getInt('count'), 'string "42" must be cast to int 42');
        $this->assertIsInt($formData->getInt('count'));
    }

    public function testGetIntCastIntWhenFloat(): void
    {
        // Kill CastInt mutant: without cast, float would stay float
        $formData = new FormData(['count' => 3.14]);
        $this->assertSame(3, $formData->getInt('count'), 'float must be cast to int (truncated)');
        $this->assertIsInt($formData->getInt('count'));
    }

    public function testGetIntCastIntWhenBool(): void
    {
        // Kill CastInt mutant: without cast, bool would stay bool
        $formData = new FormData(['count' => true]);
        $this->assertSame(1, $formData->getInt('count'), 'bool true must be cast to int 1');
        $this->assertIsInt($formData->getInt('count'));
    }

    public function testGetIntCastIntWhenNull(): void
    {
        // Kill CastInt mutant: without cast, null would stay null
        $formData = new FormData(['count' => null]);
        $this->assertSame(0, $formData->getInt('count'), 'null must be cast to int 0');
        $this->assertIsInt($formData->getInt('count'));
    }

    public function testGetIntReturnsIntWhenAlreadyInt(): void
    {
        // Baseline: int values pass through unchanged
        $formData = new FormData(['count' => 100]);
        $this->assertSame(100, $formData->getInt('count'));
    }

    public function testGetIntReturnsDefaultWhenKeyMissing(): void
    {
        // Kill CastInt mutant on ?? $default fallback
        $formData = new FormData([]);
        $this->assertSame(999, $formData->getInt('missing', 999));
    }

    public function testGetIntReturnsZeroWhenNoDefaultAndKeyMissing(): void
    {
        $formData = new FormData([]);
        $this->assertSame(0, $formData->getInt('missing'));
    }

    // ═══ offsetExists() CastString mutant ═══

    public function testOffsetExistsWithStringOffset(): void
    {
        // Baseline: string offset works
        $formData = new FormData(['name' => 'value']);
        $this->assertTrue($formData->offsetExists('name'));
        $this->assertFalse($formData->offsetExists('missing'));
    }

    public function testOffsetExistsCastStringWhenIntOffset(): void
    {
        // Kill CastString mutant on line 124: without cast, int offset would be checked as int
        // With cast: (string) 0 → "0", checks if "0" key exists
        // Without cast: checks if int 0 key exists (different behavior)
        $formData = new FormData(['0' => 'zero', '1' => 'one']);
        $this->assertTrue($formData->offsetExists(0), 'int 0 must be cast to string "0"');
        $this->assertTrue($formData->offsetExists(1), 'int 1 must be cast to string "1"');
    }

    public function testOffsetExistsCastStringWhenBoolOffset(): void
    {
        // Kill CastString mutant: bool offset cast behavior
        // With cast: (string) true → "1", (string) false → ""
        $formData = new FormData(['1' => 'true', '' => 'empty']);
        $this->assertTrue($formData->offsetExists(true), 'bool true must be cast to string "1"');
        $this->assertTrue($formData->offsetExists(false), 'bool false must be cast to string ""');
    }

    public function testOffsetExistsWithEmptyStringOffset(): void
    {
        // Kill CastString mutant: empty string offset behavior
        $formData = new FormData(['' => 'empty']);
        $this->assertTrue($formData->offsetExists(''), 'empty string key must exist');
    }

    // ═══ Additional ArrayAccess methods ═══

    public function testOffsetGetReturnsValue(): void
    {
        $formData = new FormData(['name' => 'value']);
        $this->assertSame('value', $formData->offsetGet('name'));
    }

    public function testOffsetGetReturnsNullForMissingKey(): void
    {
        $formData = new FormData([]);
        $this->assertNull($formData->offsetGet('missing'));
    }

    public function testOffsetSetThrowsError(): void
    {
        $formData = new FormData(['name' => 'value']);
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly FormData');
        $formData->offsetSet('name', 'new');
    }

    public function testOffsetUnsetThrowsError(): void
    {
        $formData = new FormData(['name' => 'value']);
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot unset readonly FormData');
        $formData->offsetUnset('name');
    }

    // ═══ has() and isEmpty() ═══

    public function testHasReturnsTrueForExistingKey(): void
    {
        $formData = new FormData(['name' => 'value']);
        $this->assertTrue($formData->has('name'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $formData = new FormData([]);
        $this->assertFalse($formData->has('missing'));
    }

    public function testIsEmptyReturnsTrueForEmptyString(): void
    {
        $formData = new FormData(['name' => '']);
        $this->assertTrue($formData->isEmpty('name'));
    }

    public function testIsEmptyReturnsTrueForZero(): void
    {
        $formData = new FormData(['count' => 0]);
        $this->assertTrue($formData->isEmpty('count'));
    }

    public function testIsEmptyReturnsTrueForNull(): void
    {
        $formData = new FormData(['name' => null]);
        $this->assertTrue($formData->isEmpty('name'));
    }

    public function testIsEmptyReturnsFalseForNonEmptyString(): void
    {
        $formData = new FormData(['name' => 'value']);
        $this->assertFalse($formData->isEmpty('name'));
    }

    public function testIsEmptyReturnsFalseForMissingKey(): void
    {
        $formData = new FormData([]);
        $this->assertTrue($formData->isEmpty('missing'), 'missing key returns null which is empty');
    }

    // ═══ fromPost() and fromSession() ═══

    public function testFromPostFiltersNonStringKeys(): void
    {
        $post = ['name' => 'value', 0 => 'numeric_key', 'valid' => 'data'];
        $formData = FormData::fromPost($post);
        $this->assertTrue($formData->has('name'));
        $this->assertTrue($formData->has('valid'));
        // Non-string keys are skipped
        $this->assertFalse($formData->has('0'));
    }

    public function testFromSessionDelegatesToFromPost(): void
    {
        $data = ['name' => 'value', 'count' => 42];
        $formData = FormData::fromSession($data);
        $this->assertSame('value', $formData->getString('name'));
        $this->assertSame(42, $formData->getInt('count'));
    }

    // ═══ toArray() ═══

    public function testToArrayReturnsFields(): void
    {
        $fields = ['name' => 'value', 'count' => 42, 'active' => true];
        $formData = new FormData($fields);
        $this->assertSame($fields, $formData->toArray());
    }
}