<?php
/**
 * Router Unit Tests — Page Titles
 *
 * Tests routing functions from src/router.php:
 * - getPageTitle()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/router.php';

class RouterPageTitleTest extends TestCase
{
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
