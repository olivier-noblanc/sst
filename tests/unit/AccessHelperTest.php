<?php
/**
 * Access Helper Unit Tests — Edit, Respond, Access Checks
 *
 * Tests the access control functions from src/helpers/access.php:
 * - canEditReport()
 * - canRespondToReport()
 * - canAccessReport()
 */

use PHPUnit\Framework\TestCase;
use App\DTO\SessionUser;

require_once __DIR__ . '/../../src/helpers/access.php';

class AccessHelperTest extends TestCase
{
    private function makeReport(array $overrides = []): \App\DTO\ReportData
    {
        $defaults = [
            'uuid' => 'test-uuid',
            'reference' => 'RSST-25-001',
            'type' => 'rsst',
            'objet' => 'Test',
            'description' => 'Test',
            'dateEvenement' => '2026-01-15',
            'heureEvenement' => '',
            'lieu' => '',
            'declarantId' => 5,
            'declarantNom' => '',
            'declarantPrenom' => '',
            'pourCompteDe' => '',
            'pourCompteNom' => '',
            'pourComptePrenom' => '',
            'natureAuteur' => '',
            'typeActe' => '',
            'siteId' => 1,
            'siteText' => '',
            'pole' => '',
            'serviceAffectation' => '',
            'telephoneMobile' => '',
            'isConfidential' => 0,
            'consentSyndicat' => 0,
            'etat' => 'nouveau',
            'repondantId' => null,
            'dateReponse' => null,
            'reponse' => null,
            'attachmentName' => null,
            'attachmentMime' => null,
            'createdAt' => '2026-01-15 10:00:00',
            'updatedAt' => '2026-01-15 10:00:00',
            'siteCode' => 'UR21',
            'siteNom' => 'UR Test',
            'repondantNom' => null,
            'repondantPrenom' => null,
        ];
        return new \App\DTO\ReportData(...array_merge($defaults, $overrides));
    }

    // ─── canEditReport ──────────────────────────────────────────────────────

    public function testDeclarantCanEditNewReport(): void
    {
        $report = $this->makeReport(['declarantId' => 5, 'etat' => 'nouveau']);
        $this->assertTrue(canEditReport($report, 5));
    }

    public function testDeclarantCanEditInProgressReport(): void
    {
        $report = $this->makeReport(['declarantId' => 5, 'etat' => 'en_cours']);
        $this->assertTrue(canEditReport($report, 5));
    }

    public function testDeclarantCannotEditTreatedReport(): void
    {
        $report = $this->makeReport(['declarantId' => 5, 'etat' => 'traite']);
        $this->assertFalse(canEditReport($report, 5));
    }

    public function testDeclarantCannotEditAbandonedReport(): void
    {
        $report = $this->makeReport(['declarantId' => 5, 'etat' => 'abandonne']);
        $this->assertFalse(canEditReport($report, 5));
    }

    public function testNonDeclarantCannotEditNewReport(): void
    {
        $report = $this->makeReport(['declarantId' => 5, 'etat' => 'nouveau']);
        $this->assertFalse(canEditReport($report, 99));
    }

    public function testDeclarantIdAsInt(): void
    {
        $report = $this->makeReport(['declarantId' => 5, 'etat' => 'nouveau']);
        $this->assertTrue(canEditReport($report, 5));
    }

    public function testDeclarantCannotEditReouvertReport(): void
    {
        $report = $this->makeReport(['declarantId' => 5, 'etat' => 'reouvert']);
        $this->assertFalse(canEditReport($report, 5));
    }

    public function testNonDeclarantCannotEditInProgressReport(): void
    {
        $report = $this->makeReport(['declarantId' => 5, 'etat' => 'en_cours']);
        $this->assertFalse(canEditReport($report, 99));
    }

    public function testDeclarantCannotEditTreatedReportEvenIfDeclarant(): void
    {
        $report = $this->makeReport(['declarantId' => 5, 'etat' => 'traite']);
        $this->assertFalse(canEditReport($report, 5));
    }

    // ─── canRespondToReport ─────────────────────────────────────────────────

    public function testSuperviseurCanRespondToNewReport(): void
    {
        $report = $this->makeReport(['etat' => 'nouveau']);
        $this->assertTrue(canRespondToReport($report, 'superviseur'));
    }

    public function testSuperviseurCanRespondToInProgressReport(): void
    {
        $report = $this->makeReport(['etat' => 'en_cours']);
        $this->assertTrue(canRespondToReport($report, 'superviseur'));
    }

    public function testSuperviseurCannotRespondToTreatedReport(): void
    {
        $report = $this->makeReport(['etat' => 'traite']);
        $this->assertFalse(canRespondToReport($report, 'superviseur'));
    }

    public function testSuperviseurCannotRespondToAbandonedReport(): void
    {
        $report = $this->makeReport(['etat' => 'abandonne']);
        $this->assertFalse(canRespondToReport($report, 'superviseur'));
    }

    public function testAgentCannotRespondToNewReport(): void
    {
        $report = $this->makeReport(['etat' => 'nouveau']);
        $this->assertFalse(canRespondToReport($report, 'agent'));
    }

    public function testChsctCannotRespondToNewReport(): void
    {
        $report = $this->makeReport(['etat' => 'nouveau']);
        $this->assertFalse(canRespondToReport($report, 'chsct'));
    }

