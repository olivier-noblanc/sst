<?php
/**
 * Mail Helper Unit Tests — Base URL
 *
 * Tests mail functions from src/mail.php:
 * - getBaseUrl()
 * - absoluteUrl()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class MailHelperUrlTest extends TestCase
{
    protected function setUp(): void
    {
        // PHPUnit's own CLI invocation sets $_SERVER['SCRIPT_NAME'] to
        // something like 'vendor/bin/phpunit' — nothing to do with a real
        // web deployment path. Reset to a root-deployment default before
        // each test so getBaseUrl()'s subfolder detection doesn't leak
        // PHPUnit's own script path into the result; tests for the
        // subfolder case override this explicitly.
        $_SERVER['SCRIPT_NAME'] = '/index.php';
    }

    // ─── getBaseUrl ─────────────────────────────────────────────────────────

    public function testGetBaseUrlWithHttp(): void
    {
        $_SERVER['HTTPS'] = '';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertEquals('http://example.com', getBaseUrl());
    }

    public function testGetBaseUrlWithHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertEquals('https://example.com', getBaseUrl());
    }

    public function testGetBaseUrlWithHttpsOff(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertEquals('http://example.com', getBaseUrl());
    }

    public function testGetBaseUrlWithPort(): void
    {
        $_SERVER['HTTPS'] = '';
        $_SERVER['HTTP_HOST'] = 'localhost:8080';
        $this->assertEquals('http://localhost:8080', getBaseUrl());
    }

    public function testGetBaseUrlWithMissingHost(): void
    {
        $_SERVER['HTTPS'] = '';
        unset($_SERVER['HTTP_HOST']);
        $this->assertEquals('http://localhost', getBaseUrl());
    }

    // ─── subfolder deployment (SCRIPT_NAME) ─────────────────────────────────

    public function testGetBaseUrlIncludesSubfolderWhenDeployedInOne(): void
    {
        // Real-world bug this guards against: IIS commonly mounts the app
        // as an "application" under a site rather than at the domain root
        // (e.g. https://server/sst/index.php). url() always returns a path
        // relative to index.php, which works for in-app navigation (the
        // browser resolves it against the page it's already on) but not
        // for an email link, which needs the full path. Without this, a
        // recipient got a link to "https://server/index.php" — silently
        // missing the "/sst" segment, landing on a 404 or the wrong app.
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'server.dreets-bfc.gouv.fr';
        $_SERVER['SCRIPT_NAME'] = '/sst/index.php';

        $this->assertEquals('https://server.dreets-bfc.gouv.fr/sst', getBaseUrl());
    }

    public function testGetBaseUrlNoSubfolderAtDomainRoot(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $this->assertEquals('https://example.com', getBaseUrl());
    }

    public function testAbsoluteUrlIncludesSubfolder(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'server.dreets-bfc.gouv.fr';
        $_SERVER['SCRIPT_NAME'] = '/sst/index.php';

        $result = absoluteUrl('report_view', ['uuid' => 'xyz']);

        $this->assertEquals('https://server.dreets-bfc.gouv.fr/sst/index.php?page=report_view&uuid=xyz', $result);
    }

    // ─── app_base_url config override ──────────────────────────────────────

    public function testGetBaseUrlUsesConfigWhenSet(): void
    {
        // Real-world bug this guards against: $_SERVER['HTTP_HOST'] isn't
        // reliable in every context email gets sent from (missing outside
        // a real HTTP request, or reflecting an internal hostname behind a
        // reverse proxy) — without an explicit override, every recipient
        // gets a link that only resolves on the server itself.
        \App\Services\getConfigService()->set('app_base_url', 'https://sst.dreets-bfc.gouv.fr');
        \App\Services\getConfigService()->clearCache();

        $_SERVER['HTTPS'] = '';
        unset($_SERVER['HTTP_HOST']);

        $this->assertEquals('https://sst.dreets-bfc.gouv.fr', getBaseUrl());

        \App\Services\getConfigService()->set('app_base_url', '');
        \App\Services\getConfigService()->clearCache();
    }

    public function testGetBaseUrlStripsTrailingSlashFromConfig(): void
    {
        \App\Services\getConfigService()->set('app_base_url', 'https://sst.dreets-bfc.gouv.fr/');
        \App\Services\getConfigService()->clearCache();

        $this->assertEquals('https://sst.dreets-bfc.gouv.fr', getBaseUrl());

        \App\Services\getConfigService()->set('app_base_url', '');
        \App\Services\getConfigService()->clearCache();
    }

    public function testGetBaseUrlFallsBackToRequestWhenConfigEmpty(): void
    {
        \App\Services\getConfigService()->set('app_base_url', '');
        \App\Services\getConfigService()->clearCache();

        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $this->assertEquals('https://example.com', getBaseUrl());
    }

    // ─── absoluteUrl() ──────────────────────────────────────────────────────

    public function testAbsoluteUrlCombinesBaseAndPath(): void
    {
        // Real bug this guards against: sendAgentInviteEmails() built its
        // confirmation link with a bare url() call, forgetting the host
        // entirely — the recipient received a link like
        // "index.php?page=agent_confirm&token=..." with no scheme or
        // domain, meaningless in an email client (there's no "current
        // page" to resolve a relative URL against). absoluteUrl() exists
        // specifically so a caller can't make that mistake again.
        \App\Services\getConfigService()->set('app_base_url', 'https://sst.dreets-bfc.gouv.fr');
        \App\Services\getConfigService()->clearCache();

        $result = absoluteUrl('agent_confirm', ['token' => 'abc123']);

        $this->assertEquals('https://sst.dreets-bfc.gouv.fr/index.php?page=agent_confirm&token=abc123', $result);

        \App\Services\getConfigService()->set('app_base_url', '');
        \App\Services\getConfigService()->clearCache();
    }

    public function testAbsoluteUrlNeverReturnsARelativePath(): void
    {
        \App\Services\getConfigService()->set('app_base_url', '');
        \App\Services\getConfigService()->clearCache();
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $result = absoluteUrl('report_view', ['uuid' => 'xyz']);

        $this->assertStringStartsWith('https://example.com/', $result);
    }
}
