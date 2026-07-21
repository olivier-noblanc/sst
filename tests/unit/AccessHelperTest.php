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

    public function testSuperviseurCanAlwaysAccess(): void
    {
        $report = ['site_id' => '1', 'declarant_id' => '99', 'is_confidential' => '1', 'type' => 'rsst'];
        $user = ['id' => 5, 'site_id' => 2, 'role' => 'superviseur'];
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testChsctCanAlwaysAccessWithConsent(): void
    {
        $report = ['site_id' => '1', 'declarant_id' => '99', 'is_confidential' => '1', 'type' => 'rsst', 'consent_syndicat' => '1'];
        $user = ['id' => 5, 'site_id' => 2, 'role' => 'chsct'];
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testChsctCannotAccessWithoutConsent(): void
    {
        $report = ['site_id' => '1', 'declarant_id' => '99', 'is_confidential' => '1', 'type' => 'rsst', 'consent_syndicat' => '0'];
        $user = ['id' => 5, 'site_id' => 2, 'role' => 'chsct'];
        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessOtherSiteReport(): void
    {
        $report = ['site_id' => '1', 'declarant_id' => '99', 'is_confidential' => '0', 'type' => 'rsst'];
        $user = ['id' => 5, 'site_id' => 2, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCannotAccessOtherConfidentialReport(): void
    {
        $report = ['site_id' => '1', 'declarant_id' => '99', 'is_confidential' => '1', 'type' => 'rsst'];
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessOwnConfidentialReport(): void
    {
        $report = ['site_id' => '1', 'declarant_id' => '5', 'is_confidential' => '1', 'type' => 'rsst'];
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessPublicReportOnSameSite(): void
    {
        $report = ['site_id' => '1', 'declarant_id' => '99', 'is_confidential' => '0', 'type' => 'rsst'];
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentChoiceModeRespectsConfidentialFlag(): void
    {
        $report = ['site_id' => '1', 'declarant_id' => '99', 'is_confidential' => '0', 'type' => 'rsst'];
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'agent_choice'));

        $report2 = ['site_id' => '1', 'declarant_id' => '99', 'is_confidential' => '1', 'type' => 'rsst'];
        $this->assertFalse(canAccessReport($report2, $user, 'agent_choice'));
    }

    public function testAgentCanAccessOwnConfidentialReportInAgentChoiceMode(): void
    {
        $report = ['site_id' => '1', 'declarant_id' => '5', 'is_confidential' => '1', 'type' => 'rsst'];
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'agent_choice'));
    }

    public function testSuperviseurCanAccessConfidentialAcrossSites(): void
    {
        $report = ['site_id' => '10', 'declarant_id' => '99', 'is_confidential' => '1', 'type' => 'rsst'];
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'superviseur'];
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testChsctCanAccessPublicAcrossSitesWithConsent(): void
    {
        $report = ['site_id' => '10', 'declarant_id' => '99', 'is_confidential' => '0', 'type' => 'rsst', 'consent_syndicat' => '1'];
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'chsct'];
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCanAccessDifferentSiteInPublicMode(): void
    {
        $report = ['site_id' => '2', 'declarant_id' => '99', 'is_confidential' => '0', 'type' => 'rsst'];
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCanAccessOwnConfidentialInConfidentialMode(): void
    {
        $report = ['site_id' => '1', 'declarant_id' => '5', 'is_confidential' => '1', 'type' => 'rsst'];
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCannotAccessOtherConfidentialInConfidentialModeSameSite(): void
    {
        $report = ['site_id' => '1', 'declarant_id' => '99', 'is_confidential' => '1', 'type' => 'rsst'];
        $user = ['id' => 5, 'site_id' => 1, 'role' => 'agent'];
        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }
}
