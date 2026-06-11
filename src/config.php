<?php
/**
 * Configuration — Application SST DREETS BFC
 * 
 * Central configuration file for all application constants.
 */

// Application metadata
define('APP_NAME', 'Application SST — DREETS BFC');
define('APP_VERSION', '2.6.1');
define('SITE_NAME', 'DREETS Bourgogne-Franche-Comté');

// Environment: set to 'dev' for mock auth, 'prod' for IIS Windows Auth
define('APP_ENV', getenv('APP_ENV') ?: 'prod');
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
define('MAX_DESCRIPTION_LENGTH', 5000);
define('MAX_LIEU_LENGTH', 200);

// No LDAP needed — IIS Windows Auth provides $_SERVER['AUTH_USER']
// Format: "DOMAIN\username" — PHP extracts the username and strips the domain.

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
