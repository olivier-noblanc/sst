<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Détecte les DELETE FROM users / DELETE FROM reports bruts dans les fichiers tests/.
 *
 * Ces DELETE bruts violent les FK (reports.declarant_id → users.id,
 * report_responses.report_uuid → reports.uuid, etc.) et provoquent
 * "Integrity constraint violation: 19 FOREIGN KEY constraint failed".
 *
 * Utiliser cleanupForTest() ou cleanupAllForTest() du bootstrap à la place.
 *
 * @implements Rule<String_>
 */
final class NoBareDeleteInTestsRule implements Rule
{
    /**
     * Détecte DELETE FROM users / DELETE FROM reports / DELETE FROM report_responses
     * (les 3 tables les plus souvent supprimées sans respecter l'ordre FK).
     */
    private const BARE_DELETE_PATTERN = '/^\s*DELETE\s+FROM\s+(users|reports|report_responses)\s*$/is';

    public function getNodeType(): string
    {
        return String_::class;
    }

    /** @var list<string> Fichiers exclus (containent les helpers cleanup* ou des suppressions FK-safe) */
    private const EXCLUDED_FILES = [
        'bootstrap.php',
    ];

    public function processNode(Node $node, Scope $scope): array
    {
        $file = str_replace('\\', '/', $scope->getFile());

        if (!str_contains($file, '/tests/')) {
            return [];
        }

        $basename = basename($file);
        foreach (self::EXCLUDED_FILES as $excluded) {
            if ($basename === $excluded) {
                return [];
            }
        }

        $value = $node->value;

        if (!preg_match(self::BARE_DELETE_PATTERN, $value)) {
            return [];
        }

        $table = '';
        if (preg_match(self::BARE_DELETE_PATTERN, $value, $m)) {
            $table = strtolower($m[1]);
        }

        if ($table === 'users' || $table === 'reports') {
            return [
                RuleErrorBuilder::message(
                    "DELETE FROM $table interdit dans les tests — utiliser cleanupForTest(\$pdo, 'prefix%') ou cleanupAllForTest(\$pdo) (tests/bootstrap.php)"
                )
                    ->identifier('app.bareDeleteInTests')
                    ->build(),
            ];
        }

        if ($table === 'report_responses') {
            return [
                RuleErrorBuilder::message(
                    "DELETE FROM report_responses interdit dans les tests — utiliser cleanupForTest(\$pdo, 'prefix%') ou cleanupAllForTest(\$pdo) qui gère l'ordre FK"
                )
                    ->identifier('app.bareDeleteInTests')
                    ->build(),
            ];
        }

        return [];
    }
}
