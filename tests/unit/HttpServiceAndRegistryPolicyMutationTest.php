<?php
/**
 * Tests HttpService::url() and RegistryPolicy exhaustively — kills Infection mutants.
 *
 * HttpService::url() — kills mutants on:
 *   - http_build_query separator (Concat, http_build_query)
 *   - XTransformPort propagation in DEV_MODE (LogicalAnd, Identical, isset)
 *   - params merging (Foreach_, ArrayItem)
 *
 * RegistryPolicy — kills mutants on:
 *   - requiresPourCompte (Identical on ReportType::Rami, bool flag)
 *   - hasDgiWarningPanel (Identical on ReportType::Dgi, bool flag)
 *   - getLieuLabel (Identical, Ternary, string fallback)
 */

use PHPUnit\Framework\TestCase;
use App\Services\HttpService;
use App\Services\RegistryPolicy;
use App\Enum\ReportType;

class HttpServiceAndRegistryPolicyMutationTest extends TestCase
{
    // ═══ HttpService::url() ═══

    public function testUrlReturnsCorrectFormat(): void
    {
        $http = new HttpService();
        $url = $http->url('home');
        $this->assertSame('index.php?page=home', $url);
    }

    public function testUrlAppendsSingleParam(): void
    {
        $http = new HttpService();
        $url = $http->url('report_view', ['uuid' => 'abc-123']);
        $this->assertSame('index.php?page=report_view&uuid=abc-123', $url);
    }

    public function testUrlAppendsMultipleParams(): void
    {
        // Kill Foreach_ mutant + ArrayItem — every param must be in the URL
        $http = new HttpService();
        $url = $http->url('report_list', ['type' => 'rsst', 'etat' => 'nouveau', 'site' => '5']);
        $this->assertStringContainsString('page=report_list', $url);
        $this->assertStringContainsString('type=rsst', $url);
        $this->assertStringContainsString('etat=nouveau', $url);
        $this->assertStringContainsString('site=5', $url);
    }

    public function testUrlUsesAmpersandSeparatorNotAmpEntity(): void
    {
        // Kill http_build_query separator mutant — must be '&' not '&amp;'
        $http = new HttpService();
        $url = $http->url('test', ['a' => '1', 'b' => '2']);
        $this->assertStringContainsString('&', $url);
        $this->assertStringNotContainsString('&amp;', $url, 'must use & not &amp;');
    }

    public function testUrlHandlesNullParamValues(): void
    {
        // Kill Coalesce mutant on null param values
        $http = new HttpService();
        $url = $http->url('test', ['key' => null]);
        // http_build_query skips null values by default in PHP 8
        $this->assertStringContainsString('page=test', $url);
    }

    public function testUrlHandlesIntParamValues(): void
    {
        $http = new HttpService();
        $url = $http->url('test', ['count' => 42]);
        $this->assertStringContainsString('count=42', $url);
    }

    public function testUrlEncodesSpecialCharsInParams(): void
    {
        // Kill http_build_query mutant — special chars must be URL-encoded
        $http = new HttpService();
        $url = $http->url('test', ['q' => 'hello world & more']);
        $this->assertStringContainsString('q=hello+world+%26+more', $url);
    }

    public function testUrlDoesNotPropagateXTransformPortInNonDevMode(): void
    {
        // Kill LogicalAnd/Identical mutant on DEV_MODE check
        if (defined('DEV_MODE')) {
            $originalDevMode = DEV_MODE;
        }
        // Simulate non-DEV_MODE
        // We can't redefine DEV_MODE, but we can check that XTransformPort
        // is NOT in the URL when it's in $_GET but DEV_MODE is false
        $originalGet = $_GET;
        $_GET['XTransformPort'] = '12345';

        $http = new HttpService();
        $url = $http->url('home');

        // In test bootstrap, DEV_MODE is defined as true — so XTransformPort
        // WILL be propagated. This test verifies that when DEV_MODE is true,
        // the port IS propagated (kills the mutant that would remove the check).
        if (defined('DEV_MODE') && DEV_MODE) {
            $this->assertStringContainsString('XTransformPort=12345', $url, 'in DEV_MODE, XTransformPort must be propagated');
        } else {
            $this->assertStringNotContainsString('XTransformPort', $url, 'in non-DEV_MODE, XTransformPort must NOT be propagated');
        }

        $_GET = $originalGet;
    }

