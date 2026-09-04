<?php
/**
 * CHSCT Scope Consistency Tests — Application SST DREETS BFC
 *
 * Verifies that canAccessReport() and findPaginated() give consistent
 * results for CHSCT users under both app_chsct_report_scope values.
 *
 * This is the guard against future divergence between the two access paths.
 */

use PHPUnit\Framework\TestCase;
use App\Services\AccessService;
use App\Services\ConfigService;
use App\DTO\ReportData;
use App\DTO\ReportFilter;
use App\DTO\SessionUser;
use App\Repository\ReportRepository;

class ChsctScopeConsistencyTest extends TestCase
{
    private PDO $pdo;
    private AccessService $access;
    private int $siteId;
    private int $chsctUserId;
    private int $agentUserId;
    private string $reportUuid;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->access = new AccessService();

        // Clean up
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM report_agent_invites');

        // Seed site
        $this->pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UD99', 'Test CHSCT Scope', 1)");
        $this->siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UD99'")->fetchColumn();

        // Seed CHSCT user
        $this->pdo->exec("INSERT OR IGNORE INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('chsct_scope_test', 'CHSCT', 'Test', 'chsct', {$this->siteId}, 1, 'fixture@dreets-bfc.gouv.fr')");
        $this->chsctUserId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'chsct_scope_test'")->fetchColumn();

        // Seed agent user
        $this->pdo->exec("INSERT OR IGNORE INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('agent_scope_test', 'Agent', 'Test', 'agent', {$this->siteId}, 1, 'fixture@dreets-bfc.gouv.fr')");
        $this->agentUserId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'agent_scope_test'")->fetchColumn();

