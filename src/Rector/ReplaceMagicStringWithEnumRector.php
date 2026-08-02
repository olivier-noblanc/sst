<?php

declare(strict_types=1);

namespace App\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Case_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;

/**
 * Remplace les magic strings métier par les cases d'enum correspondantes.
 *
 * Gère :
 * - ===, !==, switch/case (string literals)
 * - Constantes legacy (ROLE_*, ETAT_*, TYPE_*) → Enum::Case->value
 */
final class ReplaceMagicStringWithEnumRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var array<string, string> Map magic string → "EnumClass::CaseName" */
    private array $stringToEnum = [];

    /** @var array<string, string> Map constant name → "EnumClass::CaseName" */
    private array $constToEnum = [];

    /**
     * @param array{stringToEnum: array<string, string>, constToEnum?: array<string, string>} $configuration
     */
    public function configure(array $configuration): void
    {
        $this->stringToEnum = $configuration['stringToEnum'];

        // Explicit const-to-enum mappings from config
        $this->constToEnum = $configuration['constToEnum'] ?? [];

        // Auto-build TYPE_* mappings from stringToEnum: TYPE_RSST → ReportType::Rsst->value
        foreach ($this->stringToEnum as $stringVal => $enumFqcn) {
            $parts = explode('::', $enumFqcn);
            if (count($parts) === 2) {
                $constName = 'TYPE_' . strtoupper($stringVal);
                $this->constToEnum[$constName] ??= $enumFqcn;
            }
        }
    }

    public function getNodeTypes(): array
    {
        return [
            Identical::class,
            NotIdentical::class,
            Case_::class,
            ConstFetch::class,
        ];
    }

    public function refactor(Node $node): ?Node
    {
        return match (true) {
            $node instanceof Identical, $node instanceof NotIdentical
                => $this->refactorComparison($node),
            $node instanceof Case_
                => $this->refactorCase($node),
            $node instanceof ConstFetch
                => $this->refactorConstFetch($node),
            default => null,
        };
    }

    /**
     * @param Identical|NotIdentical $node
     */
    private function refactorComparison(Node $node): ?Node
    {
        $stringNode = null;

        if ($node->left instanceof String_ && isset($this->stringToEnum[$node->left->value])) {
            $stringNode = $node->left;
        } elseif ($node->right instanceof String_ && isset($this->stringToEnum[$node->right->value])) {
            $stringNode = $node->right;
        }

        if ($stringNode === null) {
            return null;
        }

        $valueNode = $this->buildEnumValueNode($this->stringToEnum[$stringNode->value]);
        if ($valueNode === null) {
            return null;
        }

        if ($node->left instanceof String_) {
            $node->left = $valueNode;
        } else {
            $node->right = $valueNode;
        }

        return $node;
    }

    private function refactorCase(Case_ $node): ?Node
    {
        if (!$node->cond instanceof String_) {
            return null;
        }

        $value = $node->cond->value;
        if (!isset($this->stringToEnum[$value])) {
            return null;
        }

        $valueNode = $this->buildEnumValueNode($this->stringToEnum[$value]);
        if ($valueNode === null) {
            return null;
        }

        $node->cond = $valueNode;
        return $node;
    }

    /**
     * Replace TYPE_RSST → ReportType::Rsst->value, TYPE_RAMI → ReportType::Rami->value, etc.
     */
    private function refactorConstFetch(ConstFetch $node): ?Node
    {
        $constName = $node->name->toString();
        if (!isset($this->constToEnum[$constName])) {
            return null;
        }

        return $this->buildEnumValueNode($this->constToEnum[$constName]);
    }

    private function buildEnumValueNode(string $enumFqcn): ?PropertyFetch
    {
        $parts = explode('::', $enumFqcn);
        if (count($parts) !== 2) {
            return null;
        }

        [$enumClass, $caseName] = $parts;

        $enumNode = new ClassConstFetch(
            new FullyQualified($enumClass),
            $caseName
        );

        return new PropertyFetch(
            $enumNode,
            new Identifier('value')
        );
    }
}
