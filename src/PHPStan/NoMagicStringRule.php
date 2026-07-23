<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bloque les magic strings métier (valeurs d'enums) dans le code applicatif.
 *
 * @implements Rule<String_>
 */
final class NoMagicStringRule implements Rule
{
    /** @var list<string> Valeurs d'enum interdites en magic string */
    private const BLOCKED_VALUES = [
        // VisibilityMode
        'confidential', 'public', 'agent_choice',
        // ReportType
        'rsst', 'rami', 'dgi',
        // ReportState
        'nouveau', 'en_cours', 'traite', 'reouvert', 'abandonne',
    ];

    /** @var list<string> Chemins whitelistés (pas de contrôle) */
    private const WHITELIST_PATHS = [
        '/Enum/',
        '/PHPStan/',
        '/lib/',
        '/vendor/',
        '/seed/',
        '/tests/',
        '/tools/',
    ];

    /** @var list<string> Fichiers whitelistés (faux positifs : colonnes SQL, seed data, config Rector) */
    private const WHITELIST_FILES = [
        'synthesis.php',
        'statistics.php',
        'seed.php',
        'rector.php',
    ];

    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $value = $node->value;

        if (!in_array($value, self::BLOCKED_VALUES, true)) {
            return [];
        }

        $file = str_replace('\\', '/', $scope->getFile());

        // Whitelist : fichiers enum, lib, vendor, seed, tests, tools
        foreach (self::WHITELIST_PATHS as $path) {
            if (str_contains($file, $path)) {
                return [];
            }
        }

        // Whitelist : fichiers spécifiques (faux positifs SQL)
        $basename = basename($file);
        if (in_array($basename, self::WHITELIST_FILES, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message("Magic string '$value' détectée — utiliser l'enum correspondant.")
                ->identifier('app.magicString')
                ->build(),
        ];
    }
}
