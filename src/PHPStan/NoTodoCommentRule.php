<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Nop;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Interdit les commentaires TODO dans le code source.
 *
 * Les TODO doivent aller dans TODO.md, pas dans le code.
 * Les FIXME, HACK et XXX sont également interdits.
 *
 * @implements Rule<Nop>
 */
final class NoTodoCommentRule implements Rule
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

    private const TODO_PATTERN = '/\b(TODO|FIXME|HACK|XXX)\b/i';

    public function getNodeType(): string
    {
        return Nop::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $file = str_replace('\\', '/', $scope->getFile());

        foreach (self::WHITELIST_PATHS as $path) {
            if (str_contains($file, $path)) {
                return [];
            }
        }

        $comments = $node->getComments();
        $errors = [];

        foreach ($comments as $comment) {
            $text = $comment->getText();
            if (preg_match(self::TODO_PATTERN, $text, $matches)) {
                $keyword = strtoupper($matches[1]);
                $errors[] = RuleErrorBuilder::message(
                    "Commentaire \"$keyword\" interdit dans le code — utiliser TODO.md à la racine du projet."
                )
                    ->identifier('app.todoComment')
                    ->line($comment->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }
}
