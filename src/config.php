<?php

/**
 * Configuration — Application SST DREETS BFC
 *
 * Central configuration file for all application constants.
 */

date_default_timezone_set('Europe/Paris');

// Application metadata
define('APP_NAME', 'Application SST — DREETS BFC');
// Version: the source of truth is CHANGELOG.md, read by getAppVersion().
// There is NO APP_VERSION constant — if the changelog is unreadable,
// getAppVersion() returns '0.0.0' so the problem is visible, not hidden.
// To change the displayed version, add a new entry at the top of CHANGELOG.md.
define('SITE_NAME', 'DREETS Bourgogne-Franche-Comté');

// Environment configuration:
// - 'prod' : IIS Windows Authentication (AUTH_USER). No login form.
// - 'dev'  : Mock login form for local development.
//
// Detection priority:
//   1. APP_ENV constant (if you hardcode it below)
//   2. APP_ENV environment variable (e.g. SetEnv in Apache/IIS config)
//   3. Auto-detection: if AUTH_USER is available → prod, otherwise → dev
//
// IMPORTANT: On non-IIS servers (Apache, Caddy, Space-Z, Docker, etc.),
// AUTH_USER will NOT be set. The app will auto-detect dev mode and show
// the login form. This is correct and expected.
// To force prod mode, uncomment and set the line below:
// define('APP_ENV_FORCE', 'prod');

if (defined('APP_ENV_FORCE')) {
    define('APP_ENV', APP_ENV_FORCE);
} elseif (getenv('APP_ENV') !== false) {
    define('APP_ENV', getenv('APP_ENV'));
} else {
    // Auto-detect: if IIS provides AUTH_USER, we're in prod; otherwise dev
    // This handles non-IIS deployments gracefully (Space-Z, Docker, Apache, etc.)
    $hasAuthUser = !empty($_SERVER['AUTH_USER']);
    define('APP_ENV', $hasAuthUser ? 'prod' : 'dev');
}

if (!defined('DEV_MODE')) {
    define('DEV_MODE', APP_ENV === 'dev');
}

// Error handling: environment-dependent
// DEV : errors displayed on screen for immediate diagnosis.
// PROD: errors are logged and emailed (see error_handler.php),
//       but NOT displayed to users — they see a clean error page instead.
ini_set('display_errors', DEV_MODE ? '1' : '0');
ini_set('display_startup_errors', DEV_MODE ? '1' : '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../data/php-error.log');

// Database
define('DB_PATH', __DIR__ . '/../data/sst.db');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Field constraints
if (!defined('MAX_OBJECT_LENGTH')) {
    define('MAX_OBJECT_LENGTH', 100);
}
if (!defined('MAX_DESCRIPTION_LENGTH')) {
    define('MAX_DESCRIPTION_LENGTH', 20000);
}
if (!defined('MAX_LIEU_LENGTH')) {
    define('MAX_LIEU_LENGTH', 200);
}

// Attachment constraints
if (!defined('MAX_ATTACHMENT_SIZE')) {
    define('MAX_ATTACHMENT_SIZE', 10 * 1024 * 1024);
} // 10 MB
if (!defined('ALLOWED_ATTACHMENT_MIMES')) {
    define('ALLOWED_ATTACHMENT_MIMES', ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);
}

// No LDAP needed — IIS Windows Auth provides $_SERVER['AUTH_USER']
// Format: "DOMAIN\username" — PHP extracts the username and strips the domain.

// Superviseur auto-promotion (bootstrap, première installation uniquement) :
//   1. Settings UI (Paramètres → Application) — stocké en base, survit aux git pulls
//   2. Env var APP_SUPERVISEUR_USERNAMES (backup) — si la DB n'a pas de liste
// La DB est prioritaire. Après la promotion initiale, vider la liste pour la sécurité.

// Roles — aliases on UserRole enum for backward compatibility
define('ROLE_AGENT', \App\Enum\UserRole::Agent->value);
define('ROLE_SUPERVISEUR', \App\Enum\UserRole::Superviseur->value);
define('ROLE_CHSCT', \App\Enum\UserRole::Chsct->value);

