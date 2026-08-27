<?php
/**
 * Tests pour les valeurs par défaut des DTO - tue les mutants DecrementInteger/IncrementInteger/Ternary
 * 
 * Mutants ciblés :
 * - ReportEventData::siteIdInt() ligne 66 : ?? 0 → ?? -1 ou ?? 1
 * - ReportEventData::userIdInt() ligne 74 : ?? 0 → ?? -1 ou ?? 1
 * - UserEventData::userIdInt() ligne 57 : ?? 0 → ?? -1 ou ?? 1
 * - SitesStatsView::getRows() ligne 47 : : 0 → : -1 ou : 1
 * - SitesStatsView::getRows() ligne 52 : : 0 → : -1 ou : 1
 * - SitesStatsView::getTotalByRegistry() ligne 66 : $total = 0 → $total = -1
 * - SitesStatsView::getGrandTotal() ligne 78 : $total = 0 → $total = -1
 * - CreateReportCommand::fromPost() ligne 84 : ?? 0 → ?? -1
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\DTO\ReportEventData;
use App\DTO\UserEventData;
use App\DTO\SitesStatsView;
use App\DTO\SiteStatsRow;
use App\DTO\CreateReportCommand;
use App\DTO\SiteId;
use App\DTO\ReportData;
use App\DTO\SessionUser;

class DtoDefaultValuesMutationTest extends TestCase
{
    // ═══ ReportEventData ═══

    public function testSiteIdIntReturnsZeroWhenSiteIdAndReportAreNull(): void
    {
        // Kill DecrementInteger/IncrementInteger/Ternary mutants on line 66
        $data = new ReportEventData(
            report: null,
            siteId: null,
        );
        $this->assertSame(0, $data->siteIdInt(), 'siteIdInt must return 0 when both siteId and report are null');
    }

    public function testSiteIdIntReturnsReportSiteIdWhenSiteIdIsNull(): void
    {
        $report = new ReportData(
            uuid: 'test-uuid',
            reference: 'REF-001',
            type: 'rsst',
            objet: 'Test',
            description: 'Test desc',
            dateEvenement: '2026-01-15',
            heureEvenement: '10:00',
            lieu: 'Test location',
            declarantId: 1,
            declarantNom: 'Test',
            declarantPrenom: 'User',
            pourCompteDe: '',
            pourCompteNom: '',
            pourComptePrenom: '',
            natureAuteur: '',
            typeActe: '',
            siteId: 42,
            siteText: 'Test site',
            pole: '',
            serviceAffectation: '',
            telephoneMobile: '',
            isConfidential: 0,
            consentSyndicat: 0,
            etat: 'nouveau',
            repondantId: null,
            dateReponse: null,
            reponse: null,
            attachmentName: null,
            attachmentMime: null,
            createdAt: '2026-01-15 10:00:00',
            updatedAt: '2026-01-15 10:00:00',
            siteCode: 'TEST',
            siteNom: 'Test Site',
            repondantNom: null,
            repondantPrenom: null,
        );
        
        $data = new ReportEventData(
            report: $report,
            siteId: null,
        );
        $this->assertSame(42, $data->siteIdInt());
    }

    public function testSiteIdIntReturnsSiteIdWhenNotNull(): void
    {
        $data = new ReportEventData(
            report: null,
            siteId: 99,
        );
        $this->assertSame(99, $data->siteIdInt());
    }

    public function testUserIdIntReturnsZeroWhenUserIdIsNull(): void
    {
        // Kill DecrementInteger/IncrementInteger mutants on line 74
        $data = new ReportEventData(
            userId: null,
        );
        $this->assertSame(0, $data->userIdInt(), 'userIdInt must return 0 when userId is null');
    }

    public function testUserIdIntReturnsUserIdWhenNotNull(): void
    {
        $data = new ReportEventData(
            userId: 123,
        );
        $this->assertSame(123, $data->userIdInt());
    }

    // ═══ UserEventData ═══

    public function testUserEventDataUserIdIntReturnsZeroWhenUserIdAndUserAreNull(): void
    {
        // Kill DecrementInteger/IncrementInteger/Ternary mutants on line 57
        $data = new UserEventData(
            user: null,
            userId: null,
        );
        $this->assertSame(0, $data->userIdInt(), 'userIdInt must return 0 when both userId and user are null');
    }

    public function testUserEventDataUserIdIntReturnsUserWhenUserIdIsNull(): void
    {
        $user = SessionUser::fromArray([
            'id' => 42,
            'username' => 'test',
            'nom' => 'Test',
            'prenom' => 'User',
            'role' => 'agent',
        ]);
        
        $data = new UserEventData(
            user: $user,
            userId: null,
        );
        $this->assertSame(42, $data->userIdInt());
    }

    public function testUserEventDataUserIdIntReturnsUserIdWhenNotNull(): void
    {
        $data = new UserEventData(
            userId: 55,
        );
        $this->assertSame(55, $data->userIdInt());
    }

    // ═══ SitesStatsView ═══

    public function testGetGrandTotalReturnsZeroWithEmptyStats(): void
    {
        // Kill DecrementInteger mutant on line 78: $total = 0 → $total = -1
        $view = new SitesStatsView(
            statsBySite: [],
            sites: [],
            registryCodes: [],
        );
        $this->assertSame(0, $view->getGrandTotal(), 'getGrandTotal must return 0 with empty stats');
    }

    public function testGetTotalByRegistryReturnsZeroWithEmptyStats(): void
    {
        // Kill DecrementInteger mutant on line 66: $total = 0 → $total = -1
        $view = new SitesStatsView(
            statsBySite: [],
            sites: [],
            registryCodes: ['rsst'],
        );
        $this->assertSame(0, $view->getTotalByRegistry('rsst'), 'getTotalByRegistry must return 0 with empty stats');
    }

    public function testGetRowsReturnsEmptyArrayWithNoSites(): void
    {
        // Kill Foreach_ mutant on line 40
        $view = new SitesStatsView(
            statsBySite: [],
            sites: [],
            registryCodes: [],
        );
        $this->assertSame([], $view->getRows());
    }

    public function testGetRowsReturnsCorrectTotalWithMatchingStats(): void
    {
        // Kill DecrementInteger/IncrementInteger on line 47: : 0 → : -1 or : 1
        $statsRow = new SiteStatsRow(
            code: 'UR21',
            total: 5,
            registryCounts: ['rsst' => 3, 'rami' => 2],
        );
        
        $view = new SitesStatsView(
            statsBySite: [$statsRow],
            sites: [['code' => 'UR21', 'nom' => 'Test Site', 'id' => 1]],
            registryCodes: ['rsst', 'rami'],
        );
        
        $rows = $view->getRows();
        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows[0]['total'], 'total must match stats when site matches');
    }

    public function testGetRowsReturnsZeroTotalWhenNoMatchingStats(): void
    {
        // Kill DecrementInteger/IncrementInteger on line 47: no match → 0
        $view = new SitesStatsView(
            statsBySite: [],
            sites: [['code' => 'UR21', 'nom' => 'Test Site', 'id' => 1]],
            registryCodes: ['rsst'],
        );
        
        $rows = $view->getRows();
        $this->assertCount(1, $rows);
        $this->assertSame(0, $rows[0]['total'], 'total must be 0 when no matching stats');
    }

    // ═══ CreateReportCommand ═══

    public function testFromPostSiteIdDefaultsToNoneWhenSiteIdMissing(): void
    {
        // Kill DecrementInteger mutant on line 84: ?? 0 → ?? -1
        $post = [
            'type' => 'rsst',
            'objet' => 'Test',
            'description' => 'Test desc',
            'date_evenement' => '2026-01-15',
        ];
        $user = ['id' => 1, 'nom' => 'Test', 'prenom' => 'User'];
        
        $cmd = CreateReportCommand::fromPost($post, $user);
        
        $this->assertTrue($cmd->siteId->isNone(), 'siteId must be none when site_id is missing from POST');
    }

    public function testFromPostSiteIdPreservesValueWhenPresent(): void
    {
        $post = [
            'type' => 'rsst',
            'objet' => 'Test',
            'description' => 'Test desc',
            'date_evenement' => '2026-01-15',
            'site_id' => '42',
        ];
        $user = ['id' => 1, 'nom' => 'Test', 'prenom' => 'User'];
        
        $cmd = CreateReportCommand::fromPost($post, $user);
        
        $this->assertSame(42, $cmd->siteId->toNullableInt());
    }
}