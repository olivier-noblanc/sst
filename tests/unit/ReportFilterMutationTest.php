<?php
/**
 * Tests ReportFilter exhaustively — kills Infection mutants on:
 *   - Coalesce on every ?? '' / ?? 0
 *   - UnwrapTrim on trim($get['q'] ?? '')
 *   - Ternary on trim() !== '' ? trim() : null
 *   - CastInt on (int) ($get['site'] ?? 0)
 *   - TrueValue on seeAllSites=true / chsctConsentOnly=false
 *   - ArrayItemRemoval / ArrayItem on toArray()
 */

use PHPUnit\Framework\TestCase;
use App\DTO\ReportFilter;

class ReportFilterMutationTest extends TestCase
{
    public function testFromGetDefaultsWhenEmpty(): void
    {
        $f = ReportFilter::fromGet([], []);
        $this->assertSame('', $f->type);
        $this->assertSame('', $f->etat);
        $this->assertSame(0, $f->siteId);
        $this->assertNull($f->search);
        $this->assertTrue($f->seeAllSites);
        $this->assertFalse($f->chsctConsentOnly);
    }

    public function testFromGetPopulatesAllFields(): void
    {
        $f = ReportFilter::fromGet(['type' => 'rsst', 'etat' => 'nouveau', 'site' => '5', 'q' => 'keyword'], []);
        $this->assertSame('rsst', $f->type);
        $this->assertSame('nouveau', $f->etat);
        $this->assertSame(5, $f->siteId);
        $this->assertIsInt($f->siteId, 'siteId must be (int)');
        $this->assertSame('keyword', $f->search);
    }

    public function testSiteIdCastIntFromVariousInputs(): void
    {
        $this->assertSame(0, ReportFilter::fromGet([], [])->siteId);
        $this->assertSame(0, ReportFilter::fromGet(['site' => ''], [])->siteId);
        $this->assertSame(5, ReportFilter::fromGet(['site' => '5'], [])->siteId);
        $this->assertSame(5, ReportFilter::fromGet(['site' => 5], [])->siteId);
        $this->assertSame(0, ReportFilter::fromGet(['site' => 'abc'], [])->siteId);
    }

    public function testSearchTrimmedAndNullWhenEmpty(): void
    {
        $this->assertNull(ReportFilter::fromGet(['q' => ''], [])->search);
        $this->assertNull(ReportFilter::fromGet(['q' => '   '], [])->search);
        $this->assertNull(ReportFilter::fromGet([], [])->search);

        $this->assertSame('kw', ReportFilter::fromGet(['q' => 'kw'], [])->search);
        $this->assertSame('kw', ReportFilter::fromGet(['q' => '  kw  '], [])->search, 'q must be trimmed');
        $this->assertSame('multi word', ReportFilter::fromGet(['q' => '  multi word  '], [])->search);
    }

    public function testDefaultsSeeAllSitesTrueAndChsctConsentOnlyFalse(): void
    {
        $f = ReportFilter::fromGet([], []);
        $this->assertTrue($f->seeAllSites);
        $this->assertFalse($f->chsctConsentOnly);
    }

    /**
     * Kill toArray() ArrayItem mutants — every key must be in the array.
     */
    public function testToArrayContainsAllKeys(): void
    {
        $arr = ReportFilter::fromGet([], [])->toArray();
        foreach (['etat', 'site_id', 'q', 'confidential_filter', 'own_only', 'force_site_id', 'declarant_id', 'chsct_consent_only', 'linked_agent_id', 'linked_agent_visibility'] as $key) {
            $this->assertArrayHasKey($key, $arr, "toArray() must include $key");
        }
    }

    public function testToArrayValuesPropagated(): void
    {
        $f = new ReportFilter(
            type: 'rsst',
            etat: 'nouveau',
            siteId: 5,
            declarantId: 42,
            confidentialFilter: 1,
            forceSiteId: 3,
            search: 'kw',
            seeAllSites: false,
            chsctConsentOnly: true,
            linkedAgentId: 99,
            linkedAgentVisibility: 'confidential',
        );
        $arr = $f->toArray();
        $this->assertSame('nouveau', $arr['etat']);
        $this->assertSame(5, $arr['site_id']);
        $this->assertSame('kw', $arr['q']);
        $this->assertSame(1, $arr['confidential_filter']);
        $this->assertNull($arr['own_only']);
        $this->assertSame(3, $arr['force_site_id']);
        $this->assertSame(42, $arr['declarant_id']);
        $this->assertTrue($arr['chsct_consent_only']);
        $this->assertSame(99, $arr['linked_agent_id']);
        $this->assertSame('confidential', $arr['linked_agent_visibility']);
    }

    public function testToArrayOwnOnlyAlwaysNull(): void
    {
        // Kill any mutant that would try to populate own_only with something other than null.
        $f = new ReportFilter(type: 'rsst');
        $this->assertNull($f->toArray()['own_only']);
    }
}
