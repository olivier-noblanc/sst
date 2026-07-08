<?php
use PHPUnit\Framework\TestCase;
use App\Middleware\RoleMiddleware;

class RoleMiddlewareTest extends TestCase
{
    private bool $nextCalled;

    protected function setUp(): void
    {
        $this->nextCalled = false;

        // Reset session and globals
        unset($_SESSION);
        $GLOBALS['_PHP_REDIRECT'] = null;
    }

    private function callNext(): void
    {
        $this->nextCalled = true;
    }

    private function setUserRole(string $role): void
    {
        $_SESSION['user'] = ['role' => $role];
    }

    // ─── User has matching role ─────────────────────────────────────────────

    public function testUserWithMatchingRoleProceeds(): void
    {
        $this->setUserRole('superviseur');
        $middleware = new RoleMiddleware(['superviseur']);

        $middleware->__invoke(fn() => $this->callNext());

        $this->assertTrue($this->nextCalled);
    }

    public function testUserWithOneOfMultipleRolesProceeds(): void
    {
        $this->setUserRole('chsct');
        $middleware = new RoleMiddleware(['superviseur', 'chsct']);

        $middleware->__invoke(fn() => $this->callNext());

        $this->assertTrue($this->nextCalled);
    }

    public function testUserWithFirstRoleInListProceeds(): void
    {
        $this->setUserRole('agent');
        $middleware = new RoleMiddleware(['agent', 'superviseur']);

        $middleware->__invoke(fn() => $this->callNext());

        $this->assertTrue($this->nextCalled);
    }

    // ─── User does NOT have matching role ───────────────────────────────────

    public function testUserWithWrongRoleRedirects(): void
    {
        $this->setUserRole('agent');
        $middleware = new RoleMiddleware(['superviseur']);

        $this->expectException(\Exception::class);

        $middleware->__invoke(fn() => $this->callNext());
    }

    public function testUserWithWrongRoleDoesNotCallNext(): void
    {
        $this->setUserRole('agent');
        $middleware = new RoleMiddleware(['superviseur', 'chsct']);

        try {
            $middleware->__invoke(fn() => $this->callNext());
        } catch (\Exception $e) {
            // redirect() calls exit
        }

        $this->assertFalse($this->nextCalled);
    }

    public function testUserWithWrongRoleSetsFlashError(): void
    {
        $this->setUserRole('agent');
        $middleware = new RoleMiddleware(['superviseur']);

        try {
            $middleware->__invoke(fn() => $this->callNext());
        } catch (\Exception $e) {
            // redirect() calls exit
        }

        $this->assertEquals('error', $_SESSION['flash']['type']);
        $this->assertEquals('Accès refusé.', $_SESSION['flash']['message']);
    }

    // ─── No user session ────────────────────────────────────────────────────

    public function testNoSessionRedirects(): void
    {
        unset($_SESSION['user']);
        $middleware = new RoleMiddleware(['agent']);

        $this->expectException(\Exception::class);

        $middleware->__invoke(fn() => $this->callNext());
    }

    public function testEmptyRoleArrayAllowsNoOne(): void
    {
        $this->setUserRole('superviseur');
        $middleware = new RoleMiddleware([]);

        $this->expectException(\Exception::class);

        $middleware->__invoke(fn() => $this->callNext());
    }

    // ─── Role comparison is strict ──────────────────────────────────────────

    public function testRoleComparisonIsStrict(): void
    {
        $this->setUserRole('agent');
        $middleware = new RoleMiddleware(['Agent']); // different case

        $this->expectException(\Exception::class);

        $middleware->__invoke(fn() => $this->callNext());
    }
}
