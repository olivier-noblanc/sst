<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Interdit d'appeler certaines méthodes sans utiliser leur valeur de retour
 * (ni assignation, ni condition, ni return, ni argument — juste un appel en
 * l'air dont le résultat est jeté).
 *
 * Contexte (Audit #8, cf. commentaire dans UserRepository::anonymize()) :
 * cette méthode catch toute Exception et retourne `false` au lieu de la
 * laisser remonter (voir NoSilentCatchRule) — un choix assumé pour permettre
 * un message d'erreur utilisateur propre plutôt qu'un crash. Mais ce choix
 * déplace la responsabilité de ne pas être silencieux vers CHAQUE appelant :
 * un appelant qui ignore le booléen fait exactement la même erreur que celui
 * qui avale une exception. C'est très précisément ce qui s'est produit une
 * fois (handler qui ignorait le retour, "succès" affiché à tort).
 *
 * Cette règle ne remplace pas NoSilentCatchRule (qui couvre le try/catch
 * lui-même) — elle couvre le pas suivant : le contrat du bool en sortie.
 *
 * @implements Rule<Expression>
 */
final class MustCheckReturnValueRule implements Rule
{
    /**
     * Class => [méthodes]. Uniquement des méthodes bool "signal
     * critique" (succès/échec RGPD ou verrou cron) — pas une liste
     * générale, qui produirait trop de faux positifs sur des méthodes
     * dont ignorer le retour est parfaitement normal.
     *
     * @var array<string, list<string>>
     */
    private const MUST_CHECK = [
        'App\\Repository\\UserRepository' => ['anonymize'],
        'App\\Repository\\ReportRepository' => ['anonymize'],
        'App\\Repository\\ConfigRepository' => ['claimLazyCronLock'],
    ];

    public function getNodeType(): string
    {
        return Expression::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->expr instanceof MethodCall) {
            return [];
        }

        $call = $node->expr;
        if (!$call->name instanceof Node\Identifier) {
            return [];
        }
        $methodName = $call->name->toString();

        $callerType = $scope->getType($call->var);
        foreach ($callerType->getObjectClassNames() as $className) {
            foreach (self::MUST_CHECK[$className] ?? [] as $watchedMethod) {
                if (strcasecmp($methodName, $watchedMethod) === 0) {
                    return [
                        RuleErrorBuilder::message(
                            "Retour de {$className}::{$methodName}() ignoré — cette méthode signale un échec via son booléen de retour (elle catch ses exceptions, voir NoSilentCatchRule) au lieu de les laisser remonter. Ignorer le retour revient à ignorer un échec silencieusement (déjà arrivé une fois, Audit #8). Vérifier explicitement la valeur retournée."
                        )
                            ->identifier('app.mustCheckReturnValue')
                            ->build(),
                    ];
                }
            }
        }

        return [];
    }
}
