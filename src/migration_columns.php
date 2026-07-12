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
    // ── Add is_confidential column to reports ──────────────────────────────
    try {
        $cols = $pdo->query('PRAGMA table_info(reports)')->fetchAll();
        $hasConfidential = false;
        foreach ($cols as $col) {
            if ($col['name'] === 'is_confidential') {
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
        $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll();
        foreach ($cols as $col) {
            if ($col['name'] === 'site_id' && $col['notnull'] === 1) {
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
        $cols = $pdo->query('PRAGMA table_info(reports)')->fetchAll();
        $hasUuid = false;
        foreach ($cols as $col) {
            if ($col['name'] === 'uuid') {
                $hasUuid = true;
                break;
            }
        }
        if (!$hasUuid) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN uuid TEXT');
            // Backfill existing reports with UUIDs
            $stmt = $pdo->query('SELECT id FROM reports WHERE uuid IS NULL');
            while ($row = $stmt->fetch()) {
                $hex = bin2hex(random_bytes(16));
                $uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-' . dechex((hexdec(substr($hex, 16, 2)) & 0x3F) | 0x80) . substr($hex, 18, 2) . '-' . substr($hex, 20, 12);
                $upd = $pdo->prepare('UPDATE reports SET uuid = :uuid WHERE id = :id');
                $upd->execute([':uuid' => $uuid, ':id' => $row['id']]);
            }
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_reports_uuid ON reports(uuid)');
        }
    } catch (Exception $e) {
        error_log('Migration warning for reports.uuid: ' . $e->getMessage());
    }
    // ── Migrate report_responses: report_id → report_uuid ──────────────────
    try {
        $cols = $pdo->query('PRAGMA table_info(report_responses)')->fetchAll();
        $hasReportUuid = false;
        foreach ($cols as $col) {
            if ($col['name'] === 'report_uuid') {
                $hasReportUuid = true;
                break;
            }
        }
        if (!$hasReportUuid) {
            $pdo->exec('ALTER TABLE report_responses ADD COLUMN report_uuid TEXT');
            // Backfill: map old report_id → report uuid
            $stmt = $pdo->query('SELECT rr.id, rr.report_id FROM report_responses rr WHERE rr.report_uuid IS NULL');
            while ($row = $stmt->fetch()) {
                $uuidStmt = $pdo->prepare('SELECT uuid FROM reports WHERE id = :id');
                $uuidStmt->execute([':id' => $row['report_id']]);
                $reportUuid = $uuidStmt->fetchColumn();
                if ($reportUuid) {
                    $upd = $pdo->prepare('UPDATE report_responses SET report_uuid = :uuid WHERE id = :id');
                    $upd->execute([':uuid' => $reportUuid, ':id' => $row['id']]);
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
        $cols = $pdo->query('PRAGMA table_info(reports)')->fetchAll();
        $hasId = false;
        $hasUuid = false;
        foreach ($cols as $col) {
            if ($col['name'] === 'id') {
                $hasId = true;
            }
            if ($col['name'] === 'uuid') {
                $hasUuid = true;
            }
        }

        // Backfill NULL UUIDs even if column already exists (partial migration)
        if ($hasUuid) {
            $idCol = $hasId ? 'id' : 'rowid';
            $stmt = $pdo->query("SELECT $idCol FROM reports WHERE uuid IS NULL");
            while ($row = $stmt->fetch()) {
                $hex = bin2hex(random_bytes(16));
                $uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-' . dechex((hexdec(substr($hex, 16, 2)) & 0x3F) | 0x80) . substr($hex, 18, 2) . '-' . substr($hex, 20, 12);
                $upd = $pdo->prepare("UPDATE reports SET uuid = :uuid WHERE $idCol = :id");
                $upd->execute([':uuid' => $uuid, ':id' => $row[$idCol]]);
            }
        }

        // Fix UUIDs with invalid variant bits (4th group starts with c-f)
        if ($hasUuid) {
            $stmt = $pdo->query('SELECT uuid FROM reports WHERE uuid IS NOT NULL');
            $fixes = [];
            while ($row = $stmt->fetch()) {
                $oldUuid = $row['uuid'];
                $variantNibble = strtolower((string) $oldUuid[19]);
                if (in_array($variantNibble, ['c', 'd', 'e', 'f'])) {
                    $nibbleMap = ['c' => '8', 'd' => '9', 'e' => 'a', 'f' => 'b'];
                    $newUuid = substr((string) $oldUuid, 0, 19) . $nibbleMap[$variantNibble] . substr((string) $oldUuid, 20);
                    $fixes[] = ['old' => $oldUuid, 'new' => $newUuid];
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
        $cols = $pdo->query('PRAGMA table_info(reports)')->fetchAll();
        $hasAttachment = false;
        foreach ($cols as $col) {
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
        $cols = $pdo->query('PRAGMA table_info(reports)')->fetchAll();
        $hasNatureAuteur = false;
        $hasTypeActe = false;
        foreach ($cols as $col) {
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
        $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll();
        $hasSiteChosenAt = false;
        foreach ($cols as $col) {
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
        $cols = $pdo->query('PRAGMA table_info(reports)')->fetchAll();
        $hasConsentSyndicat = false;
        foreach ($cols as $col) {
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
        $cols = $pdo->query('PRAGMA table_info(reports)')->fetchAll();
        $existingCols = array_column($cols, 'name');
        $newCols = ['pole', 'service_affectation', 'telephone_mobile', 'site_text'];
        foreach ($newCols as $colName) {
            if (!in_array($colName, $existingCols)) {
                $pdo->exec("ALTER TABLE reports ADD COLUMN $colName TEXT");
            }
        }
    } catch (Exception $e) {
        error_log('Migration warning for reports new fields: ' . $e->getMessage());
    }
    // ── Add response attachment columns to report_responses ─────────────────
    try {
        $cols = $pdo->query('PRAGMA table_info(report_responses)')->fetchAll();
        $existingCols = array_column($cols, 'name');
        $newRespCols = ['attachment_blob', 'attachment_name', 'attachment_mime'];
        foreach ($newRespCols as $colName) {
            if (!in_array($colName, $existingCols)) {
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
        $cols = $pdo->query('PRAGMA table_info(audit_log)')->fetchAll();
        $existingCols = array_column($cols, 'name');
        if (!in_array('target_uuid', $existingCols)) {
            $pdo->exec('ALTER TABLE audit_log ADD COLUMN target_uuid TEXT');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_target_uuid ON audit_log(target_uuid)');
        }
    } catch (Exception $e) {
        error_log('Migration warning for audit_log.target_uuid: ' . $e->getMessage());
    }
}
