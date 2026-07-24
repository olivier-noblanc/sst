<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Echo_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bloque les balises <script> inline dans le code PHP.
 *
 * Le JS doit aller dans des fichiers .js externes dans public/js/.
 * Les scripts inline dans les templates sont interdits (bonne pratique
 * de sécurité, même si le CSP autorise unsafe-inline pour l'instant).
 *
 * @implements Rule<Echo_>
 */
final class NoInlineScriptRule implements Rule
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

        // Check for <script> tag in the echo output
        $content = '';
        foreach ($node->exprs as $expr) {
            $content .= $expr->getAttribute('rawText') ?? '';
        }

        if (preg_match('/<script[\s>]/i', $content)) {
            return [
                RuleErrorBuilder::message('Balise <script> inline détectée — utiliser un fichier .js externe (public/js/).')
                    ->identifier('app.inlineScript')
                    ->build(),
            ];
        }

        return [];
    }
}
