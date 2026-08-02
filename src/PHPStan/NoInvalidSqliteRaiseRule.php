<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Interdit RAISE(ABORT|FAIL|ROLLBACK, <expression>) dans un trigger SQLite —
 * RAISE n'accepte qu'un STRING LITERAL comme message, jamais une expression
 * concaténée (`'texte' || col || '...'`).
 *
 * Historique (commit 1859fdd, 29/07/2026) : deux triggers de test utilisaient
 * exactement ce pattern pour inclure la valeur fautive de site_id dans le
 * message d'erreur. SQLite refuse cette syntaxe ("near '||': syntax error"),
 * cassant toute la CI (PHPUnit + Infection) pendant 5 commits avant d'être
 * repéré — l'erreur n'apparaît qu'à l'exécution du trigger, jamais avant.
 * Ces triggers ont depuis été supprimés (commit c125cf4, validation site_id
 * déplacée côté applicatif), mais rien n'empêchait qu'un futur trigger
 * réintroduise le même piège ailleurs — le message d'erreur SQLite ne dit pas
 * "RAISE n'accepte qu'un littéral", il dit juste "syntax error near '||'",
 * ce qui n'oriente pas naturellement vers la vraie cause.
 *
 * @implements Rule<String_>
 */
final class NoInvalidSqliteRaiseRule implements Rule
{
    private const RAISE_PATTERN = '/RAISE\s*\(\s*(ABORT|FAIL|ROLLBACK)\s*,\s*[^)]*\|\|/i';

    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!preg_match(self::RAISE_PATTERN, $node->value)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                "RAISE(...) avec une expression concaténée ('texte' || col) détecté — SQLite n'accepte qu'un STRING LITERAL comme message RAISE, pas une expression. Cette syntaxe compile en PHP mais échoue à l'exécution du trigger avec une erreur peu explicite ('syntax error near ||'). Utiliser un message statique."
            )
                ->identifier('app.invalidSqliteRaise')
                ->build(),
        ];
    }
}
