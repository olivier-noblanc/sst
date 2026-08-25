<?php

/**
 * SessionService Unit Tests — Flash messages, form data, CSRF, session state
 *
 * Tests SessionService from src/Services/SessionService.php:
 * - setFlash() / getFlash() round-trip and one-shot consume
 * - setFormErrors() / getFormErrors() round-trip and one-shot consume
 * - setFormData() / getFormData() round-trip and one-shot consume
 * - generateCsrfToken() / validateCsrfToken() round-trip and one-shot consume
 * - validateCsrfToken() rejects empty or unknown tokens
 * - User session (setUserSession / getUserSession / isUserLoggedIn / clearSession)
 * - Impersonation (startImpersonation / stopImpersonation / isImpersonatingRole)
 */

use PHPUnit\Framework\TestCase;
use App\Services\SessionService;
use App\DTO\FormData;
use App\DTO\SessionUser;

class SessionServiceTest extends TestCase
{
    private SessionService $service;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->service = new SessionService();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // setFlash() / getFlash()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSetFlashAndRetrieve(): void
    {
        $this->service->setFlash('success', 'Opération réussie');
        $flash = $this->service->getFlash();
        $this->assertNotNull($flash);
        $this->assertSame('success', $flash->type);
        $this->assertSame('Opération réussie', $flash->message);
    }

    public function testGetFlashClearsAfterRead(): void
    {
        $this->service->setFlash('error', 'Erreur');
        $this->service->getFlash();
        $this->assertNull($this->service->getFlash());
    }

