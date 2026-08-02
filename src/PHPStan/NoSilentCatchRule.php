<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Impose la règle déjà écrite noir sur blanc dans AGENTS.md (« Erreurs —
 * Crash hard, jamais silencieux ») : un `catch` qui n'aboutit à aucun
 * `throw` fait taire une exception au lieu de la laisser remonter — donc,
 * pour une requête SQL qui échoue, un échec invisible en prod.
 *
 * Exemple réel trouvé le 30/07/2026 dans ce repo : `UserRepository::anonymize()`
 * catch toute Exception, rollback, error_log, `return false` — si le seul
 * appelant qui checke le retour oublie de le faire un jour (ça a déjà été le
 * cas, cf. Audit #8), l'anonymisation RGPD échoue en silence, "succès"
 * affiché quand même.
 *
 * Cette règle ne bannit pas tous les catch — beaucoup sont légitimes
 * (logging d'audit qui ne doit jamais bloquer l'app, notifications
 * best-effort après une transaction déjà commitée, fallback pré-migration
 * pendant une fenêtre de déploiement). Un catch est accepté si :
 * - son corps contient un `throw` (le cas normal — l'exception remonte
 *   après nettoyage/rollback/log), ou
 * - son corps contient un `exit()`/`die()` (scripts CLI — il n'y a pas
 *   d'appelant à qui remonter, le code de sortie non-zéro est le signal), ou
 * - un commentaire `@silent-ok: <raison>` est présent dans le corps du catch,
 *   documentant explicitement pourquoi avaler est le bon choix ici.
 *
 * @implements Rule<Catch_>
 */
final class NoSilentCatchRule implements Rule
{
    private const MARKER = '@silent-ok';

    public function getNodeType(): string
    {
        return Catch_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $finder = new NodeFinder();

        // Rethrow (direct ou conditionnel) ou exit()/die() (scripts CLI — pas
        // d'appelant à qui remonter, le code de sortie est le signal) → OK.
        if ($finder->findFirstInstanceOf($node->stmts, Throw_::class) !== null) {
            return [];
        }
        if ($finder->findFirstInstanceOf($node->stmts, Exit_::class) !== null) {
            return [];
        }

        // Marqueur : lu dans le texte source brut plutôt que via l'attachement de
        // commentaires PHP-Parser à un stmt — un corps de catch qui ne contient
        // qu'un commentaire (catch {} vide à part la doc) n'a AUCUN stmt pour
        // porter ce commentaire, et l'attachement de commentaires "orphelins" au
        // node suivant est trop peu fiable pour qu'on s'y fie ici.
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();
        if ($start >= 0 && $end >= $start) {
            $file = $scope->getFile();
            $source = @file_get_contents($file);
            if ($source !== false && str_contains(substr($source, $start, $end - $start + 1), self::MARKER)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                "Catch sans throw ni marqueur '" . self::MARKER . ": <raison>' — l'exception est avalée silencieusement (AGENTS.md § Erreurs : crash hard, jamais silencieux). Soit relancer (throw), soit documenter explicitement pourquoi avaler est correct ici avec un commentaire '" . self::MARKER . ": <raison>' dans le corps du catch."
            )
                ->identifier('app.noSilentCatch')
                ->build(),
        ];
    }
}
