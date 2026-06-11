<?php
/**
 * Database — Application SST DREETS BFC
 * 
 * Provides a PDO singleton connection to the SQLite database.
 * Initializes the schema on first run.
 */

/**
 * Get the PDO database connection (singleton).
 * On first call, creates the database and schema if they don't exist.
 * 
 * @return PDO
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // Ensure the data directory exists
    $dir = dirname(DB_PATH);
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
            $pdo->exec($sql);
        }

        // Seed the sites table with default data
        seedDefaultData($pdo);
    }

    // Auto-migrate: ensure config_app table and new keys exist in existing databases
    migrateSchema($pdo);
    migrateConfigKeys($pdo);

    return $pdo;
}

/**
 * Seed the database with default sites and dev users.
 * 
 * @param PDO $pdo
 */
function seedDefaultData(PDO $pdo): void {
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
}

/**
 * Auto-migrate: create missing tables in existing databases.
 * This handles databases created before a table was added to the schema.
 * Uses CREATE TABLE IF NOT EXISTS so it's safe to run on every request.
 * 
 * @param PDO $pdo
 */
function migrateSchema(PDO $pdo): void {
    // List of tables that might be missing from older databases.
    // CREATE TABLE IF NOT EXISTS is safe — no-op if table already exists.
    $migrations = [
        'config_app' => 'CREATE TABLE IF NOT EXISTS config_app (
            cle TEXT PRIMARY KEY,
            valeur TEXT,
            type TEXT DEFAULT \'text\',
            categorie TEXT DEFAULT \'app\',
            libelle TEXT,
            modifiable INTEGER DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )',
        'report_sequence' => 'CREATE TABLE IF NOT EXISTS report_sequence (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            year INTEGER NOT NULL,
            last_sequence INTEGER NOT NULL DEFAULT 0,
            UNIQUE(type, year)
        )',
        'notification_settings' => 'CREATE TABLE IF NOT EXISTS notification_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER,
            type TEXT NOT NULL,
            registry TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            FOREIGN KEY (site_id) REFERENCES sites(id)
        )',
        'report_responses' => 'CREATE TABLE IF NOT EXISTS report_responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            reponse TEXT NOT NULL,
            nouvel_etat TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )',
    ];

    foreach ($migrations as $table => $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            error_log("Migration warning for table $table: " . $e->getMessage());
        }
    }

    // === Column migrations (SQLite cannot ALTER COLUMN, so we check pragmas) ===

    // Add is_confidential column to reports table
    try {
        $cols = $pdo->query("PRAGMA table_info(reports)")->fetchAll();
        $hasConfidential = false;
        foreach ($cols as $col) {
            if ($col['name'] === 'is_confidential') {
                $hasConfidential = true;
                break;
            }
        }
        if (!$hasConfidential) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN is_confidential INTEGER NOT NULL DEFAULT 1');
            // Migrate existing reports: if app_agent_visibility was 'site',
            // existing reports were public → set them to is_confidential = 0
            $vis = getConfig('app_agent_visibility', 'confidential');
            if ($vis === 'site') {
                $pdo->exec('UPDATE reports SET is_confidential = 0');
            }
            // Add index
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_is_confidential ON reports(is_confidential)');
        }
    } catch (Exception $e) {
        error_log("Migration warning for reports.is_confidential: " . $e->getMessage());
    }

    // Make users.site_id nullable for existing databases
    try {
        $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll();
        foreach ($cols as $col) {
            if ($col['name'] === 'site_id' && $col['notnull'] === 1) {
                // SQLite doesn't support ALTER COLUMN — we recreate the table
                $pdo->exec('CREATE TABLE users_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    nom TEXT NOT NULL,
                    prenom TEXT NOT NULL,
                    email TEXT,
                    role TEXT NOT NULL DEFAULT \'agent\',
                    site_id INTEGER,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                    updated_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                    FOREIGN KEY (site_id) REFERENCES sites(id)
                )');
                $pdo->exec('INSERT INTO users_new SELECT * FROM users');
                $pdo->exec('DROP TABLE users');
                $pdo->exec('ALTER TABLE users_new RENAME TO users');
                break;
            }
        }
    } catch (Exception $e) {
        error_log("Migration warning for users.site_id nullable: " . $e->getMessage());
    }

    // Add uuid column to reports table (for non-guessable URLs)
    try {
        $cols = $pdo->query("PRAGMA table_info(reports)")->fetchAll();
        $hasUuid = false;
        foreach ($cols as $col) {
            if ($col['name'] === 'uuid') {
                $hasUuid = true;
                break;
            }
        }
        if (!$hasUuid) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN uuid TEXT');
            // Backfill existing reports with UUIDs
            $stmt = $pdo->query('SELECT id FROM reports WHERE uuid IS NULL');
            while ($row = $stmt->fetch()) {
                $hex = bin2hex(random_bytes(16));
                $uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-' . dechex(hexdec(substr($hex, 16, 2)) | 0x8) . substr($hex, 18, 2) . '-' . substr($hex, 20, 12);
                $upd = $pdo->prepare('UPDATE reports SET uuid = :uuid WHERE id = :id');
                $upd->execute([':uuid' => $uuid, ':id' => $row['id']]);
            }
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_reports_uuid ON reports(uuid)');
        }
    } catch (Exception $e) {
        error_log("Migration warning for reports.uuid: " . $e->getMessage());
    }

    // Migrate report_responses: report_id (integer) → report_uuid (text)
    try {
        $cols = $pdo->query("PRAGMA table_info(report_responses)")->fetchAll();
        $hasReportUuid = false;
        foreach ($cols as $col) {
            if ($col['name'] === 'report_uuid') {
                $hasReportUuid = true;
                break;
            }
        }
        if (!$hasReportUuid) {
            // Add report_uuid column
            $pdo->exec('ALTER TABLE report_responses ADD COLUMN report_uuid TEXT');
            // Backfill: map old report_id → report uuid
            $stmt = $pdo->query('SELECT rr.id, rr.report_id FROM report_responses rr WHERE rr.report_uuid IS NULL');
            while ($row = $stmt->fetch()) {
                $uuidStmt = $pdo->prepare('SELECT uuid FROM reports WHERE id = :id');
                $uuidStmt->execute([':id' => $row['report_id']]);
                $reportUuid = $uuidStmt->fetchColumn();
                if ($reportUuid) {
                    $upd = $pdo->prepare('UPDATE report_responses SET report_uuid = :uuid WHERE id = :id');
                    $upd->execute([':uuid' => $reportUuid, ':id' => $row['id']]);
                }
            }
            // Create index on new column
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_report_responses_report_uuid ON report_responses(report_uuid)');
        }
    } catch (Exception $e) {
        error_log("Migration warning for report_responses.report_uuid: " . $e->getMessage());
    }

    // Also ensure indexes exist
    $indexes = [
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_reports_uuid ON reports(uuid)',
        'CREATE INDEX IF NOT EXISTS idx_reports_type ON reports(type)',
        'CREATE INDEX IF NOT EXISTS idx_reports_etat ON reports(etat)',
        'CREATE INDEX IF NOT EXISTS idx_reports_site_id ON reports(site_id)',
        'CREATE INDEX IF NOT EXISTS idx_reports_declarant_id ON reports(declarant_id)',
        'CREATE INDEX IF NOT EXISTS idx_reports_created_at ON reports(created_at)',
        'CREATE INDEX IF NOT EXISTS idx_reports_type_etat ON reports(type, etat)',
        'CREATE INDEX IF NOT EXISTS idx_reports_type_site ON reports(type, site_id)',
        'CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)',
        'CREATE INDEX IF NOT EXISTS idx_users_site_id ON users(site_id)',
        'CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)',
        'CREATE INDEX IF NOT EXISTS idx_report_responses_report_uuid ON report_responses(report_uuid)',
        'CREATE INDEX IF NOT EXISTS idx_notification_settings_site_id ON notification_settings(site_id)',
    ];
    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            error_log("Index migration warning: " . $e->getMessage());
        }
    }
}

