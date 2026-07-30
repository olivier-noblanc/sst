<?php
/**
 * Tests ConfigService methods exhaustively — kills Infection mutants on:
 *   - getRoleLabel / getRoleLabelShort (default label, custom label, prefix stripping)
 *   - getAppVersion (changelog parsing, path resolution, regex)
 *   - getRoleLabels (mapping)
 *   - isRegistryEnabled (CastInt, Identical)
 */

use PHPUnit\Framework\TestCase;
use App\Services\ConfigService;
use App\Enum\UserRole;

class ConfigServiceMutationTest extends TestCase
{
    private PDO $pdo;
    private ConfigService $service;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('PRAGMA foreign_keys = OFF');
        $this->pdo->exec('DELETE FROM config_app');
        $this->pdo->exec('DELETE FROM registry_fields');
        $this->pdo->exec('DELETE FROM registries');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        clearConfigCache();
        $this->service = new ConfigService();
    }

    protected function tearDown(): void
    {
        reseedDefaultRegistries($this->pdo);
    }

    // ═══ getRoleLabel() ═══

    public function testGetRoleLabelReturnsDefaultForAgent(): void
    {
        // Kill mutant that would return a different default
        $this->assertSame('Agent', $this->service->getRoleLabel('agent'));
    }

    public function testGetRoleLabelReturnsDefaultForSuperviseur(): void
    {
        $this->assertSame('Superviseur', $this->service->getRoleLabel('superviseur'));
    }

    public function testGetRoleLabelReturnsDefaultForChsct(): void
    {
        // Kill mutant on UserRole::Chsct->defaultLabel() — must be 'Membre FS/CSA'
        $this->assertSame('Membre FS/CSA', $this->service->getRoleLabel('chsct'));
    }

    public function testGetRoleLabelReturnsCustomWhenSet(): void
    {
        // Kill LogicalNot mutant on $dbValue !== ''
        $this->service->set('app_role_label_agent', 'Agent SST');
        $this->assertSame('Agent SST', $this->service->getRoleLabel('agent'));
    }

    public function testGetRoleLabelFallsBackToUcfirstForUnknownRole(): void
    {
        // Kill mutant on UserRole::tryFrom($role)?->defaultLabel() ?? ucfirst($role)
        $this->assertSame('Admin', $this->service->getRoleLabel('admin'), 'unknown role → ucfirst');
        $this->assertSame('Unknownrole', $this->service->getRoleLabel('unknownrole'));
    }

    public function testGetRoleLabelEmptyCustomFallsBackToDefault(): void
    {
        // Kill mutant on $dbValue !== '' — empty string should fall back
        $this->service->set('app_role_label_agent', '');
        clearConfigCache();
        $this->assertSame('Agent', $this->service->getRoleLabel('agent'), 'empty custom → default');
    }

    // ═══ getRoleLabelShort() ═══

    public function testGetRoleLabelShortStripsMembrePrefix(): void
    {
        // Kill mutant on stripos / substr
        $this->service->set('app_role_label_chsct', 'Membre FS/CSA');
        $this->assertSame('FS/CSA', $this->service->getRoleLabelShort('chsct'), 'strip "Membre " prefix');
    }

    public function testGetRoleLabelShortStripsLowercaseMembrePrefix(): void
    {
        // Kill mutant on second prefix 'membre '
        $this->service->set('app_role_label_chsct', 'membre FS/CSA');
        $this->assertSame('FS/CSA', $this->service->getRoleLabelShort('chsct'), 'strip "membre " prefix');
    }

    public function testGetRoleLabelShortReturnsLabelUnchangedWhenNoPrefix(): void
    {
        // Kill mutant on the foreach loop / stripos check
        $this->assertSame('Agent', $this->service->getRoleLabelShort('agent'));
        $this->assertSame('Superviseur', $this->service->getRoleLabelShort('superviseur'));
    }

    public function testGetRoleLabelShortDoesNotMatchPartialPrefix(): void
    {
        // Kill mutant that would match 'Membre' anywhere in the string
        $this->service->set('app_role_label_agent', 'Membres associés');
        $this->assertSame('Membres associés', $this->service->getRoleLabelShort('agent'), 'partial prefix should not be stripped');
    }

    // ═══ getRoleLabels() ═══

    public function testGetRoleLabelsReturnsAllThreeRoles(): void
    {
        // Kill ArrayItemRemoval / UnwrapArrayMap mutants
        $labels = $this->service->getRoleLabels();
        $this->assertCount(3, $labels);
        $this->assertArrayHasKey('agent', $labels);
        $this->assertArrayHasKey('superviseur', $labels);
        $this->assertArrayHasKey('chsct', $labels);
    }

    public function testGetRoleLabelsReturnsCorrectDefaultValues(): void
    {
        $labels = $this->service->getRoleLabels();
        $this->assertSame('Agent', $labels['agent']);
        $this->assertSame('Superviseur', $labels['superviseur']);
        $this->assertSame('Membre FS/CSA', $labels['chsct']);
    }

    public function testGetRoleLabelsReturnsCustomValuesWhenSet(): void
    {
        $this->service->set('app_role_label_agent', 'Agent Personnalisé');
        $labels = $this->service->getRoleLabels();
        $this->assertSame('Agent Personnalisé', $labels['agent']);
        $this->assertSame('Superviseur', $labels['superviseur']); // unchanged
    }

    // ═══ getAppVersion() ═══

    public function testGetAppVersionReturnsValidSemverFormat(): void
    {
        // Kill preg_match mutant — must match /\[(\d+\.\d+\.\d+)\]/
        $version = $this->service->getAppVersion();
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version, 'version must be semver X.Y.Z');
    }

    public function testGetAppVersionIsConsistentAcrossCalls(): void
    {
        // Kill ReturnRemoval mutant on the static cache
        $v1 = $this->service->getAppVersion();
        $v2 = $this->service->getAppVersion();
        $this->assertSame($v1, $v2, 'getAppVersion must be deterministic');
    }

    public function testGetAppVersionReturnsNonEmptyString(): void
    {
        $version = $this->service->getAppVersion();
        $this->assertNotSame('', $version, 'version must not be empty');
        $this->assertNotSame('0.0.0', $version, 'CHANGELOG.md should be readable and have a version');
    }

    // ═══ isRegistryEnabled() ═══

    public function testIsRegistryEnabledReturnsFalseForUnknownCode(): void
    {
        // Kill mutant on $reg !== null check
        $this->assertFalse($this->service->isRegistryEnabled('unknown_registry'));
    }

    public function testIsRegistryEnabledHandlesStringIsEnabledValue(): void
    {
        // Kill CastInt mutant on (int) $reg['is_enabled']
        // Seed RSST as enabled for this test
        $this->pdo->exec("INSERT INTO registries (code, label, short_label, is_enabled, is_system, sort_order, default_visibility) VALUES ('rsst', 'RSST', 'RSST', 1, 1, 1, 'agent_choice')");
        clearConfigCache();
        $this->assertTrue($this->service->isRegistryEnabled('rsst'));
    }

    public function testIsRegistryEnabledReturnsFalseWhenIsEnabledIsZero(): void
    {
        // Kill Identical mutant on === 1
        // Seed RAMI as disabled (is_enabled=0)
        $this->pdo->exec("INSERT INTO registries (code, label, short_label, is_enabled, is_system, sort_order, default_visibility) VALUES ('rami', 'RAMI', 'RAMI', 0, 0, 2, 'agent_choice')");
        clearConfigCache();
        $this->assertFalse($this->service->isRegistryEnabled('rami'));
    }
}