        // Create report WITHOUT consent
        $this->reportUuid = 'test-chsct-scope-' . uniqid();
        $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, lieu, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, consent_syndicat, etat)
            VALUES (:uuid, :reference, :type, :objet, :description, :date_evenement, :lieu, :declarant_id, :declarant_nom, :declarant_prenom, :site_id, 0, 0, :etat)
        ')->execute([
            ':uuid' => $this->reportUuid,
            ':reference' => 'RSST-25-999',
            ':type' => 'rsst',
            ':objet' => 'Test CHSCT scope consistency',
            ':description' => 'Test report',
            ':date_evenement' => '2025-01-15',
            ':lieu' => 'Bureau test',
            ':declarant_id' => $this->agentUserId,
            ':declarant_nom' => 'Test',
            ':declarant_prenom' => 'Agent',
            ':site_id' => $this->siteId,
            ':etat' => 'nouveau',
        ]);
    }

    private function setChsctScope(string $scope): void
    {
        $configService = getConfigService();
        $configService->set('app_chsct_report_scope', $scope);
        $configService->clearCache();
    }

    private function rowToReportData(array $row): ReportData
    {
        return new ReportData(
            uuid: $row['uuid'],
            reference: $row['reference'],
            type: $row['type'],
            objet: $row['objet'],
            description: $row['description'],
            dateEvenement: $row['date_evenement'],
            heureEvenement: $row['heure_evenement'] ?? '',
            lieu: $row['lieu'] ?? '',
            declarantId: (int) $row['declarant_id'],
            declarantNom: $row['declarant_nom'],
            declarantPrenom: $row['declarant_prenom'],
            pourCompteDe: $row['pour_compte_de'] ?? '',
            pourCompteNom: $row['pour_compte_nom'] ?? '',
            pourComptePrenom: $row['pour_compte_prenom'] ?? '',
            natureAuteur: $row['nature_auteur'] ?? '',
            typeActe: $row['type_acte'] ?? '',
            siteId: $row['site_id'] !== null ? (int) $row['site_id'] : null,
            siteText: $row['site_text'] ?? '',
            pole: $row['pole'] ?? '',
            serviceAffectation: $row['service_affectation'] ?? '',
            telephoneMobile: $row['telephone_mobile'] ?? '',
            isConfidential: (int) $row['is_confidential'],
            consentSyndicat: (int) $row['consent_syndicat'],
            etat: $row['etat'],
            repondantId: $row['repondant_id'] !== null ? (int) $row['repondant_id'] : null,
            dateReponse: $row['date_reponse'] ?? null,
            reponse: $row['reponse'] ?? null,
            attachmentName: $row['attachment_name'] ?? null,
            attachmentMime: $row['attachment_mime'] ?? null,
            createdAt: $row['created_at'] ?? '',
            updatedAt: $row['updated_at'] ?? '',
            siteCode: $row['site_code'] ?? '',
            siteNom: $row['site_nom'] ?? '',
            repondantNom: $row['repondant_nom'] ?? null,
            repondantPrenom: $row['repondant_prenom'] ?? null,
        );
    }

    /**
     * When scope is 'consent_only', CHSCT should NOT see reports without consent
     * in both canAccessReport() and findPaginated().
     */
    public function testConsentOnlyScopeBlocksInBothAccessPaths(): void
    {
        $this->setChsctScope('consent_only');

        $report = $this->pdo->prepare('SELECT * FROM reports WHERE uuid = :uuid');
        $report->execute([':uuid' => $this->reportUuid]);
        $reportRow = $report->fetch();

        $user = SessionUser::fromArray([
            'id' => $this->chsctUserId,
            'role' => ROLE_CHSCT,
            'site_id' => $this->siteId,
        ]);

        // canAccessReport should block
        $this->assertFalse(
            $this->access->canAccessReport($this->rowToReportData($reportRow), $user),
            'canAccessReport() should block CHSCT when scope is consent_only and consent_syndicat=0'
        );

        // findPaginated should also exclude it
        $filter = new ReportFilter(
            type: 'rsst',
            seeAllSites: true,
            chsctConsentOnly: true,
        );
        $result = ReportRepository::instance()->findPaginated($filter, 1, 100);
        $uuids = array_map(fn($r) => $r->uuid, $result->reports);

        $this->assertNotContains(
            $this->reportUuid,
            $uuids,
            'findPaginated() should exclude non-consented reports when chsctConsentOnly=true'
        );
    }

    /**
     * When scope is 'all', CHSCT should see ALL reports
     * in both canAccessReport() and findPaginated().
     */
    public function testAllScopeAllowsInBothAccessPaths(): void
    {
        $this->setChsctScope('all');

        $report = $this->pdo->prepare('SELECT * FROM reports WHERE uuid = :uuid');
        $report->execute([':uuid' => $this->reportUuid]);
        $reportRow = $report->fetch();

        $user = SessionUser::fromArray([
            'id' => $this->chsctUserId,
            'role' => ROLE_CHSCT,
            'site_id' => $this->siteId,
        ]);

        // canAccessReport should allow
        $this->assertTrue(
            $this->access->canAccessReport($this->rowToReportData($reportRow), $user),
            'canAccessReport() should allow CHSCT when scope is all'
        );

        // findPaginated without consent filter should also include it
        $filter = new ReportFilter(
            type: 'rsst',
            seeAllSites: true,
            chsctConsentOnly: false,
        );
        $result = ReportRepository::instance()->findPaginated($filter, 1, 100);
        $uuids = array_map(fn($r) => $r->uuid, $result->reports);

        $this->assertContains(
            $this->reportUuid,
            $uuids,
            'findPaginated() should include all reports when chsctConsentOnly=false'
        );
    }

    /**
     * When scope is 'consent_only', CHSCT SHOULD see reports WITH consent.
     */
    public function testConsentOnlyScopeAllowsConsentedReport(): void
    {
        $this->setChsctScope('consent_only');

        // Update report to have consent
        $this->pdo->prepare('UPDATE reports SET consent_syndicat = 1 WHERE uuid = :uuid')
            ->execute([':uuid' => $this->reportUuid]);

        $report = $this->pdo->prepare('SELECT * FROM reports WHERE uuid = :uuid');
        $report->execute([':uuid' => $this->reportUuid]);
        $reportRow = $report->fetch();

        $user = SessionUser::fromArray([
            'id' => $this->chsctUserId,
            'role' => ROLE_CHSCT,
            'site_id' => $this->siteId,
        ]);

        // canAccessReport should allow
        $this->assertTrue(
            $this->access->canAccessReport($this->rowToReportData($reportRow), $user),
            'canAccessReport() should allow CHSCT when scope is consent_only and consent_syndicat=1'
        );

        // findPaginated should also include it
        $filter = new ReportFilter(
            type: 'rsst',
            seeAllSites: true,
            chsctConsentOnly: true,
        );
        $result = ReportRepository::instance()->findPaginated($filter, 1, 100);
        $uuids = array_map(fn($r) => $r->uuid, $result->reports);

        $this->assertContains(
            $this->reportUuid,
            $uuids,
            'findPaginated() should include consented reports when chsctConsentOnly=true'
        );
    }

    /**
     * Superviseur should always see all reports regardless of scope setting.
     */
    public function testSuperviseurAlwaysSeesAllRegardlessOfScope(): void
    {
        $this->setChsctScope('consent_only');

        $report = $this->pdo->prepare('SELECT * FROM reports WHERE uuid = :uuid');
        $report->execute([':uuid' => $this->reportUuid]);
        $reportRow = $report->fetch();

        $user = SessionUser::fromArray([
            'id' => 999,
            'role' => ROLE_SUPERVISEUR,
            'site_id' => $this->siteId,
        ]);

        $this->assertTrue(
            $this->access->canAccessReport($this->rowToReportData($reportRow), $user),
            'Superviseur should always have access regardless of CHSCT scope'
        );
    }

    protected function tearDown(): void
    {
        // Reset config
        $configService = getConfigService();
        $configService->set('app_chsct_report_scope', 'consent_only');
        $configService->clearCache();
    }
}
