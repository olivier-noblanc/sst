<?php
/**
 * Router Unit Tests — Page Titles
 *
 * Tests the App\Router\Router class getPageTitle() method.
 */

use PHPUnit\Framework\TestCase;
use App\Router\Router;

require_once __DIR__ . '/../../src/router.php';
require_once __DIR__ . '/../../src/Router/routes.php';

class RouterPageTitleTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = getRouter();
    }

    public function testGetPageTitleHome(): void
    {
        $this->assertEquals('Accueil', $this->router->getPageTitle('home'));
    }

    public function testGetPageTitlePreamble(): void
    {
        $this->assertEquals('Préambule', $this->router->getPageTitle('preamble'));
    }

    public function testGetPageTitleHelp(): void
    {
        $this->assertEquals('Documentation', $this->router->getPageTitle('help'));
    }

    public function testGetPageTitleReportCreate(): void
    {
        $_GET['type'] = 'rsst';
        $this->assertStringContainsString('Signaler un événement', $this->router->getPageTitle('report_create'));
    }

    public function testGetPageTitleReportView(): void
    {
        $this->assertEquals('Signalement', $this->router->getPageTitle('report_view'));
    }

    public function testGetPageTitleReportEdit(): void
    {
        $this->assertEquals('Modifier le signalement', $this->router->getPageTitle('report_edit'));
    }

    public function testGetPageTitleReportRespond(): void
    {
        $this->assertEquals('Répondre au signalement', $this->router->getPageTitle('report_respond'));
    }

    public function testGetPageTitleReportReopen(): void
    {
        $this->assertEquals('Réouvrir le signalement', $this->router->getPageTitle('report_reopen'));
    }

    public function testGetPageTitleSynthesis(): void
    {
        $this->assertEquals('Synthèse des signalements', $this->router->getPageTitle('synthesis'));
    }

    public function testGetPageTitleStatistics(): void
    {
        $this->assertEquals('Statistiques', $this->router->getPageTitle('statistics'));
    }

    public function testGetPageTitleExport(): void
    {
        $this->assertEquals('Export des données', $this->router->getPageTitle('export'));
    }

    public function testGetPageTitleSettings(): void
    {
        $this->assertStringContainsString('Paramètres', $this->router->getPageTitle('settings'));
    }

    public function testGetPageTitleUsers(): void
    {
        $this->assertStringContainsString('utilisateurs', $this->router->getPageTitle('users'));
    }

    public function testGetPageTitleLogs(): void
    {
        $this->assertEquals('Journal', $this->router->getPageTitle('logs'));
    }

    public function testGetPageTitleAccessDenied(): void
    {
        $this->assertEquals('Accès refusé', $this->router->getPageTitle('access_denied'));
    }

    public function testGetPageTitleUnknownPageReturnsAccueil(): void
    {
        $this->assertEquals('Accueil', $this->router->getPageTitle('unknown_page'));
    }

    public function testGetPageTitleEmptyStringReturnsAccueil(): void
    {
        $this->assertEquals('Accueil', $this->router->getPageTitle(''));
    }

    public function testGetPageTitleChooseSite(): void
    {
        $this->assertStringContainsString('Choisir', $this->router->getPageTitle('choose_site'));
    }

    public function testGetPageTitleUserEdit(): void
    {
        $this->assertStringContainsString('Éditer', $this->router->getPageTitle('user_edit'));
    }

    public function testGetPageTitleUserView(): void
    {
        $this->assertStringContainsString('Profil', $this->router->getPageTitle('user_view'));
    }
}
