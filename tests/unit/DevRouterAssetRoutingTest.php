<?php

declare(strict_types=1);

/**
 * Dev Router — asset endpoints wiring (public/router.php).
 *
 * The PHP built-in dev server (used by the E2E suite and `php -S`) goes
 * through `public/router.php` for EVERY request. Unlike IIS, where inbound
 * URL Rewrite rules map `js.php` / `css.php` requests, the dev router must
 * branch on the endpoint itself — otherwise the request falls through to
 * `index.php`, which answers with an HTML page. The `<script
 * src="js.php?f=js/wordcloud.js">` tag in footer.php then receives HTML and
 * `wordcloud.js` never runs (silent word-cloud regression).
 *
 * This test exercises the real routing decision in a subprocess (router.php
 * bootstraps index.php at the end of its fallthrough, so it cannot be
 * included in-process): it asserts that an `/js.php` request returns the
 * actual JS body, mirroring the existing `/css.php` behaviour.
 *
 * Pattern: same subprocess + temp-file approach as
 * RouterCsrfIntegrationTest / tests/router_runner.php.
 */

use PHPUnit\Framework\TestCase;

final class DevRouterAssetRoutingTest extends TestCase
{
    private string $routerPath;
    private string $publicPath;

    protected function setUp(): void
    {
        $this->publicPath = dirname(__DIR__, 2) . '/public';
        $this->routerPath = $this->publicPath . '/router.php';
        $this->assertFileExists($this->routerPath);
    }

    /**
     * Run `public/router.php` in a fresh PHP process with a synthetic request
     * and return the raw response body.
     *
     * @param array<string, string> $query
     */
    private function requestThroughRouter(string $uri, array $query): string
    {
        $code = '<?php '
            . '$_SERVER["REQUEST_URI"] = ' . var_export($uri, true) . ';'
            . '$_SERVER["HTTP_HOST"] = "localhost";'
            . '$_SERVER["REMOTE_ADDR"] = "127.0.0.1";'
            . '$_GET = ' . var_export($query, true) . ';'
            . 'require ' . var_export($this->routerPath, true) . ';';

        $scriptPath = tempnam(sys_get_temp_dir(), 'sst_dev_router_');
        $this->assertIsString($scriptPath);
        file_put_contents($scriptPath, $code);

        try {
            $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($scriptPath) . ' 2>&1');
        } finally {
            @unlink($scriptPath);
        }

        // shell_exec returns null when the subprocess produces no output;
        // normalise to '' so a missing route fails on the body comparison
        // with an actionable diff rather than a type error.
        return is_string($output) ? $output : '';
    }

    public function testDevRouterServesWordcloudJsThroughJsPhp(): void
    {
        $response = $this->requestThroughRouter('/js.php?f=js/wordcloud.js&v=3.66.2', [
            'f' => 'js/wordcloud.js',
            'v' => '3.66.2',
        ]);

        $expected = file_get_contents($this->publicPath . '/js/wordcloud.js');
        $this->assertIsString($expected);

        $this->assertStringContainsString(
            "document.querySelectorAll('.word-cloud[data-words]')",
            $response,
            'The dev router must serve js/wordcloud.js through js.php; '
            . 'an HTML fallthrough to index.php would break the word cloud.'
        );
        $this->assertStringNotContainsString(
            '<!DOCTYPE',
            $response,
            'A /js.php request must not fall through to an index.php HTML page.'
        );
        $this->assertSame(
            $expected,
            $response,
            'The dev router must return the exact wordcloud.js body for /js.php.'
        );
    }

    public function testDevRouterServesCssThroughCssPhp(): void
    {
        $response = $this->requestThroughRouter('/css.php?f=css/style.css&v=3.66.2', [
            'f' => 'css/style.css',
            'v' => '3.66.2',
        ]);

        $expected = file_get_contents($this->publicPath . '/css/style.css');
        $this->assertIsString($expected);

        $this->assertSame(
            $expected,
            $response,
            'The existing /css.php dev route must keep returning the exact CSS body.'
        );
    }
}
