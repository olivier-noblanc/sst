<?php
/**
 * VisibilityMode Enum Tests — Application SST DREETS BFC
 *
 * Tests the VisibilityMode enum for correctness and consistency.
 */

use PHPUnit\Framework\TestCase;
use App\Enum\VisibilityMode;

class VisibilityModeTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset RSST visibility to default (public) to avoid test ordering issues
        $configService = \getConfigService();
        $configService->set('app_report_visibility_rsst', 'public');
        $configService->clearCache();
    }

    /**
     * VisibilityMode::cases() must contain exactly 3 values.
     */
    public function testCasesContainsExactlyThreeValues(): void
    {
        $cases = VisibilityMode::cases();
        $this->assertCount(3, $cases, 'VisibilityMode must have exactly 3 cases');

        $values = array_map(fn(VisibilityMode $v) => $v->value, $cases);
        $this->assertEqualsCanonicalizing(
            ['confidential', 'agent_choice', 'public'],
            $values,
            'VisibilityMode values must match the expected set'
        );
    }

    /**
     * normalizeVisibilityValue() must return correct values for legacy inputs.
     */
    public function testNormalizeVisibilityValueLegacyInputs(): void
    {
        $accessService = new \App\Services\AccessService();
        $this->assertEquals('public', $accessService->normalizeVisibilityValue('0'));
        $this->assertEquals('public', $accessService->normalizeVisibilityValue('site'));
        $this->assertEquals('confidential', $accessService->normalizeVisibilityValue('1'));
        $this->assertEquals('confidential', $accessService->normalizeVisibilityValue('own'));
    }

    /**
     * normalizeVisibilityValue() must pass through valid values.
     */
    public function testNormalizeVisibilityValuePassThrough(): void
    {
        $accessService = new \App\Services\AccessService();
        $this->assertEquals('confidential', $accessService->normalizeVisibilityValue('confidential'));
        $this->assertEquals('agent_choice', $accessService->normalizeVisibilityValue('agent_choice'));
        $this->assertEquals('public', $accessService->normalizeVisibilityValue('public'));
    }

    /**
     * normalizeVisibilityValue() must default to agent_choice for invalid values.
     */
    public function testNormalizeVisibilityValueInvalidDefault(): void
    {
        $accessService = new \App\Services\AccessService();
        $this->assertEquals('agent_choice', $accessService->normalizeVisibilityValue('bogus'));
        $this->assertEquals('agent_choice', $accessService->normalizeVisibilityValue(''));
    }

    /**
     * reportVisibilityIs* methods must be consistent with normalizeVisibilityValue().
     */
    public function testReportVisibilityIsConsistency(): void
    {
        $accessService = new \App\Services\AccessService();
        // rsst defaults to 'public' in config (per schema.sql default)
        $this->assertFalse($accessService->reportVisibilityIsConfidential('rsst'));
        $this->assertFalse($accessService->reportVisibilityIsAgentChoice('rsst'));
        $this->assertTrue($accessService->reportVisibilityIsPublic('rsst'));
    }

    /**
     * from() must reject invalid values.
     */
    public function testFromRejectsInvalidValues(): void
    {
        $this->expectException(ValueError::class);
        VisibilityMode::from('invalid_mode');
    }

    /**
     * tryFrom() must return null for invalid values.
     */
    public function testTryFromReturnsNullForInvalid(): void
    {
        $this->assertNull(VisibilityMode::tryFrom('invalid_mode'));
    }

    /**
     * tryFrom() must return the correct case for valid values.
     */
    public function testTryFromReturnsCorrectCase(): void
    {
        $this->assertSame(VisibilityMode::Confidential, VisibilityMode::tryFrom('confidential'));
        $this->assertSame(VisibilityMode::AgentChoice, VisibilityMode::tryFrom('agent_choice'));
        $this->assertSame(VisibilityMode::Public, VisibilityMode::tryFrom('public'));
    }
}
