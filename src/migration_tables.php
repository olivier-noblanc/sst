<?php

/**
 * Migration — Table Creation
 *
 * All tables currently required by the app are already created directly
 * by schema.sql (kept as the single source of truth — verified to produce
 * an identical result to "schema.sql + every historical migration step").
 * This function is intentionally empty right now, not removed: it stays
 * wired into migrateSchema() (called on every request) so that the next
 * time a table needs adding for an already-running database, the pattern
 * is ready to receive it — add a `CREATE TABLE IF NOT EXISTS ...` below,
 * matching the same statement already added to schema.sql for fresh
 * installs. No try/catch: a migration that fails is a code bug to see
 * immediately, not a condition to silently degrade from.
 *
 * @param PDO $pdo
 */
function migrateTables(PDO $pdo): void
{
    // ── Registries table (dynamic registry definitions) ────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS registries (
        id                  INTEGER PRIMARY KEY AUTOINCREMENT,
        code                TEXT NOT NULL UNIQUE,
        label               TEXT NOT NULL,
        short_label         TEXT NOT NULL,
        description         TEXT,
        icon                TEXT NOT NULL DEFAULT '📋',
        color_theme         TEXT NOT NULL DEFAULT 'rsst',
        is_enabled          INTEGER NOT NULL DEFAULT 1,
        is_system           INTEGER NOT NULL DEFAULT 0,
        sort_order          INTEGER NOT NULL DEFAULT 0,
        default_visibility  TEXT NOT NULL DEFAULT 'agent_choice',
        notify_chsct        INTEGER NOT NULL DEFAULT 0,
        legal_note          TEXT,
        created_at          TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at          TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // ── Registry fields table (custom fields per registry) ─────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS registry_fields (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        registry_id     INTEGER NOT NULL,
        field_code      TEXT NOT NULL,
        label           TEXT NOT NULL,
        field_type      TEXT NOT NULL DEFAULT 'text',
        options         TEXT,
        is_required     INTEGER NOT NULL DEFAULT 0,
        sort_order      INTEGER NOT NULL DEFAULT 0,
        created_at      TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (registry_id) REFERENCES registries(id) ON DELETE CASCADE,
        UNIQUE(registry_id, field_code)
    )");

    // ── Sessions table (SQLite-backed session handler) ──────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        id TEXT PRIMARY KEY,
        data TEXT NOT NULL DEFAULT '',
        last_accessed INTEGER NOT NULL DEFAULT 0
    )");

    // ── reports_fts sync triggers ────────────────────────────────────────
    // An existing database already has reports_fts (from an earlier
    // schema.sql) but not these triggers, added later once external-content
    // FTS5 tables were found to need them (without them, any raw write to
    // reports outside ReportRepository leaves the FTS5 index out of sync,
    // and SQLite's own consistency check then throws "database disk image
    // is malformed" on the next write — not real corruption, just FTS5
    // detecting its shadow index disagrees with the content table). See
    // schema.sql for the full explanation and where these are also created
    // for a fresh install.
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
