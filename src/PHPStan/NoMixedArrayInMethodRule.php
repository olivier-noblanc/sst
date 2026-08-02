<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Bloque `array<..., mixed>` dans les @param/@return d'une méthode de
 * classe. Voir NoMixedArrayTrait pour le détail (whitelist, pattern,
 * pourquoi 3 classes séparées plutôt qu'une seule sur Node\FunctionLike).
 *
 * @implements Rule<ClassMethod>
 */
final class NoMixedArrayInMethodRule implements Rule
{
    use NoMixedArrayTrait;

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        return $this->checkFunctionLike($node, $scope);
    }
}
