<?php
/**
 * Router Unit Tests — Page Titles
 *
 * Tests the App\Router\Router class getPageTitle() method.
 */

use PHPUnit\Framework\TestCase;
use App\Router\Router;

require_once __DIR__ . '/../../src/Router/Renderer.php';
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

    // ─── app_report_create_label (personnalisation admin) ──────────────────

    public function testReportCreateTitleDefaultsToSignalerUnEvenement(): void
    {
        // getRouter() is a process-wide singleton (static $router in
        // routes.php) — by the time this test runs, it may already be
        // cached from an earlier test in the same PHPUnit run. Call
        // createRouter() directly to get a fresh instance that actually
        // re-reads config, matching what happens on every real HTTP
        // request in production (a new PHP process each time).
        \App\Services\ConfigService::getInstance()->set('app_report_create_label', 'Signaler un événement');
        \App\Services\ConfigService::getInstance()->clearCache();

        $freshRouter = createRouter();

        $this->assertEquals('Signaler un événement', $freshRouter->getPageTitle('report_create'));
    }

    public function testReportCreateTitleReflectsCustomConfigValue(): void
    {
        \App\Services\ConfigService::getInstance()->set('app_report_create_label', 'Déclarer un incident');
        \App\Services\ConfigService::getInstance()->clearCache();

        $freshRouter = createRouter();

        $this->assertEquals('Déclarer un incident', $freshRouter->getPageTitle('report_create'));

        // Reset for any test running after this one in the same process.
        \App\Services\ConfigService::getInstance()->set('app_report_create_label', 'Signaler un événement');
        \App\Services\ConfigService::getInstance()->clearCache();
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
