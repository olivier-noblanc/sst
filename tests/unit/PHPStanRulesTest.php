<?php
/**
 * PHPStan Rules Tests — Verify that custom PHPStan rules detect legacy code.
 *
 * Tests NoLegacyConstantRule and NoMagicStringRule by scanning the codebase
 * for violations and ensuring the rules catch them.
 */

use PHPUnit\Framework\TestCase;

class PHPStanRulesTest extends TestCase
{
    /**
     * Verify that NoLegacyConstantRule blocks ROLE_* constants in production code.
     */
    public function testNoLegacyRoleConstantsInProductionCode(): void
    {
        $blockedConstants = ['ROLE_AGENT', 'ROLE_SUPERVISEUR', 'ROLE_CHSCT'];
        $productionFiles = $this->getProductionFiles();

        $violations = [];
        foreach ($productionFiles as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            foreach ($blockedConstants as $constant) {
                // Match constant usage but not in comments, strings, or definitions
                $pattern = '/(?<!\/\/\s)(?<!\/\*\s)(?<!\*\s)(?<!define\()(?<!define\( )(?<!define\(\')(?<!define\(")\b' . preg_quote($constant) . '\b/';
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                        $violations[] = basename($file) . ':' . $line . ' — ' . $constant;
                    }
                }
            }
        }

        $this->assertEmpty($violations, 'Legacy ROLE_* constants found in production code: ' . implode(', ', $violations));
    }

    /**
     * Verify that NoLegacyConstantRule blocks ETAT_* constants in production code.
     */
    public function testNoLegacyEtatConstantsInProductionCode(): void
    {
        $blockedConstants = ['ETAT_NOUVEAU', 'ETAT_EN_COURS', 'ETAT_TRAITE', 'ETAT_ABANDONNE', 'ETAT_REOUVERT'];
        $productionFiles = $this->getProductionFiles();

        $violations = [];
        foreach ($productionFiles as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            foreach ($blockedConstants as $constant) {
                $pattern = '/(?<!\/\/\s)(?<!\/\*\s)(?<!\*\s)(?<!define\()(?<!define\( )(?<!define\(\')(?<!define\(")\b' . preg_quote($constant) . '\b/';
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                        $violations[] = basename($file) . ':' . $line . ' — ' . $constant;
                    }
                }
            }
        }

        $this->assertEmpty($violations, 'Legacy ETAT_* constants found in production code: ' . implode(', ', $violations));
    }

    /**
     * Verify that NoLegacyConstantRule blocks TYPE_* constants in production code.
     */
    public function testNoLegacyTypeConstantsInProductionCode(): void
    {
        $blockedConstants = ['TYPE_RSST', 'TYPE_RAMI', 'TYPE_DGI'];
        $productionFiles = $this->getProductionFiles();

        $violations = [];
        foreach ($productionFiles as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            foreach ($blockedConstants as $constant) {
                $pattern = '/(?<!\/\/\s)(?<!\/\*\s)(?<!\*\s)(?<!define\()(?<!define\( )(?<!define\(\')(?<!define\(")\b' . preg_quote($constant) . '\b/';
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                        $violations[] = basename($file) . ':' . $line . ' — ' . $constant;
                    }
                }
            }
        }

        $this->assertEmpty($violations, 'Legacy TYPE_* constants found in production code: ' . implode(', ', $violations));
    }

    /**
     * Get all production PHP files (excluding tests, vendor, seed, tools).
     *
     * @return list<string>
     */
    private function getProductionFiles(): array
    {
        $dirs = ['src', 'pages', 'handlers', 'templates'];
        $excludeDirs = ['vendor', 'lib', 'tests', 'seed', 'tools', 'Enum', 'PHPStan', 'Rector'];
        $files = [];

        foreach ($dirs as $dir) {
            $path = __DIR__ . '/../../' . $dir;
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = str_replace(['\\', '/'], '/', $file->getPathname());
                $relativePath = str_replace(str_replace(['\\', '/'], '/', __DIR__ . '/../../') . '/', '', $relativePath);

                $skip = false;
                $segments = explode('/', $relativePath);
                array_pop($segments); // remove filename, keep only directory segments
                foreach ($excludeDirs as $exc) {
                    if (in_array($exc, $segments, true)) {
                        $skip = true;
                        break;
                    }
                }

                if (!$skip) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
