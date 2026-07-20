<?php
/**
 * HttpService Unit Tests — URL building, redirects
 *
 * Tests HttpService from src/Services/HttpService.php:
 * - url() builds correct internal URLs with params
 * - url() forwards XTransformPort when present
 * - removeUnwantedHeaders() does not throw in CLI
 *
 * Note: redirect() and sendFileDownload() call exit and cannot be tested
 * directly in PHPUnit. Their URL/content arguments are validated through url().
 */

use PHPUnit\Framework\TestCase;
use App\Services\HttpService;

class HttpServiceTest extends TestCase
{
    private HttpService $service;

    protected function setUp(): void
    {
        $this->service = new HttpService();
        unset($_GET['XTransformPort']);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // url()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testUrlReturnsIndexPhpWithPageParam(): void
    {
        $result = $this->service->url('home');
        $this->assertEquals('index.php?page=home', $result);
    }

    public function testUrlWithExtraParams(): void
    {
        $result = $this->service->url('report_view', ['uuid' => 'abc-123']);
        $this->assertStringContainsString('page=report_view', $result);
        $this->assertStringContainsString('uuid=abc-123', $result);
    }

    public function testUrlWithMultipleParams(): void
    {
        $result = $this->service->url('search', ['q' => 'test', 'type' => 'rsst']);
        $this->assertStringContainsString('page=search', $result);
        $this->assertStringContainsString('q=test', $result);
        $this->assertStringContainsString('type=rsst', $result);
    }

    public function testUrlWithEmptyParamsArray(): void
    {
        $result = $this->service->url('settings', []);
        $this->assertEquals('index.php?page=settings', $result);
    }

    public function testUrlForwardsXTransformPort(): void
    {
        $_GET['XTransformPort'] = '8080';
        $result = $this->service->url('home');
        $this->assertStringContainsString('XTransformPort=8080', $result);
        $this->assertStringContainsString('page=home', $result);
        unset($_GET['XTransformPort']);
    }

    public function testUrlXTransformPortComesBeforePage(): void
    {
        $_GET['XTransformPort'] = '8080';
        $result = $this->service->url('home');
        $pos = strpos($result, 'XTransformPort');
        $pagePos = strpos($result, 'page=');
        $this->assertNotFalse($pos);
        $this->assertNotFalse($pagePos);
        $this->assertLessThan($pagePos, $pos);
        unset($_GET['XTransformPort']);
    }

    public function testUrlWithParamsAndXTransformPort(): void
    {
        $_GET['XTransformPort'] = '443';
        $result = $this->service->url('report_view', ['uuid' => 'x']);
        $this->assertStringContainsString('XTransformPort=443', $result);
        $this->assertStringContainsString('page=report_view', $result);
        $this->assertStringContainsString('uuid=x', $result);
        unset($_GET['XTransformPort']);
    }

    public function testUrlSpecialCharsAreEncoded(): void
    {
        $result = $this->service->url('search', ['q' => 'hello world']);
        $this->assertStringContainsString('q=hello+world', $result);
    }

    public function testUrlPageNamesCanContainUnderscores(): void
    {
        $result = $this->service->url('report_create');
        $this->assertEquals('index.php?page=report_create', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // removeUnwantedHeaders()
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testRemoveUnwantedHeadersDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->service->removeUnwantedHeaders();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Service instantiation
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testServiceCanBeInstantiated(): void
    {
        $service = new HttpService();
        $this->assertInstanceOf(HttpService::class, $service);
    }
}