    public function testSuperviseurCanRespondToReouvertReport(): void
    {
        $report = $this->makeReport(['etat' => 'reouvert']);
        $this->assertTrue(canRespondToReport($report, 'superviseur'));
    }

    public function testAgentCannotRespondToReouvertReport(): void
    {
        $report = $this->makeReport(['etat' => 'reouvert']);
        $this->assertFalse(canRespondToReport($report, 'agent'));
    }

    public function testChsctCannotRespondToReouvertReport(): void
    {
        $report = $this->makeReport(['etat' => 'reouvert']);
        $this->assertFalse(canRespondToReport($report, 'chsct'));
    }

    // ─── canAccessReport ────────────────────────────────────────────────────

    /**
     * Audit #85 — canAccessReport() attend un ReportData en natif (contrairement
     * à canEditReport()/canRespondToReport() plus haut dans ce fichier, qui
     * prennent bien un array). Les 16 tests de cette section construisaient un
     * array à la main — TypeError garanti dès que ce fichier tournait seul.
     */
    private function makeReportForAccess(int $siteId, int $declarantId, bool $isConfidential, string $type = 'rsst', bool $consentSyndicat = false): \App\DTO\ReportData
    {
        return new \App\DTO\ReportData(
            uuid: 'test-uuid', reference: 'RSST-25-001', type: $type,
            objet: 'Objet test', description: 'Description test',
            dateEvenement: '2025-01-01', heureEvenement: '', lieu: '',
            declarantId: $declarantId, declarantNom: 'Dupont', declarantPrenom: 'Jean',
            pourCompteDe: '', pourCompteNom: '', pourComptePrenom: '',
            natureAuteur: '', typeActe: '', siteId: $siteId, siteText: '',
            pole: '', serviceAffectation: '', telephoneMobile: '',
            isConfidential: $isConfidential ? 1 : 0, consentSyndicat: $consentSyndicat ? 1 : 0,
            etat: 'nouveau', repondantId: null, dateReponse: null, reponse: null,
            attachmentName: null, attachmentMime: null,
            createdAt: '2025-01-01 10:00:00', updatedAt: '2025-01-01 10:00:00',
            siteCode: 'UR21', siteNom: 'UR Test', repondantNom: null, repondantPrenom: null,
        );
    }

    public function testSuperviseurCanAlwaysAccess(): void
    {
        $report = $this->makeReportForAccess(1, 99, (bool) 1, 'rsst');
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 2, 'role' => 'superviseur']);
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testChsctCanAlwaysAccessWithConsent(): void
    {
        $report = $this->makeReportForAccess(1, 99, (bool) 1, 'rsst', (bool) 1);
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 2, 'role' => 'chsct']);
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testChsctCannotAccessWithoutConsent(): void
    {
        $report = $this->makeReportForAccess(1, 99, (bool) 1, 'rsst', (bool) 0);
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 2, 'role' => 'chsct']);
        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessOtherSiteReport(): void
    {
        $report = $this->makeReportForAccess(1, 99, (bool) 0, 'rsst');
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 2, 'role' => 'agent']);
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCannotAccessOtherConfidentialReport(): void
    {
        $report = $this->makeReportForAccess(1, 99, (bool) 1, 'rsst');
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 1, 'role' => 'agent']);
        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessOwnConfidentialReport(): void
    {
        $report = $this->makeReportForAccess(1, 5, (bool) 1, 'rsst');
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 1, 'role' => 'agent']);
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessPublicReportOnSameSite(): void
    {
        $report = $this->makeReportForAccess(1, 99, (bool) 0, 'rsst');
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 1, 'role' => 'agent']);
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentChoiceModeRespectsConfidentialFlag(): void
    {
        $report = $this->makeReportForAccess(1, 99, (bool) 0, 'rsst');
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 1, 'role' => 'agent']);
        $this->assertTrue(canAccessReport($report, $user, 'agent_choice'));

        $report2 = $this->makeReportForAccess(1, 99, (bool) 1, 'rsst');
        $this->assertFalse(canAccessReport($report2, $user, 'agent_choice'));
    }

    public function testAgentCanAccessOwnConfidentialReportInAgentChoiceMode(): void
    {
        $report = $this->makeReportForAccess(1, 5, (bool) 1, 'rsst');
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 1, 'role' => 'agent']);
        $this->assertTrue(canAccessReport($report, $user, 'agent_choice'));
    }

    public function testSuperviseurCanAccessConfidentialAcrossSites(): void
    {
        $report = $this->makeReportForAccess(10, 99, (bool) 1, 'rsst');
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 1, 'role' => 'superviseur']);
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testChsctCanAccessPublicAcrossSitesWithConsent(): void
    {
        $report = $this->makeReportForAccess(10, 99, (bool) 0, 'rsst', (bool) 1);
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 1, 'role' => 'chsct']);
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCanAccessDifferentSiteInPublicMode(): void
    {
        $report = $this->makeReportForAccess(2, 99, (bool) 0, 'rsst');
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 1, 'role' => 'agent']);
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCanAccessOwnConfidentialInConfidentialMode(): void
    {
        $report = $this->makeReportForAccess(1, 5, (bool) 1, 'rsst');
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 1, 'role' => 'agent']);
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCannotAccessOtherConfidentialInConfidentialModeSameSite(): void
    {
        $report = $this->makeReportForAccess(1, 99, (bool) 1, 'rsst');
        $user = SessionUser::fromArray(['id' => 5, 'site_id' => 1, 'role' => 'agent']);
        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }
}
