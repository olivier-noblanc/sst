<?php
/**
 * Router Unit Tests — Application SST DREETS BFC
 *
 * Tests routing functions from src/router.php:
 * - getValidPages()
 * - validatePage()
 * - getHandlerMap()
 * - getPageTitle()
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

        // Core pages
        $this->assertContains('home', $pages);
        $this->assertContains('logout', $pages);

        // Report pages
        $this->assertContains('report_create', $pages);
        $this->assertContains('report_list', $pages);
        $this->assertContains('report_view', $pages);
        $this->assertContains('report_edit', $pages);
        $this->assertContains('report_respond', $pages);
        $this->assertContains('report_abandon', $pages);
        $this->assertContains('report_reopen', $pages);

        // Admin pages
        $this->assertContains('settings', $pages);
        $this->assertContains('users', $pages);
        $this->assertContains('user_edit', $pages);

        // Info pages
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
        // Page names are case-sensitive — 'Home' is not valid
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
        $map = getHandlerMap();
        $this->assertArrayHasKey('report_create', $map);
    }

    public function testGetHandlerMapContainsReportEdit(): void
    {
        $map = getHandlerMap();
        $this->assertArrayHasKey('report_edit', $map);
    }

    public function testGetHandlerMapContainsReportRespond(): void
    {
        $map = getHandlerMap();
        $this->assertArrayHasKey('report_respond', $map);
    }

    public function testGetHandlerMapContainsSettings(): void
    {
        $map = getHandlerMap();
        $this->assertArrayHasKey('settings', $map);
    }

    public function testGetHandlerMapContainsUserEdit(): void
    {
        $map = getHandlerMap();
        $this->assertArrayHasKey('user_edit', $map);
    }

    public function testGetHandlerMapContainsImpersonate(): void
    {
        $map = getHandlerMap();
        $this->assertArrayHasKey('impersonate', $map);
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

    // ─── getPageTitle ──────────────────────────────────────────────────────

    public function testGetPageTitleHome(): void
    {
        $this->assertEquals('Accueil', getPageTitle('home'));
    }

    public function testGetPageTitlePreamble(): void
    {
        $this->assertEquals('Préambule', getPageTitle('preamble'));
    }

    public function testGetPageTitleHelp(): void
    {
        $this->assertEquals('Documentation', getPageTitle('help'));
    }

    public function testGetPageTitleReportCreate(): void
    {
        $_GET['type'] = 'rsst';
        $this->assertStringContainsString('Signaler un événement', getPageTitle('report_create'));
    }

    public function testGetPageTitleReportView(): void
    {
        $this->assertEquals('Signalement', getPageTitle('report_view'));
    }

    public function testGetPageTitleReportEdit(): void
    {
        $this->assertEquals('Modifier le signalement', getPageTitle('report_edit'));
    }

    public function testGetPageTitleReportRespond(): void
    {
        $this->assertEquals('Répondre au signalement', getPageTitle('report_respond'));
    }

    public function testGetPageTitleReportReopen(): void
    {
        $this->assertEquals('Réouvrir le signalement', getPageTitle('report_reopen'));
    }

    public function testGetPageTitleSynthesis(): void
    {
        $this->assertEquals('Synthèse des signalements', getPageTitle('synthesis'));
    }

    public function testGetPageTitleStatistics(): void
    {
        $this->assertEquals('Statistiques', getPageTitle('statistics'));
    }

    public function testGetPageTitleExport(): void
    {
        $this->assertEquals('Export des données', getPageTitle('export'));
    }

    public function testGetPageTitleSettings(): void
    {
        $this->assertStringContainsString('Paramètres', getPageTitle('settings'));
    }

    public function testGetPageTitleUsers(): void
    {
        $this->assertStringContainsString('utilisateurs', getPageTitle('users'));
    }

    public function testGetPageTitleLogs(): void
    {
        $this->assertEquals('Journal', getPageTitle('logs'));
    }

    public function testGetPageTitleAccessDenied(): void
    {
        $this->assertEquals('Accès refusé', getPageTitle('access_denied'));
    }

    public function testGetPageTitleUnknownPageReturnsAccueil(): void
    {
        $this->assertEquals('Accueil', getPageTitle('unknown_page'));
    }

    public function testGetPageTitleEmptyStringReturnsAccueil(): void
    {
        $this->assertEquals('Accueil', getPageTitle(''));
    }

    public function testGetPageTitleChooseSite(): void
    {
        $this->assertStringContainsString('Choisir', getPageTitle('choose_site'));
    }

    public function testGetPageTitleUserEdit(): void
    {
        $this->assertStringContainsString('Éditer', getPageTitle('user_edit'));
    }

    public function testGetPageTitleUserView(): void
    {
        $this->assertStringContainsString('Profil', getPageTitle('user_view'));
    }
}
