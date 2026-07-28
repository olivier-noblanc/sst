<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Bloque array<..., mixed> dans les @var inline dans le corps d’une méthode
 * de classe. Voir NoMixedArrayTrait pour le détail (whitelist, pattern).
 *
 * @implements Rule<ClassMethod>
 */
final class NoMixedArrayInVarRule implements Rule
{
    use NoMixedArrayTrait;

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        return $this->checkVarAnnotations($node, $scope);
    }
}
