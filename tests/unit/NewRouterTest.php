<?php
use PHPUnit\Framework\TestCase;
use App\Router\Router;

class NewRouterTest extends TestCase
{
    public function testMatchReturnsHandlerForExactPath(): void
    {
        $router = new Router();
        $called = false;
        $router->addRoute('/reports', 'report_list', ['GET'], function() use (&$called) { $called = true; });
        $result = $router->match('GET', '/reports');
        $this->assertNotNull($result);
        $this->assertEquals('report_list', $result['name']);
        ($result['handler'])();
        $this->assertTrue($called);
    }

    public function testMatchReturnsNullForUnknownPath(): void
    {
        $router = new Router();
        $router->addRoute('/reports', 'report_list', ['GET'], function() {});
        $this->assertNull($router->match('GET', '/unknown'));
    }

    public function testMatchRespectsHttpMethod(): void
    {
        $router = new Router();
        $router->addRoute('/reports', 'report_list', ['GET'], function() {});
        $router->addRoute('/reports', 'report_create', ['POST'], function() {});
        $this->assertNotNull($router->match('GET', '/reports'));
        $this->assertNotNull($router->match('POST', '/reports'));
        $this->assertNull($router->match('DELETE', '/reports'));
    }

    public function testMatchExtractsParameters(): void
    {
        $router = new Router();
        $router->addRoute('/reports/{uuid}', 'report_view', ['GET'], function() {});
        $result = $router->match('GET', '/reports/abc-123');
        $this->assertNotNull($result);
        $this->assertEquals('abc-123', $result['params']['uuid']);
    }

    public function testMatchMultipleParameters(): void
    {
        $router = new Router();
        $router->addRoute('/users/{id}/reports/{uuid}', 'user_report', ['GET'], function() {});
        $result = $router->match('GET', '/users/42/reports/abc-123');
        $this->assertNotNull($result);
        $this->assertEquals('42', $result['params']['id']);
        $this->assertEquals('abc-123', $result['params']['uuid']);
    }

}
