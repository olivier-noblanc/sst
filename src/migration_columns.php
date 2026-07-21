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
    // Nothing pending — see docblock above for how to add the next one.
}
