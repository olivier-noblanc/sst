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
        $this->pdo->exec("DELETE FROM config_app WHERE cle = 'word_cloud_words'");
        clearConfigCache();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // testBuildWordCloudReturnsHtmlWhenConfigHasWords
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

        $html = $this->fmt->buildWordCloud($this->pdo, 'rsst');

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('class="word-cloud"', $html);
        $this->assertStringContainsString('class="word-cloud__word"', $html);
        $this->assertStringContainsString('chute', $html);
        $this->assertStringContainsString('incendie', $html);
        $this->assertStringContainsString('blessure', $html);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // testBuildWordCloudReturnsEmptyWhenNoWords
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testBuildWordCloudReturnsEmptyWhenNoWords(): void
    {
        ConfigService::getInstance()->set('word_cloud_words', '[]');
        clearConfigCache();

        $html = $this->fmt->buildWordCloud($this->pdo, 'rsst');

        $this->assertEmpty($html);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // testBuildWordCloudReturnsEmptyWhenConfigMissing
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testBuildWordCloudReturnsEmptyWhenConfigMissing(): void
    {
        // Ensure the key does not exist in DB
        $this->pdo->exec("DELETE FROM config_app WHERE cle = 'word_cloud_words'");
        clearConfigCache();

        $html = $this->fmt->buildWordCloud($this->pdo, 'rsst');

        $this->assertEmpty($html);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // testBuildWordCloudHtmlIsEscaped
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testBuildWordCloudHtmlIsEscaped(): void
    {
        $words = [
            ['word' => '<script>alert(1)</script>', 'weight' => 10],
            ['word' => 'safe', 'weight' => 5],
        ];
        ConfigService::getInstance()->set('word_cloud_words', json_encode($words));
        clearConfigCache();

        $html = $this->fmt->buildWordCloud($this->pdo, 'rsst');

        $this->assertNotEmpty($html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // testSettingsPageLoadsWordCloudTab
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testSettingsPageLoadsWordCloudTab(): void
    {
        $tabFile = __DIR__ . '/../../pages/settings/tab_wordcloud.php';
        $this->assertFileExists($tabFile);

        $content = file_get_contents($tabFile);
        $this->assertStringContainsString('wordcloud', $content);
        $this->assertStringContainsString('word_cloud_words', $content);
        $this->assertStringContainsString('Nuage de mots', $content);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // testHomeWordCloudSectionExists
    // ═══════════════════════════════════════════════════════════════════════════════

    public function testHomeWordCloudSectionExists(): void
    {
        $homeFile = __DIR__ . '/../../pages/home.php';
        $this->assertFileExists($homeFile);

        $lines = file($homeFile);
        $fullContent = implode('', $lines);

        // Word cloud rendering code must exist
        $this->assertStringContainsString('buildWordCloud', $fullContent, 'home.php must call buildWordCloud()');
        $this->assertStringContainsString('wordCloud', $fullContent, 'home.php must use $wordCloud variable');
        $this->assertStringContainsString('Nuage de mots', $fullContent, 'home.php must display word cloud title');
    }

    /**
     * Regression guard: the word cloud must NOT be gated by rsstCount > 0.
     * Previous versions had `if ($rsstCount > 0):` wrapping the word cloud,
     * which hid it when there were no RSST reports. This test ensures
     * the word cloud is visible for ALL profiles regardless of report count.
     */
    public function testWordCloudNotGatedByReportCount(): void
    {
        $homeFile = __DIR__ . '/../../pages/home.php';
        $lines = file($homeFile);

        // Find the line with buildWordCloud
        $wcLineIndex = null;
        foreach ($lines as $i => $line) {
            if (str_contains($line, 'buildWordCloud')) {
                $wcLineIndex = $i;
                break;
            }
        }
        $this->assertNotNull($wcLineIndex, 'buildWordCloud not found in home.php');

        // Check the 3 lines before buildWordCloud — none should be `if ($rsstCount > 0)`
        $precedingLines = array_slice($lines, max(0, $wcLineIndex - 3), 3);
        foreach ($precedingLines as $line) {
            $this->assertStringNotContainsString(
                'rsstCount > 0',
                $line,
                'Word cloud must not be inside an rsstCount > 0 condition block'
            );
        }
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
}
