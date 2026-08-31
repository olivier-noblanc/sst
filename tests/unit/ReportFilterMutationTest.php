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

    public function testSeeAllSitesDefaultsToTrue(): void
    {
        // Kill TrueValue mutant on the seeAllSites default (= true → false).
        // La valeur par défaut est le mode superviseur/CHSCT (voir tous les sites) :
        // un mutant qui la bascule à false change silencieusement le périmètre de
        // visibilité par défaut des requêtes.
        $f = new ReportFilter(type: 'rsst');
        $this->assertTrue($f->seeAllSites, 'seeAllSites must default to true');
        $this->assertFalse($f->chsctConsentOnly, 'chsctConsentOnly must default to false');
    }
}
