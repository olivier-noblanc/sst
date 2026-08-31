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
    /** Factory minimale de ReportData pour les tests de précédence siteId/report. */
    private function makeReport(int $siteId): ReportData
    {
        return new ReportData(
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
            siteId: $siteId,
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
    }

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

    public function testSiteIdIntPrefersExplicitSiteIdOverReportSiteId(): void
    {
        // Tue le mutant Coalesce (ligne 65) qui inverse la précédence :
        // `$this->siteId ?? (report…)` devient `$this->report !== null ? … : null ?? $this->siteId`.
        // Avec siteId ET report présents et DIFFÉRENTS, le mutant renverrait le
        // siteId du report (9) au lieu du siteId explicite (5).
        $data = new ReportEventData(
            report: $this->makeReport(9),
            siteId: 5,
        );
        $this->assertSame(5, $data->siteIdInt(), 'explicit siteId must win over the report\'s own siteId');
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

    public function testUserEventDataForUserNullReturnsNullUserId(): void
    {
        // Tue le mutant NullSafePropertyCall sur forUser() : `$user?->id` → `$user->id`.
        // Avec user=null le mutant lèverait une Error (lecture de propriété sur null)
        // au lieu de stocker userId=null proprement.
        $data = UserEventData::forUser(null);
        $this->assertNull($data->user);
        $this->assertNull($data->userId);
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

    public function testGetRowsReturnsRegistryCountsForMatchedSite(): void
    {
        // Tue le mutant Foreach_ (ligne 51) qui vide le parcours de $this->registryCodes :
        // sans lui, registryCounts resterait vide pour un site pourtant matché.
        $statsRow = new SiteStatsRow(
            code: 'UR21',
            total: 5,
            registryCounts: ['rsst' => 3, 'rami' => 2],
        );

        $view = new SitesStatsView(
            statsBySite: [$statsRow],
            sites: [['code' => 'UR21', 'nom' => 'Test Site', 'id' => 1]],
            registryCodes: ['rsst', 'rami', 'dgi'],
        );

        $rows = $view->getRows();
        $this->assertSame(3, $rows[0]['registryCounts']['rsst'], 'rsst count from matched stats');
        $this->assertSame(2, $rows[0]['registryCounts']['rami'], 'rami count from matched stats');
        $this->assertSame(0, $rows[0]['registryCounts']['dgi'], 'dgi absent from stats → 0');
        $this->assertCount(3, $rows[0]['registryCounts'], 'one key per registry code');
    }

    public function testGetRowsReturnsZeroRegistryCountsForUnmatchedSite(): void
    {
        // Tue les mutants DecrementInteger/IncrementInteger (ligne 52) :
        // `: 0` → `: -1` ou `: 1` quand le site n'a pas de stats.
        $view = new SitesStatsView(
            statsBySite: [],
            sites: [['code' => 'UR22', 'nom' => 'Autre Site', 'id' => 2]],
            registryCodes: ['rsst', 'rami'],
        );

        $rows = $view->getRows();
        $this->assertSame(0, $rows[0]['registryCounts']['rsst'], 'unmatched site → rsst count 0');
        $this->assertSame(0, $rows[0]['registryCounts']['rami'], 'unmatched site → rami count 0');
    }

    public function testGetRowsRegistryCountsForMatchedSiteAbsentFromStats(): void
    {
        // Tue le mutant IncrementInteger (ligne 52) : le site EST matché mais le
        // registre est absent de ses stats — `: 0` → `: 1`.
        // Le mutant fait la cellule absente compter 1 sans aucun signalement :
        // exactement le faux total que le tableau de synthèse ne doit jamais
        // afficher. Un site matché avec un registre absent doit rester 0.
        $statsRow = new SiteStatsRow(
            code: 'UR21',
            total: 5,
            registryCounts: ['rsst' => 5],  # rami absent des stats de ce site
        );

        $view = new SitesStatsView(
            statsBySite: [$statsRow],
            sites: [['code' => 'UR21', 'nom' => 'Test Site', 'id' => 1]],
            registryCodes: ['rsst', 'rami'],
        );

        $rows = $view->getRows();
        $this->assertSame(5, $rows[0]['registryCounts']['rsst'], 'matched registry keeps its count');
        $this->assertSame(0, $rows[0]['registryCounts']['rami'], 'matched site, absent registry → 0, never 1');
    }

    public function testGetRowsReturnsAllSitesNotJustTheFirst(): void
    {
        // Tue le mutant ArrayOneItem (ligne 58) : `return $rows` → un seul élément.
        $statsRow = new SiteStatsRow(
            code: 'UR21',
            total: 5,
            registryCounts: ['rsst' => 5],
        );

        $view = new SitesStatsView(
            statsBySite: [$statsRow],
            sites: [
                ['code' => 'UR21', 'nom' => 'Site A', 'id' => 1],
                ['code' => 'UR22', 'nom' => 'Site B', 'id' => 2],
            ],
            registryCodes: ['rsst'],
        );

        $rows = $view->getRows();
        $this->assertCount(2, $rows, 'one row per site — not only the first one');
        $this->assertSame('UR21', $rows[0]['code']);
        $this->assertSame('Site A', $rows[0]['nom']);
        $this->assertSame(5, $rows[0]['total']);
        $this->assertSame('UR22', $rows[1]['code']);
        $this->assertSame('Site B', $rows[1]['nom']);
        $this->assertSame(0, $rows[1]['total']);
    }

    public function testGetTotalByRegistrySumsMultipleSites(): void
    {
        // Tue les mutants Foreach_ (ligne 67), Assignment (ligne 68 : += → =)
        // et PlusEqual (ligne 68 : += → -=) sur getTotalByRegistry().
        // Deux sites avec des comptes DIFFÉRENTS sont indispensables :
        // avec un seul site, `=` et `+=` donnent le même résultat.
        $statsA = new SiteStatsRow(
            code: 'UR21',
            total: 3,
            registryCounts: ['rsst' => 3],
        );
        $statsB = new SiteStatsRow(
            code: 'UR22',
            total: 2,
            registryCounts: ['rsst' => 2],
        );

        $view = new SitesStatsView(
            statsBySite: [$statsA, $statsB],
            sites: [],
            registryCodes: ['rsst'],
        );

        $this->assertSame(5, $view->getTotalByRegistry('rsst'), '3 + 2 must be summed across sites');
    }

    public function testGetGrandTotalSumsMultipleSites(): void
    {
        // Tue les mutants Foreach_ (ligne 79), Assignment (ligne 80 : += → =)
        // et PlusEqual (ligne 80 : += → -=) sur getGrandTotal().
        $statsA = new SiteStatsRow(
            code: 'UR21',
            total: 5,
            registryCounts: ['rsst' => 3, 'rami' => 2],
        );
        $statsB = new SiteStatsRow(
            code: 'UR22',
            total: 4,
            registryCounts: ['rsst' => 1, 'rami' => 3],
        );

        $view = new SitesStatsView(
            statsBySite: [$statsA, $statsB],
            sites: [],
            registryCodes: ['rsst', 'rami'],
        );

        $this->assertSame(9, $view->getGrandTotal(), '5 + 4 must be summed across sites');
        $this->assertSame(4, $view->getTotalByRegistry('rsst'), '3 + 1 = 4 (cross-check registries stay independent)');
        $this->assertSame(5, $view->getTotalByRegistry('rami'), '2 + 3 = 5');
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