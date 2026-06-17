<?php
/**
 * Session Helper Unit Tests — Application SST DREETS BFC
 *
 * Tests session management functions from src/session.php:
 * - generateCsrfToken() / validateCsrfToken()
 * - setFlash() / getFlash()
 * - setFormData() / getFormData()
 * - setFormErrors() / getFormErrors() / getFieldError()
 * - startImpersonation() / stopImpersonation() / isImpersonatingRole()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/session.php';

class SessionHelperTest extends TestCase
{
    /**
     * Start a fresh session before each test.
     * Each test runs in isolation to avoid session conflicts.
     */
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
        // bin2hex(random_bytes(32)) = 64 hex characters
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
        // Second use should fail
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

        // All three should be valid
        $this->assertTrue(validateCsrfToken($token2));
        $this->assertTrue(validateCsrfToken($token1));
        $this->assertTrue(validateCsrfToken($token3));
    }

    public function testConsumingOneTokenDoesNotAffectOthers(): void
    {
        $token1 = generateCsrfToken();
        $token2 = generateCsrfToken();

        // Consume token1
        $this->assertTrue(validateCsrfToken($token1));
        // token2 should still be valid
        $this->assertTrue(validateCsrfToken($token2));
        // token1 should no longer be valid
        $this->assertFalse(validateCsrfToken($token1));
    }

    public function testGenerateCsrfTokenGarbageCollection(): void
    {
        // Generate more than 20 tokens to trigger garbage collection
        $tokens = [];
        for ($i = 0; $i < 25; $i++) {
            $tokens[] = generateCsrfToken();
        }

        // Session should have at most 20 tokens stored
        $this->assertLessThanOrEqual(20, count($_SESSION['csrf_tokens']));

        // The last 20 tokens should still be there
        $last20 = array_slice($tokens, -20);
        foreach ($last20 as $token) {
            $this->assertArrayHasKey($token, $_SESSION['csrf_tokens']);
        }
    }

    public function testValidateCsrfTokenWithoutAnyGeneratedToken(): void
    {
        // No tokens generated — any validation should fail
        $this->assertFalse(validateCsrfToken('any_token'));
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

    // ─── Impersonation ─────────────────────────────────────────────────────

    public function testStartImpersonationSetsSession(): void
    {
        $_SESSION['user'] = ['id' => 1, 'role' => 'superviseur'];

        startImpersonation('superviseur', 'agent');

        $this->assertTrue(isImpersonatingRole());
        $this->assertEquals('superviseur', $_SESSION['real_role']);
        $this->assertEquals('agent', $_SESSION['impersonated_role']);
        $this->assertEquals('agent', $_SESSION['user']['role']);
    }

    public function testStopImpersonationRestoresRole(): void
    {
        $_SESSION['user'] = ['id' => 1, 'role' => 'agent'];

        startImpersonation('superviseur', 'agent');
        $this->assertTrue(isImpersonatingRole());

        $restoredRole = stopImpersonation();
        $this->assertEquals('superviseur', $restoredRole);
        $this->assertFalse(isImpersonatingRole());
        $this->assertEquals('superviseur', $_SESSION['user']['role']);
    }

    public function testStopImpersonationWhenNotImpersonating(): void
    {
        $result = stopImpersonation();
        $this->assertNull($result);
    }

    public function testIsImpersonatingRoleWhenNotImpersonating(): void
    {
        $this->assertFalse(isImpersonatingRole());
    }

    public function testFullImpersonationCycle(): void
    {
        $_SESSION['user'] = ['id' => 1, 'role' => 'superviseur', 'nom' => 'Admin'];

        // Start impersonating agent
        startImpersonation('superviseur', 'agent');
        $this->assertTrue(isImpersonatingRole());
        $this->assertEquals('agent', $_SESSION['user']['role']);
        $this->assertEquals('superviseur', $_SESSION['real_role']);

        // Stop impersonation
        $restored = stopImpersonation();
        $this->assertEquals('superviseur', $restored);
        $this->assertFalse(isImpersonatingRole());
        $this->assertEquals('superviseur', $_SESSION['user']['role']);
        $this->assertArrayNotHasKey('real_role', $_SESSION);
        $this->assertArrayNotHasKey('impersonated_role', $_SESSION);
    }

    public function testImpersonationToChsct(): void
    {
        $_SESSION['user'] = ['id' => 1, 'role' => 'superviseur'];

        startImpersonation('superviseur', 'chsct');
        $this->assertTrue(isImpersonatingRole());
        $this->assertEquals('chsct', $_SESSION['user']['role']);

        stopImpersonation();
        $this->assertEquals('superviseur', $_SESSION['user']['role']);
    }

    // ─── User Session ──────────────────────────────────────────────────────

    public function testSetUserSessionAndGetUserSession(): void
    {
        $user = ['id' => 5, 'nom' => 'Dupont', 'role' => 'agent'];
        setUserSession($user);
        $this->assertEquals($user, getUserSession());
    }

    public function testGetUserSessionReturnsNullWhenNotSet(): void
    {
        $this->assertNull(getUserSession());
    }

    public function testIsUserLoggedInReturnsTrueWhenSet(): void
    {
        $_SESSION['user'] = ['id' => 1];
        $this->assertTrue(isUserLoggedIn());
    }

    public function testIsUserLoggedInReturnsFalseWhenNotSet(): void
    {
        $this->assertFalse(isUserLoggedIn());
    }

    public function testClearSessionRemovesAllData(): void
    {
        $_SESSION['user'] = ['id' => 1];
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'OK'];
        $_SESSION['csrf_tokens'] = ['token1' => time()];

        clearSession();

        $this->assertEmpty($_SESSION);
        $this->assertFalse(isUserLoggedIn());
        $this->assertNull(getUserSession());
    }

    // ─── Intended URL ──────────────────────────────────────────────────────

    public function testSetIntendedUrlAndGetIntendedUrl(): void
    {
        setIntendedUrl('/index.php?page=report_edit&uuid=abc');
        $this->assertEquals('/index.php?page=report_edit&uuid=abc', getIntendedUrl());
    }

    public function testGetIntendedUrlReturnsNullWhenNotSet(): void
    {
        $this->assertNull(getIntendedUrl());
    }

    public function testClearIntendedUrlReturnsAndRemoves(): void
    {
        setIntendedUrl('/some/page');
        $url = clearIntendedUrl();

        $this->assertEquals('/some/page', $url);
        $this->assertNull(getIntendedUrl());
    }

    public function testClearIntendedUrlReturnsNullWhenNotSet(): void
    {
        $this->assertNull(clearIntendedUrl());
    }
}
