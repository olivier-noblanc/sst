<?php
/**
 * Router Unit Tests — Valid Pages, Validation, Handler Map
 *
 * Tests the App\Router\Router class methods:
 * - getValidPages()
 * - validatePage()
 * - getHandlerMap()
 */

use PHPUnit\Framework\TestCase;
use App\Router\Router;

require_once __DIR__ . '/../../src/router.php';
require_once __DIR__ . '/../../src/Router/routes.php';

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = getRouter();
    }

    // ─── getValidPages ─────────────────────────────────────────────────────

    public function testGetValidPagesReturnsArray(): void
    {
        $pages = $this->router->getValidPages();
        $this->assertIsArray($pages);
    }

    public function testGetValidPagesContainsEssentialPages(): void
    {
        $pages = $this->router->getValidPages();
        $this->assertContains('home', $pages);
        $this->assertContains('logout', $pages);
        $this->assertContains('report_create', $pages);
        $this->assertContains('report_list', $pages);
        $this->assertContains('report_view', $pages);
        $this->assertContains('report_edit', $pages);
        $this->assertContains('report_respond', $pages);
        $this->assertContains('report_abandon', $pages);
        $this->assertContains('report_reopen', $pages);
        $this->assertContains('settings', $pages);
        $this->assertContains('users', $pages);
        $this->assertContains('user_edit', $pages);
        $this->assertContains('help', $pages);
        $this->assertContains('changelog', $pages);
        $this->assertContains('preamble', $pages);
    }

    public function testGetValidPagesDoesNotContainEmptyStrings(): void
    {
        $pages = $this->router->getValidPages();
        foreach ($pages as $page) {
            $this->assertNotEmpty($page, "Valid pages should not contain empty strings");
        }
    }

    public function testGetValidPagesAreAllStrings(): void
    {
        $pages = $this->router->getValidPages();
        foreach ($pages as $page) {
            $this->assertIsString($page, "Each page name should be a string");
        }
    }

    public function testGetValidPagesAreUnique(): void
    {
        $pages = $this->router->getValidPages();
        $uniquePages = array_unique($pages);
        $this->assertCount(count($pages), $uniquePages, 'All page names should be unique');
    }

    // ─── validatePage ──────────────────────────────────────────────────────

    public function testValidatePageWithValidHomePage(): void
    {
        $this->assertEquals('home', $this->router->validatePage('home'));
    }

    public function testValidatePageWithValidReportCreate(): void
    {
        $this->assertEquals('report_create', $this->router->validatePage('report_create'));
    }

    public function testValidatePageWithValidSettings(): void
    {
        $this->assertEquals('settings', $this->router->validatePage('settings'));
    }

    public function testValidatePageWithInvalidPageReturnsHome(): void
    {
        $this->assertEquals('home', $this->router->validatePage('nonexistent'));
    }

    public function testValidatePageWithEmptyStringReturnsHome(): void
    {
        $this->assertEquals('home', $this->router->validatePage(''));
    }

    public function testValidatePageWithMaliciousInputReturnsHome(): void
    {
        $this->assertEquals('home', $this->router->validatePage('../../../etc/passwd'));
    }

    public function testValidatePageWithXssInputReturnsHome(): void
    {
        $this->assertEquals('home', $this->router->validatePage('<script>alert(1)</script>'));
    }

    public function testValidatePageCaseSensitive(): void
    {
        $this->assertEquals('home', $this->router->validatePage('Home'));
        $this->assertEquals('home', $this->router->validatePage('HOME'));
        $this->assertEquals('home', $this->router->validatePage('Settings'));
    }

    public function testValidatePageAllValidPages(): void
    {
        $pages = $this->router->getValidPages();
        foreach ($pages as $page) {
            $this->assertEquals($page, $this->router->validatePage($page), "validatePage('$page') should return '$page'");
        }
    }

    public function testValidatePageWithSqlInjectionReturnsHome(): void
    {
        $this->assertEquals('home', $this->router->validatePage("'; DROP TABLE users; --"));
    }

    public function testValidatePageWithNumericStringReturnsHome(): void
    {
        $this->assertEquals('home', $this->router->validatePage('123'));
    }

    // ─── getHandlerMap ──────────────────────────────────────────────────────

    public function testGetHandlerMapReturnsArray(): void
    {
        $map = $this->router->getHandlerMap();
        $this->assertIsArray($map);
    }

    public function testGetHandlerMapContainsReportCreate(): void
    {
        $this->assertArrayHasKey('report_create', $this->router->getHandlerMap());
    }

    public function testGetHandlerMapContainsReportEdit(): void
    {
        $this->assertArrayHasKey('report_edit', $this->router->getHandlerMap());
    }

    public function testGetHandlerMapContainsReportRespond(): void
    {
        $this->assertArrayHasKey('report_respond', $this->router->getHandlerMap());
    }

    public function testGetHandlerMapContainsSettings(): void
    {
        $this->assertArrayHasKey('settings', $this->router->getHandlerMap());
    }

    public function testGetHandlerMapContainsUserEdit(): void
    {
        $this->assertArrayHasKey('user_edit', $this->router->getHandlerMap());
    }

    public function testGetHandlerMapContainsImpersonate(): void
    {
        $this->assertArrayHasKey('impersonate', $this->router->getHandlerMap());
    }

    public function testGetHandlerMapValuesAreStrings(): void
    {
        $map = $this->router->getHandlerMap();
        foreach ($map as $page => $path) {
            $this->assertIsString($path, "Handler path for '$page' should be a string");
        }
    }

    public function testGetHandlerMapHandlerPathsContainHandlerDirectory(): void
    {
        $map = $this->router->getHandlerMap();
        foreach ($map as $page => $path) {
            $this->assertStringContainsString('handlers', $path,
                "Handler path for '$page' should contain 'handlers' directory");
        }
    }

    public function testGetHandlerMapHandlerPathsEndWithPhp(): void
    {
        $map = $this->router->getHandlerMap();
        foreach ($map as $page => $path) {
            $this->assertStringEndsWith('.php', $path,
                "Handler path for '$page' should end with .php");
        }
    }
}
