<?php

/**
 * Migration — Table Creation
 *
 * Creates any missing tables in existing databases.
 * All statements use CREATE TABLE IF NOT EXISTS, so they are
 * safe to run on every request without side effects.
 *
 * Deliberately not wrapped in try/catch: these statements should always
 * succeed (idempotent CREATE TABLE IF NOT EXISTS). A failure here is a
 * code bug, not a condition to silently degrade from — let it throw.
 *
 * @param PDO $pdo
 */
function migrateTables(PDO $pdo): void
{
    // ── config_app ──────────────────────────────────────────────────────────
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

    // ── report_sequence ────────────────────────────────────────────────────
    $pdo->exec('CREATE TABLE IF NOT EXISTS report_sequence (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        year INTEGER NOT NULL,
        last_sequence INTEGER NOT NULL DEFAULT 0,
        UNIQUE(type, year)
    )');

    // ── notification_settings ──────────────────────────────────────────────
    $pdo->exec('CREATE TABLE IF NOT EXISTS notification_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        site_id INTEGER,
        type TEXT NOT NULL,
        registry TEXT NOT NULL,
        email TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
        FOREIGN KEY (site_id) REFERENCES sites(id)
    )');

    // ── report_responses ───────────────────────────────────────────────────
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

    // ── audit_log ─────────────────────────────────────────────────────────
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

    // ── report_access_log ──────────────────────────────────────────────────
    $pdo->exec('CREATE TABLE IF NOT EXISTS report_access_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        report_uuid TEXT NOT NULL,
        user_id INTEGER NOT NULL,
        role TEXT NOT NULL,
        accessed_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
        FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // ── schema_version ─────────────────────────────────────────────────────
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
        $stmt->closeCursor();
    }
    if ($count === 0) {
        $pdo->exec("INSERT INTO schema_version (version, description) VALUES (1, 'Baseline — existing database before version tracking')");
    }

    // ── FTS5 full-text search index ────────────────────────────────────────
    $ftsCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='reports_fts'");
    $ftsExists = ($ftsCheck !== false && $ftsCheck->fetch() !== false);
    if ($ftsCheck !== false) {
        $ftsCheck->closeCursor();
    }
    if (!$ftsExists) {
        $pdo->exec('CREATE VIRTUAL TABLE IF NOT EXISTS reports_fts USING fts5(uuid, objet, description, content=reports, content_rowid=rowid)');
        $pdo->exec('INSERT INTO reports_fts(uuid, objet, description) SELECT uuid, objet, description FROM reports WHERE uuid IS NOT NULL');
    }

    // ── report_state_history ───────────────────────────────────────────────
    // Tracks state transitions for audit/legal compliance (especially reopenings).
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

    // ── report_agents (many-to-many agent ↔ report) ────────────────────────
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

    // ── report_agent_invites (pending confirmation) ────────────────────────
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
}
