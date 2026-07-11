<?php
/**
 * AssetService Unit Tests — CSS serving, asset URLs, icons, CSS classes
 *
 * Tests AssetService from src/Services/AssetService.php:
 * - cssLink() generates valid <link> tags with version
 * - assetUrl() builds versioned asset URLs
 * - getIcon() returns correct icon HTML per entity type
 * - getCssClass() returns correct badge CSS classes
 * - inlineDataUri() returns empty string for missing files
 */

use PHPUnit\Framework\TestCase;
use App\Services\AssetService;

class AssetServiceTest extends TestCase
{
    private AssetService $service;

    protected function setUp(): void
    {
        $this->service = new AssetService();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // cssLink()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testCssLinkReturnsLinkTag(): void
    {
        $result = $this->service->cssLink('css/style.css');
        $this->assertStringStartsWith('<link', $result);
        $this->assertStringEndsWith('">', $result);
        $this->assertStringContainsString('rel="stylesheet"', $result);
    }

    public function testCssLinkIncludesCssPhpEndpoint(): void
    {
        $result = $this->service->cssLink('css/style.css');
        $this->assertStringContainsString('css.php?f=', $result);
    }

    public function testCssLinkEncodesPath(): void
    {
        $result = $this->service->cssLink('css/my style.css');
        $this->assertStringContainsString('my+style.css', $result);
    }

    public function testCssLinkIncludesVersionParam(): void
    {
        $result = $this->service->cssLink('css/style.css');
        $this->assertStringContainsString('&amp;v=', $result);
    }

    public function testCssLinkVersionIsSemver(): void
    {
        $result = $this->service->cssLink('css/style.css');
        preg_match('/v=(\d+\.\d+\.\d+)/', $result, $matches);
        $this->assertNotEmpty($matches, 'Version parameter not found in cssLink output');
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // assetUrl()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testAssetUrlReturnsAssetPhpEndpoint(): void
    {
        $result = $this->service->assetUrl('img/logo.png');
        $this->assertStringStartsWith('asset.php?f=', $result);
    }

    public function testAssetUrlEncodesPath(): void
    {
        $result = $this->service->assetUrl('img/my logo.png');
        $this->assertStringContainsString('my+logo.png', $result);
    }

    public function testAssetUrlIncludesVersionParam(): void
    {
        $result = $this->service->assetUrl('img/logo.png');
        $this->assertStringContainsString('&v=', $result);
    }

    public function testAssetUrlVersionIsSemver(): void
    {
        $result = $this->service->assetUrl('img/logo.png');
        preg_match('/v=(\d+\.\d+\.\d+)/', $result, $matches);
        $this->assertNotEmpty($matches, 'Version parameter not found in assetUrl output');
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // getIcon()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testGetIconReportReturnsSpan(): void
    {
        $result = $this->service->getIcon('report');
        $this->assertStringContainsString('<span', $result);
        $this->assertStringContainsString('icon--report', $result);
    }

    public function testGetIconUserReturnsSpan(): void
    {
        $result = $this->service->getIcon('user');
        $this->assertStringContainsString('icon--user', $result);
    }

    public function testGetIconSiteReturnsSpan(): void
    {
        $result = $this->service->getIcon('site');
        $this->assertStringContainsString('icon--site', $result);
    }

    public function testGetIconConfigReturnsSpan(): void
    {
        $result = $this->service->getIcon('config');
        $this->assertStringContainsString('icon--config', $result);
    }

    public function testGetIconLogoutReturnsSpan(): void
    {
        $result = $this->service->getIcon('logout');
        $this->assertStringContainsString('icon--logout', $result);
    }

    public function testGetIconSearchReturnsSpan(): void
    {
        $result = $this->service->getIcon('search');
        $this->assertStringContainsString('icon--search', $result);
    }

    public function testGetIconUnknownTypeReturnsEmpty(): void
    {
        $result = $this->service->getIcon('unknown');
        $this->assertEquals('', $result);
    }

    public function testGetIconAllHaveAriaHidden(): void
    {
        $types = ['report', 'user', 'site', 'config', 'logout', 'search'];
        foreach ($types as $type) {
            $result = $this->service->getIcon($type);
            $this->assertStringContainsString('aria-hidden="true"', $result, "Icon for '$type' missing aria-hidden");
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // getCssClass()
    // ═══════════════════════════════════════════════════════════════════════════════

    // -- etat --

    public function testGetCssClassEtatNouveau(): void
    {
        $this->assertEquals('badge--nouveau', $this->service->getCssClass('etat', 'nouveau'));
    }

    public function testGetCssClassEtatEnCours(): void
    {
        $this->assertEquals('badge--en-cours', $this->service->getCssClass('etat', 'en_cours'));
    }

    public function testGetCssClassEtatTraite(): void
    {
        $this->assertEquals('badge--traite', $this->service->getCssClass('etat', 'traite'));
    }

    public function testGetCssClassEtatAbandonne(): void
    {
        $this->assertEquals('badge--abandonne', $this->service->getCssClass('etat', 'abandonne'));
    }

    public function testGetCssClassEtatReouvert(): void
    {
        $this->assertEquals('badge--reouvert', $this->service->getCssClass('etat', 'reouvert'));
    }

    public function testGetCssClassEtatUnknownReturnsEmpty(): void
    {
        $this->assertEquals('', $this->service->getCssClass('etat', 'unknown'));
    }

    // -- registry --

    public function testGetCssClassRegistryRsst(): void
    {
        $this->assertEquals('badge--rsst', $this->service->getCssClass('registry', 'rsst'));
    }

    public function testGetCssClassRegistryRami(): void
    {
        $this->assertEquals('badge--rami', $this->service->getCssClass('registry', 'rami'));
    }

    public function testGetCssClassRegistryDgi(): void
    {
        $this->assertEquals('badge--dgi', $this->service->getCssClass('registry', 'dgi'));
    }

    public function testGetCssClassRegistryUnknownReturnsEmpty(): void
    {
        $this->assertEquals('', $this->service->getCssClass('registry', 'unknown'));
    }

    // -- role --

    public function testGetCssClassRoleAgent(): void
    {
        $this->assertEquals('badge--agent', $this->service->getCssClass('role', 'agent'));
    }

    public function testGetCssClassRoleSuperviseur(): void
    {
        $this->assertEquals('badge--superviseur', $this->service->getCssClass('role', 'superviseur'));
    }

    public function testGetCssClassRoleChsct(): void
    {
        $this->assertEquals('badge--chsct', $this->service->getCssClass('role', 'chsct'));
    }

    public function testGetCssClassRoleUnknownReturnsEmpty(): void
    {
        $this->assertEquals('', $this->service->getCssClass('role', 'unknown'));
    }

    // -- unknown context --

    public function testGetCssClassUnknownContextReturnsEmpty(): void
    {
        $this->assertEquals('', $this->service->getCssClass('unknown', 'value'));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // inlineDataUri()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testInlineDataUriReturnsEmptyForMissingFile(): void
    {
        $result = $this->service->inlineDataUri('nonexistent/file.png');
        $this->assertEquals('', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Service instantiation
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testServiceCanBeInstantiated(): void
    {
        $service = new AssetService();
        $this->assertInstanceOf(AssetService::class, $service);
    }
}
