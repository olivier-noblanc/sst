<?php
/**
 * Router Unit Tests — Valid Pages, Validation, Handler Map
 *
 * Tests routing functions from src/router.php:
 * - getValidPages()
 * - validatePage()
 * - getHandlerMap()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/router.php';

class RouterTest extends TestCase
{
    // ─── getValidPages ─────────────────────────────────────────────────────

    public function testGetValidPagesReturnsArray(): void
    {
        $pages = getValidPages();
        $this->assertIsArray($pages);
    }

    public function testGetValidPagesContainsEssentialPages(): void
    {
        $pages = getValidPages();
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
        $pages = getValidPages();
        foreach ($pages as $page) {
            $this->assertNotEmpty($page, "Valid pages should not contain empty strings");
        }
    }

    public function testGetValidPagesAreAllStrings(): void
    {
        $pages = getValidPages();
        foreach ($pages as $page) {
            $this->assertIsString($page, "Each page name should be a string");
        }
    }

    public function testGetValidPagesAreUnique(): void
    {
        $pages = getValidPages();
        $uniquePages = array_unique($pages);
        $this->assertCount(count($pages), $uniquePages, 'All page names should be unique');
    }

    // ─── validatePage ──────────────────────────────────────────────────────

    public function testValidatePageWithValidHomePage(): void
    {
        $this->assertEquals('home', validatePage('home'));
    }

    public function testValidatePageWithValidReportCreate(): void
    {
        $this->assertEquals('report_create', validatePage('report_create'));
    }

    public function testValidatePageWithValidSettings(): void
    {
        $this->assertEquals('settings', validatePage('settings'));
    }

    public function testValidatePageWithInvalidPageReturnsHome(): void
    {
        $this->assertEquals('home', validatePage('nonexistent'));
    }

    public function testValidatePageWithEmptyStringReturnsHome(): void
    {
        $this->assertEquals('home', validatePage(''));
    }

    public function testValidatePageWithMaliciousInputReturnsHome(): void
    {
        $this->assertEquals('home', validatePage('../../../etc/passwd'));
    }

    public function testValidatePageWithXssInputReturnsHome(): void
    {
        $this->assertEquals('home', validatePage('<script>alert(1)</script>'));
    }

    public function testValidatePageCaseSensitive(): void
    {
        $this->assertEquals('home', validatePage('Home'));
        $this->assertEquals('home', validatePage('HOME'));
        $this->assertEquals('home', validatePage('Settings'));
    }

    public function testValidatePageAllValidPages(): void
    {
        $pages = getValidPages();
        foreach ($pages as $page) {
            $this->assertEquals($page, validatePage($page), "validatePage('$page') should return '$page'");
        }
    }

    public function testValidatePageWithSqlInjectionReturnsHome(): void
    {
        $this->assertEquals('home', validatePage("'; DROP TABLE users; --"));
    }

    public function testValidatePageWithNumericStringReturnsHome(): void
    {
        $this->assertEquals('home', validatePage('123'));
    }

    // ─── getHandlerMap ──────────────────────────────────────────────────────

    public function testGetHandlerMapReturnsArray(): void
    {
        $map = getHandlerMap();
        $this->assertIsArray($map);
    }

    public function testGetHandlerMapContainsReportCreate(): void
    {
        $this->assertArrayHasKey('report_create', getHandlerMap());
    }

    public function testGetHandlerMapContainsReportEdit(): void
    {
        $this->assertArrayHasKey('report_edit', getHandlerMap());
    }

    public function testGetHandlerMapContainsReportRespond(): void
    {
        $this->assertArrayHasKey('report_respond', getHandlerMap());
    }

    public function testGetHandlerMapContainsSettings(): void
    {
        $this->assertArrayHasKey('settings', getHandlerMap());
    }

    public function testGetHandlerMapContainsUserEdit(): void
    {
        $this->assertArrayHasKey('user_edit', getHandlerMap());
    }

    public function testGetHandlerMapContainsImpersonate(): void
    {
        $this->assertArrayHasKey('impersonate', getHandlerMap());
    }

    public function testGetHandlerMapValuesAreStrings(): void
    {
        $map = getHandlerMap();
        foreach ($map as $page => $path) {
            $this->assertIsString($path, "Handler path for '$page' should be a string");
        }
    }

    public function testGetHandlerMapHandlerPathsContainHandlerDirectory(): void
    {
        $map = getHandlerMap();
        foreach ($map as $page => $path) {
            $this->assertStringContainsString('handlers', $path,
                "Handler path for '$page' should contain 'handlers' directory");
        }
    }

    public function testGetHandlerMapHandlerPathsEndWithPhp(): void
    {
        $map = getHandlerMap();
        foreach ($map as $page => $path) {
            $this->assertStringEndsWith('.php', $path,
                "Handler path for '$page' should end with .php");
        }
    }
}
