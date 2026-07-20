<?php
/**
 * AssetService Unit Tests — CSS serving, asset URLs
 *
 * Tests AssetService from src/Services/AssetService.php:
 * - cssLink() generates valid <link> tags with version
 * - assetUrl() builds versioned asset URLs
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
