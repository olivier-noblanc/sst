<?php

/**
 * Migration — Column Additions & Data Fixes
 *
 * Adds missing columns to existing tables and performs data migrations
 * (e.g. UUID backfill, variant-bit fixes). Every operation is idempotent:
 * it checks whether the column already exists before attempting ALTER TABLE.
 *
 * @param PDO $pdo
 */
function migrateColumns(PDO $pdo): void
{
    /** @param string $table @return list<array<string, mixed>> */
    $pragma = static function (PDO $pdo, string $table): array {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        $rows = $stmt !== false ? $stmt->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    };
    // ── Add is_confidential column to reports ──────────────────────────────
    try {
        $stmt = $pdo->query('PRAGMA table_info(reports)');
        $cols = $stmt !== false ? $stmt->fetchAll() : [];
        $hasConfidential = false;
        foreach (is_array($cols) ? $cols : [] as $col) {
            if (is_array($col) && ($col['name'] ?? '') === 'is_confidential') {
                $hasConfidential = true;
                break;
            }
        }
        if (!$hasConfidential) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN is_confidential INTEGER NOT NULL DEFAULT 1');
            // If app_agent_visibility was 'site', existing reports were public
            $vis = getConfig('app_agent_visibility', 'confidential');
            if ($vis === 'site') {
                $pdo->exec('UPDATE reports SET is_confidential = 0');
            }
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_is_confidential ON reports(is_confidential)');
        }
    } catch (Exception $e) {
        error_log('Migration warning for reports.is_confidential: ' . $e->getMessage());
    }
    // ── Make users.site_id nullable for existing databases ─────────────────
    try {
        $stmt = $pdo->query('PRAGMA table_info(users)');
        $cols = $stmt !== false ? $stmt->fetchAll() : [];
        foreach (is_array($cols) ? $cols : [] as $col) {
            if (is_array($col) && ($col['name'] ?? '') === 'site_id' && ($col['notnull'] ?? 0) === 1) {
                $pdo->exec('CREATE TABLE users_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    nom TEXT NOT NULL,
                    prenom TEXT NOT NULL,
                    email TEXT,
                    role TEXT NOT NULL DEFAULT \'agent\',
                    site_id INTEGER,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                    updated_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
                    FOREIGN KEY (site_id) REFERENCES sites(id)
                )');
                $pdo->exec('INSERT INTO users_new SELECT * FROM users');
                $pdo->exec('DROP TABLE users');
                $pdo->exec('ALTER TABLE users_new RENAME TO users');
                break;
            }
        }
    } catch (Exception $e) {
        error_log('Migration warning for users.site_id nullable: ' . $e->getMessage());
    }
    // ── Add uuid column to reports (for non-guessable URLs) ────────────────
    try {
        $cols = $pragma($pdo, 'reports');
        $hasUuid = false;
        foreach ($cols as $col) {
            if (is_array($col) && ($col['name'] ?? '') === 'uuid') {
                $hasUuid = true;
                break;
            }
        }
        if (!$hasUuid) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN uuid TEXT');
            // Backfill existing reports with UUIDs
            $stmt = $pdo->query('SELECT id FROM reports WHERE uuid IS NULL');
            if ($stmt !== false) {
                while ($row = $stmt->fetch()) {
                    if (!is_array($row)) { continue; }
                    /** @var array{id: int} $row */
                    $hex = bin2hex(random_bytes(16));
                    $uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-' . dechex((hexdec(substr($hex, 16, 2)) & 0x3F) | 0x80) . substr($hex, 18, 2) . '-' . substr($hex, 20, 12);
                    $upd = $pdo->prepare('UPDATE reports SET uuid = :uuid WHERE id = :id');
                    $upd->execute([':uuid' => $uuid, ':id' => $row['id']]);
                }
            }
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_reports_uuid ON reports(uuid)');
        }
    } catch (Exception $e) {
        error_log('Migration warning for reports.uuid: ' . $e->getMessage());
    }
    // ── Migrate report_responses: report_id → report_uuid ──────────────────
    try {
        $cols = $pragma($pdo, 'report_responses');
        $hasReportUuid = false;
        foreach ($cols as $col) {
            /** @var array<string, mixed> $col */
            if ($col['name'] === 'report_uuid') {
                $hasReportUuid = true;
                break;
            }
        }
        if (!$hasReportUuid) {
            $pdo->exec('ALTER TABLE report_responses ADD COLUMN report_uuid TEXT');
            // Backfill: map old report_id → report uuid
            $stmt = $pdo->query('SELECT rr.id, rr.report_id FROM report_responses rr WHERE rr.report_uuid IS NULL');
            if ($stmt !== false) {
                while ($row = $stmt->fetch()) {
                    /** @var array{id: int, report_id: int}|false $row */
                    if (!is_array($row)) { continue; }
                    $uuidStmt = $pdo->prepare('SELECT uuid FROM reports WHERE id = :id');
                    $uuidStmt->execute([':id' => $row['report_id']]);
                    $reportUuid = $uuidStmt->fetchColumn();
                    if ($reportUuid !== false) {
                        $upd = $pdo->prepare('UPDATE report_responses SET report_uuid = :uuid WHERE id = :id');
                        $upd->execute([':uuid' => $reportUuid, ':id' => $row['id']]);
                    }
                }
            }
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_report_responses_report_uuid ON report_responses(report_uuid)');
        }
    } catch (Exception $e) {
        error_log('Migration warning for report_responses.report_uuid: ' . $e->getMessage());
    }
    // ── Fix UUIDs with invalid variant bits ─────────────────────────────────
    // Old generateUuid() used | 0x8 instead of (& 0x3F | 0x80),
    // producing UUIDs whose 4th group starts with c-f instead of 8-b.
    try {
        $cols = $pragma($pdo, 'reports');
        $hasId = false;
        $hasUuid = false;
        foreach ($cols as $col) {
            /** @var array<string, mixed> $col */
            if ($col['name'] === 'id') {
                $hasId = true;
            }
            if (is_array($col) && ($col['name'] ?? '') === 'uuid') {
                $hasUuid = true;
            }
        }

        // Backfill NULL UUIDs even if column already exists (partial migration)
        if ($hasUuid) {
            $idCol = $hasId ? 'id' : 'rowid';
            $stmt = $pdo->query("SELECT $idCol FROM reports WHERE uuid IS NULL");
            if ($stmt !== false) {
                while ($row = $stmt->fetch()) {
                    /** @var array<string, int>|false $row */
                    if (!is_array($row)) { continue; }
                    $hex = bin2hex(random_bytes(16));
                    $uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-' . dechex((hexdec(substr($hex, 16, 2)) & 0x3F) | 0x80) . substr($hex, 18, 2) . '-' . substr($hex, 20, 12);
                    $upd = $pdo->prepare("UPDATE reports SET uuid = :uuid WHERE $idCol = :id");
                    $upd->execute([':uuid' => $uuid, ':id' => $row[$idCol]]);
                }
            }
        }

        // Fix UUIDs with invalid variant bits (4th group starts with c-f)
        if ($hasUuid) {
            $stmt = $pdo->query('SELECT uuid FROM reports WHERE uuid IS NOT NULL');
            $fixes = [];
            if ($stmt !== false) {
                while ($row = $stmt->fetch()) {
                    /** @var array{uuid: string}|false $row */
                    if (!is_array($row)) { continue; }
                    /** @var string */
                    $oldUuid = $row['uuid'] ?? '';
                    $variantNibble = strtolower($oldUuid[19]);
                    if (in_array($variantNibble, ['c', 'd', 'e', 'f'], true)) {
                        $nibbleMap = ['c' => '8', 'd' => '9', 'e' => 'a', 'f' => 'b'];
                        $newUuid = substr($oldUuid, 0, 19) . $nibbleMap[$variantNibble] . substr($oldUuid, 20);
                        $fixes[] = ['old' => $oldUuid, 'new' => $newUuid];
                    }
                }
            }
            foreach ($fixes as $fix) {
                $upd1 = $pdo->prepare('UPDATE report_responses SET report_uuid = :new WHERE report_uuid = :old');
                $upd1->execute([':new' => $fix['new'], ':old' => $fix['old']]);
                $upd2 = $pdo->prepare('UPDATE reports SET uuid = :new WHERE uuid = :old');
                $upd2->execute([':new' => $fix['new'], ':old' => $fix['old']]);
            }
            if (count($fixes) > 0) {
                error_log('[SST-MIGRATION] Fixed ' . count($fixes) . ' report UUIDs with invalid variant bits.');
            }
        }
    } catch (Exception $e) {
        error_log('Migration warning for UUID variant fix: ' . $e->getMessage());
    }
    // ── Add attachment columns to reports ───────────────────────────────────
    try {
        $cols = $pragma($pdo, 'reports');
        $hasAttachment = false;
        foreach ($cols as $col) {
            /** @var array<string, mixed> $col */
            if ($col['name'] === 'attachment_blob') {
                $hasAttachment = true;
                break;
            }
        }
        if (!$hasAttachment) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN attachment_blob BLOB');
            $pdo->exec('ALTER TABLE reports ADD COLUMN attachment_name TEXT');
            $pdo->exec('ALTER TABLE reports ADD COLUMN attachment_mime TEXT');
        }
    } catch (Exception $e) {
        error_log('Migration warning for attachment columns: ' . $e->getMessage());
    }
    // ── Add RAMI structured fields: nature_auteur and type_acte ─────────────
    try {
        $cols = $pragma($pdo, 'reports');
        $hasNatureAuteur = false;
        $hasTypeActe = false;
        foreach ($cols as $col) {
            /** @var array<string, mixed> $col */
            if ($col['name'] === 'nature_auteur') {
                $hasNatureAuteur = true;
            }
            if ($col['name'] === 'type_acte') {
                $hasTypeActe = true;
            }
        }
        if (!$hasNatureAuteur) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN nature_auteur TEXT');
        }
        if (!$hasTypeActe) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN type_acte TEXT');
        }
    } catch (Exception $e) {
        error_log('Migration warning for RAMI structured fields: ' . $e->getMessage());
    }
    // ── Add site_chosen_at column to users ─────────────────────────────────
    // Tracks when the agent first chose their site, enabling a 7-day grace period
    // for self-service site changes before requiring supervisor intervention.
    try {
        $cols = $pragma($pdo, 'users');
        $hasSiteChosenAt = false;
        foreach ($cols as $col) {
            /** @var array<string, mixed> $col */
            if ($col['name'] === 'site_chosen_at') {
                $hasSiteChosenAt = true;
                break;
            }
        }
        if (!$hasSiteChosenAt) {
            $pdo->exec('ALTER TABLE users ADD COLUMN site_chosen_at TEXT');
            // Backfill: use updated_at as approximation (no grace period for legacy users)
            $pdo->exec('UPDATE users SET site_chosen_at = updated_at WHERE site_id IS NOT NULL AND site_chosen_at IS NULL');
        }
    } catch (Exception $e) {
        error_log('Migration warning for users.site_chosen_at: ' . $e->getMessage());
    }
    // ── Add consent_syndicat column to reports ─────────────────────────────
    try {
        $cols = $pragma($pdo, 'reports');
        $hasConsentSyndicat = false;
        foreach ($cols as $col) {
            /** @var array<string, mixed> $col */
            if ($col['name'] === 'consent_syndicat') {
                $hasConsentSyndicat = true;
                break;
            }
        }
        if (!$hasConsentSyndicat) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN consent_syndicat INTEGER NOT NULL DEFAULT 0');
        }
    } catch (Exception $e) {
        error_log('Migration warning for reports.consent_syndicat: ' . $e->getMessage());
    }
    // ── Add pole, service_affectation, telephone_mobile, site_text ──────────
    try {
        $cols = $pragma($pdo, 'reports');
        $existingCols = array_column($cols, 'name');
        $newCols = ['pole', 'service_affectation', 'telephone_mobile', 'site_text'];
        foreach ($newCols as $colName) {
            if (!in_array($colName, $existingCols, true)) {
                $pdo->exec("ALTER TABLE reports ADD COLUMN $colName TEXT");
            }
        }
    } catch (Exception $e) {
        error_log('Migration warning for reports new fields: ' . $e->getMessage());
    }
    // ── Add response attachment columns to report_responses ─────────────────
    try {
        $cols = $pragma($pdo, 'report_responses');
        $existingCols = array_column($cols, 'name');
        $newRespCols = ['attachment_blob', 'attachment_name', 'attachment_mime'];
        foreach ($newRespCols as $colName) {
            if (!in_array($colName, $existingCols, true)) {
                $pdo->exec("ALTER TABLE report_responses ADD COLUMN $colName " . ($colName === 'attachment_blob' ? 'BLOB' : 'TEXT'));
            }
        }
    } catch (Exception $e) {
        error_log('Migration warning for report_responses attachment columns: ' . $e->getMessage());
    }
    // ── Add target_uuid column to audit_log ────────────────────────────────
    // Reports use uuid (TEXT) as primary key, not an integer id.
    // target_id is always 0 for report entries — target_uuid stores the actual UUID.
    try {
        $cols = $pragma($pdo, 'audit_log');
        $existingCols = array_column($cols, 'name');
        if (!in_array('target_uuid', $existingCols, true)) {
            $pdo->exec('ALTER TABLE audit_log ADD COLUMN target_uuid TEXT');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_target_uuid ON audit_log(target_uuid)');
        }
    } catch (Exception $e) {
        error_log('Migration warning for audit_log.target_uuid: ' . $e->getMessage());
    }
    // ── Remove legacy wordcloud config key ──────────────────────────────────
    // app_wordcloud_words (plaintext format) was replaced by word_cloud_words (JSON).
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM config_app WHERE cle = 'app_wordcloud_words'");
        $count = $stmt !== false ? (int) $stmt->fetchColumn() : 0;
        if ($count > 0) {
            $pdo->exec("DELETE FROM config_app WHERE cle = 'app_wordcloud_words'");
            error_log('[SST-MIGRATION] Removed legacy config key app_wordcloud_words.');
        }
    } catch (Exception $e) {
        error_log('Migration warning for wordcloud cleanup: ' . $e->getMessage());
    }
    // ── Add CHECK constraints on reports.type and reports.etat ──────────────
    // schema.sql already defines these for new installs; this migration
    // applies them to existing databases via table rebuild (SQLite limitation:
    // ALTER TABLE cannot add CHECK constraints).
    // ALWAYS runs — no schema_version guard, no silent skip on violations.
    try {
        // 1. Verify no existing rows violate the constraints
        $badTypes = $pdo->query("SELECT DISTINCT type FROM reports WHERE type NOT IN ('rsst','rami','dgi')");
        $badTypeRows = ($badTypes !== false) ? $badTypes->fetchAll() : [];
        if (!empty($badTypeRows)) {
            $vals = array_column($badTypeRows, 'type');
            error_log('[SST-MIGRATION] CHECK constraint FAILED: invalid type values found: ' . implode(', ', $vals));
            throw new \RuntimeException('CHECK constraint violation: invalid type values: ' . implode(', ', $vals));
        }
        $badEtats = $pdo->query("SELECT DISTINCT etat FROM reports WHERE etat NOT IN ('nouveau','en_cours','traite','reouvert','abandonne')");
        $badEtatRows = ($badEtats !== false) ? $badEtats->fetchAll() : [];
        if (!empty($badEtatRows)) {
            $vals = array_column($badEtatRows, 'etat');
            error_log('[SST-MIGRATION] CHECK constraint FAILED: invalid etat values found: ' . implode(', ', $vals));
            throw new \RuntimeException('CHECK constraint violation: invalid etat values: ' . implode(', ', $vals));
        }
        // 2. Backup before destructive migration
        backupBeforeMigration($pdo);
        // 3. Get current column list via PRAGMA
        $colStmt = $pdo->query('PRAGMA table_info(reports)');
        $columns = ($colStmt !== false) ? $colStmt->fetchAll() : [];
        // Build column definitions for CREATE TABLE, preserving exact schema
        $colDefs = [];
        foreach ($columns as $col) {
            if (!is_array($col)) { continue; }
            /** @var array{name: string, type: string, notnull: int, dflt_value: mixed, pk: int} $col */
            $def = $col['name'] . ' ' . $col['type'];
            if ($col['pk']) {
                $def .= ' PRIMARY KEY';
            }
            if ($col['notnull'] && !$col['pk']) {
                $def .= ' NOT NULL';
            }
            if ($col['dflt_value'] !== null) {
                $def .= ' DEFAULT ' . $col['dflt_value'];
            }
            $colDefs[] = $def;
        }
        // Add CHECK constraints
        $colDefs[] = "CHECK (type IN ('rsst','rami','dgi'))";
        $colDefs[] = "CHECK (etat IN ('nouveau','en_cours','traite','reouvert','abandonne'))";
        // Get foreign keys
        $fkStmt = $pdo->query('PRAGMA foreign_key_list(reports)');
        $fks = ($fkStmt !== false) ? $fkStmt->fetchAll() : [];
        $fkClauses = [];
        foreach ($fks as $fk) {
            if (!is_array($fk)) { continue; }
            /** @var array{from: string, table: string, to: string} $fk */
            $fkClauses[] = "FOREIGN KEY ({$fk['from']}) REFERENCES {$fk['table']}({$fk['to']})";
        }
        $allDefs = array_merge($colDefs, $fkClauses);
        $createSql = 'CREATE TABLE IF NOT EXISTS reports_new (' . implode(', ', $allDefs) . ')';
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
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_reports_uuid ON reports(uuid)');
            error_log('[SST-MIGRATION] Applied CHECK constraints on reports.type and reports.etat.');
    } catch (Exception $e) {
        error_log('[SST-MIGRATION] CHECK constraint migration failed: ' . $e->getMessage());
    }
}
