<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Interdit l'appel à ReportType::from() (native PHP enum method).
 *
 * ReportType::from() lève ValueError sur les valeurs inconnues (codes
 * de registre personnalisés comme 'violences', 'harassment'). Utiliser
 * ReportType::tryFrom() ou ReportType::fromCode() à la place.
 *
 * @implements Rule<StaticCall>
 */
final class NoForbiddenEnumMethodRule implements Rule
{
    private const FORBIDDEN_CALLS = [
        'App\Enum\ReportType' => ['from'],
    ];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Name) {
            return [];
        }

        $className = $scope->resolveName($node->class);
        if (!$node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        if (!isset(self::FORBIDDEN_CALLS[$className])) {
            return [];
        }

        if (!in_array($methodName, self::FORBIDDEN_CALLS[$className], true)) {
            return [];
        }

        $file = str_replace('\\', '/', $scope->getFile());

        // Autoriser dans les tests (tests/), l'enum elle-même (Enum/), et les règles PHPStan (PHPStan/)
        foreach (['/tests/', '/Enum/', '/PHPStan/'] as $allowed) {
            if (str_contains($file, $allowed)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                "{$className}::{$methodName}() est interdit — utiliser tryFrom() ou fromCode() à la place (from() lève ValueError sur les valeurs inconnues)."
            )
                ->identifier('app.forbiddenEnumMethod')
                ->build(),
        ];
    }
}
