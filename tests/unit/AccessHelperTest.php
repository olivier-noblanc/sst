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

require_once __DIR__ . '/../../src/helpers/access.php';

class AccessHelperTest extends TestCase
{
    // ─── canEditReport ──────────────────────────────────────────────────────

    public function testDeclarantCanEditNewReport(): void
    {
        $report = ['declarant_id' => '5', 'etat' => 'nouveau'];
        $this->assertTrue(canEditReport($report, 5));
    }

    public function testDeclarantCanEditInProgressReport(): void
    {
        $report = ['declarant_id' => '5', 'etat' => 'en_cours'];
        $this->assertTrue(canEditReport($report, 5));
    }

    public function testDeclarantCannotEditTreatedReport(): void
    {
        $report = ['declarant_id' => '5', 'etat' => 'traite'];
        $this->assertFalse(canEditReport($report, 5));
    }

    public function testDeclarantCannotEditAbandonedReport(): void
    {
        $report = ['declarant_id' => '5', 'etat' => 'abandonne'];
        $this->assertFalse(canEditReport($report, 5));
    }

    public function testNonDeclarantCannotEditNewReport(): void
    {
        $report = ['declarant_id' => '5', 'etat' => 'nouveau'];
        $this->assertFalse(canEditReport($report, 99));
    }

    public function testDeclarantIdAsInt(): void
    {
        $report = ['declarant_id' => 5, 'etat' => 'nouveau'];
        $this->assertTrue(canEditReport($report, 5));
    }

    public function testDeclarantCannotEditReouvertReport(): void
    {
        $report = ['declarant_id' => '5', 'etat' => 'reouvert'];
        $this->assertFalse(canEditReport($report, 5));
    }

    public function testNonDeclarantCannotEditInProgressReport(): void
    {
        $report = ['declarant_id' => '5', 'etat' => 'en_cours'];
        $this->assertFalse(canEditReport($report, 99));
    }

    public function testDeclarantCannotEditTreatedReportEvenIfDeclarant(): void
    {
        $report = ['declarant_id' => '5', 'etat' => 'traite'];
        $this->assertFalse(canEditReport($report, 5));
    }

    // ─── canRespondToReport ─────────────────────────────────────────────────

    public function testSuperviseurCanRespondToNewReport(): void
    {
        $report = ['etat' => 'nouveau'];
        $this->assertTrue(canRespondToReport($report, 'superviseur'));
    }

    public function testSuperviseurCanRespondToInProgressReport(): void
    {
        $report = ['etat' => 'en_cours'];
        $this->assertTrue(canRespondToReport($report, 'superviseur'));
    }

    public function testSuperviseurCannotRespondToTreatedReport(): void
    {
        $report = ['etat' => 'traite'];
        $this->assertFalse(canRespondToReport($report, 'superviseur'));
    }

    public function testSuperviseurCannotRespondToAbandonedReport(): void
    {
        $report = ['etat' => 'abandonne'];
        $this->assertFalse(canRespondToReport($report, 'superviseur'));
    }

    public function testAgentCannotRespondToNewReport(): void
    {
        $report = ['etat' => 'nouveau'];
        $this->assertFalse(canRespondToReport($report, 'agent'));
    }

    public function testChsctCannotRespondToNewReport(): void
    {
        $report = ['etat' => 'nouveau'];
        $this->assertFalse(canRespondToReport($report, 'chsct'));
    }

    public function testSuperviseurCanRespondToReouvertReport(): void
    {
        $report = ['etat' => 'reouvert'];
        $this->assertTrue(canRespondToReport($report, 'superviseur'));
    }

    public function testAgentCannotRespondToReouvertReport(): void
    {
        $report = ['etat' => 'reouvert'];
        $this->assertFalse(canRespondToReport($report, 'agent'));
    }

    public function testChsctCannotRespondToReouvertReport(): void
    {
        $report = ['etat' => 'reouvert'];
        $this->assertFalse(canRespondToReport($report, 'chsct'));
    }

    // ─── canAccessReport ────────────────────────────────────────────────────

    /**
     * Audit #85 — canAccessReport() attend un ReportData en natif (contrairement
     * à canEditReport()/canRespondToReport() plus haut dans ce fichier, qui
     * prennent bien un array). Les 16 tests de cette section construisaient un
     * array à la main — TypeError garanti dès que ce fichier tournait seul.
     */
    private function makeReport(int $siteId, int $declarantId, bool $isConfidential, string $type = 'rsst', bool $consentSyndicat = false): \App\DTO\ReportData
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
        $report = $this->makeReport(1, 99, (bool) 1, 'rsst');
        $user = ['id' => 5, 'site_id' => 2, 'role' => 'superviseur'];
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testChsctCanAlwaysAccessWithConsent(): void
    {
        $report = $this->makeReport(1, 99, (bool) 1, 'rsst', (bool) 1);
        $user = ['id' => 5, 'site_id' => 2, 'role' => 'chsct'];
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testChsctCannotAccessWithoutConsent(): void
    {
        $report = $this->makeReport(1, 99, (bool) 1, 'rsst', (bool) 0);
        $user = ['id' => 5, 'site_id' => 2, 'role' => 'chsct'];
        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessOtherSiteReport(): void
    {
        $report = $this->makeReport(1, 99, (bool) 0, 'rsst');
        $user = ['id' => 5, 'site_id' => 2, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCannotAccessOtherConfidentialReport(): void
    {
        $report = $this->makeReport(1, 99, (bool) 1, 'rsst');
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessOwnConfidentialReport(): void
    {
        $report = $this->makeReport(1, 5, (bool) 1, 'rsst');
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessPublicReportOnSameSite(): void
    {
        $report = $this->makeReport(1, 99, (bool) 0, 'rsst');
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentChoiceModeRespectsConfidentialFlag(): void
    {
        $report = $this->makeReport(1, 99, (bool) 0, 'rsst');
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'agent_choice'));

        $report2 = $this->makeReport(1, 99, (bool) 1, 'rsst');
        $this->assertFalse(canAccessReport($report2, $user, 'agent_choice'));
    }

    public function testAgentCanAccessOwnConfidentialReportInAgentChoiceMode(): void
    {
        $report = $this->makeReport(1, 5, (bool) 1, 'rsst');
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'agent_choice'));
    }

    public function testSuperviseurCanAccessConfidentialAcrossSites(): void
    {
        $report = $this->makeReport(10, 99, (bool) 1, 'rsst');
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'superviseur'];
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testChsctCanAccessPublicAcrossSitesWithConsent(): void
    {
        $report = $this->makeReport(10, 99, (bool) 0, 'rsst', (bool) 1);
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'chsct'];
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCanAccessDifferentSiteInPublicMode(): void
    {
        $report = $this->makeReport(2, 99, (bool) 0, 'rsst');
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCanAccessOwnConfidentialInConfidentialMode(): void
    {
        $report = $this->makeReport(1, 5, (bool) 1, 'rsst');
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCannotAccessOtherConfidentialInConfidentialModeSameSite(): void
    {
        $report = $this->makeReport(1, 99, (bool) 1, 'rsst');
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }
}
