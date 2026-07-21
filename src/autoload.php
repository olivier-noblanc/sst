<?php

/**
 * Simple PSR-4 Autoloader — Application SST DREETS BFC
 *
 * Remplace Composer en production. Pas de dépendance externe.
 * Charge les classes App\\ depuis src/ et les fichiers procéduraux.
 */

// PSR-4 autoloader pour le namespace App\
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Fichiers procéduraux (helpers, config, session, auth, etc.)
// Ordre important : config d'abord (les autres helpers appellent getConfig())
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/session_form.php';
require_once __DIR__ . '/session_patch.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/auth_flow.php';
require_once __DIR__ . '/user_context.php';
require_once __DIR__ . '/validation.php';
// database.php chargé par le caller (index.php ou tests/bootstrap.php)
require_once __DIR__ . '/Router/Renderer.php';
require_once __DIR__ . '/Router/routes.php';
require_once __DIR__ . '/bootstrap_services.php';
require_once __DIR__ . '/queries/user_queries.php';
require_once __DIR__ . '/queries/report_queries.php';
require_once __DIR__ . '/queries/site_queries.php';
require_once __DIR__ . '/queries/stats_queries.php';
require_once __DIR__ . '/queries/report_agent_queries.php';
require_once __DIR__ . '/queries/report_invite_queries.php';
require_once __DIR__ . '/queries/report_count_queries.php';
require_once __DIR__ . '/queries/report_response_queries.php';
require_once __DIR__ . '/queries/rami_stats_queries.php';
require_once __DIR__ . '/queries/user_admin_queries.php';
require_once __DIR__ . '/queries/user_gdpr_queries.php';
