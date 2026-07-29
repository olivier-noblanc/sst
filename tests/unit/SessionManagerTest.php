<?php
/**
 * SessionManager Unit Tests — OOP Session Wrapper
 *
 * Tests SessionManager from src/Services/SessionManager.php:
 * - Service instantiation
 * - Method existence and type hints
 * - Delegation to global session functions
 */

use PHPUnit\Framework\TestCase;
use App\Services\SessionManager;

class SessionManagerTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure session is available for testing
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testServiceCanBeInstantiated(): void
    {
        $manager = new SessionManager();
        $this->assertInstanceOf(SessionManager::class, $manager);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Method existence
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testStartMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'start'));
    }

    public function testIsLoggedInMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'isLoggedIn'));
    }

    public function testSetUserMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'setUser'));
    }

    public function testGetUserMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'getUser'));
    }

    public function testClearMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'clear'));
    }

    public function testSetIntendedUrlMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'setIntendedUrl'));
    }

    public function testGetIntendedUrlMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'getIntendedUrl'));
    }

    public function testClearIntendedUrlMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'clearIntendedUrl'));
    }

    public function testStartImpersonationMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'startImpersonation'));
    }

    public function testStopImpersonationMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'stopImpersonation'));
    }

    public function testIsImpersonatingMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'isImpersonating'));
    }

    public function testGetImpersonatedRoleMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'getImpersonatedRole'));
    }

    public function testGetRealRoleMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'getRealRole'));
    }

    public function testGenerateCsrfTokenMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'generateCsrfToken'));
    }

    public function testValidateCsrfTokenMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'validateCsrfToken'));
    }

    public function testSetFlashMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'setFlash'));
    }

    public function testGetFlashMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'getFlash'));
    }

    public function testSetFormDataMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'setFormData'));
    }

    public function testGetFormDataMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'getFormData'));
    }

    public function testSetFormErrorsMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'setFormErrors'));
    }

    public function testGetFormErrorsMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'getFormErrors'));
    }

    public function testGetFieldErrorMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'getFieldError'));
    }

    public function testRefreshUserMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'refreshUser'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Return types
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testIsLoggedInReturnsBool(): void
    {
        $manager = new SessionManager();
        $result = $manager->isLoggedIn();
        $this->assertIsBool($result);
    }

    public function testGetUserReturnsNullWhenNotLoggedIn(): void
    {
        clearSession();
        $manager = new SessionManager();
        $result = $manager->getUser();
        $this->assertNull($result);
    }

    public function testGetUserReturnsArrayWhenLoggedIn(): void
    {
        $manager = new SessionManager();
        $user = [
            'id' => 1,
            'username' => 'test.user',
            'nom' => 'Test',
            'prenom' => 'User',
            'role' => ROLE_AGENT,
            'site_id' => 1,
            'is_active' => 1,
        ];
        $manager->setUser($user);
        $result = $manager->getUser();
        $this->assertIsArray($result);
        $this->assertEquals('test.user', $result['username']);
    }

    public function testGetIntendedUrlReturnsNullWhenNotSet(): void
    {
        $manager = new SessionManager();
        $result = $manager->getIntendedUrl();
        $this->assertNull($result);
    }

    public function testGetIntendedUrlReturnsUrlWhenSet(): void
    {
        $manager = new SessionManager();
        $manager->setIntendedUrl('/reports/123');
        $result = $manager->getIntendedUrl();
        $this->assertEquals('/reports/123', $result);
    }

    public function testClearIntendedUrlReturnsUrlAndClears(): void
    {
        $manager = new SessionManager();
        $manager->setIntendedUrl('/reports/123');
        $result = $manager->clearIntendedUrl();
        $this->assertEquals('/reports/123', $result);
        $this->assertNull($manager->getIntendedUrl());
    }

    public function testIsImpersonatingReturnsBool(): void
    {
        $manager = new SessionManager();
        $result = $manager->isImpersonating();
        $this->assertIsBool($result);
    }

    public function testGetImpersonatedRoleReturnsNullWhenNotImpersonating(): void
    {
        $manager = new SessionManager();
        $result = $manager->getImpersonatedRole();
        $this->assertNull($result);
    }

    public function testGetRealRoleReturnsNullWhenNotImpersonating(): void
    {
        $manager = new SessionManager();
        $result = $manager->getRealRole();
        $this->assertNull($result);
    }

    public function testGenerateCsrfTokenReturnsString(): void
    {
        $manager = new SessionManager();
        $token = $manager->generateCsrfToken();
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testValidateCsrfTokenReturnsBool(): void
    {
        $manager = new SessionManager();
        $token = $manager->generateCsrfToken();
        $this->assertIsBool($manager->validateCsrfToken($token));
    }

    public function testValidateCsrfTokenReturnsFalseForInvalidToken(): void
    {
        $manager = new SessionManager();
        $this->assertFalse($manager->validateCsrfToken('invalid-token'));
    }

    public function testGetFlashReturnsNullWhenNotSet(): void
    {
        clearSession();
        $manager = new SessionManager();
        $result = $manager->getFlash();
        $this->assertNull($result);
    }

    public function testSetAndGetFlash(): void
    {
        $manager = new SessionManager();
        $manager->setFlash('success', 'Opération réussie');
        $flash = $manager->getFlash();
        $this->assertIsArray($flash);
        $this->assertEquals('success', $flash['type']);
        $this->assertEquals('Opération réussie', $flash['message']);
    }

    public function testGetFlashClearsAfterRead(): void
    {
        $manager = new SessionManager();
        $manager->setFlash('error', 'Erreur test');
        $manager->getFlash();
        $this->assertNull($manager->getFlash());
    }

    public function testGetFormDataReturnsEmptyArrayWhenNotSet(): void
    {
        clearSession();
        $manager = new SessionManager();
        $result = $manager->getFormData();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSetAndGetFormData(): void
    {
        $manager = new SessionManager();
        $data = ['nom' => 'Dupont', 'prenom' => 'Jean'];
        $manager->setFormData($data);
        $result = $manager->getFormData();
        $this->assertEquals($data, $result);
    }

    public function testGetFormErrorsReturnsEmptyArrayWhenNotSet(): void
    {
        clearSession();
        $manager = new SessionManager();
        $result = $manager->getFormErrors();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSetAndGetFormErrors(): void
    {
        $manager = new SessionManager();
        $errors = ['nom' => 'Le nom est requis', 'email' => 'Email invalide'];
        $manager->setFormErrors($errors);
        $result = $manager->getFormErrors();
        $this->assertEquals($errors, $result);
    }

    public function testGetFieldErrorReturnsCorrectError(): void
    {
        $manager = new SessionManager();
        $errors = ['nom' => 'Le nom est requis', 'email' => 'Email invalide'];
        $result = $manager->getFieldError($errors, 'nom');
        $this->assertEquals('Le nom est requis', $result);
    }

    public function testGetFieldErrorReturnsNullForMissingField(): void
    {
        $manager = new SessionManager();
        $errors = ['nom' => 'Le nom est requis'];
        $result = $manager->getFieldError($errors, 'prenom');
        $this->assertNull($result);
    }

    public function testGetFieldErrorReturnsNullForEmptyErrors(): void
    {
        $manager = new SessionManager();
        $result = $manager->getFieldError([], 'nom');
        $this->assertNull($result);
    }

    public function testClearResetsAllSessionData(): void
    {
        $manager = new SessionManager();
        $manager->setUser(['id' => 1, 'username' => 'test', 'role' => ROLE_AGENT]);
        $manager->setFlash('success', 'Test');
        $manager->setIntendedUrl('/test');
        $manager->clear();
        $this->assertFalse($manager->isLoggedIn());
        $this->assertNull($manager->getUser());
    }

    public function testSetUserThenClearThenGetUserReturnsNull(): void
    {
        $manager = new SessionManager();
        $manager->setUser(['id' => 1, 'username' => 'test', 'role' => ROLE_AGENT]);
        $this->assertNotNull($manager->getUser());
        $manager->clear();
        $this->assertNull($manager->getUser());
    }
}
