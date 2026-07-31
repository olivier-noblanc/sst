<?php
/**
 * Session Helper Unit Tests — Impersonation, User Session, Intended URL
 *
 * Tests session management functions from src/session.php:
 * - startImpersonation() / stopImpersonation() / isImpersonatingRole()
 * - setUserSession() / getUserSession() / isUserLoggedIn() / clearSession()
 * - setIntendedUrl() / getIntendedUrl() / clearIntendedUrl()
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SessionUser;

require_once __DIR__ . '/../../src/session.php';

class SessionHelperImpersonateTest extends TestCase
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

    // ─── Impersonation ─────────────────────────────────────────────────────

    public function testStartImpersonationSetsSession(): void
    {
        setUserSession(SessionUser::fromArray(['id' => 1, 'role' => 'superviseur']));

        startImpersonation('superviseur', 'agent');

        $this->assertTrue(isImpersonatingRole());
        $this->assertEquals('superviseur', $_SESSION['real_role']);
        $this->assertEquals('agent', $_SESSION['impersonated_role']);
        $user = getUserSession();
        $this->assertInstanceOf(SessionUser::class, $user);
        $this->assertEquals('agent', $user->role);
    }

    public function testStopImpersonationRestoresRole(): void
    {
        setUserSession(SessionUser::fromArray(['id' => 1, 'role' => 'agent']));

        startImpersonation('superviseur', 'agent');
        $this->assertTrue(isImpersonatingRole());

        $restoredRole = stopImpersonation();
        $this->assertEquals('superviseur', $restoredRole);
        $this->assertFalse(isImpersonatingRole());
        $user = getUserSession();
        $this->assertInstanceOf(SessionUser::class, $user);
        $this->assertEquals('superviseur', $user->role);
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
        setUserSession(SessionUser::fromArray(['id' => 1, 'role' => 'superviseur', 'nom' => 'Admin']));

        // Start impersonating agent
        startImpersonation('superviseur', 'agent');
        $this->assertTrue(isImpersonatingRole());
        $user = getUserSession();
        $this->assertInstanceOf(SessionUser::class, $user);
        $this->assertEquals('agent', $user->role);
        $this->assertEquals('superviseur', $_SESSION['real_role']);

        // Stop impersonation
        $restored = stopImpersonation();
        $this->assertEquals('superviseur', $restored);
        $this->assertFalse(isImpersonatingRole());
        $user = getUserSession();
        $this->assertInstanceOf(SessionUser::class, $user);
        $this->assertEquals('superviseur', $user->role);
        $this->assertArrayNotHasKey('real_role', $_SESSION);
        $this->assertArrayNotHasKey('impersonated_role', $_SESSION);
    }

    public function testImpersonationToChsct(): void
    {
        setUserSession(SessionUser::fromArray(['id' => 1, 'role' => 'superviseur']));

        startImpersonation('superviseur', 'chsct');
        $this->assertTrue(isImpersonatingRole());
        $user = getUserSession();
        $this->assertInstanceOf(SessionUser::class, $user);
        $this->assertEquals('chsct', $user->role);

        stopImpersonation();
        $user = getUserSession();
        $this->assertInstanceOf(SessionUser::class, $user);
        $this->assertEquals('superviseur', $user->role);
    }

    // ─── User Session ──────────────────────────────────────────────────────

    public function testSetUserSessionAndGetUserSession(): void
    {
        $user = SessionUser::fromArray(['id' => 5, 'nom' => 'Dupont', 'role' => 'agent']);
        setUserSession($user);
        $restored = getUserSession();
        $this->assertInstanceOf(SessionUser::class, $restored);
        $this->assertSame(5, $restored->id);
        $this->assertSame('Dupont', $restored->nom);
        $this->assertSame('agent', $restored->role);
    }

    public function testGetUserSessionReturnsNullWhenNotSet(): void
    {
        $this->assertNull(getUserSession());
    }

    public function testIsUserLoggedInReturnsTrueWhenSet(): void
    {
        setUserSession(SessionUser::fromArray(['id' => 1]));
        $this->assertTrue(isUserLoggedIn());
    }

    public function testIsUserLoggedInReturnsFalseWhenNotSet(): void
    {
        $this->assertFalse(isUserLoggedIn());
    }

    public function testClearSessionRemovesAllData(): void
    {
        setUserSession(SessionUser::fromArray(['id' => 1]));
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
