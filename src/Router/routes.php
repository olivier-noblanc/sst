<?php

/**
 * Route definitions — Application SST DREETS BFC
 *
 * Creates and configures the Router with all application routes.
 * Called once per request from public/index.php.
 *
 * Uses the DI Container for middleware instantiation.
 */
use App\Services\ConfigService;
use App\Enum\UserRole;
use App\Router\Router;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;

function createRouter(): Router
{
    $router = new Router();

    // ═══════════════════════════════════════════════════════════════════════════════
    // POST handlers
    // ═══════════════════════════════════════════════════════════════════════════════

    $handlers = __DIR__ . '/../../handlers';
    $router->addPostHandler('report_create', "$handlers/report_create_handler.php");
    $router->addPostHandler('report_edit', "$handlers/report_edit_handler.php");
    $router->addPostHandler('report_abandon', "$handlers/report_abandon_handler.php");
    $router->addPostHandler('report_respond', "$handlers/report_respond_handler.php");
    $router->addPostHandler('report_reopen', "$handlers/report_reopen_handler.php");
    $router->addPostHandler('export', "$handlers/export_handler.php");
    $router->addPostHandler('settings', "$handlers/settings_handler.php");
    $router->addPostHandler('site_edit', "$handlers/site_edit_handler.php");
    $router->addPostHandler('smtp_test', "$handlers/smtp_test_handler.php");
    $router->addPostHandler('user_edit', "$handlers/user_edit_handler.php");
    $router->addPostHandler('user_create', "$handlers/user_create_handler.php");
    $router->addPostHandler('user_delete', "$handlers/user_delete_handler.php");
    $router->addPostHandler('user_reactivate', "$handlers/user_reactivate_handler.php");
    $router->addPostHandler('impersonate', "$handlers/impersonate_handler.php");
    $router->addPostHandler('agent_confirm', "$handlers/agent_confirm_handler.php");

    // CSRF middleware for all POST handlers
    $csrf = new CsrfMiddleware();
    $allHandlers = array_keys($router->getHandlerMap());
    foreach ($allHandlers as $name) {
        $router->setPostMiddleware($name, [$csrf]);
    }

    // Role-based middlewares (override CSRF-only for specific handlers)
    $superviseur = new RoleMiddleware([UserRole::Superviseur->value]);
    $router->setPostMiddleware('export', [$csrf, $superviseur]);
    $router->setPostMiddleware('settings', [$csrf, $superviseur]);
    $router->setPostMiddleware('site_edit', [$csrf, $superviseur]);
    $router->setPostMiddleware('smtp_test', [$csrf, $superviseur]);
    $router->setPostMiddleware('user_edit', [$csrf, $superviseur]);
    $router->setPostMiddleware('user_create', [$csrf, $superviseur]);
    $router->setPostMiddleware('user_delete', [$csrf, $superviseur]);
    $router->setPostMiddleware('user_reactivate', [$csrf, $superviseur]);
    $router->setPostMiddleware('report_respond', [$csrf, $superviseur]);
    $router->setPostMiddleware('report_reopen', [$csrf, $superviseur]);

    // ═══════════════════════════════════════════════════════════════════════════════
    // GET pages (with standard layout)
    // ═══════════════════════════════════════════════════════════════════════════════

    $layout = (fn(string $page): callable => function () use ($page, $router) {
        global $csrfToken;
        renderPageWithLayout($router, $page, $csrfToken ?? '');
    });

    $router->addRoute('home', 'home', ['GET'], $layout('home'));
    $router->addRoute('preamble', 'preamble', ['GET'], $layout('preamble'));
    $router->addRoute('help', 'help', ['GET'], $layout('help'));
    $router->addRoute('guide', 'guide', ['GET'], $layout('guide'));
    $router->addRoute('changelog', 'changelog', ['GET'], $layout('changelog'));
    $router->addRoute('access_denied', 'access_denied', ['GET'], $layout('access_denied'));
    $router->addRoute('report_list', 'report_list', ['GET'], $layout('report_list'));
    $router->addRoute('report_view', 'report_view', ['GET'], $layout('report_view'));
    $router->addRoute('report_abandon', 'report_abandon', ['GET'], $layout('report_abandon'));
    $router->addRoute('report_reopen', 'report_reopen', ['GET'], $layout('report_reopen'));
    $router->addRoute('synthesis', 'synthesis', ['GET'], $layout('synthesis'));
    $router->addRoute('statistics', 'statistics', ['GET'], $layout('statistics'));
    $router->addRoute('users', 'users', ['GET'], $layout('users'));
    $router->addRoute('user_view', 'user_view', ['GET'], $layout('user_view'));
    $router->addRoute('logs', 'logs', ['GET'], $layout('logs'));
    $router->addRoute('logout', 'logout', ['GET'], function () { /* handled in index.php */
    });

    // GET + POST pages
    $router->addRoute('choose_site', 'choose_site', ['GET', 'POST'], $layout('choose_site'));
    $router->addRoute('report_create', 'report_create', ['GET', 'POST'], $layout('report_create'));
    $router->addRoute('report_edit', 'report_edit', ['GET', 'POST'], $layout('report_edit'));
    $router->addRoute('report_respond', 'report_respond', ['GET', 'POST'], $layout('report_respond'));
    $router->addRoute('agent_confirm', 'agent_confirm', ['GET', 'POST'], $layout('agent_confirm'));
    $router->addRoute('export', 'export', ['GET', 'POST'], $layout('export'));
    $router->addRoute('settings', 'settings', ['GET', 'POST'], $layout('settings'));
    $router->addRoute('site_edit', 'site_edit', ['GET', 'POST'], $layout('site_edit'));
    $router->addRoute('user_edit', 'user_edit', ['GET', 'POST'], $layout('user_edit'));
    $router->addRoute('impersonate', 'impersonate', ['GET', 'POST'], $layout('impersonate'));

    // Standalone pages (no layout)
    $router->addRoute('report_print', 'report_print', ['GET'], function () {
        renderStandalonePage(__DIR__ . '/../../pages/report_print.php');
    });
    $router->addRoute('report_attachment', 'report_attachment', ['GET'], function () {
        renderStandalonePage(__DIR__ . '/../../pages/report_attachment.php');
    });
    $router->addRoute('response_attachment', 'response_attachment', ['GET'], function () {
        renderStandalonePage(__DIR__ . '/../../pages/response_attachment.php');
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // Page titles
    // ═══════════════════════════════════════════════════════════════════════════════

    $router->setPageTitle('home', 'Accueil');
    $router->setPageTitle('preamble', 'Préambule');
    $router->setPageTitle('help', 'Documentation');
    $router->setPageTitle('guide', 'Guide rapide — Comment signaler');
    $router->setPageTitle('changelog', 'Historique des modifications');
    $router->setPageTitle('choose_site', 'Choisir mon site');
    $router->setPageTitle('report_create', getConfigService()->get('app_report_create_label', 'Signaler un événement'));
    $router->setPageTitle('report_list', 'Liste des fiches');
    $router->setPageTitle('report_view', 'Signalement');
    $router->setPageTitle('report_edit', 'Modifier le signalement');
    $router->setPageTitle('report_abandon', 'Abandonner le signalement');
    $router->setPageTitle('report_respond', 'Répondre au signalement');
    $router->setPageTitle('report_reopen', 'Réouvrir le signalement');
    $router->setPageTitle('synthesis', 'Synthèse des signalements');
    $router->setPageTitle('export', 'Export des données');
    $router->setPageTitle('statistics', 'Statistiques');
    $router->setPageTitle('settings', 'Paramètres — Notifications');
    $router->setPageTitle('users', 'Gestion des utilisateurs');
    $router->setPageTitle('user_edit', 'Éditer l\'utilisateur');
    $router->setPageTitle('user_view', 'Profil utilisateur');
    $router->setPageTitle('site_edit', 'Éditer le site');
    $router->setPageTitle('access_denied', 'Accès refusé');
    $router->setPageTitle('user_create', 'Créer un utilisateur');
    $router->setPageTitle('logs', 'Journal');
    $router->setPageTitle('logout', 'Déconnexion');
    $router->setPageTitle('report_print', 'Impression');
    $router->setPageTitle('report_attachment', 'Pièce jointe');
    $router->setPageTitle('response_attachment', 'Pièce jointe');

    return $router;
}

/**
 * Get the singleton Router instance.
 */
function getRouter(): Router
{
    static $router = null;
    if ($router === null) {
        $router = createRouter();
    }
    return $router;
}
