<?php
use PHPUnit\Framework\TestCase;
use App\Middleware\Pipeline;

class PipelineTest extends TestCase
{
    // ─── Basic pipeline ─────────────────────────────────────────────────────

    public function testRunWithNoMiddlewaresCallsFinal(): void
    {
        $pipeline = new Pipeline();
        $result = $pipeline->run(function ($req) {
            return 'final';
        });

        $this->assertEquals('final', $result);
    }

    public function testRunWithSingleMiddlewareCallsIt(): void
    {
        $called = false;
        $pipeline = new Pipeline();
        $pipeline->pipe(function ($req, $next) use (&$called) {
            $called = true;
            return $next($req);
        });

        $pipeline->run(function ($req) {
            return 'done';
        });

        $this->assertTrue($called);
    }

    public function testSingleMiddlewareCanShortCircuit(): void
    {
        $pipeline = new Pipeline();
        $pipeline->pipe(function ($req, $next) {
            return 'blocked';
        });

        $result = $pipeline->run(function ($req) {
            return 'final';
        });

        $this->assertEquals('blocked', $result);
    }

    // ─── Multiple middlewares ───────────────────────────────────────────────

    public function testMiddlewaresRunInOrder(): void
    {
        $order = [];
        $pipeline = new Pipeline();

        $pipeline->pipe(function ($req, $next) use (&$order) {
            $order[] = 'first';
            return $next($req);
        });
        $pipeline->pipe(function ($req, $next) use (&$order) {
            $order[] = 'second';
            return $next($req);
        });
        $pipeline->pipe(function ($req, $next) use (&$order) {
            $order[] = 'third';
            return $next($req);
        });

        $pipeline->run(function ($req) use (&$order) {
            $order[] = 'final';
            return 'done';
        });

        $this->assertEquals(['first', 'second', 'third', 'final'], $order);
    }

    public function testMiddlewaresCanTransformResult(): void
    {
        $pipeline = new Pipeline();

        $pipeline->pipe(function ($req, $next) {
            $result = $next($req);
            return strtoupper($result);
        });
        $pipeline->pipe(function ($req, $next) {
            $result = $next($req);
            return $result . '!';
        });

        $result = $pipeline->run(function ($req) {
            return 'hello';
        });

        $this->assertEquals('HELLO!', $result);
    }

    // ─── Pipe returns self for chaining ─────────────────────────────────────

    public function testPipeReturnsSelfForChaining(): void
    {
        $pipeline = new Pipeline();

        $result = $pipeline
            ->pipe(function ($req, $next) { return $next($req); })
            ->pipe(function ($req, $next) { return $next($req); });

        $this->assertSame($pipeline, $result);
    }

    // ─── Request is passed through ──────────────────────────────────────────

    public function testRequestIsPassedThroughMiddlewares(): void
    {
        $pipeline = new Pipeline();
        $receivedRequest = null;

        $pipeline->pipe(function ($req, $next) use (&$receivedRequest) {
            $receivedRequest = $req;
            return $next($req);
        });

        $pipeline->run(function ($req) use (&$receivedRequest) {
            $receivedRequest = $req;
            return 'ok';
        });

        $this->assertEquals('test-data', $receivedRequest);
    }

    // ─── Middleware can modify request ──────────────────────────────────────

    public function testMiddlewareCanModifyRequest(): void
    {
        $pipeline = new Pipeline();

        $pipeline->pipe(function ($req, $next) {
            return $next($req . '-modified');
        });

        $result = $pipeline->run(function ($req) {
            return $req;
        });

        $this->assertEquals('original-modified', $result);
    }

    // ─── Empty pipeline with arguments ──────────────────────────────────────

    public function testRunPassesArgumentsToFinal(): void
    {
        $pipeline = new Pipeline();

        $result = $pipeline->run(function ($req) {
            return $req;
        });

        $this->assertNull($result);
    }
}
