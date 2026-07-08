<?php
use PHPUnit\Framework\TestCase;
use App\Middleware\AuthMiddleware;

class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $middleware;
    private bool $nextCalled;

    protected function setUp(): void
    {
        $this->middleware = new AuthMiddleware();
        $this->nextCalled = false;

        // Reset session and globals
        unset($_SESSION);
        $GLOBALS['_PHP_REDIRECT'] = null;
    }

    private function callNext(): void
    {
        $this->nextCalled = true;
    }

    // ─── User is logged in ──────────────────────────────────────────────────

    public function testLoggedInUserProceeds(): void
    {
        $_SESSION['user'] = ['id' => 1, 'role' => 'agent'];

        $this->middleware->__invoke(fn() => $this->callNext());

        $this->assertTrue($this->nextCalled);
    }

    public function testLoggedInUserWithMinimalSessionProceeds(): void
    {
        $_SESSION['user'] = ['id' => 1];

        $this->middleware->__invoke(fn() => $this->callNext());

        $this->assertTrue($this->nextCalled);
    }

    // ─── User is NOT logged in (DEV_MODE = true) ────────────────────────────

    public function testUnauthenticatedUserRedirectsInDevMode(): void
    {
        unset($_SESSION['user']);
        // DEV_MODE is true in bootstrap.php

        $this->expectException(\Exception::class);

        $this->middleware->__invoke(fn() => $this->callNext());
    }

    public function testUnauthenticatedUserDoesNotCallNextInDevMode(): void
    {
        unset($_SESSION['user']);

        try {
            $this->middleware->__invoke(fn() => $this->callNext());
        } catch (\Exception $e) {
            // redirect() calls exit
        }

        $this->assertFalse($this->nextCalled);
    }

    public function testUnauthenticatedUserSetsIntendedUrlInDevMode(): void
    {
        unset($_SESSION['user']);
        $_SERVER['REQUEST_URI'] = '/index.php?page=report_view&id=42';

        try {
            $this->middleware->__invoke(fn() => $this->callNext());
        } catch (\Exception $e) {
            // redirect() calls exit
        }

        // setIntendedUrl stores the URL in session
        $this->assertEquals('/index.php?page=report_view&id=42', $_SESSION['intended_url'] ?? '');
    }

    public function testUnauthenticatedUserRedirectsToLoginInDevMode(): void
    {
        unset($_SESSION['user']);

        try {
            $this->middleware->__invoke(fn() => $this->callNext());
        } catch (\Exception $e) {
            // redirect() calls exit
        }

        $this->assertStringContainsString('login', $GLOBALS['_PHP_REDIRECT'] ?? '');
    }

    // ─── User is NOT logged in (DEV_MODE = false) ───────────────────────────

    public function testUnauthenticatedUserShowsErrorInProdMode(): void
    {
        // Temporarily override DEV_MODE
        $originalDevMode = constant('DEV_MODE');
        redefine('DEV_MODE', false);

        unset($_SESSION['user']);

        ob_start();
        try {
            $this->middleware->__invoke(fn() => $this->callNext());
        } catch (\Exception $e) {
            // exit() throws in test
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('Erreur de configuration', $output);
        $this->assertStringContainsString('Auth non disponible', $output);

        // Restore DEV_MODE
        redefine('DEV_MODE', $originalDevMode);
    }

    public function testUnauthenticatedUserDoesNotCallNextInProdMode(): void
    {
        $originalDevMode = constant('DEV_MODE');
        redefine('DEV_MODE', false);

        unset($_SESSION['user']);

        ob_start();
        try {
            $this->middleware->__invoke(fn() => $this->callNext());
        } catch (\Exception $e) {
            // exit() throws in test
        }
        ob_end_clean();

        $this->assertFalse($this->nextCalled);

        redefine('DEV_MODE', $originalDevMode);
    }

    // ─── Empty session user ─────────────────────────────────────────────────

    public function testEmptyUserArrayRedirectsInDevMode(): void
    {
        $_SESSION['user'] = [];

        $this->expectException(\Exception::class);

        $this->middleware->__invoke(fn() => $this->callNext());
    }
}
