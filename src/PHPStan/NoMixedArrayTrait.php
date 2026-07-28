<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Logique partagée par NoMixedArrayInMethodRule / NoMixedArrayInFunctionRule /
 * NoMixedArrayInClosureRule / NoMixedArrayInVarRule.
 *
 * Audit #80 — la v1 (une seule classe NoMixedArrayRule ciblant
 * Node\Param) ne s'est jamais déclenchée : Param::getDocComment() n'est
 * renseigné que pour un commentaire collé devant CE paramètre précis dans
 * la signature, pas pour le docblock de la méthode. La v2 (une seule classe
 * ciblant l'interface Node\FunctionLike) ne s'est pas déclenchée non plus —
 * empiriquement confirmé en CI (commit ad979d4, vert sur des violations
 * connues de src/DTO/) : le dispatch de règles de PHPStan ne matche pas de
 * façon fiable sur une interface passée à getNodeType(), seulement sur des
 * classes concrètes. D'où 3 petites classes concrètes (ClassMethod,
 * Function_, Closure/ArrowFunction) partageant cette même logique, à
 * l'image de comment PHPStan structure ses propres règles "toutes les
 * déclarations de fonction".
 */
trait NoMixedArrayTrait
{
    private const WHITELIST_PATHS = [
        '/PHPStan/',
        '/Rector/',
        '/lib/',
        '/vendor/',
        '/tests/',
        '/tools/',
        '/seed/',
    ];

    private const WHITELIST_FILES = [
        'src/database.php',           // PDO fetch
        'src/audit.php',              // audit log context
        'src/migration_columns.php',  // migration data
        'src/migration_tables.php',   // migration data
        'src/migration_config.php',   // config data
        'src/migration_indexes.php',  // migration data
        'src/config.php',             // config helpers
        'src/helpers/config.php',     // config helpers
        'src/session.php',            // $_SESSION
        'src/session_form.php',       // form data
        'src/session_patch.php',      // session patch
        'src/auth.php',               // auth helpers
        'src/auth_flow.php',          // auth flow
        'src/error_notify.php',       // error context
        'src/error_handler.php',      // error context
        'src/mail.php',               // SMTP config
        'src/mail_templates.php',     // email data
        'src/mail_notifications.php', // notification data
        'src/backup.php',             // backup fingerprint
        'src/backup_protection.php',  // backup data
        'src/cron.php',               // cron context
        'src/cron_anonymize.php',     // cron data
        'src/cron_cleanup.php',       // cron data
        'src/user_context.php',       // session user
        'src/helpers/http.php',       // $_POST / $_SERVER
        'src/helpers/access.php',     // access helpers
        'src/helpers/formatting.php', // formatting helpers
        'src/helpers/uuid.php',       // uuid helpers
        'src/helpers/assets.php',     // asset helpers
        'src/helpers/crypto.php',     // crypto helpers
        'src/helpers/registry_card_renderer.php', // renderer data
        'src/helpers.php',            // helpers loader
        'src/autoload.php',           // autoloader
        'src/bootstrap_services.php', // DI container
        'src/Event/event_listeners.php', // event data
        'src/Event/EventDispatcher.php', // event data
        'src/Container/Container.php', // container data
        'src/Router/Router.php',      // route params
        'src/Router/Renderer.php',    // render params
        'src/Router/routes.php',      // route config
        'src/validation.php',         // validation data
        'src/Middleware/bootstrap.php', // middleware data
        'src/Middleware/require_auth.php',
        'src/Middleware/require_role.php',
    ];

    private const MIXED_ARRAY_TAG_PATTERN = '/@(param|return)\b[^\n]*\barray<[^>\n]*\bmixed\b[^>\n]*>[^\n]*/i';

    private const VAR_ARRAY_TAG_PATTERN = '/@var\b[^\n]*\barray<[^>\n]*\bmixed\b[^>\n]*>[^\n]*/i';

    /**
     * @param FunctionLike $node
     * @return list<IdentifierRuleError>
     */
    private function checkFunctionLike(Node $node, Scope $scope): array
    {
        $file = str_replace('\\', '/', $scope->getFile());

        foreach (self::WHITELIST_PATHS as $path) {
            if (str_contains($file, $path)) {
                return [];
            }
        }

        foreach (self::WHITELIST_FILES as $wf) {
            if (str_contains($file, $wf)) {
                return [];
            }
        }

        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return [];
        }

        $docText = $docComment->getText();
        if (!preg_match_all(self::MIXED_ARRAY_TAG_PATTERN, $docText, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $errors = [];
        foreach ($matches[0] as $i => [, $offset]) {
            $tag = strtolower((string) $matches[1][$i][0]);
            $line = $docComment->getStartLine() + substr_count(substr($docText, 0, (int) $offset), "\n");
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Type array<..., mixed> interdit dans un tag @%s — utiliser un DTO typé ou un array avec type de '
                . 'valeur précis (array<string, string>, array<string, int>, etc.). mixed désactive la vérification '
                . 'de type et est la cause racine de nombreux bugs.',
                $tag
            ))
                ->identifier('app.noMixedArray')
                ->line($line)
                ->build();
        }

        return $errors;
    }

    /**
     * Check @var annotations inside the method body (statements).
     *
     * Recursively walks $node->stmts to find @var annotations with
     * array<..., mixed> in inline doc comments inside the method body.
     *
     * @return list<IdentifierRuleError>
     */
    private function checkVarAnnotations(Node $node, Scope $scope): array
    {
        $file = str_replace('\\', '/', $scope->getFile());

        foreach (self::WHITELIST_PATHS as $path) {
            if (str_contains($file, $path)) {
                return [];
            }
        }

        foreach (self::WHITELIST_FILES as $wf) {
            if (str_contains($file, $wf)) {
                return [];
            }
        }

        if (!property_exists($node, 'stmts') || !is_array($node->stmts)) {
            return [];
        }

        $errors = [];
        $this->walkStatementsForVarAnnotations($node->stmts, $errors);

        return $errors;
    }

    /**
     * Recursively collect @var array<..., mixed> errors from statements.
     *
     * @param list<\PhpParser\Node\Stmt> $stmts
     * @param list<IdentifierRuleError> $errors
     * @return void
     */
    private function walkStatementsForVarAnnotations(array $stmts, array &$errors): void
    {
        foreach ($stmts as $stmt) {
            $docComment = $stmt->getDocComment();
            if ($docComment !== null) {
                $docText = $docComment->getText();
                if (preg_match_all(self::VAR_ARRAY_TAG_PATTERN, $docText, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $i => [, $offset]) {
                        $line = $docComment->getStartLine() + substr_count(substr($docText, 0, (int) $offset), "\n");
                        $errors[] = RuleErrorBuilder::message(
                            'Type array<..., mixed> interdit dans un tag @var — utiliser un DTO typé ou un array avec type de '
                            . 'valeur précis (array<string, string>, array<string, int>, etc.). mixed désactive la vérification '
                            . 'de type et est la cause racine de nombreux bugs.'
                        )
                            ->identifier('app.noMixedArray')
                            ->line($line)
                            ->build();
                    }
                }
            }

            // Recurse into nested statements (if/for/foreach/while/try/etc.)
            foreach (['stmts', 'cases', 'catches', 'finallyStmts', 'elseifs', 'else'] as $property) {
                if (property_exists($stmt, $property) && is_array($stmt->$property)) {
                    $this->walkStatementsForVarAnnotations($stmt->$property, $errors);
                }
            }
        }
    }
}
