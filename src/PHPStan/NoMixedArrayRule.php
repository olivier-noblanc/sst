<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bloque l'utilisation de `array<..., mixed>` dans les @param/@return du code
 * de production.
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
 * Audit #80 — v1 de cette règle ciblait Param::getDocComment(), qui n'est
 * renseigné que pour un commentaire collé directement devant CE paramètre
 * dans la signature (style que ce projet n'utilise nulle part). Les vrais
 * @param/@return vivent dans le docblock de la méthode/fonction elle-même.
 * Résultat : la règle ne s'est jamais déclenchée une seule fois, y compris
 * sur des cas non whitelistés (src/DTO/CreateReportCommand.php,
 * src/DTO/ReportFilter.php) — le CI est passé vert sur du code qu'elle est
 * censée interdire. Cette version cible FunctionLike (ClassMethod, Function_,
 * Closure, ArrowFunction) et lit son propre docblock, en distinguant @param
 * et @return. Ne couvre pas encore les @var locaux en cours de méthode
 * (ex. RegistryRepository.php) — hors périmètre de cette passe.
 *
 * @implements Rule<FunctionLike>
 */
final class NoMixedArrayRule implements Rule
{
    // Pas de @var ici : sur un `private const = [litéral]`, PHPStan infère
    // déjà le type le plus précis possible depuis le littéral lui-même —
    // un @var list<string> ne fait que le réélargir sans rien vérifier de
    // plus.
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

    public function getNodeType(): string
    {
        return FunctionLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
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
}
