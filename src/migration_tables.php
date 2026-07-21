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
}
