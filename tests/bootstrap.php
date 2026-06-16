<?php
/**
 * PHPUnit Bootstrap — Application SST DREETS BFC
 *
 * Sets up an in-memory SQLite database for unit testing.
 * Loads the application's source files and seeds the test database.
 */

// Define constants that config.php expects
define('DEV_MODE', true);
// APP_VERSION is no longer defined in config.php — version comes from CHANGELOG.md.
// Defined here as a safety net for test isolation (getAppVersion() reads the real changelog).
define('APP_VERSION', '0.0.0');
define('CHANGELOG_PATH', __DIR__ . '/../CHANGELOG.md');

// Define config constants normally set by config.php
define('MAX_OBJECT_LENGTH', 200);
define('MAX_DESCRIPTION_LENGTH', 5000);
define('MAX_LIEU_LENGTH', 200);
define('MAX_ATTACHMENT_SIZE', 10485760);
define('ALLOWED_ATTACHMENT_MIMES', [
    'image/jpeg', 'image/png', 'image/gif', 'application/pdf',
]);
define('REGISTRY_TYPES', ['rsst', 'rami', 'dgi']);
define('REGISTRY_SHORT_LABELS', [
    'rsst' => 'RSST',
    'rami' => 'RAMI',
    'dgi'  => 'DGI',
]);
define('ETAT_LABELS', [
    'nouveau'    => 'Nouveau',
    'en_cours'   => 'En cours',
    'traite'     => 'Traité',
    'abandonne'  => 'Abandonné',
]);
define('ROLE_LABELS', [
    'agent'       => 'Agent',
    'superviseur' => 'Superviseur',
    'chsct'       => 'CSA/CHSCT',
]);
define('RAMI_NATURE_AUTEUR_LABELS', [
    'usager'    => 'Usager',
    'collegue'  => 'Collègue',
    'hierarchie'=> 'Hiérarchie',
    'tiers'     => 'Tiers',
]);
define('RAMI_TYPE_ACTE_LABELS', [
    'verbal'  => 'Verbal',
    'physique'=> 'Physique',
    'moral'   => 'Moral',
    'sexiste' => 'Sexiste',
    'autre'   => 'Autre',
]);

// Mock $_SERVER for CLI
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/../public';

// Create in-memory SQLite database
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

// Load schema
$schema = file_get_contents(__DIR__ . '/../schema.sql');
$pdo->exec($schema);

// Override getDB() to return our test PDO
function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys = ON');
        $schema = file_get_contents(__DIR__ . '/../schema.sql');
        $db->exec($schema);
    }
    return $db;
}

// Load application source files
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/queries/user_queries.php';
require_once __DIR__ . '/../src/queries/site_queries.php';
require_once __DIR__ . '/../src/queries/report_queries.php';
require_once __DIR__ . '/../src/user_context.php';
