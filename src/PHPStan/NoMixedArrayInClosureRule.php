<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * Bloque `array<..., mixed>` dans les @param/@return d'une closure.
 * Voir NoMixedArrayTrait pour le détail. Les arrow functions (fn() => ...)
 * n'ont pas de syntaxe pour un docblock à elles en pratique (une seule
 * expression, pas de bloc), donc pas de classe dédiée pour
 * Node\Expr\ArrowFunction — rien à y détecter.
 *
 * @implements Rule<Closure>
 */
final class NoMixedArrayInClosureRule implements Rule
{
    use NoMixedArrayTrait;

    public function getNodeType(): string
    {
        return Closure::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return $this->checkFunctionLike($node, $scope);
    }
}
