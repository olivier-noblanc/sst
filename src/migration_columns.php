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
        $colDefs[] = "CHECK (type IN ('rsst','rami','dgi'))";
        $colDefs[] = "CHECK (etat IN ('nouveau','en_cours','traite','reouvert','abandonne'))";
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
        $allDefs = array_merge($colDefs, $fkClauses);
        $createSql = 'CREATE TABLE IF NOT EXISTS reports_new (' . implode(', ', $allDefs) . ')';
        backupBeforeMigration($pdo);
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
        error_log('[SST-MIGRATION] reports.site_id is now nullable (no-site mode support).');
    }

    // ── Remove CHECK constraint on reports.type ──────────────────────────────
    // The CHECK (type IN ('rsst','rami','dgi')) prevents adding custom registres.
    // Since SQLite has no ALTER TABLE DROP CONSTRAINT, rebuild the table without it.
    // Check if the constraint exists by trying to insert a custom type — if it
    // fails, the constraint is present and needs removal.
    $hasTypeCheck = false;
    try {
        $pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, etat) VALUES ('00000000-0000-0000-0000-000000000000', 'test-check-removal', 'custom_test', 'test', 'test', '2025-01-01', 1, 'test', 'test', 'nouveau')");
        $hasTypeCheck = false; // No constraint — insertion succeeded
        $pdo->exec("DELETE FROM reports WHERE uuid = '00000000-0000-0000-0000-000000000000'");
    } catch (Exception) {
        $hasTypeCheck = true; // Constraint rejected the insert
    }

    if ($hasTypeCheck) {
        $colStmt = $pdo->query('PRAGMA table_info(reports)');
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
        // NOT adding CHECK (type IN (...)) — that's the whole point
        $colDefs[] = "CHECK (etat IN ('nouveau','en_cours','traite','reouvert','abandonne'))";
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
        $allDefs = array_merge($colDefs, $fkClauses);
        $createSql = 'CREATE TABLE IF NOT EXISTS reports_new (' . implode(', ', $allDefs) . ')';
        backupBeforeMigration($pdo);
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
        error_log('[SST-MIGRATION] CHECK constraint on reports.type removed (custom registres supported).');
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
        $pdo->exec("ALTER TABLE registries ADD COLUMN btn_label TEXT");
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
        $pdo->exec($createSql);
        $pdo->exec('INSERT OR IGNORE INTO report_responses_new SELECT * FROM report_responses');
        $pdo->exec('DROP TABLE IF EXISTS report_responses');
        $pdo->exec('ALTER TABLE report_responses_new RENAME TO report_responses');
        // Recreate indexes (SQLite drops them with the table)
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_report_responses_report_uuid ON report_responses(report_uuid)');
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
        $pdo->exec("ALTER TABLE users ADD COLUMN sessions_invalid_before DATETIME");
        error_log('[SST-MIGRATION] Added users.sessions_invalid_before column (R4 — session invalidation support).');
    }
}
