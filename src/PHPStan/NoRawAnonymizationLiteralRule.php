<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Interdit les littéraux d'anonymisation ('Anonymisé', 'Anonymé') en dehors de
 * AnonymizationPolicy.php.
 *
 * Contexte : avant AnonymizationPolicy, ces deux valeurs étaient tapées en dur
 * indépendamment dans UserRepository::anonymize() ET ReportRepository::anonymize()
 * — deux sources de vérité qui auraient pu diverger silencieusement. Cette règle
 * rend la duplication impossible à réintroduire : si un futur agent (ou humain)
 * réécrit ces valeurs en dur ailleurs plutôt que d'utiliser
 * AnonymizationPolicy::ANONYMIZED_NAME / ANONYMIZED_FIRSTNAME, PHPStan casse.
 *
 * @implements Rule<String_>
 */
final class NoRawAnonymizationLiteralRule implements Rule
{
    /** @var list<string> Valeurs qui ne doivent exister qu'à un seul endroit */
    private const BLOCKED_LITERALS = [
        'Anonymisé',
        'Anonymé',
    ];

    /** @var list<string> Fichiers whitelistés (la définition elle-même + les règles/tests d'infra) */
    private const WHITELIST_FILES = [
        'AnonymizationPolicy.php',
    ];

    /** @var list<string> Chemins whitelistés */
    private const WHITELIST_PATHS = [
        '/PHPStan/',
        '/tests/',
        '/lib/',
        '/vendor/',
    ];

    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!in_array($node->value, self::BLOCKED_LITERALS, true)) {
            return [];
        }

        $file = str_replace('\\', '/', $scope->getFile());

        $basename = basename($file);
        if (in_array($basename, self::WHITELIST_FILES, true)) {
            return [];
        }

        foreach (self::WHITELIST_PATHS as $path) {
            if (str_contains($file, $path)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                "Littéral d'anonymisation '{$node->value}' en dur détecté — utiliser AnonymizationPolicy::ANONYMIZED_NAME / ANONYMIZED_FIRSTNAME (une seule source de vérité, voir src/Repository/AnonymizationPolicy.php)."
            )
                ->identifier('app.rawAnonymizationLiteral')
                ->build(),
        ];
    }
}
