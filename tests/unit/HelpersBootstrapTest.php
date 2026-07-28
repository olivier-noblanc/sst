<?php
/**
 * Helpers Bootstrap Test — Application SST DREETS BFC
 *
 * Verifies that EVERY file in src/helpers/ is included by src/helpers.php.
 * This prevents the production-undefined-function bug (e.g. buildRegistryCards
 * was missing because registry_card_renderer.php was never added to the loader).
 *
 * How it works:
 * - Scans src/helpers/*.php for function declarations
 * - Checks that src/helpers.php has a require_once line for each file
 * - Checks that each declared function is callable after loading helpers.php
 *
 * Whitelist: files that are intentionally NOT in helpers.php
 * (dead code, duplicates, or loaded elsewhere).
 */

use PHPUnit\Framework\TestCase;

class HelpersBootstrapTest extends TestCase
{
    private string $helpersDir;
    private string $helpersLoader;

    /** Files intentionally NOT required by helpers.php (explain why). */
    private const WHITELIST = [
        // Add entries here for files intentionally excluded from helpers.php.
        // Each entry MUST have a comment explaining why.
        'uuid.php', // Audit #85 — pure, dependency-free (no DB), loaded from
                    // src/autoload.php instead (required by public/index.php,
                    // just as reliably always-loaded on every real request).
    ];

    protected function setUp(): void
    {
        $this->helpersDir = __DIR__ . '/../../src/helpers/';
        $this->helpersLoader = __DIR__ . '/../../src/helpers.php';
    }

    /**
     * Every .php file in src/helpers/ must be required by src/helpers.php.
     * A missing require means the function will be undefined in production.
     */
    public function testEveryHelperFileIsIncludedInLoader(): void
    {
        $loaderContent = file_get_contents($this->helpersLoader);
        $missing = [];

        $files = glob($this->helpersDir . '*.php');
        $this->assertNotEmpty($files, 'No helper files found in src/helpers/');

        foreach ($files as $file) {
            $basename = basename($file);

            if (in_array($basename, self::WHITELIST, true)) {
                continue;
            }

            // Check that helpers.php has a require_once for this file
            $pattern = '/require_once\s+__DIR__\s*\.\s*[\'"]\/helpers\/' . preg_quote($basename, '/') . '[\'"]/';
            if (!preg_match($pattern, $loaderContent)) {
                $missing[] = $basename;
            }
        }

        $this->assertEmpty(
            $missing,
            "The following helper files are NOT included in src/helpers.php:\n"
            . implode("\n", array_map(fn($f) => "  - src/helpers/$f", $missing))
            . "\n\nAdd a require_once line to src/helpers.php to fix this."
        );
    }

    /**
     * All functions declared in src/helpers/*.php must be callable
     * after loading ONLY src/helpers.php (production bootstrap chain).
     */
    public function testAllHelperFunctionsAreCallable(): void
    {
        $files = glob($this->helpersDir . '*.php');
        $uncallable = [];

        foreach ($files as $file) {
            $basename = basename($file);
            if (in_array($basename, self::WHITELIST, true)) {
                continue;
            }

            // Extract function names from the file
            $content = file_get_contents($file);
            preg_match_all('/^function\s+(\w+)\s*\(/m', $content, $matches);

            foreach ($matches[1] as $funcName) {
                if (!function_exists($funcName)) {
                    $uncallable[] = "$funcName (from $basename)";
                }
            }
        }

        $this->assertEmpty(
            $uncallable,
            "The following functions are defined but NOT callable (file not loaded in production):\n"
            . implode("\n", array_map(fn($f) => "  - $f", $uncallable))
        );
    }
}
