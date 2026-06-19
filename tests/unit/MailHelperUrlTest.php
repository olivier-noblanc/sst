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
}