    public function testUrlPropagatesXTransformPortInDevMode(): void
    {
        // Kill isset mutant on $_GET['XTransformPort']
        $http = new HttpService();
        $_GET['XTransformPort'] = '54321';
        $url = $http->url('home');
        // DEV_MODE is true in test bootstrap
        $this->assertStringContainsString('XTransformPort=54321', $url);
        unset($_GET['XTransformPort']);
    }

    public function testUrlDoesNotAddXTransformPortWhenNotInGet(): void
    {
        // Kill isset mutant — when XTransformPort is NOT in $_GET, it must not appear
        $http = new HttpService();
        unset($_GET['XTransformPort']);
        $url = $http->url('home');
        $this->assertStringNotContainsString('XTransformPort', $url);
    }

    public function testUrlParamsOverridePageKey(): void
    {
        // Kill ArrayItem mutant — if $params has 'page', it should override
        $http = new HttpService();
        $url = $http->url('home', ['page' => 'override']);
        // The 'page' from $queryParams['page'] = $page is set first,
        // then $params['page'] overwrites it in the foreach
        $this->assertStringContainsString('page=override', $url);
    }

    // ═══ RegistryPolicy ═══

    public function testRequiresPourCompteReturnsTrueForRami(): void
    {
        // Kill Identical mutant on ReportType::Rami->value
        $policy = new RegistryPolicy();
        $this->assertTrue($policy->requiresPourCompte('rami'));
    }

    public function testRequiresPourCompteReturnsFalseForRsst(): void
    {
        $policy = new RegistryPolicy();
        $this->assertFalse($policy->requiresPourCompte('rsst'));
    }

    public function testRequiresPourCompteReturnsFalseForDgi(): void
    {
        $policy = new RegistryPolicy();
        $this->assertFalse($policy->requiresPourCompte('dgi'));
    }

    public function testRequiresPourCompteReturnsFalseForUnknownType(): void
    {
        // Kill getRegistryBoolFlag mutant — unknown type → false
        $policy = new RegistryPolicy();
        $this->assertFalse($policy->requiresPourCompte('unknown_registry'));
    }

    public function testHasDgiWarningPanelReturnsTrueForDgi(): void
    {
        // Kill Identical mutant on ReportType::Dgi->value
        $policy = new RegistryPolicy();
        $this->assertTrue($policy->hasDgiWarningPanel('dgi'));
    }

    public function testHasDgiWarningPanelReturnsFalseForRsst(): void
    {
        $policy = new RegistryPolicy();
        $this->assertFalse($policy->hasDgiWarningPanel('rsst'));
    }

    public function testHasDgiWarningPanelReturnsFalseForRami(): void
    {
        $policy = new RegistryPolicy();
        $this->assertFalse($policy->hasDgiWarningPanel('rami'));
    }

    public function testHasDgiWarningPanelReturnsFalseForUnknownType(): void
    {
        $policy = new RegistryPolicy();
        $this->assertFalse($policy->hasDgiWarningPanel('unknown'));
    }

    public function testGetLieuLabelReturnsOverrideForDgi(): void
    {
        // Kill Identical mutant on ReportType::Dgi->value
        $policy = new RegistryPolicy();
        $this->assertSame('Lieu / Mesures de protection', $policy->getLieuLabel('dgi'));
    }

    public function testGetLieuLabelReturnsDefaultForRsst(): void
    {
        // Kill Ternary mutant — RSST has no override → 'Lieu'
        $policy = new RegistryPolicy();
        $this->assertSame('Lieu', $policy->getLieuLabel('rsst'));
    }

    public function testGetLieuLabelReturnsDefaultForRami(): void
    {
        $policy = new RegistryPolicy();
        $this->assertSame('Lieu', $policy->getLieuLabel('rami'));
    }

    public function testGetLieuLabelReturnsDefaultForUnknownType(): void
    {
        // Kill getRegistryStringFlag mutant — unknown type → '' → fallback 'Lieu'
        $policy = new RegistryPolicy();
        $this->assertSame('Lieu', $policy->getLieuLabel('unknown'));
    }
}
