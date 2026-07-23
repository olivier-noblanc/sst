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
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS reports_fts_ai AFTER INSERT ON reports BEGIN
        INSERT INTO reports_fts(rowid, uuid, objet, description) VALUES (new.rowid, new.uuid, new.objet, new.description);
    END");
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS reports_fts_ad AFTER DELETE ON reports BEGIN
        INSERT INTO reports_fts(reports_fts, rowid, uuid, objet, description) VALUES ('delete', old.rowid, old.uuid, old.objet, old.description);
    END");
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS reports_fts_au AFTER UPDATE ON reports BEGIN
        INSERT INTO reports_fts(reports_fts, rowid, uuid, objet, description) VALUES ('delete', old.rowid, old.uuid, old.objet, old.description);
        INSERT INTO reports_fts(rowid, uuid, objet, description) VALUES (new.rowid, new.uuid, new.objet, new.description);
    END");
}
