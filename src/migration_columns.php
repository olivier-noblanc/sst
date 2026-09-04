<?php

use App\Repository\AnonymizationPolicy;

/**
 * Migration — Column Additions & Data Fixes
 *
 * Every column, CHECK constraint, and data fix this function used to add
 * incrementally (is_confidential, uuid + backfill, report_uuid, UUID
 * variant-bit fixes, attachment columns, RAMI fields, site_chosen_at,
 * consent_syndicat, pole/service/telephone/site_text, response
 * attachment columns, audit_log.target_uuid, the wordcloud config
 * cleanup, and the CHECK constraints on reports.type/etat) is now
 * present directly in schema.sql, verified identical to running every
 * one of those steps against a bare schema.sql install. The live
 * database has already run through all of them (confirmed up to date
 * as of the CHECK-constraint fix).
 *
 * Intentionally empty, not removed: stays wired into migrateSchema()
 * (called on every request) so the next column/data fix an
 * already-running database needs has a ready pattern to slot into — add
 * it below, matching what's also added to schema.sql for fresh installs.
 * No try/catch: a migration that fails is a code bug to see immediately,
 * not a condition to silently degrade from.
 *
 * @param PDO $pdo
 */

/**
 * Rebuild reports table with custom column definitions.
 *
 * Encapsulates the backup → DROP → CREATE → INSERT → DROP → RENAME →
 * recreate-indexes → recreate-FTS5 → rollback → foreign_keys reset →
 * FK-orphan-check pattern shared by the two reports-table rebuilds in
 * migrateColumns() below.
 *
 * @param PDO     $pdo             Database connection.
 * @param Closure $buildColumnDefs Callable that returns the full array of
 *                                 column/FK/CHECK definitions ready for
 *                                 implode (reads PRAGMA table_info and
 *                                 PRAGMA foreign_key_list internally).
 * @param string  $logMessage      Message to write via error_log() on success.
 */
