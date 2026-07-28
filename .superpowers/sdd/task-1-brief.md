### Task 1 : Créer `NoMixedArrayInVarRule`

**Files:**
- Create: `src/PHPStan/NoMixedArrayInVarRule.php`
- Modify: `src/PHPStan/NoMixedArrayTrait.php`
- Modify: `phpstan-no-magic-string.neon`

**Interfaces:**
- Produces: Nouvelle règle PHPStan qui détecte `@var array<..., mixed>` dans les corps de méthode

- [ ] **Step 1 : Étendre `NoMixedArrayTrait`**

Ajouter une méthode `checkVarAnnotations(Node $node, Scope $scope): array` dans le trait. Cette méthode :
1. Vérifie la whitelist (même logique que `checkFunctionLike`)
2. Parcourt `$node->stmts` récursivement (pour les `ClassMethod`) ou le node lui-même (pour `Function_`)
3. Pour chaque statement, récupère `getDocComment()`
4. Applique un regex `@var\b[^\n]*\barray<[^>\n]*\bmixed\b[^>\n]*>` sur chaque docblock
5. Retourne les erreurs avec identifier `app.noMixedArray`

Le pattern regex pour `@var` : `/@var\b[^\n]*\barray<[^>\n]*\bmixed\b[^>\n]*>[^\n]*/i`

- [ ] **Step 2 : Créer `NoMixedArrayInVarRule.php`**

```php
<?php
declare(strict_types=1);
namespace App\PHPStan;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
/** @implements Rule<ClassMethod> */
final class NoMixedArrayInVarRule implements Rule
{
    use NoMixedArrayTrait;
    public function getNodeType(): string { return ClassMethod::class; }
    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        return $this->checkVarAnnotations($node, $scope);
    }
}
```

- [ ] **Step 3 : Enregistrer dans `phpstan-no-magic-string.neon`**

Ajouter :
```neon
-
    class: App\PHPStan\NoMixedArrayInVarRule
    tags:
        - phpstan.rules.rule
```

- [ ] **Step 4 : Vérifier que PHPStan passe**

```bash
rtk phpstan analyse --memory-limit=1G
```

- [ ] **Step 5 : Commit**

```bash
git add src/PHPStan/NoMixedArrayInVarRule.php src/PHPStan/NoMixedArrayTrait.php phpstan-no-magic-string.neon
git commit -m "feat(phpstan): add NoMixedArrayInVarRule for @var annotations in method bodies"
```

---

