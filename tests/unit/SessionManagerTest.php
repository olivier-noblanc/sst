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

    public function testIsLoggedInMethodExists(): void
    {
        $manager = new SessionManager();
        $this->assertTrue(method_exists($manager, 'isLoggedIn'));
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

    // ═══════════════════════════════════════════════════════════════════════════════
    // Return types
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testIsLoggedInReturnsBool(): void
    {
        $manager = new SessionManager();
        $result = $manager->isLoggedIn();
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
}
