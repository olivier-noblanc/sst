<?php

use App\Repository\RegistryRepository;
use App\Repository\RegistryFieldRepository;
use App\Enum\ReportType;

/**
 * Database — Application SST DREETS BFC
 *
 * Provides a PDO singleton connection to the SQLite database.
 * Initializes the schema on first run.
 * Migrations are delegated to dedicated files:
 *   - migration_tables.php   (CREATE TABLE IF NOT EXISTS)
 *   - migration_columns.php  (ALTER TABLE ADD COLUMN)
 *   - migration_indexes.php  (CREATE INDEX IF NOT EXISTS)
 *   - migration_config.php   (INSERT missing config keys, encrypt smtp_pass)
 */

/**
 * Get the PDO database connection (singleton).
 * On first call, creates the database and schema if they don't exist.
 *
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // Ensure the data directory exists
    $dir = dirname((string) DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $isNew = !file_exists(DB_PATH);

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Enable foreign keys and WAL mode
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');

    // Initialize schema if this is a new database
    if ($isNew) {
        $schemaFile = __DIR__ . '/../schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            if (is_string($sql)) {
                $pdo->exec($sql);
            }
        }

        // Seed the sites table with default data
        seedDefaultData($pdo);
    }

    // Auto-backup: create a snapshot if the DB has changed since last backup.
    // Lazy check — skips if fingerprint unchanged (zero I/O waste).
    // Must run BEFORE migrations so we have a clean pre-migration state.
    require_once __DIR__ . '/backup.php';
    try {
        performBackup($pdo);
    } catch (Exception $e) {
        // @silent-ok: Backup failure must NOT block the application
        error_log('[SST-BACKUP] Auto-backup failed: ' . $e->getMessage());
    }

    // Auto-migrate: ensure schema is up-to-date in existing databases
    $fingerprintBefore = getDbFingerprint($pdo);
    migrateSchema($pdo);
    $fingerprintAfter = getDbFingerprint($pdo);
    // If migrations changed the DB, ensure we have a pre-migration backup
    if ($fingerprintBefore['mtime'] !== $fingerprintAfter['mtime'] || $fingerprintBefore['size'] !== $fingerprintAfter['size']) {
        // Migration changed something — the pre-migration backup was already created
        // by performBackup() above. Update the marker so next backup detects further changes.
        setLastBackupFingerprint($fingerprintAfter);
    }

    return $pdo;
}

/**
 * Seed the database with default sites and dev users.
 *
 * @param PDO $pdo
 */
function seedDefaultData(PDO $pdo): void
{
    // Sites — DREETS BFC Unités Régionales
    // Only UR21 and UR25 by default — more can be added via Settings
    $sites = [
        ['UR21', 'UR Côte-d\'Or', 'Côte-d\'Or'],
        ['UR25', 'UR Doubs', 'Doubs'],
    ];

    $stmt = $pdo->prepare('INSERT INTO sites (code, nom, departement) VALUES (:code, :nom, :departement)');
    foreach ($sites as $site) {
        $stmt->execute([
            ':code'        => $site[0],
            ':nom'         => $site[1],
            ':departement' => $site[2],
        ]);
    }

    // Default dev users
    // site_id: 1 = UR21, 2 = UR25 (from the sites seeded above)
    $users = [
        ['admin.dev', 'Administrateur', 'Dev', 'admin.dev@dreets.gouv.fr', 'superviseur', 1],
        ['agent.dev', 'Dupont', 'Jean', 'agent.dev@dreets.gouv.fr', 'agent', null],
        ['chsct.dev', 'Bernard', 'Pierre', 'chsct.dev@dreets.gouv.fr', 'chsct', 2],
    ];

    $stmt = $pdo->prepare('INSERT INTO users (username, nom, prenom, email, role, site_id) VALUES (:username, :nom, :prenom, :email, :role, :site_id)');
    foreach ($users as $user) {
        $stmt->execute([
            ':username' => $user[0],
            ':nom'      => $user[1],
            ':prenom'   => $user[2],
            ':email'    => $user[3],
            ':role'     => $user[4],
            ':site_id'  => $user[5],
        ]);
    }

    // Modular-audit P1.5 — Seed default registries (RSST, RAMI, DGI) and their
    // RAMI-specific registry_fields (pour_compte, pour_compte_nom, pour_compte_prenom,
    // nature_auteur, type_acte). Before this fix, only seed.php (CLI) called
    // RegistryRepository::seedDefaults(). E2E tests in CI create a fresh DB via
    // getDB() → seedDefaultData() but never ran seed.php → registries and
    // registry_fields were empty → forms.spec.js, registres.spec.js, reports.spec.js
    // all failed (RAMI fields not visible, registre labels not found).
    require_once __DIR__ . '/Repository/RegistryRepository.php';
    require_once __DIR__ . '/Repository/RegistryFieldRepository.php';
    require_once __DIR__ . '/Enum/ReportType.php';
    RegistryRepository::instance()->seedDefaults();

    // Seed RAMI-specific fields (matches seed/_registries.php for CLI seed)
    // Modular-audit P1.5 — use ReportType enum value instead of magic string
    $rami = RegistryRepository::instance()->findByCode(ReportType::Rami->value);
    if ($rami !== null) {
        $fieldRepo = RegistryFieldRepository::instance();
        $ramiFields = [
            ['pour_compte', 'Signaler pour le compte d\'un autre agent', 'checkbox', null, 0],
            ['pour_compte_nom', 'Nom de l\'agent pour le compte de qui vous signalez', 'text', null, 3],
            ['pour_compte_prenom', 'Prénom de l\'agent pour le compte de qui vous signalez', 'text', null, 4],
            ['nature_auteur', 'Nature de l\'auteur', 'select', json_encode([
                'usager' => 'Usager', 'collegue' => 'Collègue',
                'hierarchie' => 'Hiérarchie', 'tiers' => 'Tiers',
            ]), 1],
            ['type_acte', 'Type d\'acte', 'select', json_encode([
                'verbal' => 'Verbal', 'physique' => 'Physique',
                'moral' => 'Moral', 'sexiste' => 'Sexiste', 'autre' => 'Autre',
            ]), 2],
        ];
        foreach ($ramiFields as [$code, $label, $type, $options, $sortOrder]) {
            if ($fieldRepo->findByCode((int) $rami['id'], $code) === null) {
                $fieldRepo->create((int) $rami['id'], [
                    'field_code' => $code,
                    'label' => $label,
                    'field_type' => $type,
                    'options' => $options,
                    'is_required' => 0,
                    'sort_order' => $sortOrder,
                ]);
            }
        }
    }
}

/**
 * Run all schema migrations: tables, columns, indexes, config keys.
 * Delegates to dedicated migration files.
 *
 * @param PDO $pdo
 */
function migrateSchema(PDO $pdo): void
{
    require_once __DIR__ . '/migration_tables.php';
    require_once __DIR__ . '/migration_columns.php';
    require_once __DIR__ . '/migration_indexes.php';
    require_once __DIR__ . '/migration_config.php';

    migrateTables($pdo);
    migrateColumns($pdo);
    migrateIndexes($pdo);
    migrateConfigKeys($pdo);
    migrateEncryptSmtpPass($pdo);
}
