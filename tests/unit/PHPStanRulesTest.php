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
     * Verify that NoRawAnonymizationLiteralRule blocks 'Anonymisé'/'Anonymé' literals
     * outside AnonymizationPolicy.php (single source of truth for anonymization values).
     */
    public function testNoRawAnonymizationLiteralsInProductionCode(): void
    {
        $blockedLiterals = ['Anonymisé', 'Anonymé'];
        $productionFiles = $this->getProductionFiles();

        $violations = [];
        foreach ($productionFiles as $file) {
            if (basename($file) === 'AnonymizationPolicy.php') {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            foreach ($blockedLiterals as $literal) {
                $pattern = '/[\'"]' . preg_quote($literal, '/') . '[\'"]/';
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                        $violations[] = basename($file) . ':' . $line . ' — ' . $literal;
                    }
                }
            }
        }

        $this->assertEmpty($violations, "Littéraux d'anonymisation en dur trouvés hors AnonymizationPolicy.php : " . implode(', ', $violations));
    }

    /**
     * Verify that NoInvalidSqliteRaiseRule blocks RAISE(ABORT|FAIL|ROLLBACK, ... || ...)
     * — SQLite only accepts a string literal there, not a concatenated expression
     * (historical bug, commit 1859fdd — broke CI for 5 commits).
     */
    public function testNoInvalidSqliteRaiseInProductionCode(): void
    {
        $pattern = '/RAISE\s*\(\s*(ABORT|FAIL|ROLLBACK)\s*,\s*[^)]*\|\|/i';
        $productionFiles = $this->getProductionFiles();

        $violations = [];
        foreach ($productionFiles as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    $violations[] = basename($file) . ':' . $line;
                }
            }
        }

        $this->assertEmpty($violations, 'RAISE(...) avec expression concaténée trouvé (SQLite exige un littéral) : ' . implode(', ', $violations));
    }

    /**
     * Verify that NoSilentCatchRule's invariant holds: every catch in production code
     * either rethrows, exits (CLI scripts), or carries an explicit @silent-ok marker.
     * Mirrors the rule's own logic via token_get_all() rather than the AST, as a second,
     * independent check.
     */
    public function testNoSilentCatchInProductionCode(): void
    {
        $dirs = ['src', 'pages', 'templates', 'handlers', 'tools', 'seed', 'public'];
        $root = __DIR__ . '/../../';
        $files = [];

        foreach ($dirs as $dir) {
            $path = $root . $dir;
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php' && !str_contains($file->getPathname(), '/lib/')) {
                    $files[] = $file->getPathname();
                }
            }
        }
        foreach (['nuclear-reset.php', 'seed.php'] as $rootFile) {
            if (file_exists($root . $rootFile)) {
                $files[] = $root . $rootFile;
            }
        }

        $violations = [];
        foreach ($files as $file) {
            $tokens = token_get_all((string) file_get_contents($file));
            $n = count($tokens);
            for ($i = 0; $i < $n; $i++) {
                $t = $tokens[$i];
                if (!is_array($t) || $t[0] !== T_CATCH) {
                    continue;
                }
                $j = $i;
                while ($j < $n && !(is_string($tokens[$j]) && $tokens[$j] === '{')) {
                    $j++;
                }
                if ($j >= $n) {
                    continue;
                }
                $depth = 0;
                $hasThrow = false;
                $hasExit = false;
                $hasMarker = false;
                for ($k = $j; $k < $n; $k++) {
                    $tok = $tokens[$k];
                    if (is_string($tok)) {
                        if ($tok === '{') {
                            $depth++;
                        }
                        if ($tok === '}') {
                            $depth--;
                            if ($depth === 0) {
                                break;
                            }
                        }
                    } else {
                        if ($tok[0] === T_THROW) {
                            $hasThrow = true;
                        }
                        if ($tok[0] === T_EXIT) {
                            $hasExit = true;
                        }
                        if (($tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT) && str_contains($tok[1], '@silent-ok')) {
                            $hasMarker = true;
                        }
                    }
                }
                if (!$hasThrow && !$hasExit && !$hasMarker) {
                    $violations[] = basename($file) . ':' . $t[2];
                }
            }
        }

        $this->assertEmpty($violations, "Catch sans throw/exit/@silent-ok trouvé : " . implode(', ', $violations));
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
