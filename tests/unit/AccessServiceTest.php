<?php
/**
 * AccessService Unit Tests — Report access control, edit/respond permissions
 *
 * Tests AccessService from src/Services/AccessService.php:
 * - canAccessReport() with different roles and visibility modes
 * - canEditReport() based on declarant ownership and report state
 * - canRespondToReport() based on role and report state
 * - normalizeVisibilityValue() mapping of raw config values
 */

use PHPUnit\Framework\TestCase;
use App\Services\AccessService;

class AccessServiceTest extends TestCase
{
    private AccessService $service;

    protected function setUp(): void
    {
        $this->service = new AccessService();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // canAccessReport()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSuperviseurCanAccessAnyReport(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 99, 'consent_syndicat' => 0];
        $user = ['id' => 1, 'role' => ROLE_SUPERVISEUR, 'site_id' => 2];
        $this->assertTrue($this->service->canAccessReport($report, $user));
    }

    public function testChsctCanAccessReportWithConsentSyndicat(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 99, 'consent_syndicat' => 1];
        $user = ['id' => 1, 'role' => ROLE_CHSCT, 'site_id' => 1];
        $this->assertTrue($this->service->canAccessReport($report, $user));
    }

    public function testChsctCannotAccessReportWithoutConsentSyndicat(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 99, 'consent_syndicat' => 0];
        $user = ['id' => 1, 'role' => ROLE_CHSCT, 'site_id' => 1];
        $this->assertFalse($this->service->canAccessReport($report, $user));
    }

    public function testAgentCanAccessReportOnSameSitePublicVisibility(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 2, 'type' => 'rsst'];
        $user = ['id' => 3, 'role' => ROLE_AGENT, 'site_id' => 1];
        $this->assertTrue($this->service->canAccessReport($report, $user, 'public'));
    }

    public function testAgentCannotAccessReportOnDifferentSite(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 2, 'type' => 'rsst'];
        $user = ['id' => 3, 'role' => ROLE_AGENT, 'site_id' => 2];
        $this->assertFalse($this->service->canAccessReport($report, $user, 'public'));
    }

    public function testAgentCannotAccessConfidentialReportTheyDidNotDeclare(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 2, 'type' => 'rsst'];
        $user = ['id' => 3, 'role' => ROLE_AGENT, 'site_id' => 1];
        $this->assertFalse($this->service->canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessConfidentialReportTheyDeclared(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 3, 'type' => 'rsst'];
        $user = ['id' => 3, 'role' => ROLE_AGENT, 'site_id' => 1];
        $this->assertTrue($this->service->canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentChoiceConfidentialReportAccessibleOnlyToDeclarant(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 2, 'is_confidential' => 1, 'type' => 'rsst'];
        $userOther = ['id' => 3, 'role' => ROLE_AGENT, 'site_id' => 1];
        $this->assertFalse($this->service->canAccessReport($report, $userOther, 'agent_choice'));

        $userDeclarant = ['id' => 2, 'role' => ROLE_AGENT, 'site_id' => 1];
        $this->assertTrue($this->service->canAccessReport($report, $userDeclarant, 'agent_choice'));
    }

    public function testAgentChoiceNonConfidentialReportAccessibleToAllOnSite(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 2, 'is_confidential' => 0, 'type' => 'rsst'];
        $user = ['id' => 3, 'role' => ROLE_AGENT, 'site_id' => 1];
        $this->assertTrue($this->service->canAccessReport($report, $user, 'agent_choice'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // canEditReport()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testDeclarantCanEditNouveauReport(): void
    {
        $report = ['declarant_id' => 1, 'etat' => ETAT_NOUVEAU];
        $this->assertTrue($this->service->canEditReport($report, 1));
    }

    public function testDeclarantCanEditEnCoursReport(): void
    {
        $report = ['declarant_id' => 1, 'etat' => ETAT_EN_COURS];
        $this->assertTrue($this->service->canEditReport($report, 1));
    }

    public function testDeclarantCannotEditTraiteReport(): void
    {
        $report = ['declarant_id' => 1, 'etat' => ETAT_TRAITE];
        $this->assertFalse($this->service->canEditReport($report, 1));
    }

    public function testDeclarantCannotEditAbandonneReport(): void
    {
        $report = ['declarant_id' => 1, 'etat' => ETAT_ABANDONNE];
        $this->assertFalse($this->service->canEditReport($report, 1));
    }

    public function testNonDeclarantCannotEditReport(): void
    {
        $report = ['declarant_id' => 1, 'etat' => ETAT_NOUVEAU];
        $this->assertFalse($this->service->canEditReport($report, 2));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // canRespondToReport()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSuperviseurCanRespondToNouveauReport(): void
    {
        $report = ['etat' => ETAT_NOUVEAU];
        $this->assertTrue($this->service->canRespondToReport($report, ROLE_SUPERVISEUR));
    }

    public function testSuperviseurCanRespondToEnCoursReport(): void
    {
        $report = ['etat' => ETAT_EN_COURS];
        $this->assertTrue($this->service->canRespondToReport($report, ROLE_SUPERVISEUR));
    }

    public function testSuperviseurCanRespondToReouvertReport(): void
    {
        $report = ['etat' => ETAT_REOUVERT];
        $this->assertTrue($this->service->canRespondToReport($report, ROLE_SUPERVISEUR));
    }

    public function testSuperviseurCannotRespondToTraiteReport(): void
    {
        $report = ['etat' => ETAT_TRAITE];
        $this->assertFalse($this->service->canRespondToReport($report, ROLE_SUPERVISEUR));
    }

    public function testAgentCannotRespondToReport(): void
    {
        $report = ['etat' => ETAT_NOUVEAU];
        $this->assertFalse($this->service->canRespondToReport($report, ROLE_AGENT));
    }

    public function testChsctCannotRespondToReport(): void
    {
        $report = ['etat' => ETAT_NOUVEAU];
        $this->assertFalse($this->service->canRespondToReport($report, ROLE_CHSCT));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // normalizeChsctScope()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testNormalizeChsctScopeConsentOnly(): void
    {
        $this->assertEquals('consent_only', $this->service->normalizeChsctScope('consent_only'));
    }

    public function testNormalizeChsctScopeAll(): void
    {
        $this->assertEquals('all', $this->service->normalizeChsctScope('all'));
    }

    public function testNormalizeChsctScopeInvalidDefaultsToConsentOnly(): void
    {
        $this->assertEquals('consent_only', $this->service->normalizeChsctScope('bogus'));
    }

    public function testNormalizeChsctScopeEmptyDefaultsToConsentOnly(): void
    {
        $this->assertEquals('consent_only', $this->service->normalizeChsctScope(''));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // normalizeVisibilityValue()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testNormalizeVisibilityValueZeroToPublic(): void
    {
        $this->assertEquals('public', $this->service->normalizeVisibilityValue('0'));
    }

    public function testNormalizeVisibilityValueSiteToPublic(): void
    {
        $this->assertEquals('public', $this->service->normalizeVisibilityValue('site'));
    }

    public function testNormalizeVisibilityValueOneToConfidential(): void
    {
        $this->assertEquals('confidential', $this->service->normalizeVisibilityValue('1'));
    }

    public function testNormalizeVisibilityValueOwnToConfidential(): void
    {
        $this->assertEquals('confidential', $this->service->normalizeVisibilityValue('own'));
    }

    public function testNormalizeVisibilityValuePassThroughValidValues(): void
    {
        $this->assertEquals('confidential', $this->service->normalizeVisibilityValue('confidential'));
        $this->assertEquals('agent_choice', $this->service->normalizeVisibilityValue('agent_choice'));
        $this->assertEquals('public', $this->service->normalizeVisibilityValue('public'));
    }

    public function testNormalizeVisibilityValueUnknownDefaultsToAgentChoice(): void
    {
        $this->assertEquals('agent_choice', $this->service->normalizeVisibilityValue('bogus'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Service instantiation
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testServiceCanBeInstantiated(): void
    {
        $service = new AccessService();
        $this->assertInstanceOf(AccessService::class, $service);
    }
}
