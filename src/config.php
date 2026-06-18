<?php

/**
 * Configuration — Application SST DREETS BFC
 *
 * Central configuration file for all application constants.
 */

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
} elseif (getenv('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV'));
} else {
    // Auto-detect: if IIS provides AUTH_USER, we're in prod; otherwise dev
    // This handles non-IIS deployments gracefully (Space-Z, Docker, Apache, etc.)
    $hasAuthUser = !empty($_SERVER['AUTH_USER']);
    define('APP_ENV', $hasAuthUser ? 'prod' : 'dev');
}

define('DEV_MODE', APP_ENV === 'dev');

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

// Roles
define('ROLE_AGENT', 'agent');
define('ROLE_SUPERVISEUR', 'superviseur');
define('ROLE_CHSCT', 'chsct');

// Report states (etats)
define('ETAT_NOUVEAU', 'nouveau');
define('ETAT_EN_COURS', 'en_cours');
define('ETAT_TRAITE', 'traite');
define('ETAT_ABANDONNE', 'abandonne');
define('ETAT_REOUVERT', 'reouvert');

// Registry types
define('TYPE_RSST', 'rsst');
define('TYPE_RAMI', 'rami');
define('TYPE_DGI', 'dgi');

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

// Role labels — defaults (overridden by DB config app_role_label_*)
define('ROLE_LABELS_DEFAULT', [
    'agent'       => 'Agent',
    'superviseur' => 'Superviseur',
    'chsct'       => 'Membre FS/CSA',
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
    'reouvert'   => 'Réouvert',
]);

// Registry toggle defaults (overridden by DB config app_registry_*_enabled)
// RSST is always active. RAMI and DGI are disabled by default —
// the supervisor can enable them in Settings > Application.
define('REGISTRY_RAMI_ENABLED_DEFAULT', false);
define('REGISTRY_DGI_ENABLED_DEFAULT', false);

// RAMI structured field labels (shared by statistics, export, and validation)
define('RAMI_NATURE_AUTEUR_LABELS', [
    'usager'    => 'Usager',
    'collegue'  => 'Collègue',
    'hierarchie' => 'Hiérarchie',
    'tiers'     => 'Tiers',
]);

define('RAMI_TYPE_ACTE_LABELS', [
    'verbal'  => 'Verbal',
    'physique' => 'Physique',
    'moral'   => 'Moral',
    'sexiste' => 'Sexiste',
    'autre'   => 'Autre',
]);