/**
 * Auto-migrate: add missing config_app keys for existing databases.
 * This ensures that databases created before a key was added
 * will automatically receive it on next request.
 * 
 * @param PDO $pdo
 */
function migrateConfigKeys(PDO $pdo): void {
    $newKeys = [
        'app_superviseur_usernames' => ['', 'text', 'app', 'Logins Windows des superviseurs (séparés par virgule, ex: jean.martin, sophie.dupont). Ces utilisateurs seront automatiquement promus Superviseur lors de leur première connexion via IIS. Utile pour une première installation.', 1],
        'app_agent_see_only_own' => ['0', 'text', 'app', 'Obsolète : utilisez app_report_visibility', 1],
        'app_agent_visibility' => ['agent_choice', 'text', 'app', 'Obsolète : utilisez app_report_visibility', 1],
        'app_report_visibility' => ['agent_choice', 'text', 'app', 'Visibilité des signalements : "confidential" (l\'agent ne voit que ses propres signalements), "agent_choice" (l\'agent choisit au cas par cas, confidentiel par défaut), "public" (tous les signalements du site sont visibles par tous les agents).', 1],
    ];

    foreach ($newKeys as $cle => $data) {
        // Check if key already exists
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM config_app WHERE cle = :cle');
        $stmt->execute([':cle' => $cle]);
        $exists = (int) $stmt->fetchColumn();

        if ($exists === 0) {
            // For app_agent_visibility: migrate from old values
            $value = $data[0];
            if ($cle === 'app_agent_visibility') {
                // Check if key already exists with old value
                $stmt2 = $pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
                $stmt2->execute([':cle' => 'app_agent_visibility']);
                $existingValue = $stmt2->fetchColumn();
                if ($existingValue !== false) {
                    // Key exists but with old value — migrate it
                    if ($existingValue === 'site') {
                        $value = 'public'; // old "site" → new "public"
                    } elseif ($existingValue === 'own') {
                        $value = 'confidential'; // old "own" → new "confidential"
                    }
                    // Update the existing row instead of inserting
                    $stmt3 = $pdo->prepare('UPDATE config_app SET valeur = :valeur, libelle = :libelle, updated_at = datetime("now") WHERE cle = :cle');
                    $stmt3->execute([':valeur' => $value, ':libelle' => $data[3], ':cle' => $cle]);
                    continue; // Skip the INSERT below
                }
                // Key doesn't exist at all — also check old app_agent_see_only_own
                $stmt2 = $pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
                $stmt2->execute([':cle' => 'app_agent_see_only_own']);
                $oldValue = $stmt2->fetchColumn();
                if ($oldValue === '1') {
                    $value = 'confidential'; // Migrate: old "see only own" → new "confidential"
                }
            }

            // For app_report_visibility: migrate from app_agent_visibility value
            if ($cle === 'app_report_visibility') {
                $stmt2 = $pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
                $stmt2->execute([':cle' => 'app_agent_visibility']);
                $oldVisValue = $stmt2->fetchColumn();
                if ($oldVisValue !== false) {
                    // Map old 2-mode value to new 3-mode value
                    if ($oldVisValue === 'confidential') {
                        $value = 'agent_choice'; // old "confidential" was actually agent_choice mode
                    } elseif ($oldVisValue === 'public') {
                        $value = 'public';
                    } elseif ($oldVisValue === 'site') {
                        $value = 'public';
                    } elseif ($oldVisValue === 'own') {
                        $value = 'confidential'; // old "own" = truly confidential
                    }
                    // else: keep default 'agent_choice'
                }
            }

            $stmt = $pdo->prepare('INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES (:cle, :valeur, :type, :categorie, :libelle, :modifiable)');
            $stmt->execute([
                ':cle'        => $cle,
                ':valeur'     => $value,
                ':type'       => $data[1],
                ':categorie'  => $data[2],
                ':libelle'    => $data[3],
                ':modifiable' => $data[4],
            ]);
        }
    }
}
