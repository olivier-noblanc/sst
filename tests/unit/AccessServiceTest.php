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

    public function testAgentCanAccessReportRegardlessOfSite(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 2, 'type' => 'rsst'];
        $user = ['id' => 3, 'role' => ROLE_AGENT, 'site_id' => 2];
        $this->assertTrue($this->service->canAccessReport($report, $user, 'public'));
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

    // ═══════════════════════════════════════════════════════════════════════════════
    // getReportVisibilityMode() — custom registries
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Regression test — custom registries have no entry in Paramètres >
     * Signalements (that tab only lists the 3 ReportType cases), so their
     * 'app_report_visibility_{code}' config key is never set via any admin
     * screen. Before this fix, getReportVisibilityMode() ignored the
     * registry's own "Visibilité par défaut" (registries.default_visibility)
     * entirely and silently fell back to the global default — so setting a
     * custom registry to "confidentiel" in Paramètres > Registres had zero
     * effect: the "signalement confidentiel" checkbox still showed on
     * report_create instead of being forced.
     */
    public function testCustomRegistryUsesItsOwnDefaultVisibility(): void
    {
        $pdo = getDB();
        $pdo->exec("INSERT INTO registries (code, label, short_label, icon, color_theme, is_enabled, is_system, sort_order, default_visibility) VALUES ('test.confidential-registry', 'Test Confidentiel', 'TC', '📋', 'violet', 0, 0, 999, 'confidential')");

        $this->assertTrue($this->service->reportVisibilityIsConfidential('test.confidential-registry'));
        $this->assertSame('confidential', $this->service->getReportVisibilityMode('test.confidential-registry'));
    }

    /**
     * Guard: the fallback above must only apply to genuinely custom registry
     * codes (outside the rsst/rami/dgi ReportType enum). System registries
     * must keep being governed exclusively by Paramètres > Signalements
     * (app_report_visibility_{type} config / global default), regardless of
     * whatever value happens to sit in their own registries.default_visibility
     * row — that column is a seed-time default, not a live override, for
     * rsst/rami/dgi.
     */
    public function testSystemRegistryIgnoresItsOwnDefaultVisibilityColumn(): void
    {
        $configService = \getConfigService();
        $configService->set('app_report_visibility_rami', '');
        $configService->set('app_report_visibility', 'public');
        $configService->clearCache();

        // registries.default_visibility for 'rami' is seeded as 'agent_choice'
        // (see RegistryRepository::seedDefaults()) — must be ignored here.
        $this->assertSame('public', $this->service->getReportVisibilityMode('rami'));
    }
}
