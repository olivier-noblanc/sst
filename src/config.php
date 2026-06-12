<?php
/**
 * Configuration — Application SST DREETS BFC
 * 
 * Central configuration file for all application constants.
 */

// Application metadata
define('APP_NAME', 'Application SST — DREETS BFC');
define('APP_VERSION', '3.2.1');
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
} elseif (getenv('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV'));
} else {
    // Auto-detect: if IIS provides AUTH_USER, we're in prod; otherwise dev
    // This handles non-IIS deployments gracefully (Space-Z, Docker, Apache, etc.)
    $hasAuthUser = !empty($_SERVER['AUTH_USER']);
    define('APP_ENV', $hasAuthUser ? 'prod' : 'dev');
}

define('DEV_MODE', APP_ENV === 'dev');

// Error handling: ALWAYS display errors (even in production)
// This is intentional: the app must always show PHP errors on screen
// for immediate diagnosis, even on IIS in production.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// Also log errors to file
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../data/php-error.log');

// Database
define('DB_PATH', __DIR__ . '/../data/sst.db');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Field constraints
define('MAX_OBJECT_LENGTH', 100);
define('MAX_DESCRIPTION_LENGTH', 20000);
define('MAX_LIEU_LENGTH', 200);

// Attachment constraints
define('MAX_ATTACHMENT_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_ATTACHMENT_MIMES', ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);

// No LDAP needed — IIS Windows Auth provides $_SERVER['AUTH_USER']
// Format: "DOMAIN\username" — PHP extracts the username and strips the domain.

// Superviseur auto-promotion (bootstrap, première installation uniquement) :
//   1. Settings UI (Paramètres → Application) — stocké en base, survit aux git pulls
//   2. Env var APP_SUPERVISEUR_USERNAMES (backup) — si la DB n'a pas de liste
// La DB est prioritaire. Après la promotion initiale, vider la liste pour la sécurité.

// Registry type labels
define('REGISTRY_LABELS', [
    'rsst' => 'Registre de Santé et de Sécurité au Travail',
    'rami' => 'Registre des Actes d\'Agressions, de Menaces et d\'Incivilités',
    'dgi'  => 'Registre de signalement d\'un Danger Grave et Imminent',
]);

define('REGISTRY_SHORT_LABELS', [
    'rsst' => 'RSST',
    'rami' => 'RAMI',
    'dgi'  => 'DGI',
]);

// Role labels
define('ROLE_LABELS', [
    'agent'       => 'Agent',
    'superviseur' => 'Superviseur',
    'chsct'       => 'Membre CHSCT',
]);

// Report visibility modes (admin-configurable in Settings → Application)
// 'confidential'  : Agent sees ONLY their own reports — most restrictive
// 'agent_choice'  : Agent chooses per-report (public/confidential), defaulting to confidential
// 'public'        : All reports visible to all agents in the site
define('REPORT_VISIBILITY_MODES', [
    'confidential' => 'Confidentiel (l\'agent ne voit que ses signalements)',
    'agent_choice' => 'Choix de l\'agent (confidentiel par défaut)',
    'public'       => 'Visibilité publique de tous les signalements',
]);

// State labels
define('ETAT_LABELS', [
    'nouveau'    => 'Nouveau',
    'en_cours'   => 'En cours',
    'traite'     => 'Traité',
    'abandonne'  => 'Abandonné',
]);
