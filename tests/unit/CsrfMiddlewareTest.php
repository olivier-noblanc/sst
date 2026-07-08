<?php
use PHPUnit\Framework\TestCase;
use App\Middleware\CsrfMiddleware;

class CsrfMiddlewareTest extends TestCase
{
    private CsrfMiddleware $middleware;
    private bool $nextCalled;

    protected function setUp(): void
    {
        $this->middleware = new CsrfMiddleware();
        $this->nextCalled = false;

        // Reset superglobals
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SESSION);
        $GLOBALS['_PHP_REDIRECT'] = null;
    }

    private function callNext(): void
    {
        $this->nextCalled = true;
    }

    // ─── GET requests ───────────────────────────────────────────────────────

    public function testGetRequestSkipsCsrfCheck(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->middleware->__invoke(fn() => $this->callNext());

        $this->assertTrue($this->nextCalled);
    }

    public function testGetRequestDoesNotRedirect(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->middleware->__invoke(fn() => $this->callNext());

        $this->assertNull($GLOBALS['_PHP_REDIRECT']);
    }

    // ─── POST with valid CSRF token ────────────────────────────────────────

    public function testPostWithValidTokenProceeds(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = 'valid-token-123';
        $_SESSION['csrf_tokens'][$token] = true;
        $_POST['csrf_token'] = $token;

        $this->middleware->__invoke(fn() => $this->callNext());

        $this->assertTrue($this->nextCalled);
    }

    public function testPostWithValidTokenConsumesToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $token = 'single-use-token';
        $_SESSION['csrf_tokens'][$token] = true;
        $_POST['csrf_token'] = $token;

        $this->middleware->__invoke(fn() => $this->callNext());

        // Token should be consumed (removed from session)
        $this->assertArrayNotHasKey($token, $_SESSION['csrf_tokens']);
    }

    // ─── POST with invalid CSRF token ──────────────────────────────────────

    public function testPostWithInvalidTokenRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = 'bad-token';
        $_SESSION['csrf_tokens'] = [];

        $this->expectException(\Exception::class);

        $this->middleware->__invoke(fn() => $this->callNext());
    }

    public function testPostWithEmptyTokenRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = '';
        $_SESSION['csrf_tokens'] = [];

        $this->expectException(\Exception::class);

        $this->middleware->__invoke(fn() => $this->callNext());
    }

    public function testPostWithMissingTokenRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_POST['csrf_token']);
        $_SESSION['csrf_tokens'] = [];

        $this->expectException(\Exception::class);

        $this->middleware->__invoke(fn() => $this->callNext());
    }

    public function testPostWithInvalidTokenDoesNotCallNext(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = 'wrong';
        $_SESSION['csrf_tokens'] = [];

        try {
            $this->middleware->__invoke(fn() => $this->callNext());
        } catch (\Exception $e) {
            // redirect() calls exit, which throws in test
        }

        $this->assertFalse($this->nextCalled);
    }

    public function testPostWithInvalidTokenSetsFlashError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = 'invalid';
        $_SESSION['csrf_tokens'] = [];

        try {
            $this->middleware->__invoke(fn() => $this->callNext());
        } catch (\Exception $e) {
            // redirect() calls exit
        }

        $this->assertEquals('error', $_SESSION['flash']['type']);
        $this->assertEquals('Erreur de sécurité.', $_SESSION['flash']['message']);
    }
}