// Report states (etats) — aliases on ReportState enum for backward compatibility
define('ETAT_NOUVEAU', \App\Enum\ReportState::Nouveau->value);
define('ETAT_EN_COURS', \App\Enum\ReportState::EnCours->value);
define('ETAT_TRAITE', \App\Enum\ReportState::Traite->value);
define('ETAT_ABANDONNE', \App\Enum\ReportState::Abandonne->value);
define('ETAT_REOUVERT', \App\Enum\ReportState::Reouvert->value);

// Registry types — aliases on ReportType enum for backward compatibility
define('TYPE_RSST', \App\Enum\ReportType::Rsst->value);
define('TYPE_RAMI', \App\Enum\ReportType::Rami->value);
define('TYPE_DGI', \App\Enum\ReportType::Dgi->value);

// Registry type labels — derived from ReportType enum
if (!defined('REGISTRY_LABELS')) {
    define('REGISTRY_LABELS', array_combine(
        array_map(fn($c) => $c->value, \App\Enum\ReportType::cases()),
        array_map(fn($c) => $c->label(), \App\Enum\ReportType::cases())
    ));
}

if (!defined('REGISTRY_SHORT_LABELS')) {
    define('REGISTRY_SHORT_LABELS', array_combine(
        array_map(fn($c) => $c->value, \App\Enum\ReportType::cases()),
        array_map(fn($c) => $c->shortLabel(), \App\Enum\ReportType::cases())
    ));
}

// Role labels — defaults derived from UserRole enum (overridden by DB config app_role_label_*)
define('ROLE_LABELS_DEFAULT', array_combine(
    array_map(fn($c) => $c->value, \App\Enum\UserRole::cases()),
    array_map(fn($c) => $c->defaultLabel(), \App\Enum\UserRole::cases())
));
// ROLE_LABELS is the runtime constant used throughout the application.
// It mirrors ROLE_LABELS_DEFAULT; custom labels from DB are resolved
// at display time via getRoleLabel() / getRoleLabels().
if (!defined('ROLE_LABELS')) {
    define('ROLE_LABELS', ROLE_LABELS_DEFAULT);
}

// Report visibility modes (admin-configurable in Settings → Application)
// 'confidential'  : Agent sees ONLY their own reports — most restrictive
// 'agent_choice'  : Agent chooses per-report (public/confidential), defaulting to confidential
// 'public'        : All reports visible to all agents in the site
define('REPORT_VISIBILITY_MODES', [
    'confidential' => 'Confidentiel (l\'agent ne voit que ses signalements)',
    'agent_choice' => 'Choix de l\'agent (confidentiel par défaut)',
    'public'       => 'Visibilité publique de tous les signalements',
]);

// State labels — derived from ReportState enum
if (!defined('ETAT_LABELS')) {
    define('ETAT_LABELS', array_combine(
        array_map(fn($c) => $c->value, \App\Enum\ReportState::cases()),
        array_map(fn($c) => $c->label(), \App\Enum\ReportState::cases())
    ));
}

// Registry toggle defaults (overridden by DB config app_registry_*_enabled)
// RSST is always active. RAMI and DGI are disabled by default —
// the supervisor can enable them in Settings > Application.
define('REGISTRY_RAMI_ENABLED_DEFAULT', false);
define('REGISTRY_DGI_ENABLED_DEFAULT', false);

// RAMI structured field labels (shared by statistics, export, and validation)
if (!defined('RAMI_NATURE_AUTEUR_LABELS')) {
    define('RAMI_NATURE_AUTEUR_LABELS', [
        'usager'    => 'Usager',
        'collegue'  => 'Collègue',
        'hierarchie' => 'Hiérarchie',
        'tiers'     => 'Tiers',
    ]);
}

if (!defined('RAMI_TYPE_ACTE_LABELS')) {
    define('RAMI_TYPE_ACTE_LABELS', [
        'verbal'  => 'Verbal',
        'physique' => 'Physique',
        'moral'   => 'Moral',
        'sexiste' => 'Sexiste',
        'autre'   => 'Autre',
    ]);
}
