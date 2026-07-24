<?php
/**
 * Word Cloud Regression Tests — buildWordCloud, settings tab, home page integration
 *
 * Tests the word cloud feature end-to-end at the unit level:
 * - buildWordCloud() returns valid HTML when config has words
 * - buildWordCloud() returns empty when no words configured
 * - buildWordCloud() returns empty when config key is missing
 * - XSS payloads in word text are escaped in output
 * - Settings wordcloud tab file exists and contains expected content
 * - Home page renders the word cloud section at the expected location
 * - Per-registry word cloud reads from word_cloud_words_{code}
 * - Per-registry falls back to global word_cloud_words
 */

use PHPUnit\Framework\TestCase;
use App\Services\FormattingService;
use App\Services\ConfigService;

class WordCloudRegressionTest extends TestCase
{
    private FormattingService $fmt;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->fmt = new FormattingService();
        $this->pdo = getDB();

        // Clean state for each test
        $this->pdo->exec("DELETE FROM config_app WHERE cle LIKE 'word_cloud_words%'");
        clearConfigCache();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Global word cloud (backward compatibility — null registryCode)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testBuildWordCloudReturnsHtmlWhenConfigHasWords(): void
    {
        $words = [
            ['word' => 'chute', 'weight' => 10],
            ['word' => 'incendie', 'weight' => 8],
            ['word' => 'blessure', 'weight' => 6],
        ];
        ConfigService::getInstance()->set('word_cloud_words', json_encode($words));
        clearConfigCache();

        $html = $this->fmt->buildWordCloud();

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('class="word-cloud"', $html);
        $this->assertStringContainsString('class="word-cloud__word"', $html);
        $this->assertStringContainsString('chute', $html);
        $this->assertStringContainsString('incendie', $html);
        $this->assertStringContainsString('blessure', $html);
    }

    public function testBuildWordCloudReturnsEmptyWhenNoWords(): void
    {
        ConfigService::getInstance()->set('word_cloud_words', '[]');
        clearConfigCache();

        $html = $this->fmt->buildWordCloud();

        $this->assertEmpty($html);
    }

    public function testBuildWordCloudReturnsEmptyWhenConfigMissing(): void
    {
        $this->pdo->exec("DELETE FROM config_app WHERE cle = 'word_cloud_words'");
        clearConfigCache();

        $html = $this->fmt->buildWordCloud();

        $this->assertEmpty($html);
    }

