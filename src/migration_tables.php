<?php

/**
 * Migration — Table Creation
 *
 * Creates any missing tables in existing databases.
 * All statements use CREATE TABLE IF NOT EXISTS, so they are
 * safe to run on every request without side effects.
 *
 * @param PDO $pdo
 */
function migrateTables(PDO $pdo): void
{
    // ── config_app ──────────────────────────────────────────────────────────
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS config_app (
            cle TEXT PRIMARY KEY,
            valeur TEXT,
            type TEXT DEFAULT \'text\',
            categorie TEXT DEFAULT \'app\',
            libelle TEXT,
            modifiable INTEGER DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');
    } catch (Exception $e) {
        error_log('Migration warning for config_app: ' . $e->getMessage());
    }

    // ── report_sequence ────────────────────────────────────────────────────
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS report_sequence (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            year INTEGER NOT NULL,
            last_sequence INTEGER NOT NULL DEFAULT 0,
            UNIQUE(type, year)
        )');
    } catch (Exception $e) {
        error_log('Migration warning for report_sequence: ' . $e->getMessage());
    }

    // ── notification_settings ──────────────────────────────────────────────
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS notification_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER,
            type TEXT NOT NULL,
            registry TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            FOREIGN KEY (site_id) REFERENCES sites(id)
        )');
    } catch (Exception $e) {
        error_log('Migration warning for notification_settings: ' . $e->getMessage());
    }

    // ── report_responses ───────────────────────────────────────────────────
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS report_responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_uuid TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            reponse TEXT NOT NULL,
            nouvel_etat TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )');
    } catch (Exception $e) {
        error_log('Migration warning for report_responses: ' . $e->getMessage());
    }

    // ── audit_log ─────────────────────────────────────────────────────────
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            username TEXT NOT NULL,
            category TEXT NOT NULL,
            action TEXT NOT NULL,
            target_id INTEGER,
            target_type TEXT,
            details TEXT NOT NULL,
            context TEXT,
            ip_address TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');
    } catch (Exception $e) {
        error_log('Migration warning for audit_log: ' . $e->getMessage());
    }

    // ── report_access_log ──────────────────────────────────────────────────
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS report_access_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_uuid TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            role TEXT NOT NULL,
            accessed_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )');
    } catch (Exception $e) {
        error_log('Migration warning for report_access_log: ' . $e->getMessage());
    }

    // ── schema_version ─────────────────────────────────────────────────────
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_version (
            version     INTEGER PRIMARY KEY,
            description TEXT NOT NULL,
            applied_at  TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');
        // Record baseline for existing databases that pre-date schema_version
        $stmt = $pdo->query('SELECT COUNT(*) FROM schema_version');
        if ($stmt === false) {
            $count = 0;
        } else {
            $count = (int) $stmt->fetchColumn();
        }
        if ($count === 0) {
            $pdo->exec("INSERT INTO schema_version (version, description) VALUES (1, 'Baseline — existing database before version tracking')");
        }
    } catch (Exception $e) {
        error_log('Migration warning for schema_version: ' . $e->getMessage());
    }

    // ── FTS5 full-text search index ────────────────────────────────────────
    try {
        $ftsCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='reports_fts'");
        $ftsExists = ($ftsCheck !== false && $ftsCheck->fetch() !== false);
        if (!$ftsExists) {
            $pdo->exec('CREATE VIRTUAL TABLE IF NOT EXISTS reports_fts USING fts5(uuid, objet, description, content=reports, content_rowid=rowid)');
            $pdo->exec('INSERT INTO reports_fts(uuid, objet, description) SELECT uuid, objet, description FROM reports WHERE uuid IS NOT NULL');
        }
    } catch (Exception $e) {
        error_log('Migration warning for FTS5: ' . $e->getMessage());
    }

    // ── report_state_history ───────────────────────────────────────────────
    // Tracks state transitions for audit/legal compliance (especially reopenings).
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS report_state_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_uuid TEXT NOT NULL,
            etat_precedent TEXT NOT NULL,
            etat_suivant TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            motif TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            FOREIGN KEY (report_uuid) REFERENCES reports(uuid),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_state_history_report ON report_state_history(report_uuid)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_state_history_created ON report_state_history(created_at)');
    } catch (Exception $e) {
        error_log('Migration warning for report_state_history: ' . $e->getMessage());
    }

    // ── report_agents (many-to-many agent ↔ report) ────────────────────────
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS report_agents (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            report_uuid TEXT NOT NULL,
            user_id     INTEGER NOT NULL,
            created_at  TEXT NOT NULL DEFAULT (datetime(\'now\')),
            FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE(report_uuid, user_id)
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_report_agents_uuid ON report_agents(report_uuid)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_report_agents_user ON report_agents(user_id)');
    } catch (Exception $e) {
        error_log('Migration warning for report_agents: ' . $e->getMessage());
    }

    // ── report_agent_invites (pending confirmation) ────────────────────────
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS report_agent_invites (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            report_uuid TEXT NOT NULL,
            email       TEXT NOT NULL,
            token       TEXT NOT NULL,
            confirmed   INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime(\'now\')),
            confirmed_at TEXT DEFAULT NULL,
            FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
            UNIQUE(token)
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_agent_invites_uuid ON report_agent_invites(report_uuid)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_agent_invites_token ON report_agent_invites(token)');
    } catch (Exception $e) {
        error_log('Migration warning for report_agent_invites: ' . $e->getMessage());
    }
}