function rebuildReportsTable(PDO $pdo, Closure $buildColumnDefs, string $logMessage): void
{
    backupBeforeMigration($pdo);
    // Audit #78 — DROP TABLE reports needs foreign_keys OFF. With it ON,
    // SQLite performs an implicit row-by-row DELETE before dropping the
    // table; report_state_history references reports(uuid) without ON
    // DELETE CASCADE, so any database with at least one row in
    // report_state_history fails the rebuild. Toggling the pragma is a
    // no-op inside a transaction (SQLite requirement), so it must happen
    // outside the beginTransaction()/commit() below — restored in finally
    // so a worker's PDO connection never ends up with foreign_keys left OFF.
    $fkStmt = $pdo->query('PRAGMA foreign_keys');
    if ($fkStmt === false) {
        throw new RuntimeException('PRAGMA foreign_keys query failed unexpectedly.');
    }
    $fkWasEnabled = (bool) $fkStmt->fetchColumn();
    $pdo->exec('PRAGMA foreign_keys = OFF');
    try {
        $pdo->beginTransaction();
        try {
            // Audit #55 — Drop any leftover reports_new from a previously failed migration.
            $pdo->exec('DROP TABLE IF EXISTS reports_new');
            $colDefs = $buildColumnDefs();
            $createSql = 'CREATE TABLE IF NOT EXISTS reports_new (' . implode(', ', $colDefs) . ')';
            $pdo->exec($createSql);
            $pdo->exec('INSERT OR IGNORE INTO reports_new SELECT * FROM reports');
            $pdo->exec('DROP TABLE IF EXISTS reports');
            $pdo->exec('ALTER TABLE reports_new RENAME TO reports');
            // Recreate indexes (SQLite drops them with the table)
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_type ON reports(type)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_etat ON reports(etat)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_site_id ON reports(site_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_declarant_id ON reports(declarant_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_created_at ON reports(created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_type_etat ON reports(type, etat)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_type_site ON reports(type, site_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_type_site_etat ON reports(type, site_id, etat)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_type_declarant_etat ON reports(type, declarant_id, etat)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_type_date_evenement ON reports(type, date_evenement)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_is_confidential ON reports(is_confidential)');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_reports_uuid ON reports(uuid)');
            // Audit #42 — recreate FTS5 virtual table + triggers after rebuild.
            recreateReportsFts5($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            // Audit — never leave the cached PDO connection (static across
            // requests under a persistent FastCGI worker) mid-transaction:
            // an unrolled-back transaction here would make every subsequent
            // beginTransaction() on this worker fatal with "There is already
            // an active transaction", breaking unrelated requests until the
            // worker is recycled. Roll back, then re-throw unchanged — the
            // migration failure must stay loud, not be silently swallowed.
            $pdo->rollBack();
            throw $e;
        }
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ' . ($fkWasEnabled ? 'ON' : 'OFF'));
    }
    // Audit #78 — verify the rebuild didn't leave any dangling reference
    // (report_state_history and friends) now that enforcement is back ON.
    // A rebuild that "succeeds" but leaves orphaned FKs is worse than one
    // that fails loudly.
    $orphanStmt = $pdo->query('PRAGMA foreign_key_check(reports)');
    if ($orphanStmt === false) {
        throw new RuntimeException('PRAGMA foreign_key_check(reports) query failed unexpectedly.');
    }
    $orphans = $orphanStmt->fetchAll();
    if (!empty($orphans)) {
        throw new RuntimeException('reports rebuild left dangling foreign keys: ' . json_encode($orphans));
    }
    error_log($logMessage);
}

function migrateColumns(PDO $pdo): void
{
    // ── Make reports.site_id nullable ────────────────────────────────────────
    // Was NOT NULL. In no-site mode (isNoSiteMode() — zero active sites) the
    // report form submits site_id as empty, which CreateReportCommand turns
    // into 0 (the app-wide "no site" sentinel) — 0 is never a real site id,
    // so the NOT NULL + FOREIGN KEY combination rejected every single insert.
    // Report submission was completely broken on any install running in
    // no-site mode. SQLite has no ALTER COLUMN, so this needs the same
    // table-rebuild approach already used for the reports.type/etat CHECK
    // constraints (which stays identical here — see schema.sql colDefs are
    // read back from PRAGMA table_info, not hand-written, precisely so this
    // does not have to be duplicated by hand).
    $colStmt = $pdo->query('PRAGMA table_info(reports)');
    $columns = ($colStmt !== false) ? $colStmt->fetchAll() : [];
    $colStmt = null;
    $siteIdIsNotNull = false;
    foreach ($columns as $col) {
        if (is_array($col) && ($col['name'] ?? '') === 'site_id' && ($col['notnull'] ?? 0) === 1) {
            $siteIdIsNotNull = true;
            break;
        }
    }
    if ($siteIdIsNotNull) {
        rebuildReportsTable($pdo, function () use ($pdo, $columns): array {
            $colDefs = [];
            foreach ($columns as $col) {
                if (!is_array($col)) {
                    continue;
                }
                /** @var array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int} $col */
                $def = $col['name'] . ' ' . $col['type'];
                if ($col['pk']) {
                    $def .= ' PRIMARY KEY';
                }
                // site_id loses its NOT NULL here; every other column keeps its own.
                if ($col['notnull'] && !$col['pk'] && $col['name'] !== 'site_id') {
                    $def .= ' NOT NULL';
                }
                if ($col['dflt_value'] !== null) {
                    /** @var string $dfltValue */
                    $dfltValue = $col['dflt_value'];
                    $isLiteral = is_numeric($dfltValue) || str_starts_with($dfltValue, "'");
                    $def .= $isLiteral ? ' DEFAULT ' . $dfltValue : ' DEFAULT (' . $dfltValue . ')';
                }
                $colDefs[] = $def;
            }
            // Modular-audit P1.1 — CHECK (type IN ('rsst','rami','dgi')) supprimé.
            // Les registres doivent être 100% modulaires (création/suppression
            // dynamique selon les lois). La table `registries` est la source de
            // vérité pour les codes valides, pas une CHECK constraint hardcodée.
            $colDefs[] = "CHECK (etat IN ('nouveau','en_cours','traite','reouvert','abandonne'))";
            $hasSiteIdCheck = array_any($colDefs, fn($existingDef) => str_contains((string) $existingDef, 'CHECK (site_id IS NULL OR site_id > 0)'));
            if (!$hasSiteIdCheck) {
                $colDefs[] = 'CHECK (site_id IS NULL OR site_id > 0)';
            }
            $fkStmt = $pdo->query('PRAGMA foreign_key_list(reports)');
            $fks = ($fkStmt !== false) ? $fkStmt->fetchAll() : [];
            $fkStmt = null;
            $fkClauses = [];
            foreach ($fks as $fk) {
                if (!is_array($fk)) {
                    continue;
                }
                /** @var array{from: string, table: string, to: string} $fk */
                $fkClauses[] = "FOREIGN KEY ({$fk['from']}) REFERENCES {$fk['table']}({$fk['to']})";
            }
            return array_merge($colDefs, $fkClauses);
        }, '[SST-MIGRATION] reports.site_id is now nullable (no-site mode support).');
    }

    // ── Remove CHECK constraint on reports.type ──────────────────────────────
    // The CHECK (type IN ('rsst','rami','dgi')) prevents adding custom registres.
    // Since SQLite has no ALTER TABLE DROP CONSTRAINT, rebuild the table without it.
    // Check if the constraint exists by trying to insert a custom type — if it
    // fails, the constraint is present and needs removal.
    //
    // Audit #43 — Before this fix, the test INSERT used declarant_id=1, which
    // could fail with a FK violation if user 1 didn't exist (e.g. fresh install
    // running migrations before seed). The catch block assumed any failure was
    // the CHECK constraint → infinite rebuild loop on every page load.
    // Now we disable FK enforcement during the test, so only a real CHECK
    // constraint can reject the insert.
    $hasTypeCheck = false;
    $fkStmt = $pdo->query('PRAGMA foreign_keys');
    $fkEnabled = ($fkStmt !== false) ? (int) $fkStmt->fetchColumn() : 0;
    $fkStmt = null;
    try {
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, etat) VALUES ('00000000-0000-0000-0000-000000000000', 'test-check-removal', 'custom_test', 'test', 'test', '2025-01-01', 1, 'test', 'test', 'nouveau')");
        $hasTypeCheck = false; // No constraint — insertion succeeded
        $pdo->exec("DELETE FROM reports WHERE uuid = '00000000-0000-0000-0000-000000000000'");
    } catch (Exception) {
        // @silent-ok: feature-detection probe — does the CHECK constraint already exist?
        $hasTypeCheck = true; // Constraint rejected the insert
        try {
            $pdo->exec("DELETE FROM reports WHERE uuid = '00000000-0000-0000-0000-000000000000'");
        } catch (Exception) {
            // @silent-ok: cleanup of the probe row — failure here is inconsequential either way
            // ignore cleanup failure
        }
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ' . ($fkEnabled ? 'ON' : 'OFF'));
    }

    if ($hasTypeCheck) {
        $colStmt = $pdo->query('PRAGMA table_info(reports)');
        $columns = ($colStmt !== false) ? $colStmt->fetchAll() : [];
        $colStmt = null;

        rebuildReportsTable($pdo, function () use ($pdo, $columns): array {
            $colDefs = [];
            foreach ($columns as $col) {
                if (!is_array($col)) {
                    continue;
                }
                /** @var array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int} $col */
                $def = $col['name'] . ' ' . $col['type'];
                if ($col['pk']) {
                    $def .= ' PRIMARY KEY';
                }
                if ($col['notnull'] && !$col['pk']) {
                    $def .= ' NOT NULL';
                }
                if ($col['dflt_value'] !== null) {
                    /** @var string $dfltValue */
                    $dfltValue = $col['dflt_value'];
                    $isLiteral = is_numeric($dfltValue) || str_starts_with($dfltValue, "'");
                    $def .= $isLiteral ? ' DEFAULT ' . $dfltValue : ' DEFAULT (' . $dfltValue . ')';
                }
                $colDefs[] = $def;
            }
            // NOT adding CHECK (type IN (...)) — that's the whole point
            $colDefs[] = "CHECK (etat IN ('nouveau','en_cours','traite','reouvert','abandonne'))";
            $hasSiteIdCheck = array_any($colDefs, fn($existingDef) => str_contains((string) $existingDef, 'CHECK (site_id IS NULL OR site_id > 0)'));
            if (!$hasSiteIdCheck) {
                $colDefs[] = 'CHECK (site_id IS NULL OR site_id > 0)';
            }
            $fkStmt = $pdo->query('PRAGMA foreign_key_list(reports)');
            $fks = ($fkStmt !== false) ? $fkStmt->fetchAll() : [];
            $fkStmt = null;
            $fkClauses = [];
            foreach ($fks as $fk) {
                if (!is_array($fk)) {
                    continue;
                }
                /** @var array{from: string, table: string, to: string} $fk */
                $fkClauses[] = "FOREIGN KEY ({$fk['from']}) REFERENCES {$fk['table']}({$fk['to']})";
            }
            return array_merge($colDefs, $fkClauses);
        }, '[SST-MIGRATION] CHECK constraint on reports.type removed (custom registres supported).');
    }

    // ── Add btn_label column to registries ──────────────────────────────────
    $colStmt = $pdo->query('PRAGMA table_info(registries)');
    $columns = ($colStmt !== false) ? $colStmt->fetchAll() : [];
    $colStmt = null;
    $btnLabelExists = false;
    foreach ($columns as $col) {
        if (is_array($col) && ($col['name'] ?? '') === 'btn_label') {
            $btnLabelExists = true;
            break;
        }
    }
    if (!$btnLabelExists) {
        $pdo->exec('ALTER TABLE registries ADD COLUMN btn_label TEXT');
        // Backfill default labels for the 3 system registres
        $pdo->exec("UPDATE registries SET btn_label = 'Déposer un signalement' WHERE code = 'rsst'");
        $pdo->exec("UPDATE registries SET btn_label = 'Signaler une agression' WHERE code = 'rami'");
        $pdo->exec("UPDATE registries SET btn_label = 'Signaler un danger urgent' WHERE code = 'dgi'");
        error_log('[SST-MIGRATION] Added btn_label column to registries.');
    }

    // ── Make report_responses.user_id nullable (RGPD anonymization) ────────
    // Audit #8 — UserRepository::anonymize() does SET user_id = NULL on
    // report_responses, but the column was NOT NULL → SQLite raised a
    // NOT NULL constraint violation → transaction rolled back silently →
    // anonymization never happened → flash success was a lie.
    // Fix: rebuild the table to allow user_id = NULL.
    $rrColStmt = $pdo->query('PRAGMA table_info(report_responses)');
    $rrColumns = ($rrColStmt !== false) ? $rrColStmt->fetchAll() : [];
    $rrColStmt = null;
    $rrUserIdIsNotNull = false;
    foreach ($rrColumns as $col) {
        if (is_array($col) && ($col['name'] ?? '') === 'user_id' && ($col['notnull'] ?? 0) === 1) {
            $rrUserIdIsNotNull = true;
            break;
        }
    }
    if ($rrUserIdIsNotNull) {
        $colDefs = [];
        foreach ($rrColumns as $col) {
            if (!is_array($col)) {
                continue;
            }
            /** @var array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int} $col */
            $def = $col['name'] . ' ' . $col['type'];
            if ($col['pk']) {
                $def .= ' PRIMARY KEY';
            }
            // user_id loses its NOT NULL here; every other column keeps its own.
            if ($col['notnull'] && !$col['pk'] && $col['name'] !== 'user_id') {
                $def .= ' NOT NULL';
            }
            if ($col['dflt_value'] !== null) {
                /** @var string $dfltValue */
                $dfltValue = $col['dflt_value'];
                $isLiteral = is_numeric($dfltValue) || str_starts_with($dfltValue, "'");
                $def .= $isLiteral ? ' DEFAULT ' . $dfltValue : ' DEFAULT (' . $dfltValue . ')';
            }
            $colDefs[] = $def;
        }
        $fkStmt = $pdo->query('PRAGMA foreign_key_list(report_responses)');
        $fks = ($fkStmt !== false) ? $fkStmt->fetchAll() : [];
        $fkStmt = null;
        $fkClauses = [];
        foreach ($fks as $fk) {
            if (!is_array($fk)) {
                continue;
            }
            /** @var array{from: string, table: string, to: string} $fk */
            $fkClauses[] = "FOREIGN KEY ({$fk['from']}) REFERENCES {$fk['table']}({$fk['to']})";
        }
        $allDefs = array_merge($colDefs, $fkClauses);
        $createSql = 'CREATE TABLE IF NOT EXISTS report_responses_new (' . implode(', ', $allDefs) . ')';
        backupBeforeMigration($pdo);
        $pdo->beginTransaction();
        try {
            // Audit #55 — Drop any leftover report_responses_new from a previously failed migration.
            $pdo->exec('DROP TABLE IF EXISTS report_responses_new');
            $pdo->exec($createSql);
            $pdo->exec('INSERT OR IGNORE INTO report_responses_new SELECT * FROM report_responses');
            $pdo->exec('DROP TABLE IF EXISTS report_responses');
            $pdo->exec('ALTER TABLE report_responses_new RENAME TO report_responses');
            // Recreate indexes (SQLite drops them with the table)
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_report_responses_report_uuid ON report_responses(report_uuid)');
            $pdo->commit();
        } catch (Throwable $e) {
            // Audit — never leave the cached PDO connection (static across
            // requests under a persistent FastCGI worker) mid-transaction:
            // an unrolled-back transaction here would make every subsequent
            // beginTransaction() on this worker fatal with "There is already
            // an active transaction", breaking unrelated requests until the
            // worker is recycled. Roll back, then re-throw unchanged — the
            // migration failure must stay loud, not be silently swallowed.
            $pdo->rollBack();
            throw $e;
        }
        error_log('[SST-MIGRATION] report_responses.user_id is now nullable (RGPD anonymization support).');
    }

    // ── Add sessions_invalid_before column to users (R4 — SessionInvalidator) ──
    // Audit #9 + #22 + #23 + #38 — avant ce fix, un user désactivé (ou dont
    // le role avait changé) gardait sa session active 24h. Maintenant le
    // AuthService re-vérifie ce marqueur toutes les 5 min et force un re-fetch
    // (ou logout si is_active=0) quand le marqueur est plus récent que le
    // début de session.
    $userColStmt = $pdo->query('PRAGMA table_info(users)');
    $userColumns = ($userColStmt !== false) ? $userColStmt->fetchAll() : [];
    $userColStmt = null;
    $sessionsInvalidBeforeExists = false;
    foreach ($userColumns as $col) {
        if (is_array($col) && ($col['name'] ?? '') === 'sessions_invalid_before') {
            $sessionsInvalidBeforeExists = true;
            break;
        }
    }
    if (!$sessionsInvalidBeforeExists) {
        $pdo->exec('ALTER TABLE users ADD COLUMN sessions_invalid_before DATETIME');
        error_log('[SST-MIGRATION] Added users.sessions_invalid_before column (R4 — session invalidation support).');
    }

    // ── Add RegistryPolicy columns to registries (P2.1) ───────────────────────
    // Modular-audit P2.1 — make the registry business logic configurable.
    // Before this fix, RAMI-specific (pour_compte) and DGI-specific (warning
    // panel, lieu label) behaviors were hardcoded with `=== ReportType::Rami->value`
    // etc. Now any registry can opt-in to these behaviors via the DB.
    $policyCols = [
        'requires_pour_compte' => 'INTEGER NOT NULL DEFAULT 0',
        'has_dgi_warning'      => 'INTEGER NOT NULL DEFAULT 0',
        'lieu_label_override'  => 'TEXT',
    ];
    foreach ($policyCols as $colName => $colDef) {
        $exists = false;
        $regColStmt = $pdo->query('PRAGMA table_info(registries)');
        $regCols = ($regColStmt !== false) ? $regColStmt->fetchAll() : [];
        $regColStmt = null;
        foreach ($regCols as $col) {
            if (is_array($col) && ($col['name'] ?? '') === $colName) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $pdo->exec("ALTER TABLE registries ADD COLUMN $colName $colDef");
            error_log("[SST-MIGRATION] Added registries.$colName column (P2.1 — RegistryPolicy).");
        }
    }

    // Backfill the historical system registries with their historical behavior
    // so the RegistryPolicy behaves identically to the hardcoded version.
    // (No-op if already set — using UPDATE with WHERE is idempotent.)
    $pdo->exec("UPDATE registries SET requires_pour_compte = 1 WHERE code = 'rami' AND requires_pour_compte = 0");
    $pdo->exec("UPDATE registries SET has_dgi_warning = 1, lieu_label_override = 'Lieu / Mesures de protection' WHERE code = 'dgi' AND has_dgi_warning = 0");

    // ── Add CHECK (site_id IS NULL OR site_id > 0) on users/notification_settings ──
    // This CHECK is already in schema.sql for fresh installs, but existing databases
    // created before the CHECK was added need it applied via table rebuild.
    $checkStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users' AND sql LIKE '%CHECK (site_id IS NULL OR site_id > 0)%'");
    $checkApplied = ($checkStmt !== false) ? $checkStmt->fetchColumn() : false;
    if (!$checkApplied) {
        // Fix any existing site_id = 0 rows before adding constraint
        $pdo->exec('UPDATE users SET site_id = NULL WHERE site_id = 0');
        $pdo->exec('UPDATE notification_settings SET site_id = NULL WHERE site_id = 0');

        // Rebuild users table with CHECK constraint
        $colStmt = $pdo->query('PRAGMA table_info(users)');
        $columns = ($colStmt !== false) ? $colStmt->fetchAll() : [];
        $colStmt = null;
        $colDefs = [];
        foreach ($columns as $col) {
            if (!is_array($col)) {
                continue;
            }
            /** @var array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int} $col */
            $def = $col['name'] . ' ' . $col['type'];
            if ($col['pk']) {
                $def .= ' PRIMARY KEY';
            }
            if ($col['notnull'] && !$col['pk']) {
                $def .= ' NOT NULL';
            }
            if ($col['dflt_value'] !== null) {
                /** @var string $dfltValue */
                $dfltValue = $col['dflt_value'];
                $isLiteral = is_numeric($dfltValue) || str_starts_with($dfltValue, "'");
                $def .= $isLiteral ? ' DEFAULT ' . $dfltValue : ' DEFAULT (' . $dfltValue . ')';
            }
            $colDefs[] = $def;
        }
        $colDefs[] = 'CHECK (site_id IS NULL OR site_id > 0)';
        $fkStmt = $pdo->query('PRAGMA foreign_key_list(users)');
        $fks = ($fkStmt !== false) ? $fkStmt->fetchAll() : [];
        $fkStmt = null;
        $fkClauses = [];
        foreach ($fks as $fk) {
            if (!is_array($fk)) {
                continue;
            }
            /** @var array{from: string, table: string, to: string} $fk */
            $fkClauses[] = "FOREIGN KEY ({$fk['from']}) REFERENCES {$fk['table']}({$fk['to']})";
        }
        $allDefs = array_merge($colDefs, $fkClauses);
        $createSql = 'CREATE TABLE IF NOT EXISTS users_new (' . implode(', ', $allDefs) . ')';
        backupBeforeMigration($pdo);
        $fkStmt = $pdo->query('PRAGMA foreign_keys');
        if ($fkStmt === false) {
            throw new RuntimeException('PRAGMA foreign_keys query failed unexpectedly.');
        }
        $fkWasEnabled = (bool) $fkStmt->fetchColumn();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            $pdo->beginTransaction();
            try {
                $pdo->exec('DROP TABLE IF EXISTS users_new');
                $pdo->exec($createSql);
                $pdo->exec('INSERT OR IGNORE INTO users_new SELECT * FROM users');
                $pdo->exec('DROP TABLE IF EXISTS users');
                $pdo->exec('ALTER TABLE users_new RENAME TO users');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_site_id ON users(site_id)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)');
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ' . ($fkWasEnabled ? 'ON' : 'OFF'));
        }
        $orphanStmt = $pdo->query('PRAGMA foreign_key_check(users)');
        if ($orphanStmt === false) {
            throw new RuntimeException('PRAGMA foreign_key_check(users) query failed unexpectedly.');
        }
        $orphans = $orphanStmt->fetchAll();
        if (!empty($orphans)) {
            throw new RuntimeException('users rebuild (site_id CHECK) left dangling foreign keys: ' . json_encode($orphans));
        }
        error_log('[SST-MIGRATION] users.site_id CHECK constraint applied (site_id IS NULL OR site_id > 0).');

        // Rebuild notification_settings table with CHECK constraint
        $colStmt = $pdo->query('PRAGMA table_info(notification_settings)');
        $columns = ($colStmt !== false) ? $colStmt->fetchAll() : [];
        $colStmt = null;
        $colDefs = [];
        foreach ($columns as $col) {
            if (!is_array($col)) {
                continue;
            }
            /** @var array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int} $col */
            $def = $col['name'] . ' ' . $col['type'];
            if ($col['pk']) {
                $def .= ' PRIMARY KEY';
            }
            if ($col['notnull'] && !$col['pk']) {
                $def .= ' NOT NULL';
            }
            if ($col['dflt_value'] !== null) {
                /** @var string $dfltValue */
                $dfltValue = $col['dflt_value'];
                $isLiteral = is_numeric($dfltValue) || str_starts_with($dfltValue, "'");
                $def .= $isLiteral ? ' DEFAULT ' . $dfltValue : ' DEFAULT (' . $dfltValue . ')';
            }
            $colDefs[] = $def;
        }
        $colDefs[] = 'CHECK (site_id IS NULL OR site_id > 0)';
        $fkStmt = $pdo->query('PRAGMA foreign_key_list(notification_settings)');
        $fks = ($fkStmt !== false) ? $fkStmt->fetchAll() : [];
        $fkStmt = null;
        $fkClauses = [];
        foreach ($fks as $fk) {
            if (!is_array($fk)) {
                continue;
            }
            /** @var array{from: string, table: string, to: string} $fk */
            $fkClauses[] = "FOREIGN KEY ({$fk['from']}) REFERENCES {$fk['table']}({$fk['to']})";
        }
        $allDefs = array_merge($colDefs, $fkClauses);
        $createSql = 'CREATE TABLE IF NOT EXISTS notification_settings_new (' . implode(', ', $allDefs) . ')';
        backupBeforeMigration($pdo);
        $fkStmt = $pdo->query('PRAGMA foreign_keys');
        if ($fkStmt === false) {
            throw new RuntimeException('PRAGMA foreign_keys query failed unexpectedly.');
        }
        $fkWasEnabled = (bool) $fkStmt->fetchColumn();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            $pdo->beginTransaction();
            try {
                $pdo->exec('DROP TABLE IF EXISTS notification_settings_new');
                $pdo->exec($createSql);
                $pdo->exec('INSERT OR IGNORE INTO notification_settings_new SELECT * FROM notification_settings');
                $pdo->exec('DROP TABLE IF EXISTS notification_settings');
                $pdo->exec('ALTER TABLE notification_settings_new RENAME TO notification_settings');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notification_settings_site_id ON notification_settings(site_id)');
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ' . ($fkWasEnabled ? 'ON' : 'OFF'));
        }
        $orphanStmt = $pdo->query('PRAGMA foreign_key_check(notification_settings)');
        if ($orphanStmt === false) {
            throw new RuntimeException('PRAGMA foreign_key_check(notification_settings) query failed unexpectedly.');
        }
        $orphans = $orphanStmt->fetchAll();
        if (!empty($orphans)) {
            throw new RuntimeException('notification_settings rebuild (site_id CHECK) left dangling foreign keys: ' . json_encode($orphans));
        }
        error_log('[SST-MIGRATION] notification_settings.site_id CHECK constraint applied (site_id IS NULL OR site_id > 0).');
    }
}

/**
 * Invariant users.email NOT NULL (décision produit, oracle).
 *
 * - Idempotent : détecte via PRAGMA (email notnull + CHECK email <> '') ;
 * - Préflight 1 : REFUSE (crash, jamais silencieux) toute contrainte UNIQUE
 *   inattendue sur email (incompatible avec la sentinelle partagée) ;
 * - Préflight 2 : REFUSE (crash) tout schéma users déviant — le schéma
 *   legacy réel compte 12 colonnes (les 10 historiques de schema.sql +
 *   site_chosen_at et sessions_invalid_before ajoutées par migrateColumns(),
 *   qui tourne avant cet appel dans migrateSchema()) et TOUT écart exige
 *   une décision de migration, jamais d'adaptation silencieuse ;
 * - Backfill + rebuild dans UNE transaction : si le rebuild échoue, le
 *   backfill est rollBack avec le reste (jamais de backfill commité sur
 *   une table non migrée) — NULL et vides → sentinelle
 *   AnonymizationPolicy::ANONYMIZED_EMAIL (compat fixtures/legacy) ;
 * - Rebuild users : DDL généré depuis PRAGMA table_info (types/NOT NULL/
 *   DEFAULT réels préservés) + re-ajouts explicites AUTOINCREMENT,
 *   username UNIQUE, FK sites et CHECK site_id. email cible = NOT NULL +
 *   CHECK (email <> '') SANS DEFAULT (sentinelle réservée au chemin
 *   d'anonymisation) ;
 * - PRAGMA foreign_keys géré + foreign_key_check obligatoire ;
 * - Jamais de chemin temporaire fixe.
 */
function migrateUsersEmailNotNull(PDO $pdo): void
{
    // D3 — garde explicite du retour de PRAGMA (jamais false silencieux)
    $infoStmt = $pdo->query('PRAGMA table_info(users)');
    if ($infoStmt === false) {
        throw new RuntimeException('PRAGMA table_info(users) query failed unexpectedly.');
    }
    $emailCol = null;
    foreach ($infoStmt->fetchAll() as $col) {
        if (is_array($col) && ($col['name'] ?? '') === 'email') {
            $emailCol = $col;
        }
    }
    $infoStmt = null;
    if ($emailCol === null) {
        throw new RuntimeException('migrateUsersEmailNotNull: colonne users.email absente — schéma inattendu.');
    }

    // D3 (réserve) — fetchColumn gardé : sqlite_master doit répondre avec la
    // table users, sinon crash (jamais de '' silencieux interprété comme
    // « CHECK absent »).
    $tableSqlStmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'");
    if ($tableSqlStmt === false) {
        throw new RuntimeException('migrateUsersEmailNotNull: requête sqlite_master échouée de manière inattendue.');
    }
    $tableSqlRaw = $tableSqlStmt->fetchColumn();
    $tableSqlStmt = null;
    if ($tableSqlRaw === false || $tableSqlRaw === null) {
        throw new RuntimeException('migrateUsersEmailNotNull: table users absente de sqlite_master — schéma inattendu.');
    }
    $tableSql = (string) $tableSqlRaw;

    // Réserve — alreadyDone ne dépend d'AUCUN DEFAULT : l'invariant est
    // exactement « NOT NULL + CHECK (email <> '') ». Le DDL cible n'a pas de
    // DEFAULT sur email (sentinelle réservée au chemin d'anonymisation),
    // donc exiger 'DEFAULT' dans le SQL rendait alreadyDone dépendant de la
    // présence d'un DEFAULT quelconque ailleurs dans la table.
    $alreadyDone = (int) $emailCol['notnull'] === 1
        && str_contains($tableSql, "CHECK (email <> '')");

    if (!$alreadyDone) {
        // D1 — backfill + rebuild DANS LA MÊME transaction (voir
        // rebuildUsersTableEmailNotNull) : un échec de rebuild (préflight,
        // INSERT strict, divergence de comptage) rollBack aussi le backfill.
        rebuildUsersTableEmailNotNull($pdo);
    }

    // Post-vérification défensive : aucun NULL/vide ne peut survivre.
    $remainingStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE email IS NULL OR email = ''");
    if ($remainingStmt === false) {
        throw new RuntimeException('Post-migration count query failed unexpectedly.');
    }
    $remaining = (int) $remainingStmt->fetchColumn();
    if ($remaining > 0) {
        throw new RuntimeException('migrateUsersEmailNotNull: ' . $remaining . ' user(s) sans email après migration — backfill incomplet.');
    }
}

/**
 * D2 — rebuild users pour l'invariant email NOT NULL.
 *
 * Préflight strict (crash-hard) puis, dans UNE transaction : backfill email
 * (NULL/vide → sentinelle) → création users_new → INSERT NOMMÉ strict (jamais
 * OR IGNORE, jamais SELECT * positionnel) → vérification de comptage →
 * DROP users → RENAME → recréation des index. Un échec à n'importe quelle
 * étape rollBack le backfill avec le reste.
 *
 * Le DDL de users_new est généré depuis PRAGMA table_info (préserve les
 * types/NOT NULL/DEFAULT réels du schéma legacy à 12 colonnes) avec les
 * re-ajouts explicites que PRAGMA ne transporte pas : AUTOINCREMENT,
 * username UNIQUE (détecté via PRAGMA index_list), FKs (PRAGMA
 * foreign_key_list) et CHECK site_id (ajouté si absent du legacy).
 */
function rebuildUsersTableEmailNotNull(PDO $pdo): void
{
    // D3 + D2 préflight — colonnes réelles == les 12 colonnes du schéma
    // legacy réel (oracle B1) : les 10 colonnes historiques de schema.sql +
    // site_chosen_at + sessions_invalid_before (appendées via ALTER TABLE
    // par migrateColumns(), qui tourne avant cet appel dans migrateSchema()).
    // Toute déviation (colonne manquante ou additionnelle) = crash —
    // décision de migration requise, jamais d'adaptation silencieuse.
    $infoStmt = $pdo->query('PRAGMA table_info(users)');
    if ($infoStmt === false) {
        throw new RuntimeException('PRAGMA table_info(users) query failed unexpectedly.');
    }
    $userColumns = $infoStmt->fetchAll();
    $infoStmt = null;
    $sortedActual = array_column($userColumns, 'name');
    sort($sortedActual);
    $sortedExpected = ['created_at', 'email', 'id', 'is_active', 'nom', 'prenom', 'role', 'sessions_invalid_before', 'site_chosen_at', 'site_id', 'updated_at', 'username'];
    if ($sortedActual !== $sortedExpected) {
        throw new RuntimeException(
            'migrateUsersEmailNotNull: colonnes users inattendues ('
            . implode(', ', $sortedActual) . ') — décision de migration requise avant application.'
        );
    }

    // D3 — SQL legacy de la table : détection AUTOINCREMENT et CHECK site_id
    // (PRAGMA table_info ne transporte ni l'un ni l'autre).
    $tableSqlStmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'");
    if ($tableSqlStmt === false) {
        throw new RuntimeException('migrateUsersEmailNotNull: requête sqlite_master échouée de manière inattendue.');
    }
    $legacySqlRaw = $tableSqlStmt->fetchColumn();
    $tableSqlStmt = null;
    if ($legacySqlRaw === false || $legacySqlRaw === null) {
        throw new RuntimeException('migrateUsersEmailNotNull: table users absente de sqlite_master — schéma inattendu.');
    }
    $legacySql = (string) $legacySqlRaw;
    $hadAutoincrement = str_contains($legacySql, 'AUTOINCREMENT');
    $hasSiteIdCheck = str_contains($legacySql, 'CHECK (site_id IS NULL OR site_id > 0)');

    // Préflight — refus explicite (crash) d'une contrainte UNIQUE inattendue
    // sur email : la sentinelle est partagée par tous les comptes anonymisés.
    // Au passage : username UNIQUE (autoindex) est détecté pour être ré-émis
    // dans le DDL (PRAGMA table_info ne transporte pas UNIQUE), et tout
    // index nommé (origin 'c') est capturé pour être recréé après le rebuild.
    $usernameHasUnique = false;
    $recreateIndexSql = [];
    $indexesStmt = $pdo->query('PRAGMA index_list(users)');
    if ($indexesStmt === false) {
        throw new RuntimeException('PRAGMA index_list(users) query failed unexpectedly.');
    }
    foreach ($indexesStmt->fetchAll() as $idx) {
        if (!is_array($idx)) {
            continue;
        }
        $idxName = (string) ($idx['name'] ?? '');
        $isUnique = (int) ($idx['unique'] ?? 0) === 1;
        $idxInfoStmt = $pdo->query("PRAGMA index_info('" . str_replace("'", "''", $idxName) . "')");
        if ($idxInfoStmt === false) {
            throw new RuntimeException('PRAGMA index_info query failed unexpectedly.');
        }
        $idxColumns = array_column($idxInfoStmt->fetchAll(), 'name');
        $idxInfoStmt = null;
        if ($isUnique && in_array('email', $idxColumns, true)) {
            throw new RuntimeException(
                'migrateUsersEmailNotNull: contrainte UNIQUE inattendue sur users.email (index '
                . ($idx['name'] ?? '?') . ') — incompatible avec la sentinelle d\'anonymisation partagée.'
            );
        }
        if ($isUnique && $idxColumns === ['username']) {
            $usernameHasUnique = true;
            continue;
        }
        if (($idx['origin'] ?? '') === 'c') {
            // Index nommé créé par CREATE INDEX : recréé tel quel après le
            // rebuild (le DROP TABLE emporte tous les index de la table).
            $idxSqlStmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='index' AND name='" . str_replace("'", "''", $idxName) . "'");
            if ($idxSqlStmt === false) {
                throw new RuntimeException('sqlite_master index query failed unexpectedly.');
            }
            $idxSql = $idxSqlStmt->fetchColumn();
            $idxSqlStmt = null;
            if (is_string($idxSql) && $idxSql !== '') {
                $recreateIndexSql[] = $idxSql;
            }
        }
    }
    $indexesStmt = null;

    // D7 — backup crash-hard : un échec de backup annule la migration.
    if (backupBeforeMigration($pdo) !== true) {
        throw new RuntimeException('migrateUsersEmailNotNull: backup pré-migration échoué — migration annulée.');
    }

    // DDL généré depuis PRAGMA table_info : types/NOT NULL/DEFAULT réels du
    // legacy préservés. Seule différence intentionnelle : email.
    $columnDefs = [];
    $insertColumns = [];
    foreach ($userColumns as $col) {
        if (!is_array($col)) {
            continue;
        }
        /** @var array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int} $col */
        $name = (string) $col['name'];
        $insertColumns[] = '"' . str_replace('"', '""', $name) . '"';
        if ($name === 'email') {
            // L'invariant cible — pas de DEFAULT : la sentinelle est réservée
            // au chemin d'anonymisation.
            $columnDefs[] = $name . ' ' . $col['type'] . " NOT NULL CHECK (email <> '')";
            continue;
        }
        $def = $name . ' ' . $col['type'];
        if ((int) $col['pk'] === 1) {
            $def .= ' PRIMARY KEY' . ($hadAutoincrement ? ' AUTOINCREMENT' : '');
        }
        if ((int) $col['notnull'] === 1 && (int) $col['pk'] !== 1) {
            $def .= ' NOT NULL';
        }
        if ($col['dflt_value'] !== null) {
            /** @var string $dfltValue */
            $dfltValue = (string) $col['dflt_value'];
            $isLiteral = is_numeric($dfltValue) || str_starts_with($dfltValue, "'");
            $def .= $isLiteral ? ' DEFAULT ' . $dfltValue : ' DEFAULT (' . $dfltValue . ')';
        }
        if ($name === 'username' && $usernameHasUnique) {
            $def .= ' UNIQUE';
        }
        $columnDefs[] = $def;
    }
    if (!$hasSiteIdCheck) {
        $columnDefs[] = 'CHECK (site_id IS NULL OR site_id > 0)';
    }
    $fkStmt = $pdo->query('PRAGMA foreign_key_list(users)');
    if ($fkStmt === false) {
        throw new RuntimeException('PRAGMA foreign_key_list(users) query failed unexpectedly.');
    }
    $fkClauses = [];
    foreach ($fkStmt->fetchAll() as $fk) {
        if (!is_array($fk)) {
            continue;
        }
        /** @var array{from: string, table: string, to: string} $fk */
        $fkClauses[] = "FOREIGN KEY ({$fk['from']}) REFERENCES {$fk['table']}({$fk['to']})";
    }
    $fkStmt = null;
    $createSql = 'CREATE TABLE users_new (' . implode(', ', array_merge($columnDefs, $fkClauses)) . ')';
    // INSERT NOMMÉ : insensible à l'ordre physique des colonnes — le legacy
    // 12 colonnes a site_chosen_at/sessions_invalid_before APPENDÉES en fin
    // de table (ALTER TABLE), contrairement à l'ordre de schema.sql.
    $insertColumnList = implode(', ', $insertColumns);

    $fkStmt = $pdo->query('PRAGMA foreign_keys');
    if ($fkStmt === false) {
        throw new RuntimeException('PRAGMA foreign_keys query failed unexpectedly.');
    }
    $fkWasEnabled = (bool) $fkStmt->fetchColumn();
    $fkStmt = null;
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $backfilled = 0;
    try {
        $pdo->beginTransaction();
        try {
            $countBeforeStmt = $pdo->query('SELECT COUNT(*) FROM users');
            if ($countBeforeStmt === false) {
                throw new RuntimeException('Count query failed unexpectedly.');
            }
            $countBefore = (int) $countBeforeStmt->fetchColumn();

            // D1 — backfill DANS la transaction : NULL/vide → sentinelle,
            // AVANT le INSERT strict (sinon ces lignes seraient rejetées par
            // le CHECK (email <> '')). RollBack avec le reste si le rebuild
            // échoue — jamais de backfill commité sur table non migrée.
            $backfill = $pdo->prepare("UPDATE users SET email = :sent WHERE email IS NULL OR email = ''");
            $backfill->execute([':sent' => AnonymizationPolicy::ANONYMIZED_EMAIL]);
            $backfilled = $backfill->rowCount();
            $backfill = null;

            $pdo->exec('DROP TABLE IF EXISTS users_new');
            $pdo->exec($createSql);
            // D1 — INSERT NOMMÉ STRICT (jamais OR IGNORE) : toute ligne
            // refusée lève une PDOException → rollback total → aucune perte
            // silencieuse.
            $pdo->exec("INSERT INTO users_new ($insertColumnList) SELECT $insertColumnList FROM users");
            $countAfterStmt = $pdo->query('SELECT COUNT(*) FROM users_new');
            if ($countAfterStmt === false) {
                throw new RuntimeException('Count query failed unexpectedly.');
            }
            $countAfter = (int) $countAfterStmt->fetchColumn();
            if ($countAfter !== $countBefore) {
                throw new RuntimeException(
                    'migrateUsersEmailNotNull: divergence de lignes (' . $countBefore . ' → ' . $countAfter
                    . ') — migration annulée, aucune perte acceptée.'
                );
            }

            // P16 — terminer/nuller les statements sur users avant le DROP :
            // un PDOStatement vivant verrouille la table (table is locked).
            $countBeforeStmt = null;
            $countAfterStmt = null;

            $pdo->exec('DROP TABLE IF EXISTS users');
            $pdo->exec('ALTER TABLE users_new RENAME TO users');
            // Recréation des index : tout index nommé du legacy (SQL capturé
            // ci-dessus) puis les 3 index standard (IF NOT EXISTS, idempotent).
            foreach ($recreateIndexSql as $idxSql) {
                $pdo->exec($idxSql);
            }
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_site_id ON users(site_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)');
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ' . ($fkWasEnabled ? 'ON' : 'OFF'));
    }
    $orphanStmt = $pdo->query('PRAGMA foreign_key_check(users)');
    if ($orphanStmt === false) {
        throw new RuntimeException('PRAGMA foreign_key_check(users) query failed unexpectedly.');
    }
    $orphans = $orphanStmt->fetchAll();
    if (!empty($orphans)) {
        throw new RuntimeException('users rebuild (email NOT NULL) left dangling foreign keys: ' . json_encode($orphans));
    }
    // Réserve — le log ne mentionne plus de DEFAULT : le DDL cible n'en a
    // PAS sur email (sentinelle réservée au chemin d'anonymisation).
    error_log('[SST-MIGRATION] users.email NOT NULL + CHECK (email <> \'\') appliqués — backfill sentinelle: ' . $backfilled . ' ligne(s).');
}

function recreateReportsFts5(PDO $pdo): void
{
    // Drop and recreate the FTS5 virtual table (it's a content-less mirror)
    try {
        $pdo->exec('DROP TABLE IF EXISTS reports_fts');
    } catch (Exception $e) {
        // @silent-ok: FTS5 tables can sometimes be in a weird state after the parent table
        // is rebuilt — dropping is best-effort, the rebuild step right after recreates it anyway.
        error_log('[SST-MIGRATION] recreateReportsFts5: could not drop reports_fts: ' . $e->getMessage());
    }

    $pdo->exec('CREATE VIRTUAL TABLE IF NOT EXISTS reports_fts USING fts5(uuid, objet, description, content=reports, content_rowid=rowid)');

    // Rebuild the FTS5 index from current reports data
    try {
        $pdo->exec('INSERT INTO reports_fts(rowid, uuid, objet, description) SELECT rowid, uuid, objet, description FROM reports');
    } catch (Exception $e) {
        // @silent-ok: same as above — FTS index rebuild during migration, not source-of-truth
        // data, and future writes to `reports` will keep the index in sync going forward.
        error_log('[SST-MIGRATION] recreateReportsFts5: rebuild failed: ' . $e->getMessage());
    }

    // Recreate the triggers (mirrors schema.sql)
    $pdo->exec('CREATE TRIGGER IF NOT EXISTS reports_fts_ai AFTER INSERT ON reports BEGIN
        INSERT INTO reports_fts(rowid, uuid, objet, description) VALUES (new.rowid, new.uuid, new.objet, new.description);
    END');
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS reports_fts_ad AFTER DELETE ON reports BEGIN
        INSERT INTO reports_fts(reports_fts, rowid, uuid, objet, description) VALUES ('delete', old.rowid, old.uuid, old.objet, old.description);
    END");
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS reports_fts_au AFTER UPDATE ON reports BEGIN
        INSERT INTO reports_fts(reports_fts, rowid, uuid, objet, description) VALUES ('delete', old.rowid, old.uuid, old.objet, old.description);
        INSERT INTO reports_fts(rowid, uuid, objet, description) VALUES (new.rowid, new.uuid, new.objet, new.description);
    END");
}
