<?php
/**
 * Session Helper Unit Tests — Form Data & Form Errors
 *
 * Tests form-related session functions from src/session.php:
 * - setFormData() / getFormData()
 * - setFormErrors() / getFormErrors() / getFieldError()
 */

use PHPUnit\Framework\TestCase;
use App\DTO\FormData;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/session.php';

class SessionHelperFormTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ─── Form Data ─────────────────────────────────────────────────────────

    public function testSetFormDataAndGetFormData(): void
    {
        $data = ['nom' => 'Dupont', 'prenom' => 'Marie', 'site_id' => '1'];
        setFormData(FormData::fromPost($data));
        $result = getFormData();
        $this->assertSame('Dupont', $result->getString('nom'));
        $this->assertSame('Marie', $result->getString('prenom'));
        $this->assertSame('1', $result->getString('site_id'));
    }

    public function testGetFormDataClearsData(): void
    {
        setFormData(FormData::fromPost(['field' => 'value']));
        $result1 = getFormData();
        $result2 = getFormData();
        $this->assertSame('value', $result1->getString('field'));
        $this->assertEquals([], $result2->toArray());
    }

    public function testGetFormDataReturnsEmptyArrayWhenNoneSet(): void
    {
        $this->assertEquals([], getFormData()->toArray());
    }

    public function testSetFormDataWithEmptyArray(): void
    {
        setFormData(FormData::fromPost([]));
        $this->assertEquals([], getFormData()->toArray());
    }

    public function testSetFormDataOverwritesPrevious(): void
    {
        setFormData(FormData::fromPost(['field1' => 'value1']));
        setFormData(FormData::fromPost(['field2' => 'value2']));
        $result = getFormData();
        $this->assertSame('value2', $result->getString('field2'));
        $this->assertFalse($result->has('field1'));
    }

    // ─── Form Errors ───────────────────────────────────────────────────────

    public function testSetFormErrorsAndGetFormErrors(): void
    {
        $errors = ['nom' => 'Le nom est requis', 'email' => 'Email invalide'];
        setFormErrors($errors);
        $result = getFormErrors();
        $this->assertEquals($errors, $result);
    }

    public function testGetFormErrorsClearsErrors(): void
    {
        setFormErrors(['field' => 'Error']);
        $result1 = getFormErrors();
        $result2 = getFormErrors();
        $this->assertEquals(['field' => 'Error'], $result1);
        $this->assertEquals([], $result2);
    }

    public function testGetFormErrorsReturnsEmptyArrayWhenNoneSet(): void
    {
        $this->assertEquals([], getFormErrors());
    }

    // ─── getFieldError ─────────────────────────────────────────────────────

    public function testGetFieldErrorWithExistingField(): void
    {
        $errors = ['nom' => 'Le nom est requis', 'email' => 'Email invalide'];
        $this->assertEquals('Le nom est requis', getFieldError($errors, 'nom'));
        $this->assertEquals('Email invalide', getFieldError($errors, 'email'));
    }

    public function testGetFieldErrorWithMissingField(): void
    {
        $errors = ['nom' => 'Le nom est requis'];
        $this->assertNull(getFieldError($errors, 'prenom'));
    }

    public function testGetFieldErrorWithEmptyErrors(): void
    {
        $this->assertNull(getFieldError([], 'nom'));
    }
}
