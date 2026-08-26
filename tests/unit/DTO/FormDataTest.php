<?php

use PHPUnit\Framework\TestCase;
use App\DTO\FormData;

class FormDataTest extends TestCase
{
    public function testGetStringWithNullValueReturnsDefault(): void
    {
        $formData = new FormData(['field' => null]);
        $result = $formData->getString('field', 'default');
        $this->assertSame('default', $result);
    }

    public function testGetStringWithIntValueReturnsStringCast(): void
    {
        $formData = new FormData(['field' => 42]);
        $result = $formData->getString('field', '');
        $this->assertSame('42', $result);
    }

    public function testGetStringWithBoolValueReturnsStringCast(): void
    {
        $formData = new FormData(['field' => true]);
        $result = $formData->getString('field', '');
        $this->assertSame('1', $result);
    }

    public function testGetStringWithNonStringIntValueReturnsCastedString(): void
    {
        $formData = new FormData(['nom' => 123]);
        $result = $formData->getString('nom');
        $this->assertSame('123', $result);
    }

    public function testGetStringWithValidStringReturnsString(): void
    {
        $formData = new FormData(['field' => 'valid']);
        $result = $formData->getString('field', 'default');
        $this->assertSame('valid', $result);
    }

    public function testGetStringWithMissingKeyReturnsDefault(): void
    {
        $formData = new FormData([]);
        $result = $formData->getString('missing', 'default');
        $this->assertSame('default', $result);
    }

    public function testGetIntWithStringValueReturnsIntCast(): void
    {
        $formData = new FormData(['age' => '25']);
        $result = $formData->getInt('age');
        $this->assertSame(25, $result);
    }

    public function testGetIntWithIntValueReturnsInt(): void
    {
        $formData = new FormData(['age' => 30]);
        $result = $formData->getInt('age');
        $this->assertSame(30, $result);
    }

    public function testGetIntWithNullValueReturnsDefault(): void
    {
        $formData = new FormData(['age' => null]);
        $result = $formData->getInt('age', 18);
        $this->assertSame(18, $result);
    }

    public function testGetIntWithMissingKeyUsesDefaultZero(): void
    {
        $formData = new FormData([]);
        $result = $formData->getInt('missing');
        $this->assertSame(0, $result);
    }

    public function testOffsetExistsWithNumericStringOffset(): void
    {
        $formData = new FormData(['5' => 'cinq']);
        $this->assertTrue($formData->offsetExists(5));
    }

    public function testOffsetExistsWithZeroOffset(): void
    {
        $formData = new FormData(['0' => 'zero']);
        $this->assertTrue($formData->offsetExists(0));
    }

    public function testOffsetExistsWithMissingOffsetReturnsFalse(): void
    {
        $formData = new FormData([]);
        $this->assertFalse($formData->offsetExists(123));
    }

    public function testFromSessionWithMixedDataTypes(): void
    {
        $data = ['nom' => 'Jean', 'age' => 30, 'actif' => null, 'ok' => true];
        $formData = FormData::fromSession($data);

        $this->assertSame('Jean', $formData->getString('nom'));
        $this->assertSame(30, $formData->getInt('age'));
        $this->assertSame('', $formData->getString('actif'));
        $this->assertSame(1, $formData->getInt('ok'));
    }
}
