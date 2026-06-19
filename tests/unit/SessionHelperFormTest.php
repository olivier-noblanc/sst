<?php
/**
 * Session Helper Unit Tests — Form Data & Form Errors
 *
 * Tests form-related session functions from src/session.php:
 * - setFormData() / getFormData()
 * - setFormErrors() / getFormErrors() / getFieldError()
 */

use PHPUnit\Framework\TestCase;

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
        setFormData($data);
        $result = getFormData();
        $this->assertEquals($data, $result);
    }

    public function testGetFormDataClearsData(): void
    {
        setFormData(['field' => 'value']);
        $result1 = getFormData();
        $result2 = getFormData();
        $this->assertEquals(['field' => 'value'], $result1);
        $this->assertEquals([], $result2);
    }

    public function testGetFormDataReturnsEmptyArrayWhenNoneSet(): void
    {
        $this->assertEquals([], getFormData());
    }

    public function testSetFormDataWithEmptyArray(): void
    {
        setFormData([]);
        $this->assertEquals([], getFormData());
    }

    public function testSetFormDataOverwritesPrevious(): void
    {
        setFormData(['field1' => 'value1']);
        setFormData(['field2' => 'value2']);
        $result = getFormData();
        $this->assertEquals(['field2' => 'value2'], $result);
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
