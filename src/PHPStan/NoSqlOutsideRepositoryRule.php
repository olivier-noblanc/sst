<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Détecte les SQL inline (SELECT, INSERT, UPDATE, DELETE) en dehors de src/Repository/.
 *
 * @implements Rule<String_>
 */
final class NoSqlOutsideRepositoryRule implements Rule
{
    private const SQL_PATTERN = '/\b(select|insert|update|delete)\b/i';

    /** @var list<string> Paths whitelistés (SQL légitime hors Repository) */
    private const WHITELIST_PATHS = [
        '/Repository/',
        '/PHPStan/',
        '/lib/',
        '/vendor/',
        '/migration_',
        '/seed',
        '/tools/',
        '/cron',
        '/nuclear-reset',
    ];

    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $file = str_replace('\\', '/', $scope->getFile());

        foreach (self::WHITELIST_PATHS as $path) {
            if (str_contains($file, $path)) {
                return [];
            }
        }

        $value = $node->value;

        if (preg_match(self::SQL_PATTERN, $value)) {
            return [
                RuleErrorBuilder::message("SQL détecté hors du layer Repository : '$value'")
                    ->identifier('app.sqlOutsideRepository')
                    ->build(),
            ];
        }

        return [];
    }
}
