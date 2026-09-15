<?php
/**
 * CLI Entry Points Include Smoke Test — Application SST DREETS BFC
 *
 * Every require / require_once / include / include_once directive written with
 * __DIR__ inside the CLI entry points (promote.php, seed.php) and the seed
 * fragments they include must resolve to a file that actually exists on disk.
 *
 * This is a cheap architectural smoke test: it catches stale include
 * references left behind after a file rename or deletion — exactly the case of
 * the procedural src/queries/* files removed with the DDD migration while
 * their require_once stayed in promote.php and seed.php.
 */

use PHPUnit\Framework\TestCase;

class CliEntryPointsIncludeTest extends TestCase
{
    /** CLI entry points scanned by this test, relative to the repo root. */
    private const ENTRY_POINTS = ['promote.php', 'seed.php'];

    /**
     * Absolute path of every file to scan: the CLI entry points plus all
     * seed/_*.php fragments they pull in.
     *
     * @return array<string, string> relative path => absolute path
     */
    private function scannedFiles(string $root): array
    {
        $files = [];
        foreach (self::ENTRY_POINTS as $entryPoint) {
            $files[$entryPoint] = $root . DIRECTORY_SEPARATOR . $entryPoint;
        }
        foreach (glob($root . DIRECTORY_SEPARATOR . 'seed' . DIRECTORY_SEPARATOR . '_*.php') ?: [] as $seedFile) {
            $files['seed/' . basename($seedFile)] = $seedFile;
        }

        return $files;
    }

    /**
     * Extract the relative include targets referenced as `... __DIR__ . '...'`.
     *
     * @return array<int, string>
     */
    private function includeTargets(string $content): array
    {
        preg_match_all(
            '/\b(?:require|require_once|include|include_once)\s*(?:\(\s*)?__DIR__\s*\.\s*[\'"]([^\'"]+)[\'"]/',
            $content,
            $matches
        );

        return $matches[1];
    }

    public function testCliEntryPointIncludesResolveToExistingFiles(): void
    {
        $root = dirname(__DIR__, 2);

        $missing = [];
        foreach ($this->scannedFiles($root) as $relativeFile => $absoluteFile) {
            $this->assertFileExists($absoluteFile);
            $content = file_get_contents($absoluteFile);
            $this->assertIsString($content);

            foreach ($this->includeTargets($content) as $target) {
                $resolved = dirname($absoluteFile) . str_replace('/', DIRECTORY_SEPARATOR, $target);
                if (!file_exists($resolved)) {
                    $missing[] = $relativeFile . ' -> ' . $target;
                }
            }
        }

        $this->assertEmpty(
            $missing,
            "CLI entry points reference files that do not exist:\n  - " . implode("\n  - ", $missing)
        );
    }
}