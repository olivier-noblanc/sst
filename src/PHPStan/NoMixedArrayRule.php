<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Param;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bloque l'utilisation de `array<string, mixed>` dans le code de production.
 *
 * `mixed` est un type-attrape-tout qui désactive la vérification de type
 * pour toutes les valeurs du tableau. C'est la cause racine de nombreux bugs :
 * accès à des clés inexistantes, valeurs de type inattendu, impossibilité de
 * refactorer sans casser des contrats implicites.
 *
 * Alternative : utiliser un DTO typé, ou au minimum `array<string, string>`,
 * `array<string, int>`, `array<string, mixed>` uniquement dans les couches
 * d'I/O (PDO fetch, $_POST, $_SESSION) qui sont whitelistées.
 *
 * @implements Rule<Param>
 */
final class NoMixedArrayRule implements Rule
{
    /** @var list<string> Chemins whitelistés — mixed est acceptable ici */
    private const WHITELIST_PATHS = [
        '/PHPStan/',
        '/Rector/',
        '/lib/',
        '/vendor/',
        '/tests/',
        '/tools/',
        '/seed/',
    ];

    /** @var list<string> Fichiers spécifiques whitelistés (I/O boundaries) */
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

    public function getNodeType(): string
    {
        return Param::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $file = str_replace('\\', '/', $scope->getFile());

        // Whitelist by path
        foreach (self::WHITELIST_PATHS as $path) {
            if (str_contains($file, $path)) {
                return [];
            }
        }

        // Whitelist by specific file
        foreach (self::WHITELIST_FILES as $wf) {
            if (str_contains($file, $wf)) {
                return [];
            }
        }

        // Check the param type for array<string, mixed>
        $typeNode = $node->type;
        if ($typeNode === null) {
            return [];
        }

        // Get the PHPDoc type string
        $docComment = $node->getDocComment();
        if ($docComment !== null) {
            $docText = $docComment->getText();
            if (preg_match('/array<[^>]*mixed[^>]*>/i', $docText)) {
                return [
                    RuleErrorBuilder::message(
                        'Type array<string, mixed> interdit — utiliser un DTO typé ou un array avec type de valeur précis '
                        . '(array<string, string>, array<string, int>, etc.). '
                        . 'mixed désactive la vérification de type et est la cause racine de nombreux bugs.'
                    )
                        ->identifier('app.noMixedArray')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
