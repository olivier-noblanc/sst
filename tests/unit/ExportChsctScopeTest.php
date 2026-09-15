<?php
/**
 * Export CHSCT Scope Test — Application SST DREETS BFC
 *
 * Décision Oracle — l'export CSV doit appliquer la même portée que la liste
 * des signalements pour le CSA/CHSCT :
 *   - CHSCT + app_chsct_report_scope=consent_only → AND r.consent_syndicat = 1
 *   - CHSCT + app_chsct_report_scope=all         → aucun filtre
 *   - Superviseur (quel que soit le réglage)     → aucun filtre
 *
 * Avant ce fix, un membre du CSA/CHSCT en mode « Consentement uniquement »
 * exportait les signalements non consentis qu'il ne pouvait PAS consulter
 * dans la liste : l'export contournait le contrôle de visibilité.
 */

use App\Enum\UserRole;
use App\Repository\StatsRepository;
use App\Services\ConfigService;
use App\Services\ExportService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/audit.php';

class ExportChsctScopeTest extends TestCase
{
    private const CONSENTED_UUID = 'export-chsct-scope-consented';
    private const REFUSED_UUID = 'export-chsct-scope-refused';

    private int $siteId;
    private int $declarantId;

    protected function setUp(): void
    {
        $pdo = getDB();
        $pdo->exec("DELETE FROM reports WHERE uuid IN ('" . self::CONSENTED_UUID . "', '" . self::REFUSED_UUID . "')");
        $pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('URCHSCT', 'UR CHSCT Export', 1)");
        $this->siteId = (int) $pdo->query("SELECT id FROM sites WHERE code = 'URCHSCT'")->fetchColumn();

        $pdo->exec("INSERT OR IGNORE INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('export_chsct_scope_agent', 'Dupont', 'Jean', 'agent', {$this->siteId}, 1, 'fixture@dreets-bfc.gouv.fr')");
        $this->declarantId = (int) $pdo->query("SELECT id FROM users WHERE username = 'export_chsct_scope_agent'")->fetchColumn();

        $this->seedReport(self::CONSENTED_UUID, 'RSST-25-CS1', 1);
        $this->seedReport(self::REFUSED_UUID, 'RSST-25-CS2', 0);
    }

    protected function tearDown(): void
    {
        // Reset config (le défaut de production est consent_only)
        $configService = getConfigService();
        $configService->set('app_chsct_report_scope', 'consent_only');
        $configService->clearCache();
    }

    private function seedReport(string $uuid, string $reference, int $consent): void
    {
        getDB()->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, consent_syndicat, is_confidential, etat)
            VALUES (:uuid, :reference, :type, :objet, :description, :date_evenement, :declarant_id, :declarant_nom, :declarant_prenom, :site_id, :consent_syndicat, 0, :etat)
        ')->execute([
            ':uuid' => $uuid,
            ':reference' => $reference,
            ':type' => 'rsst',
            ':objet' => 'Objet export CHSCT',
            ':description' => 'Desc',
            ':date_evenement' => '2025-01-01',
            ':declarant_id' => $this->declarantId,
            ':declarant_nom' => 'Dupont',
            ':declarant_prenom' => 'Jean',
            ':site_id' => $this->siteId,
            ':consent_syndicat' => $consent,
            ':etat' => 'nouveau',
        ]);
    }

    private function setChsctScope(string $scope): void
    {
        $configService = getConfigService();
        $configService->set('app_chsct_report_scope', $scope);
        $configService->clearCache();
    }

    private function makeService(): ExportService
    {
        return new ExportService(new ConfigService());
    }

    // ─── 1. Décision de filtre selon rôle + portée ──────────────────────────

    public function testChsctConsentOnlyAddsConsentFilter(): void
    {
        $this->setChsctScope('consent_only');

        $filters = $this->makeService()->buildFiltersFromPost([], UserRole::Chsct);

        $this->assertSame(
            true,
            $filters['chsct_consent_only'] ?? false,
            'CHSCT + consent_only : le filtre r.consent_syndicat = 1 doit être appliqué à l\'export'
        );
    }

    public function testChsctAllModeDoesNotFilter(): void
    {
        $this->setChsctScope('all');

        $filters = $this->makeService()->buildFiltersFromPost([], UserRole::Chsct);

        $this->assertArrayNotHasKey(
            'chsct_consent_only',
            $filters,
            'CHSCT + all : aucun filtre de consentement ne doit être appliqué'
        );
    }

    public function testSuperviseurNeverFilteredRegardlessOfScope(): void
    {
        $this->setChsctScope('consent_only');

        $filters = $this->makeService()->buildFiltersFromPost([], UserRole::Superviseur);

        $this->assertArrayNotHasKey(
            'chsct_consent_only',
            $filters,
            'Superviseur : le réglage de portée CHSCT ne doit jamais le restreindre'
        );
    }

    public function testFilterDecisionKeepsOtherFiltersUntouched(): void
    {
        $this->setChsctScope('consent_only');

        $filters = $this->makeService()->buildFiltersFromPost(['type' => 'rsst'], UserRole::Chsct);

        $this->assertSame('rsst', $filters['type']);
        $this->assertTrue($filters['chsct_consent_only'] ?? false, 'Le filtre consentement doit cohabiter avec les filtres POST');
    }

    // ── 2. Application SQL du filtre dans getExportData() ──────────────────

    public function testConsentFilterExcludesRefusedReports(): void
    {
        $rows = StatsRepository::instance()->getExportData(['chsct_consent_only' => true]);
        $uuids = array_column($rows, 'uuid');

        $this->assertContains(self::CONSENTED_UUID, $uuids, 'Un signalement consenti doit rester exportable');
        $this->assertNotContains(self::REFUSED_UUID, $uuids, 'Un signalement non consenti ne doit PAS être exporté en mode consent_only');
    }

    public function testWithoutConsentFilterBothReportsAreExported(): void
    {
        $rows = StatsRepository::instance()->getExportData([]);
        $uuids = array_column($rows, 'uuid');

        $this->assertContains(self::CONSENTED_UUID, $uuids);
        $this->assertContains(self::REFUSED_UUID, $uuids, 'Sans filtre CHSCT, les deux signalements sont exportés');
    }

    public function testChsctConsentOnlyFullPipelineExportsOnlyConsented(): void
    {
        $this->setChsctScope('consent_only');

        $filters = $this->makeService()->buildFiltersFromPost([], UserRole::Chsct);
        $rows = StatsRepository::instance()->getExportData($filters);
        $uuids = array_column($rows, 'uuid');

        $this->assertContains(self::CONSENTED_UUID, $uuids);
        $this->assertNotContains(self::REFUSED_UUID, $uuids, 'La chaîne buildFiltersFromPost → getExportData doit respecter la portée CHSCT');
    }

    // ── 3. Le contexte d'audit reflète le filtre ──────────────────────────

    public function testAuditContextReflectsConsentFilter(): void
    {
        $context = buildExportAuditContext(['chsct_consent_only' => true], 1);

        $this->assertTrue(
            $context['filter_chsct_consent_only'] ?? false,
            'Le contexte d\'audit doit tracer le filtre de portée CHSCT appliqué'
        );
    }

    public function testAuditContextOmitsConsentFilterWhenAbsent(): void
    {
        $context = buildExportAuditContext([], 1);

        $this->assertArrayNotHasKey(
            'filter_chsct_consent_only',
            $context,
            'Aucun filtre CHSCT → pas de clé d\'audit parasite'
        );
    }
}