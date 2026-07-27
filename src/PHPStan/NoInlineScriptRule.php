<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Echo_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bloque les balises <script> inline ET les attributs onclick= dans le code PHP.
 *
 * Le JS doit aller dans des fichiers .js externes dans public/js/.
 * Les scripts inline et les handlers onclick dans les templates sont interdits
 * (bonne pratique de sécurité, même si le CSP autorise unsafe-inline pour l'instant).
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

        $errors = [];

        // Bug #97 — Also detect onclick= and other inline event handlers
        // (onload, onchange, onsubmit, onfocus, onblur, onmouseover, etc.)
        if (preg_match('/<script[\s>]/i', $content)) {
            $errors[] = RuleErrorBuilder::message('Balise <script> inline détectée — utiliser un fichier .js externe (public/js/).')
                ->identifier('app.inlineScript')
                ->build();
        }

        if (preg_match('/\bon(click|load|change|submit|focus|blur|mouseover|mouseout|keyup|keydown|keypress)=/i', $content)) {
            $errors[] = RuleErrorBuilder::message('Handler d\'événement inline (onclick=, etc.) détecté — utiliser addEventListener dans un fichier .js externe (public/js/).')
                ->identifier('app.inlineEventHandler')
                ->build();
        }

        return $errors;
    }
}
