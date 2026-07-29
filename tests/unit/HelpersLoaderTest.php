<?php
/**
 * Helpers Loader Architectural Test — Application SST DREETS BFC
 *
 * Ensures every file in src/helpers/ is loaded by src/helpers.php.
 * If someone adds a new helper file but forgets to add the require_once,
 * this test catches it.
 */

use PHPUnit\Framework\TestCase;

class HelpersLoaderTest extends TestCase
{
    /**
     * Every .php file in src/helpers/ must be required_once by src/helpers.php.
     * This prevents "undefined function" fatal errors at runtime.
     */
    public function testHelpersLoaderIncludesAllHelperFiles(): void
    {
        $helpersDir = __DIR__ . '/../../src/helpers';
        $loaderFile = __DIR__ . '/../../src/helpers.php';

        $this->assertFileExists($loaderFile, 'src/helpers.php must exist');

        $loaderContent = file_get_contents($loaderFile);

        $helperFiles = glob($helpersDir . '/*.php');
        $this->assertNotEmpty($helperFiles, 'src/helpers/ must contain at least one .php file');

        $missing = [];
        foreach ($helperFiles as $file) {
            $basename = basename($file);
            // Audit #85 — uuid.php est volontairement chargé depuis
            // src/autoload.php (requis par public/index.php, tout aussi
            // fiable), pas helpers.php : pur, sans dépendance DB. Même
            // exception que HelpersBootstrapTest::WHITELIST.
            if ($basename === 'uuid.php') {
                continue;
            }
            $expected = "require_once __DIR__ . '/helpers/$basename';";
            if (strpos($loaderContent, $expected) === false) {
                $missing[] = $basename;
            }
        }

        $this->assertEmpty(
            $missing,
            'The following helper files are NOT loaded by src/helpers.php: '
            . implode(', ', $missing)
            . "\nAdd a require_once line for each in src/helpers.php"
        );
    }

    /**
     * src/helpers.php must not require files that don't exist.
     * Prevents stale references after file renames/deletes.
     */
    public function testHelpersLoaderDoesNotReferenceMissingFiles(): void
    {
        $loaderFile = __DIR__ . '/../../src/helpers.php';
        $loaderDir = dirname($loaderFile);
        $loaderContent = file_get_contents($loaderFile);

        preg_match_all("/require_once\s+__DIR__\s*\.\s*'([^']+)'/", $loaderContent, $matches);

        $missing = [];
        foreach ($matches[1] as $relativePath) {
            $fullPath = $loaderDir . $relativePath;
            if (!file_exists($fullPath)) {
                $missing[] = $relativePath;
            }
        }

        $this->assertEmpty(
            $missing,
            'src/helpers.php references files that do not exist: ' . implode(', ', $missing)
        );
    }
}
