<?php
/**
 * Helpers Unit Tests — Application SST DREETS BFC
 *
 * Tests the helper functions (validatePostRequest, canAccessReport,
 * userSelectWithSite, etc.) that don't require a database.
 */

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    // ─── canAccessReport() ────────────────────────────────────────────────────

    public function testSuperviseurCanAccessAnyReport(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 99, 'is_confidential' => 1, 'type' => 'rsst'];
        $user   = ['id' => 1, 'site_id' => 2, 'role' => 'superviseur'];

        $this->assertTrue(canAccessReport($report, $user));
    }

    public function testChsctCanAccessReportWithConsent(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 99, 'is_confidential' => 1, 'type' => 'rsst', 'consent_syndicat' => 1];
        $user   = ['id' => 1, 'site_id' => 2, 'role' => 'chsct'];

        $this->assertTrue(canAccessReport($report, $user));
    }

    public function testChsctCannotAccessReportWithoutConsent(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 99, 'is_confidential' => 1, 'type' => 'rsst', 'consent_syndicat' => 0];
        $user   = ['id' => 1, 'site_id' => 2, 'role' => 'chsct'];

        $this->assertFalse(canAccessReport($report, $user));
    }

    public function testAgentCannotAccessOtherSiteReport(): void
    {
        $report = ['site_id' => 2, 'declarant_id' => 99, 'is_confidential' => 0, 'type' => 'rsst'];
        $user   = ['id' => 1, 'site_id' => 1, 'role' => 'agent'];

        $this->assertFalse(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCanAccessOwnConfidentialReport(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 1, 'is_confidential' => 1, 'type' => 'rsst'];
        $user   = ['id' => 1, 'site_id' => 1, 'role' => 'agent'];

        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCannotAccessOtherAgentConfidentialReport(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 99, 'is_confidential' => 1, 'type' => 'rsst'];
        $user   = ['id' => 1, 'site_id' => 1, 'role' => 'agent'];

        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessPublicReportOnSameSite(): void
    {
        $report = ['site_id' => 1, 'declarant_id' => 99, 'is_confidential' => 0, 'type' => 'rsst'];
        $user   = ['id' => 1, 'site_id' => 1, 'role' => 'agent'];

        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentChoiceModeRespectsIsConfidential(): void
    {
        // Agent can see other agent's non-confidential report in agent_choice mode
        $report = ['site_id' => 1, 'declarant_id' => 99, 'is_confidential' => 0, 'type' => 'rsst'];
        $user   = ['id' => 1, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'agent_choice'));

        // Agent cannot see other agent's confidential report in agent_choice mode
        $report['is_confidential'] = 1;
        $this->assertFalse(canAccessReport($report, $user, 'agent_choice'));
    }

    // ─── formatDateFR() ───────────────────────────────────────────────────────

    public function testFormatDateFR(): void
    {
        $this->assertEquals('15/03/2025', formatDateFR('2025-03-15'));
    }

    public function testFormatDateFRReturnsDashForEmpty(): void
    {
        $this->assertEquals('—', formatDateFR(null));
        $this->assertEquals('—', formatDateFR(''));
    }

    public function testFormatDateFRReturnsOriginalForInvalid(): void
    {
        $this->assertEquals('not-a-date', formatDateFR('not-a-date'));
    }

    // ─── e() escaping ─────────────────────────────────────────────────────────

    public function testHtmlEscaping(): void
    {
        $this->assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
        $this->assertEquals('O&#039;Reilly', e("O'Reilly"));
        $this->assertEquals('', e(null));
    }

    // ─── generateReference() ──────────────────────────────────────────────────

    public function testGenerateReference(): void
    {
        $this->assertEquals('rsst-25-001', generateReference('rsst', '25', 1));
        $this->assertEquals('rami-26-042', generateReference('rami', '26', 42));
        $this->assertEquals('dgi-24-999', generateReference('dgi', '24', 999));
    }

    // ─── truncate() ───────────────────────────────────────────────────────────

    public function testTruncateShortString(): void
    {
        $this->assertEquals('Hello', truncate('Hello', 10));
    }

    public function testTruncateLongString(): void
    {
        $result = truncate('Un très long texte qui dépasse la limite', 20);
        $this->assertEquals(20 + mb_strlen('…', 'UTF-8'), mb_strlen($result, 'UTF-8'));
        $this->assertStringEndsWith('…', $result);
    }

    // ─── normalizeVisibilityValue() ───────────────────────────────────────────

    public function testNormalizeVisibilityValue(): void
    {
        $this->assertEquals('public', normalizeVisibilityValue('0'));
        $this->assertEquals('public', normalizeVisibilityValue('site'));
        $this->assertEquals('confidential', normalizeVisibilityValue('1'));
        $this->assertEquals('confidential', normalizeVisibilityValue('own'));
        $this->assertEquals('agent_choice', normalizeVisibilityValue('agent_choice'));
        $this->assertEquals('agent_choice', normalizeVisibilityValue('unknown_value'));
    }

    // ─── getRegistryColor() / getEtatBadgeClass() / etc. ─────────────────────

    public function testGetRegistryColor(): void
    {
        $this->assertEquals('var(--rsst-color)', getRegistryColor('rsst'));
        $this->assertEquals('var(--rami-color)', getRegistryColor('rami'));
        $this->assertEquals('var(--dgi-color)', getRegistryColor('dgi'));
    }

    public function testGetEtatBadgeClass(): void
    {
        $this->assertEquals('badge--nouveau', getEtatBadgeClass('nouveau'));
        $this->assertEquals('badge--en-cours', getEtatBadgeClass('en_cours'));
        $this->assertEquals('badge--traite', getEtatBadgeClass('traite'));
        $this->assertEquals('badge--abandonne', getEtatBadgeClass('abandonne'));
        $this->assertEquals('', getEtatBadgeClass('unknown'));
    }

    public function testGetRoleBadgeClass(): void
    {
        $this->assertEquals('badge--agent', getRoleBadgeClass('agent'));
        $this->assertEquals('badge--superviseur', getRoleBadgeClass('superviseur'));
        $this->assertEquals('badge--chsct', getRoleBadgeClass('chsct'));
    }
}