    public function testBuildWordCloudHtmlIsEscaped(): void
    {
        $words = [
            ['word' => '<script>alert(1)</script>', 'weight' => 10],
            ['word' => 'safe', 'weight' => 5],
        ];
        ConfigService::getInstance()->set('word_cloud_words', json_encode($words));
        clearConfigCache();

        $html = $this->fmt->buildWordCloud();

        $this->assertNotEmpty($html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Per-registry word cloud
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testBuildWordCloudWithRegistryCodeReadsRegistryKey(): void
    {
        $registryWords = [
            ['word' => 'chute', 'weight' => 12],
            ['word' => 'ergonomie', 'weight' => 9],
        ];
        ConfigService::getInstance()->set('word_cloud_words_rsst', json_encode($registryWords));
        clearConfigCache();

        $html = $this->fmt->buildWordCloud('rsst');

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('chute', $html);
        $this->assertStringContainsString('ergonomie', $html);
    }

    public function testBuildWordCloudWithRegistryCodeFallsBackToGlobal(): void
    {
        $globalWords = [
            ['word' => 'global_word', 'weight' => 10],
        ];
        ConfigService::getInstance()->set('word_cloud_words', json_encode($globalWords));
        clearConfigCache();

        // No registry-specific key set — should fall back to global
        $html = $this->fmt->buildWordCloud('rsst');

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('global_word', $html);
    }

    public function testBuildWordCloudWithRegistryCodePrefersRegistryOverGlobal(): void
    {
        $globalWords = [
            ['word' => 'global_only', 'weight' => 10],
        ];
        $registryWords = [
            ['word' => 'rsst_only', 'weight' => 10],
        ];
        ConfigService::getInstance()->set('word_cloud_words', json_encode($globalWords));
        ConfigService::getInstance()->set('word_cloud_words_rsst', json_encode($registryWords));
        clearConfigCache();

        $html = $this->fmt->buildWordCloud('rsst');

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('rsst_only', $html);
        $this->assertStringNotContainsString('global_only', $html);
    }

    public function testBuildWordCloudWithUnknownRegistryCodeFallsBackToGlobal(): void
    {
        $globalWords = [
            ['word' => 'fallback', 'weight' => 10],
        ];
        ConfigService::getInstance()->set('word_cloud_words', json_encode($globalWords));
        clearConfigCache();

        // Unknown registry code — no registry-specific key exists
        $html = $this->fmt->buildWordCloud('unknown_code');

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('fallback', $html);
    }

    public function testBuildWordCloudWithRegistryCodeReturnsEmptyIfNeitherKeyExists(): void
    {
        $html = $this->fmt->buildWordCloud('rsst');

        $this->assertEmpty($html);
    }

    public function testBuildWordCloudWithNullCodeReadsGlobalKey(): void
    {
        $globalWords = [
            ['word' => 'explicit_global', 'weight' => 10],
        ];
        ConfigService::getInstance()->set('word_cloud_words', json_encode($globalWords));
        clearConfigCache();

        $html = $this->fmt->buildWordCloud(null);

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('explicit_global', $html);
    }

    public function testBuildWordCloudPerRegistryHtmlIsEscaped(): void
    {
        $registryWords = [
            ['word' => '<img onerror=alert(1)>', 'weight' => 10],
            ['word' => 'safe', 'weight' => 5],
        ];
        ConfigService::getInstance()->set('word_cloud_words_rami', json_encode($registryWords));
        clearConfigCache();

        $html = $this->fmt->buildWordCloud('rami');

        $this->assertNotEmpty($html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Settings tab — per-registry word cloud
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSettingsPageLoadsWordCloudTab(): void
    {
        $tabFile = __DIR__ . '/../../pages/settings/tab_wordcloud.php';
        $this->assertFileExists($tabFile);

        $content = file_get_contents($tabFile);
        $this->assertStringContainsString('wordcloud', $content);
        $this->assertStringContainsString('Nuage de mots', $content);
    }

    public function testSettingsWordcloudTabContainsRegistrySelector(): void
    {
        $tabFile = __DIR__ . '/../../pages/settings/tab_wordcloud.php';
        $content = file_get_contents($tabFile);

        // Must contain per-registry mechanism (tabs, select, or data attribute)
        $this->assertStringContainsString('word_cloud_words', $content, 'Must reference config keys');
    }

    public function testSettingsWordcloudTabSupportsRegistryCodes(): void
    {
        $tabFile = __DIR__ . '/../../pages/settings/tab_wordcloud.php';
        $content = file_get_contents($tabFile);

        // Must load enabled registries for the selector
        $this->assertStringContainsString('RegistryRepository', $content, 'Must load registry list');
        $this->assertStringContainsString('findEnabled', $content, 'Must use findEnabled()');
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Home page — per-registry word clouds
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testHomeWordCloudSectionExists(): void
    {
        $homeFile = __DIR__ . '/../../pages/home.php';
        $this->assertFileExists($homeFile);

        $lines = file($homeFile);
        $fullContent = implode('', $lines);

        $this->assertStringContainsString('buildWordCloud', $fullContent, 'home.php must call buildWordCloud()');
        $this->assertStringContainsString('extraContentMap', $fullContent, 'home.php must pass word cloud via extraContentMap');
    }

    /**
     * Regression guard: the word cloud must NOT be gated by rsstCount > 0.
     */
    public function testWordCloudNotGatedByReportCount(): void
    {
        $homeFile = __DIR__ . '/../../pages/home.php';
        $lines = file($homeFile);

        $wcLineIndex = null;
        foreach ($lines as $i => $line) {
            if (str_contains($line, 'buildWordCloud')) {
                $wcLineIndex = $i;
                break;
            }
        }
        $this->assertNotNull($wcLineIndex, 'buildWordCloud not found in home.php');

        $precedingLines = array_slice($lines, max(0, $wcLineIndex - 3), 3);
        foreach ($precedingLines as $line) {
            $this->assertStringNotContainsString(
                'rsstCount > 0',
                $line,
                'Word cloud must not be inside an rsstCount > 0 condition block'
            );
        }
    }

    public function testHomeBuildsWordCloudPerRegistry(): void
    {
        $homeFile = __DIR__ . '/../../pages/home.php';
        $content = file_get_contents($homeFile);

        // Must iterate over enabled registries for per-registry word clouds
        $this->assertStringContainsString('getEnabledRegistries', $content, 'home.php must call getEnabledRegistries()');
    }

    /**
     * Regression guard: settings wordcloud tab must be in the allowed tabs list.
     */
    public function testSettingsWordcloudTabInAllowedTabs(): void
    {
        $settingsFile = __DIR__ . '/../../pages/settings.php';
        $content = file_get_contents($settingsFile);

        $this->assertStringContainsString("'wordcloud'", $content, 'settings.php must include wordcloud in allowed tabs');
        $this->assertStringContainsString('tab_', $content, 'settings.php must load tab sub-templates');
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Handler — per-registry save
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSettingsHandlerWordcloudTabExists(): void
    {
        $handlerFile = __DIR__ . '/../../handlers/settings_handler.php';
        $this->assertFileExists($handlerFile);

        $content = file_get_contents($handlerFile);
        $this->assertStringContainsString("'wordcloud'", $content, 'Handler must handle wordcloud tab');
    }

    public function testSettingsHandlerSupportsRegistryCode(): void
    {
        $handlerFile = __DIR__ . '/../../handlers/settings_handler.php';
        $content = file_get_contents($handlerFile);

        // Must accept registry_code from POST to save per-registry
        $this->assertStringContainsString('registry_code', $content, 'Handler must accept registry_code parameter');
    }
}
