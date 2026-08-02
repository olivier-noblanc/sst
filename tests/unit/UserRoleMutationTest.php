<?php
/**
 * Tests UserRole enum exhaustively — kills Infection mutants on:
 *   - Match arms (defaultLabel, canSeeAllSites)
 *   - Identical / TrueValue / FalseValue mutants
 */

use PHPUnit\Framework\TestCase;
use App\Enum\UserRole;

class UserRoleMutationTest extends TestCase
{
    public function testEnumValuesExact(): void
    {
        $this->assertSame('agent', UserRole::Agent->value);
        $this->assertSame('superviseur', UserRole::Superviseur->value);
        $this->assertSame('chsct', UserRole::Chsct->value);
    }

    public function testTryFromAcceptsValidValues(): void
    {
        $this->assertSame(UserRole::Agent, UserRole::tryFrom('agent'));
        $this->assertSame(UserRole::Superviseur, UserRole::tryFrom('superviseur'));
        $this->assertSame(UserRole::Chsct, UserRole::tryFrom('chsct'));
    }

    public function testTryFromRejectsInvalidValues(): void
    {
        $this->assertNull(UserRole::tryFrom('admin'));
        $this->assertNull(UserRole::tryFrom('Agent')); // case-sensitive
        $this->assertNull(UserRole::tryFrom(''));
    }

    public function testCasesReturnsAll3InOrder(): void
    {
        $cases = UserRole::cases();
        $this->assertCount(3, $cases);
        $this->assertSame(UserRole::Agent, $cases[0]);
        $this->assertSame(UserRole::Superviseur, $cases[1]);
        $this->assertSame(UserRole::Chsct, $cases[2]);
    }

    public function testDefaultLabelExactForAllCases(): void
    {
        $this->assertSame('Agent', UserRole::Agent->defaultLabel());
        $this->assertSame('Superviseur', UserRole::Superviseur->defaultLabel());
        // Kill mutant that would return 'CHSCT' instead of 'Membre FS/CSA'
        $this->assertSame('Membre FS/CSA', UserRole::Chsct->defaultLabel(), 'CHSCT default label uses FS/CSA terminology per AGENTS.md');
    }

    /**
     * Kill TrueValue/FalseValue mutants — canSeeAllSites is the security gate
     * for cross-site visibility. Each role must return the exact expected value.
     */
    public function testCanSeeAllSitesTruthTable(): void
    {
        $this->assertFalse(UserRole::Agent->canSeeAllSites(), 'Agent cannot see all sites');
        $this->assertTrue(UserRole::Superviseur->canSeeAllSites(), 'Superviseur can see all sites');
        $this->assertTrue(UserRole::Chsct->canSeeAllSites(), 'CHSCT can see all sites');
    }

    /**
     * Kill mutant where Agent could see all sites (security regression).
     */
    public function testAgentCannotSeeAllSitesIsEnforced(): void
    {
        // Negative assertion — Agent is the ONLY role that cannot see all sites
        $this->assertFalse(UserRole::Agent->canSeeAllSites());
        // All other roles must be able to see all sites
        $this->assertTrue(UserRole::Superviseur->canSeeAllSites());
        $this->assertTrue(UserRole::Chsct->canSeeAllSites());
    }

    public function testDefaultLabelsAreUnique(): void
    {
        $labels = array_map(fn($r) => $r->defaultLabel(), UserRole::cases());
        $this->assertSame(count($labels), count(array_unique($labels)));
    }
}
