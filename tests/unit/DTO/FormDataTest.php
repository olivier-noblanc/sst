<?php

use PHPUnit\Framework\TestCase;
use App\DTO\FormData;

class FormDataTest extends TestCase
{
    public function testGetStringWithNullValueReturnsDefault(): void
    {
        $formData = new FormData(['field' => null]);
        $result = $formData->getString('field', 'default');
        $this->assertEquals('default', $result);
    }

    public function testGetStringWithIntValueReturnsStringCast(): void
    {
        $formData = new FormData(['field' => 42]);
        $result = $formData->getString('field', '');
        $this->assertEquals('42', $result);
    }

    public function testGetStringWithBoolValueReturnsStringCast(): void
    {
        $formData = new FormData(['field' => true]);
        $result = $formData->getString('field', '');
        $this->assertEquals('1', $result);
    }

    public function testGetStringWithValidStringReturnsString(): void
    {
        $formData = new FormData(['field' => 'valid']);
        $result = $formData->getString('field', 'default');
        $this->assertEquals('valid', $result);
    }

    public function testGetStringWithMissingKeyReturnsDefault(): void
    {
        $formData = new FormData([]);
        $result = $formData->getString('missing', 'default');
        $this->assertEquals('default', $result);
    }
}