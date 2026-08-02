<?php
/**
 * UserRole Enum Tests — Application SST DREETS BFC
 *
 * Tests the UserRole enum for correctness and consistency.
 */

use PHPUnit\Framework\TestCase;
use App\Enum\UserRole;

class UserRoleTest extends TestCase
{
    /**
     * UserRole::cases() must contain exactly 3 values.
     */
    public function testCasesContainsExactlyThreeValues(): void
    {
        $cases = UserRole::cases();
        $this->assertCount(3, $cases, 'UserRole must have exactly 3 cases');

        $values = array_map(fn(UserRole $r) => $r->value, $cases);
        $this->assertEqualsCanonicalizing(
            ['agent', 'superviseur', 'chsct'],
            $values,
            'UserRole values must match the expected set'
        );
    }

    /**
     * canSeeAllSites() must return false for agent, true for superviseur and chsct.
     */
    public function testCanSeeAllSites(): void
    {
        $this->assertFalse(UserRole::Agent->canSeeAllSites());
        $this->assertTrue(UserRole::Superviseur->canSeeAllSites());
        $this->assertTrue(UserRole::Chsct->canSeeAllSites());
    }

    /**
     * defaultLabel() must return unique labels.
     */
    public function testDefaultLabelsAreUnique(): void
    {
        $labels = array_map(fn(UserRole $r) => $r->defaultLabel(), UserRole::cases());
        $this->assertCount(3, array_unique($labels), 'All UserRole default labels must be unique');
    }

    /**
     * ROLE_* constants must match the enum values.
     */
    public function testConstantsMatchEnumValues(): void
    {
        $this->assertSame(ROLE_AGENT, UserRole::Agent->value);
        $this->assertSame(ROLE_SUPERVISEUR, UserRole::Superviseur->value);
        $this->assertSame(ROLE_CHSCT, UserRole::Chsct->value);
    }

    /**
     * ROLE_LABELS_DEFAULT must be derived from the enum.
     */
    public function testRoleLabelsDefaultMatchEnum(): void
    {
        $expected = array_combine(
            array_map(fn(UserRole $r) => $r->value, UserRole::cases()),
            array_map(fn(UserRole $r) => $r->defaultLabel(), UserRole::cases())
        );
        $this->assertEquals($expected, ROLE_LABELS_DEFAULT);
    }

    /**
     * Regression: ConfigService::getRoleLabel() with custom DB value must still work.
     */
    public function testGetRoleLabelPrefersDbValue(): void
    {
        $configService = \getConfigService();
        // Set a custom label
        $configService->set('app_role_label_chsct', 'Custom CHSCT Label');
        $this->assertEquals('Custom CHSCT Label', $configService->getRoleLabel('chsct'));
        // Clean up
        $configService->set('app_role_label_chsct', '');
        $configService->clearCache();
    }

    /**
     * ConfigService::getRoleLabel() must fall back to defaultLabel() when no DB value.
     */
    public function testGetRoleLabelFallsBackToDefault(): void
    {
        $configService = \getConfigService();
        $configService->set('app_role_label_chsct', '');
        $configService->clearCache();
        $this->assertEquals('Membre FS/CSA', $configService->getRoleLabel('chsct'));
    }

    /**
     * ConfigService::getRoleLabel() must handle invalid role gracefully.
     */
    public function testGetRoleLabelHandlesInvalidRole(): void
    {
        $configService = \getConfigService();
        $this->assertEquals('Invalid', $configService->getRoleLabel('invalid'));
    }

    /**
     * AccessService::canSeeAllSites() must be consistent with enum for all 3 roles.
     */
    public function testAccessServiceCanSeeAllSitesConsistency(): void
    {
        $accessService = new \App\Services\AccessService();
        $this->assertFalse($accessService->canSeeAllSites(ROLE_AGENT));
        $this->assertTrue($accessService->canSeeAllSites(ROLE_SUPERVISEUR));
        $this->assertTrue($accessService->canSeeAllSites(ROLE_CHSCT));
    }

    /**
     * AccessService::canSeeAllSites() must return false for invalid role, not crash.
     */
    public function testAccessServiceCanSeeAllSitesInvalidRole(): void
    {
        $accessService = new \App\Services\AccessService();
        $this->assertFalse($accessService->canSeeAllSites('invalid_role'));
    }

    /**
     * from() must reject invalid values.
     */
    public function testFromRejectsInvalidValues(): void
    {
        $this->expectException(ValueError::class);
        UserRole::from('invalid_role');
    }

    /**
     * tryFrom() must return null for invalid values.
     */
    public function testTryFromReturnsNullForInvalid(): void
    {
        $this->assertNull(UserRole::tryFrom('invalid_role'));
    }

    /**
     * tryFrom() must return the correct case for valid values.
     */
    public function testTryFromReturnsCorrectCase(): void
    {
        $this->assertSame(UserRole::Agent, UserRole::tryFrom('agent'));
        $this->assertSame(UserRole::Superviseur, UserRole::tryFrom('superviseur'));
        $this->assertSame(UserRole::Chsct, UserRole::tryFrom('chsct'));
    }
}
