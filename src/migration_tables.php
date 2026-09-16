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

    // ── Registry field values table (submitted values of custom fields) ────
    // Même DDL que schema.sql (source de vérité pour les installations
    // fraîches) — cette clause IF NOT EXISTS couvre les bases existantes :
    // migrateTables() tourne à chaque requête (migrateSchema()).
    $pdo->exec("CREATE TABLE IF NOT EXISTS registry_field_values (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        report_uuid TEXT NOT NULL,
        registry_id INTEGER NOT NULL,
        field_code  TEXT NOT NULL,
        value       TEXT,
        created_at  TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
        FOREIGN KEY (registry_id, field_code) REFERENCES registry_fields(registry_id, field_code) ON DELETE CASCADE,
        UNIQUE(report_uuid, registry_id, field_code)
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_registry_field_values_registry ON registry_field_values(registry_id, field_code)');

    // Upgrade databases created before registry_id was part of the value key.
    $valueTableIndexesStatement = $pdo->query('PRAGMA index_list(registry_field_values)');
    if ($valueTableIndexesStatement === false) {
        throw new RuntimeException('Impossible d’inspecter les index de registry_field_values.');
    }
    $valueTableIndexes = $valueTableIndexesStatement->fetchAll(PDO::FETCH_ASSOC);
    $hasCurrentUnique = false;
    $hasLegacyUnique = false;
    foreach ($valueTableIndexes as $index) {
        if ((int) ($index['unique'] ?? 0) !== 1) {
            continue;
        }
        $indexName = (string) ($index['name'] ?? '');
        $columnsStatement = $pdo->query('PRAGMA index_info(' . $pdo->quote($indexName) . ')');
        if ($columnsStatement === false) {
            throw new RuntimeException('Impossible d’inspecter l’index de registry_field_values.');
        }
        $columns = array_map(
            static fn(array $column): string => (string) ($column['name'] ?? ''),
            $columnsStatement->fetchAll(PDO::FETCH_ASSOC)
        );
        if ($columns === ['report_uuid', 'registry_id', 'field_code']) {
            $hasCurrentUnique = true;
        } elseif ($columns === ['report_uuid', 'field_code']) {
            $hasLegacyUnique = true;
        }
    }

    if ($hasLegacyUnique) {
        $indexesToRecreate = [];
        foreach ($valueTableIndexes as $index) {
            if ((string) ($index['origin'] ?? '') !== 'c') {
                continue;
            }
            $indexName = (string) ($index['name'] ?? '');
            if ($indexName === '') {
                throw new RuntimeException('Définition d’index invalide sur registry_field_values.');
            }
            $columnsStatement = $pdo->query('PRAGMA index_info(' . $pdo->quote($indexName) . ')');
            if ($columnsStatement === false) {
                throw new RuntimeException('Impossible d’inspecter l’index de registry_field_values.');
            }
            $columns = array_map(
                static fn(array $column): string => (string) ($column['name'] ?? ''),
                $columnsStatement->fetchAll(PDO::FETCH_ASSOC)
            );
            // The table constraint recreates the current unique key.  Do not
            // replay an equivalent user index: doing so would duplicate an
            // automatically generated constraint index after the rebuild.
            if ($columns === ['report_uuid', 'registry_id', 'field_code']) {
                continue;
            }
            if ($columns === ['report_uuid', 'field_code']) {
                continue;
            }
            $definitionStatement = $pdo->prepare(
                "SELECT sql FROM sqlite_master WHERE type = 'index' AND name = :name"
            );
            $definitionStatement->execute([':name' => $indexName]);
            $definition = $definitionStatement->fetchColumn();
            $definitionStatement->closeCursor();
            if (!is_string($definition) || $definition === '') {
                throw new RuntimeException('Définition SQL absente pour l’index ' . $indexName . '.');
            }
            // Only replay complete CREATE INDEX statements belonging to this
            // table.  Rejecting anything else is deliberate: silently
            // degrading an index would change query semantics (WHERE,
            // collations, sort directions or expressions).
            $normalizedDefinition = preg_replace('/\s+/', ' ', trim($definition));
            if (!is_string($normalizedDefinition)
                || !preg_match(
                    '/^CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:"(?:[^"]|"")*"|`(?:[^`]|``)*`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+ON\s+(?:"registry_field_values"|`registry_field_values`|\[registry_field_values\]|registry_field_values)\s*\(/i',
                    $normalizedDefinition
                )
                || str_contains($normalizedDefinition, ';')
            ) {
                throw new RuntimeException('Définition SQL d’index hors contrat pour ' . $indexName . '.');
            }
            $indexesToRecreate[] = $definition;
        }
        $pdo->beginTransaction();
        try {
            $legacyTable = 'registry_field_values_legacy_' . bin2hex(random_bytes(6));
            $pdo->exec('ALTER TABLE registry_field_values RENAME TO ' . $legacyTable);
            $pdo->exec("CREATE TABLE registry_field_values (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                report_uuid TEXT NOT NULL,
                registry_id INTEGER NOT NULL,
                field_code TEXT NOT NULL,
                value TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
                FOREIGN KEY (registry_id, field_code) REFERENCES registry_fields(registry_id, field_code) ON DELETE CASCADE,
                UNIQUE(report_uuid, registry_id, field_code)
            )");
            $pdo->exec("INSERT INTO registry_field_values
                (id, report_uuid, registry_id, field_code, value, created_at, updated_at)
                SELECT id, report_uuid, registry_id, field_code, value, created_at, updated_at
                FROM {$legacyTable}");
            $pdo->exec('DROP TABLE ' . $legacyTable);
            $createdIndexes = [];
            foreach ($indexesToRecreate as $definition) {
                $pdo->exec($definition);
                if (preg_match('/\bINDEX\s+(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|([A-Za-z_][A-Za-z0-9_]*))/i', $definition, $matches) !== 1) {
                    throw new RuntimeException('Impossible d’extraire le nom d’un index à recréer.');
                }
                $indexName = null;
                for ($captureIndex = 1; $captureIndex <= 4; $captureIndex++) {
                    if (!array_key_exists($captureIndex, $matches)) {
                        continue;
                    }
                    $capture = $matches[$captureIndex];
                    if ($capture !== '') {
                        $indexName = $capture;
                        break;
                    }
                }
                if ($indexName === null) {
                    throw new RuntimeException('Impossible d’extraire le nom d’un index à recréer.');
                }
                $createdIndexes[$indexName] = true;
            }
            if (!isset($createdIndexes['idx_registry_field_values_registry'])) {
                $pdo->exec('CREATE INDEX "idx_registry_field_values_registry" ON registry_field_values("registry_id", "field_code")');
            }
            $foreignKeyCheck = $pdo->query('PRAGMA foreign_key_check(registry_field_values)');
            if ($foreignKeyCheck === false) {
                throw new RuntimeException('Impossible de vérifier les clés étrangères de registry_field_values.');
            }
            $foreignKeyErrors = $foreignKeyCheck->fetchAll(PDO::FETCH_ASSOC);
            if ($foreignKeyErrors !== []) {
                throw new RuntimeException('La migration de registry_field_values a détecté des violations de clés étrangères.');
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    // ── email_outbox table (transactional SMTP outbox) ─────────────────────
    // Même DDL que schema.sql (source de vérité pour les installations
    // fraîches) — cette clause IF NOT EXISTS couvre les bases existantes :
    // migrateTables() tourne à chaque requête (migrateSchema()).
    // enqueue() est idempotent via UNIQUE(dedup_key) ; claimBatch() réclame
    // atomiquement (pending → processing) et le backoff s'appuie sur
    // next_attempt_at. La sentinelle d'anonymisation n'y est jamais écrite.
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_outbox (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        dedup_key       TEXT NOT NULL UNIQUE,
        recipient       TEXT NOT NULL,
        subject         TEXT NOT NULL,
        body            TEXT NOT NULL,
        headers         TEXT NOT NULL DEFAULT '',
        status          TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','processing','sent','failed')),
        attempts        INTEGER NOT NULL DEFAULT 0,
        last_error      TEXT,
        next_attempt_at TEXT,
        processing_at   TEXT,
        sent_at         TEXT,
        failed_at       TEXT,
        created_at      TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_email_outbox_claim ON email_outbox(status, next_attempt_at)');

    // ── Sessions table (SQLite-backed session handler) ─────────────────────
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

    // ── Seed default registries if table is empty ──────────────────────────
    $countResult = $pdo->query('SELECT COUNT(*) FROM registries');
    $count = $countResult !== false ? (int) $countResult->fetchColumn() : 0;
    if ($count === 0) {
        $pdo->exec("INSERT INTO registries (code, label, short_label, description, icon, color_theme, is_enabled, is_system, sort_order, default_visibility, notify_chsct) VALUES
            ('rsst', 'Santé et Sécurité au Travail', 'RSST', 'Signalements généraux SST', '📋', 'rsst', 1, 1, 1, 'agent_choice', 0),
            ('rami', 'Agressions, Menaces et Incivilités', 'RAMI', 'Agressions verbales et physiques', '🚨', 'rami', 1, 0, 2, 'agent_choice', 0),
            ('dgi', 'Danger Grave et Imminent', 'DGI', 'Dangers immédiats pour la santé', '🔴', 'dgi', 1, 0, 3, 'agent_choice', 1)
        ");
    }

    // ── Ensure system registres are always enabled ─────────────────────────
    $pdo->exec('UPDATE registries SET is_enabled = 1 WHERE is_system = 1 AND is_enabled = 0');
}
