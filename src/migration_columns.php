<?php

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
 * Recreate the reports_fts virtual table and its triggers after a reports rebuild.
 *
 * Audit #42 — DROP TABLE reports silently drops the FTS5 triggers
 * (reports_fts_ai/ad/au) and orphans the reports_fts virtual table (its content
 * no longer matches reports). Without this helper, future INSERTs on reports
 * would not be reflected in reports_fts → full-text search broken after migration.
 *
 * Safe to call multiple times — uses IF NOT EXISTS for triggers and rebuilds
 * reports_fts content from scratch.
 */
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
