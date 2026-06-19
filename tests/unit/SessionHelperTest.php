<?php
/**
 * Session Helper Unit Tests — CSRF Token
 *
 * Tests CSRF token functions from src/session.php:
 * - generateCsrfToken() / validateCsrfToken()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/session.php';

class SessionHelperTest extends TestCase
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

    // ─── CSRF Token ────────────────────────────────────────────────────────

    public function testGenerateCsrfTokenReturnsNonEmptyString(): void
    {
        $token = generateCsrfToken();
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testGenerateCsrfTokenReturns64CharHex(): void
    {
        $token = generateCsrfToken();
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function testValidateCsrfTokenWithValidToken(): void
    {
        $token = generateCsrfToken();
        $this->assertTrue(validateCsrfToken($token));
    }

    public function testValidateCsrfTokenIsOneTimeUse(): void
    {
        $token = generateCsrfToken();
        $this->assertTrue(validateCsrfToken($token));
        $this->assertFalse(validateCsrfToken($token));
    }

    public function testValidateCsrfTokenWithInvalidToken(): void
    {
        generateCsrfToken();
        $this->assertFalse(validateCsrfToken('invalid_token_12345'));
    }

    public function testValidateCsrfTokenWithEmptyString(): void
    {
        generateCsrfToken();
        $this->assertFalse(validateCsrfToken(''));
    }

    public function testMultipleTokensCanBeValidSimultaneously(): void
    {
        $token1 = generateCsrfToken();
        $token2 = generateCsrfToken();
        $token3 = generateCsrfToken();

        $this->assertTrue(validateCsrfToken($token2));
        $this->assertTrue(validateCsrfToken($token1));
        $this->assertTrue(validateCsrfToken($token3));
    }

    public function testConsumingOneTokenDoesNotAffectOthers(): void
    {
        $token1 = generateCsrfToken();
        $token2 = generateCsrfToken();

        $this->assertTrue(validateCsrfToken($token1));
        $this->assertTrue(validateCsrfToken($token2));
        $this->assertFalse(validateCsrfToken($token1));
    }

    public function testGenerateCsrfTokenGarbageCollection(): void
    {
        $tokens = [];
        for ($i = 0; $i < 25; $i++) {
            $tokens[] = generateCsrfToken();
        }
        $this->assertLessThanOrEqual(20, count($_SESSION['csrf_tokens']));
        $last20 = array_slice($tokens, -20);
        foreach ($last20 as $token) {
            $this->assertArrayHasKey($token, $_SESSION['csrf_tokens']);
        }
    }

    public function testValidateCsrfTokenWithoutAnyGeneratedToken(): void
    {
        $this->assertFalse(validateCsrfToken('any_token'));
    }
}
