<?php

/**
 * Migration — Index Creation
 *
 * All indexes currently required by the app are already created directly
 * by schema.sql (kept as the single source of truth). Intentionally
 * empty, not removed: stays wired into migrateSchema() so the next index
 * needed for an already-running database has a ready pattern — add a
 * `CREATE INDEX IF NOT EXISTS ...` (via `$pdo->exec(...)`) below,
 * matching what's also added to schema.sql for fresh installs. No
 * try/catch: a migration that fails is a code bug to see immediately,
 * not a condition to silently degrade from.
 *
 * @param PDO $pdo
 */
function migrateIndexes(PDO $pdo): void
{
    // Nothing pending — see docblock above for how to add the next one.
}
