<?php
/**
 * PHPStan Bootstrap — declare global functions and constants
 * that are defined at runtime via require_once in index.php.
 *
 * PHPStan analyses files in isolation, so functions loaded by
 * index.php's bootstrap are unknown. This file bridges the gap.
 */

// Constants normally defined in config.php
if (!defined('APP_NAME'))       define('APP_NAME', 'App SST');
if (!defined('DEV_MODE'))       define('DEV_MODE', true);
if (!defined('DB_PATH'))        define('DB_PATH', __DIR__ . '/data/sst.sqlite');
if (!defined('TYPE_RSST'))      define('TYPE_RSST', 'rsst');
if (!defined('TYPE_RAMI'))      define('TYPE_RAMI', 'rami');
if (!defined('TYPE_DGI'))       define('TYPE_DGI', 'dgi');
if (!defined('ROLE_AGENT'))     define('ROLE_AGENT', 'agent');
if (!defined('ROLE_SUPERVISEUR')) define('ROLE_SUPERVISEUR', 'superviseur');
if (!defined('ROLE_CHSCT'))     define('ROLE_CHSCT', 'chsct');

// Role labels
if (!defined('ROLE_LABELS')) {
    define('ROLE_LABELS', [
        'agent' => 'Agent',
        'superviseur' => 'Superviseur',
        'chsct' => 'CSA/CHSCT',
    ]);
}

if (!defined('REGISTRY_SHORT_LABELS')) {
    define('REGISTRY_SHORT_LABELS', [
        'rsst' => 'RSST',
        'rami' => 'RAMI',
        'dgi' => 'DGI',
    ]);
}

if (!defined('REGISTRY_LABELS')) {
    define('REGISTRY_LABELS', [
        'rsst' => 'Registre RSST',
        'rami' => 'Registre RAMI',
        'dgi' => 'Registre DGI',
    ]);
}

if (!defined('ETAT_LABELS')) {
    define('ETAT_LABELS', [
        'nouveau' => 'Nouveau',
        'en_cours' => 'En cours',
        'traite' => 'Traité',
        'abandonne' => 'Abandonné',
    ]);
}

// Load all src/ files so PHPStan sees the functions
$srcDir = __DIR__ . '/src/';
foreach (glob($srcDir . '*.php') as $file) {
    require_once $file;
}
$queriesDir = $srcDir . 'queries/';
if (is_dir($queriesDir)) {
    foreach (glob($queriesDir . '*.php') as $file) {
        require_once $file;
    }
}
$middlewareDir = $srcDir . 'middleware/';
if (is_dir($middlewareDir)) {
    foreach (glob($middlewareDir . '*.php') as $file) {
        require_once $file;
    }
}
