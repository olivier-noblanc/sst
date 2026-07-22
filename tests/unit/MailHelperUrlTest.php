<?php
/**
 * Mail Helper Unit Tests — Base URL
 *
 * Tests mail functions from src/mail.php:
 * - getBaseUrl()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class MailHelperUrlTest extends TestCase
{
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

    // ─── app_base_url config override ──────────────────────────────────────

    public function testGetBaseUrlUsesConfigWhenSet(): void
    {
        // Real-world bug this guards against: $_SERVER['HTTP_HOST'] isn't
        // reliable in every context email gets sent from (missing outside
        // a real HTTP request, or reflecting an internal hostname behind a
        // reverse proxy) — without an explicit override, every recipient
        // gets a link that only resolves on the server itself.
        \App\Services\ConfigService::getInstance()->set('app_base_url', 'https://sst.dreets-bfc.gouv.fr');
        \App\Services\ConfigService::getInstance()->clearCache();

        $_SERVER['HTTPS'] = '';
        unset($_SERVER['HTTP_HOST']);

        $this->assertEquals('https://sst.dreets-bfc.gouv.fr', getBaseUrl());

        \App\Services\ConfigService::getInstance()->set('app_base_url', '');
        \App\Services\ConfigService::getInstance()->clearCache();
    }

    public function testGetBaseUrlStripsTrailingSlashFromConfig(): void
    {
        \App\Services\ConfigService::getInstance()->set('app_base_url', 'https://sst.dreets-bfc.gouv.fr/');
        \App\Services\ConfigService::getInstance()->clearCache();

        $this->assertEquals('https://sst.dreets-bfc.gouv.fr', getBaseUrl());

        \App\Services\ConfigService::getInstance()->set('app_base_url', '');
        \App\Services\ConfigService::getInstance()->clearCache();
    }

    public function testGetBaseUrlFallsBackToRequestWhenConfigEmpty(): void
    {
        \App\Services\ConfigService::getInstance()->set('app_base_url', '');
        \App\Services\ConfigService::getInstance()->clearCache();

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
        \App\Services\ConfigService::getInstance()->set('app_base_url', 'https://sst.dreets-bfc.gouv.fr');
        \App\Services\ConfigService::getInstance()->clearCache();

        $result = absoluteUrl('agent_confirm', ['token' => 'abc123']);

        $this->assertEquals('https://sst.dreets-bfc.gouv.fr/index.php?page=agent_confirm&token=abc123', $result);

        \App\Services\ConfigService::getInstance()->set('app_base_url', '');
        \App\Services\ConfigService::getInstance()->clearCache();
    }

    public function testAbsoluteUrlNeverReturnsARelativePath(): void
    {
        \App\Services\ConfigService::getInstance()->set('app_base_url', '');
        \App\Services\ConfigService::getInstance()->clearCache();
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $result = absoluteUrl('report_view', ['uuid' => 'xyz']);

        $this->assertStringStartsWith('https://example.com/', $result);
    }
}
