<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Bloque `array<..., mixed>` dans les @param/@return d'une fonction globale.
 * Voir NoMixedArrayTrait pour le détail.
 *
 * @implements Rule<Function_>
 */
final class NoMixedArrayInFunctionRule implements Rule
{
    use NoMixedArrayTrait;

    public function getNodeType(): string
    {
        return Function_::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        return $this->checkFunctionLike($node, $scope);
    }
}