    public function testGetFlashReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->service->getFlash());
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // setFormErrors() / getFormErrors()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSetFormErrorsAndRetrieve(): void
    {
        $errors = ['objet' => 'Requis', 'description' => 'Trop court'];
        $this->service->setFormErrors($errors);
        $this->assertEquals($errors, $this->service->getFormErrors());
    }

    public function testGetFormErrorsClearsAfterRead(): void
    {
        $this->service->setFormErrors(['field' => 'err']);
        $this->service->getFormErrors();
        $this->assertEquals([], $this->service->getFormErrors());
    }

    public function testGetFormErrorsReturnsEmptyArrayWhenNoneSet(): void
    {
        $this->assertEquals([], $this->service->getFormErrors());
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // setFormData() / getFormData()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSetFormDataAndRetrieve(): void
    {
        $data = ['objet' => 'Test', 'lieu' => 'Bureau'];
        $this->service->setFormData(FormData::fromPost($data));
        $formData = $this->service->getFormData();
        $this->assertSame('Test', $formData->getString('objet'));
        $this->assertSame('Bureau', $formData->getString('lieu'));
    }

    public function testGetFormDataClearsAfterRead(): void
    {
        $this->service->setFormData(FormData::fromPost(['key' => 'val']));
        $this->service->getFormData();
        $this->assertEquals([], $this->service->getFormData()->toArray());
    }

    public function testGetFormDataReturnsEmptyArrayWhenNoneSet(): void
    {
        $this->assertEquals([], $this->service->getFormData()->toArray());
    }

    public function testSetFormDataRejectsRawArray(): void
    {
        // Issue #2 — la signature setFormData(FormData|array) gardait un shim
        // acceptant les arrays bruts, contrairement à SessionUser migré proprement.
        // Après resserrement vers FormData seul, un array brut doit lever TypeError.
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-next-line — type volontairement erroné pour verrouiller le shim */
        $this->service->setFormData(['objet' => 'raw array']);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // getFieldError()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetFieldErrorReturnsExistingField(): void
    {
        $errors = ['objet' => 'Requis'];
        $this->assertEquals('Requis', $this->service->getFieldError($errors, 'objet'));
    }

    public function testGetFieldErrorReturnsNullForMissingField(): void
    {
        $this->assertNull($this->service->getFieldError([], 'objet'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // generateCsrfToken() / validateCsrfToken()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGenerateAndValidateCsrfToken(): void
    {
        $token = $this->service->generateCsrfToken();
        $this->assertNotEmpty($token);
        $this->assertTrue($this->service->validateCsrfToken($token));
    }

    public function testValidateCsrfTokenConsumesToken(): void
    {
        $token = $this->service->generateCsrfToken();
        $this->service->validateCsrfToken($token);
        $this->assertFalse($this->service->validateCsrfToken($token));
    }

    public function testValidateCsrfTokenRejectsEmptyString(): void
    {
        $this->assertFalse($this->service->validateCsrfToken(''));
    }

    public function testValidateCsrfTokenRejectsUnknownToken(): void
    {
        $this->assertFalse($this->service->validateCsrfToken('nonexistent-token'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // User session
    // ═══════════════════════════════════════════════════════════════════════════════

    private function makeUser(array $overrides = []): SessionUser
    {
        $defaults = [
            'id' => 1, 'username' => 'test.user', 'nom' => 'Dupont', 'prenom' => 'Jean',
            'email' => null, 'role' => 'agent', 'site_id' => null, 'is_active' => 1,
            'created_at' => '2025-01-01 00:00:00', 'updated_at' => null,
            'site_code' => null, 'site_nom' => null, 'site_chosen_at' => null,
            'sessions_invalid_before' => null,
        ];
        return SessionUser::fromRow(array_merge($defaults, $overrides));
    }

    public function testIsUserLoggedInReturnsFalseWhenNoUser(): void
    {
        $this->assertFalse($this->service->isUserLoggedIn());
    }

    public function testIsUserLoggedInReturnsTrueAfterSetUserSession(): void
    {
        $this->service->setUserSession($this->makeUser(['id' => 1, 'role' => 'agent']));
        $this->assertTrue($this->service->isUserLoggedIn());
    }

    public function testGetUserSessionReturnsUserData(): void
    {
        $user = $this->makeUser(['id' => 1, 'role' => 'agent', 'nom' => 'Dupont']);
        $this->service->setUserSession($user);
        $restored = $this->service->getUserSession();
        $this->assertInstanceOf(SessionUser::class, $restored);
        $this->assertSame(1, $restored->id);
        $this->assertSame('agent', $restored->role);
        $this->assertSame('Dupont', $restored->nom);
    }

    public function testGetUserSessionReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->service->getUserSession());
    }

    public function testClearSessionEmptiesUser(): void
    {
        $this->service->setUserSession($this->makeUser(['id' => 1]));
        $this->service->clearSession();
        $this->assertNull($this->service->getUserSession());
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Impersonation
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testStartImpersonationSetsRole(): void
    {
        $this->service->setUserSession($this->makeUser(['id' => 1, 'role' => 'superviseur']));
        $this->service->startImpersonation('superviseur', 'agent');
        $this->assertTrue($this->service->isImpersonatingRole());
        $this->assertEquals('agent', $this->service->getImpersonatedRole());
        $this->assertEquals('superviseur', $this->service->getRealRole());
        // Verify the user role was changed in session
        $user = $this->service->getUserSession();
        $this->assertInstanceOf(SessionUser::class, $user);
        $this->assertSame('agent', $user->role);
    }

    public function testStopImpersonationRestoresRealRole(): void
    {
        $this->service->setUserSession($this->makeUser(['id' => 1, 'role' => 'superviseur']));
        $this->service->startImpersonation('superviseur', 'agent');
        $realRole = $this->service->stopImpersonation();
        $this->assertEquals('superviseur', $realRole);
        $this->assertFalse($this->service->isImpersonatingRole());
        $this->assertNull($this->service->getImpersonatedRole());
        // Verify the user role was restored in session
        $user = $this->service->getUserSession();
        $this->assertInstanceOf(SessionUser::class, $user);
        $this->assertSame('superviseur', $user->role);
    }

    public function testStopImpersonationReturnsNullWhenNotImpersonating(): void
    {
        $this->assertNull($this->service->stopImpersonation());
    }

    public function testIsImpersonatingRoleReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->service->isImpersonatingRole());
    }

    public function testGetImpersonatedRoleReturnsNullByDefault(): void
    {
        $this->assertNull($this->service->getImpersonatedRole());
    }

    public function testGetRealRoleReturnsNullByDefault(): void
    {
        $this->assertNull($this->service->getRealRole());
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Service instantiation
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testServiceCanBeInstantiated(): void
    {
        $service = new SessionService();
        $this->assertInstanceOf(SessionService::class, $service);
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $a = SessionService::getInstance();
        $b = SessionService::getInstance();
        $this->assertSame($a, $b);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Impersonation edge cases — kill Infection mutants #14, #17
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testStartImpersonationDoesNotUpdateRoleWhenUserNotSessionUser(): void
    {
        $_SESSION['user'] = 'not-a-SessionUser';
        $this->service->startImpersonation('superviseur', 'agent');
        $this->assertSame('not-a-SessionUser', $_SESSION['user']);
    }

    public function testStopImpersonationDoesNotUpdateRoleWhenUserNotSessionUser(): void
    {
        $_SESSION['real_role'] = 'superviseur';
        $_SESSION['impersonated_role'] = 'agent';
        $_SESSION['user'] = 'not-a-SessionUser';
        $this->service->stopImpersonation();
        $this->assertSame('not-a-SessionUser', $_SESSION['user']);
    }

    public function testStartImpersonationSkipsRoleUpdateWhenUserNotSet(): void
    {
        unset($_SESSION['user']);
        $this->service->startImpersonation('superviseur', 'agent');
        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    public function testStopImpersonationSkipsRoleUpdateWhenUserNotSet(): void
    {
        $_SESSION['real_role'] = 'superviseur';
        $_SESSION['impersonated_role'] = 'agent';
        unset($_SESSION['user']);
        $this->service->stopImpersonation();
        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // CSRF token reuse — single token per session (no accumulation)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testCsrfTokenReusesExistingValidToken(): void
    {
        // Generate token multiple times - should reuse same token while valid
        $token1 = $this->service->generateCsrfToken();
        $token2 = $this->service->generateCsrfToken();
        $token3 = $this->service->generateCsrfToken();

        // All tokens should be identical (reuse, not accumulation)
        $this->assertSame($token1, $token2);
        $this->assertSame($token2, $token3);
        $this->assertCount(1, $_SESSION['csrf_tokens']);
    }

    public function testCsrfTokenGeneratesNewAfterConsumption(): void
    {
        $token1 = $this->service->generateCsrfToken();
        $this->service->validateCsrfToken($token1); // Consume the token

        // After consumption, next call should generate new token
        $token2 = $this->service->generateCsrfToken();
        $this->assertNotSame($token1, $token2);
        $this->assertCount(1, $_SESSION['csrf_tokens']);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // HTTPS detection — kill Infection mutant #5
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testStartSessionWorksWithHttpsOn(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            session_write_close();
        }
        $original = $_SERVER['HTTPS'] ?? null;
        $_SERVER['HTTPS'] = 'on';
        $this->service->startSession();
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $_SERVER['HTTPS'] = $original;
    }

    public function testStartSessionWorksWithHttpsOff(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            session_write_close();
        }
        $original = $_SERVER['HTTPS'] ?? null;
        $_SERVER['HTTPS'] = 'off';
        $this->service->startSession();
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $_SERVER['HTTPS'] = $original;
    }

    public function testStartSessionWorksWithoutHttps(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            session_write_close();
        }
        $original = $_SERVER['HTTPS'] ?? null;
        unset($_SERVER['HTTPS']);
        $this->service->startSession();
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $_SERVER['HTTPS'] = $original;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // startSession() — legacy cookie cleanup
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testStartSessionClearsLegacyPhpSessionCookie(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            session_write_close();
        }

        $_COOKIE['PHPSESSID'] = 'legacy-session-id';

        $service = new SessionService();
        $service->startSession();

        $this->assertSame('SST_SESSION', session_name());
        $this->assertArrayNotHasKey('PHPSESSID', $_COOKIE);
    }
}
