<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Echo_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bloque les styles inline (attribut style=) dans le code PHP.
 *
 * Le CSP interdit style-src 'unsafe-inline' — tous les styles doivent
 * aller dans public/css/style.css avec des classes CSS.
 *
 * @implements Rule<Echo_>
 */
final class NoInlineStyleRule implements Rule
{
    /** @var list<string> Chemins whitelistés (pas de contrôle) */
    private const WHITELIST_PATHS = [
        '/Enum/',
        '/PHPStan/',
        '/Rector/',
        '/lib/',
        '/vendor/',
        '/seed/',
        '/tests/',
        '/tools/',
        '/mail/',
    ];

    public function getNodeType(): string
    {
        return Echo_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $file = str_replace('\\', '/', $scope->getFile());

        foreach (self::WHITELIST_PATHS as $path) {
            if (str_contains($file, $path)) {
                return [];
            }
        }

        // Check for style= attribute in the echo output
        $content = $node->getAttribute('rawText') ?? '';
        if ($content === '') {
            // Check concatenated strings
            foreach ($node->exprs as $expr) {
                $content .= $expr->getAttribute('rawText') ?? '';
            }
        }

        if (preg_match_all('/style\s*=\s*["\'][^"\']*["\']/i', $content, $matches)) {
            return [
                RuleErrorBuilder::message('Style inline détecté — utiliser des classes CSS (CSP interdit style-src unsafe-inline).')
                    ->identifier('app.inlineStyle')
                    ->build(),
            ];
        }

        return [];
    }
}
