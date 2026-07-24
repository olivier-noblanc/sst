<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bloque les constantes legacy (ROLE_*, ETAT_*, TYPE_*) dans le code applicatif.
 * L'usage doit passer par les enums correspondants (UserRole, ReportState, ReportType).
 *
 * @implements Rule<ConstFetch>
 */
final class NoLegacyConstantRule implements Rule
{
    /** @var list<string> Constantes interdites */
    private const BLOCKED_CONSTANTS = [
        'ROLE_AGENT',
        'ROLE_SUPERVISEUR',
        'ROLE_CHSCT',
        'ETAT_NOUVEAU',
        'ETAT_EN_COURS',
        'ETAT_TRAITE',
        'ETAT_ABANDONNE',
        'ETAT_REOUVERT',
    ];

    /** @var list<string> Chemins whitelistés */
    private const WHITELIST_PATHS = [
        '/Enum/',
        '/PHPStan/',
        '/Rector/',
        '/lib/',
        '/vendor/',
        '/seed/',
        '/tests/',
        '/tools/',
    ];

    /** @var list<string> Fichiers whitelistés (définitions des constantes themselves) */
    private const WHITELIST_FILES = [
        'config.php',
    ];

    public function getNodeType(): string
    {
        return ConstFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!($node->name instanceof Name)) {
            return [];
        }

        $name = $node->name->toString();

        if (!in_array($name, self::BLOCKED_CONSTANTS, true)) {
            return [];
        }

        $file = str_replace('\\', '/', $scope->getFile());

        foreach (self::WHITELIST_PATHS as $path) {
            if (str_contains($file, $path)) {
                return [];
            }
        }

        $basename = basename($file);
        if (in_array($basename, self::WHITELIST_FILES, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message("Constante legacy '$name' détectée — utiliser l'enum correspondant (UserRole, ReportState, etc.).")
                ->identifier('app.legacyConstant')
                ->build(),
        ];
    }
}
