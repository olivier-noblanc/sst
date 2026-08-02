<?php
/**
 * Session Helper Unit Tests — Flash Messages
 *
 * Tests flash message functions from src/session.php:
 * - setFlash() / getFlash()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/session.php';

class SessionHelperFlashTest extends TestCase
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

    // ─── Flash Messages ────────────────────────────────────────────────────

    public function testSetFlashAndGetFlash(): void
    {
        setFlash('success', 'Operation completed');
        $flash = getFlash();
        $this->assertIsArray($flash);
        $this->assertEquals('success', $flash['type']);
        $this->assertEquals('Operation completed', $flash['message']);
    }

    public function testGetFlashClearsMessage(): void
    {
        setFlash('error', 'Something went wrong');
        $flash1 = getFlash();
        $flash2 = getFlash();
        $this->assertNotNull($flash1);
        $this->assertNull($flash2);
    }

    public function testGetFlashReturnsNullWhenNoMessage(): void
    {
        $this->assertNull(getFlash());
    }

    public function testFlashTypeSuccess(): void
    {
        setFlash('success', 'OK');
        $flash = getFlash();
        $this->assertEquals('success', $flash['type']);
    }

    public function testFlashTypeError(): void
    {
        setFlash('error', 'Fail');
        $flash = getFlash();
        $this->assertEquals('error', $flash['type']);
    }

    public function testFlashTypeWarning(): void
    {
        setFlash('warning', 'Careful');
        $flash = getFlash();
        $this->assertEquals('warning', $flash['type']);
    }

    public function testFlashTypeInfo(): void
    {
        setFlash('info', 'FYI');
        $flash = getFlash();
        $this->assertEquals('info', $flash['type']);
    }

    public function testFlashOverwritesPrevious(): void
    {
        setFlash('error', 'First error');
        setFlash('success', 'Then success');
        $flash = getFlash();
        $this->assertEquals('success', $flash['type']);
        $this->assertEquals('Then success', $flash['message']);
    }
}
