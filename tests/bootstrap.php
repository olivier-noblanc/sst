<?php
/**
 * PHPUnit Bootstrap — Application SST DREETS BFC
 *
 * Sets up an in-memory SQLite database for unit testing.
 * Loads the application's source files and seeds the test database.
 * Bootstraps the DI Container for service access in tests.
 */

// Ensure Composer autoloader is available (idempotent — safe if already loaded by phpunit)
require_once __DIR__ . '/../vendor/autoload.php';

// Define constants that config.php expects — only if not already defined.
// This avoids "Constant already defined" warnings when helpers.php loads config.php.
//
// NOTE: The values here may differ from config.php. That's intentional —
// tests use certain values for isolation. If config.php is loaded after us
// (it is, via helpers.php), the defined() guards prevent overwriting.

if (!defined('DEV_MODE')) define('DEV_MODE', true);
// APP_VERSION is no longer defined in config.php — version comes from CHANGELOG.md.
// Defined here as a safety net for test isolation (getAppVersion() reads the real changelog).
if (!defined('APP_VERSION')) define('APP_VERSION', '0.0.0');
define('CHANGELOG_PATH', __DIR__ . '/../CHANGELOG.md');

// Define config constants normally set by config.php
if (!defined('MAX_OBJECT_LENGTH')) define('MAX_OBJECT_LENGTH', 100);
if (!defined('MAX_DESCRIPTION_LENGTH')) define('MAX_DESCRIPTION_LENGTH', 5000);
if (!defined('MAX_LIEU_LENGTH')) define('MAX_LIEU_LENGTH', 200);
if (!defined('MAX_ATTACHMENT_SIZE')) define('MAX_ATTACHMENT_SIZE', 10485760);
if (!defined('ALLOWED_ATTACHMENT_MIMES')) define('ALLOWED_ATTACHMENT_MIMES', [
    'image/jpeg', 'image/png', 'image/gif', 'application/pdf',
]);
if (!defined('REGISTRY_TYPES')) define('REGISTRY_TYPES', array_map(fn($c) => $c->value, \App\Enum\ReportType::cases()));
// Registry labels are now loaded from DB via getRegistryLabel() / getRegistryShortLabel()
// Role labels
if (!defined('ROLE_LABELS')) define('ROLE_LABELS', array_combine(
    array_map(fn($c) => $c->value, \App\Enum\UserRole::cases()),
    array_map(fn($c) => $c->defaultLabel(), \App\Enum\UserRole::cases())
));

// Mock $_SERVER for CLI
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/../public';

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
        // Run the same table-creation migration production runs on every connection
        // (src/database.php getDB() -> migrateSchema() -> migrateTables()) — notably
        // CREATE VIRTUAL TABLE reports_fts. Without this, schema.sql alone never
        // creates reports_fts, and every FTS5 sync in ReportRepository silently
        // fails (caught + error_log'd), leaving full-text search completely
        // unexercised by the test suite.
        // migrateColumns()/migrateIndexes()/migrateConfigKeys() are intentionally
        // NOT run here: they depend on backupBeforeMigration() (file-based backup,
        // meaningless for an in-memory DB) and on config seeding that individual
        // tests already manage explicitly via config_app resets.
        require_once __DIR__ . '/../src/migration_tables.php';
        migrateTables($db);
    }
    return $db;
}

// Load application source files (PSR-4 autoloader + procedural files)
require_once __DIR__ . '/../src/autoload.php';
// registry_card_renderer.php is now loaded via src/helpers.php (require_once chain)
require_once __DIR__ . '/../src/mail/email_renderer.php';

// Bootstrap the DI Container for tests
$testContainer = getContainer();

/**
 * Restore the 3 default registries (rsst/rami/dgi) after a test that wiped
 * the `registries` table (e.g. `DELETE FROM registries` in setUp()).
 *
 * getDB() above is a process-wide singleton (one shared in-memory SQLite DB
 * for the whole PHPUnit run — no process isolation configured in
 * phpunit.xml). migrateTables() seeds these rows once, lazily, on the very
 * first getDB() call — but only "if the table is empty". Any test class
 * that deletes from `registries` without restoring it (ConfigServiceTest,
 * RegistryRepositoryTest, RegistryFieldRepositoryTest at the time of this
 * comment) leaves it empty for every test class that runs afterward in the
 * same process, for the rest of the suite.
 *
 * Concretely this caused PageRenderingTest::testAllLayoutPagesRenderValidHtml
 * to kill the whole PHP CLI process ("Premature end of PHP process"):
 * pages/report_list.php calls RegistryRepository::findByCode('rsst'), gets
 * null because the row is gone, and falls into
 * HttpService::redirect() -> exit — which terminates PHPUnit mid-run rather
 * than failing the single test.
 *
 * Call this from tearDown() in any test that deletes from `registries`.
 * Content kept in sync with the seed in src/migration_tables.php — not
 * reusing migrateTables() itself because its seed is conditional on
 * `COUNT(*) = 0`, which no longer holds once a test has inserted its own
 * rows into the table.
 */
function reseedDefaultRegistries(PDO $pdo): void
{
    $pdo->exec("DELETE FROM registries WHERE code IN ('rsst', 'rami', 'dgi')");
    $pdo->exec("INSERT INTO registries (code, label, short_label, description, icon, color_theme, is_enabled, is_system, sort_order, default_visibility, notify_chsct) VALUES
        ('rsst', 'Santé et Sécurité au Travail', 'RSST', 'Signalements généraux SST', '📋', 'rsst', 1, 1, 1, 'agent_choice', 0),
        ('rami', 'Agressions, Menaces et Incivilités', 'RAMI', 'Agressions verbales et physiques', '🚨', 'rami', 1, 0, 2, 'agent_choice', 0),
        ('dgi', 'Danger Grave et Imminent', 'DGI', 'Dangers immédiats pour la santé', '🔴', 'dgi', 1, 0, 3, 'agent_choice', 1)
    ");
}
