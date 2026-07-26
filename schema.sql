-- ============================================================
-- DREETS BFC SST Application — Database Schema
-- Version 2.6.1
-- ============================================================

-- ============================================================
-- Table: sites
-- Stores the Unités Régionales (UR)
-- Only UR21 and UR25 by default — more can be added via Settings
-- ============================================================
CREATE TABLE IF NOT EXISTS sites (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    code            TEXT NOT NULL UNIQUE,           -- e.g. "UR21", "UR25", "SIEGE"
    nom             TEXT NOT NULL,                   -- e.g. "UR Côte-d'Or"
    departement     TEXT,                            -- e.g. "Côte-d'Or"
    is_active       INTEGER NOT NULL DEFAULT 1,      -- 0 = désactivé, n'apparaît plus dans les listes
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- Table: users
-- Application users. Synced from IIS AUTH_USER or created in dev.
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    username        TEXT NOT NULL UNIQUE,            -- Windows login (e.g. "jean.martin")
    nom             TEXT NOT NULL,                   -- Last name
    prenom          TEXT NOT NULL,                   -- First name
    email           TEXT,                            -- Email address
    role            TEXT NOT NULL DEFAULT 'agent',   -- 'agent'|'superviseur'|'chsct'
    site_id         INTEGER,                         -- FK to sites (NULL until agent chooses on first login)
    site_chosen_at  TEXT,                            -- When the agent first chose their site (for 7-day grace period)
    is_active       INTEGER NOT NULL DEFAULT 1,      -- Soft delete: 0 = deactivated
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

-- ============================================================
-- Table: reports
-- Core table for all three registries.
-- PK = uuid (non-guessable, safe for URLs)
-- ============================================================
CREATE TABLE IF NOT EXISTS reports (
    uuid            TEXT PRIMARY KEY,                -- UUID v4 — non-guessable public identifier
    reference       TEXT NOT NULL UNIQUE,            -- e.g. "rsst-25-001"
    type            TEXT NOT NULL,                    -- Registry code (references registries.code)
    objet           TEXT NOT NULL,                   -- Subject line, max 100 chars
    description     TEXT NOT NULL,                   -- Full description
    date_evenement  TEXT NOT NULL,                   -- Date of the event (ISO 8601 date)
    heure_evenement TEXT,                            -- Time of the event (HH:MM)
    lieu            TEXT,                            -- Location of the event
    -- Declarant (person who filed the report)
    declarant_id    INTEGER NOT NULL,                -- FK to users
    declarant_nom   TEXT NOT NULL,                   -- Denormalized last name for speed
    declarant_prenom TEXT NOT NULL,                  -- Denormalized first name
    -- "Pour le compte de" (RAMI only — report filed for another agent)
    pour_compte_de  INTEGER,                         -- FK to users (nullable, RAMI only). Audit #16: currently unused — pour_compte_nom/prenom are free-text, not linked to a user ID. Kept for future enhancement (auto-link agent via email).
    pour_compte_nom TEXT,                            -- Denormalized name of the other agent
    pour_compte_prenom TEXT,                         -- Denormalized first name
    -- RAMI structured fields (optional — for statistics by nature/type)
    nature_auteur   TEXT,                            -- RAMI only: 'usager'|'collegue'|'hierarchie'|'tiers' (nullable)
    type_acte       TEXT,                            -- RAMI only: 'verbal'|'physique'|'moral'|'sexiste'|'autre' (nullable)
    -- Assignment
    site_id         INTEGER,                         -- FK to sites (UR où l'événement a eu lieu). NULL si mode sans site (isNoSiteMode()).
    site_text       TEXT,                            -- Free-text site name (autocomplete from history)
    -- Declarant additional info
    pole            TEXT,                            -- Pôle d'affectation
    service_affectation TEXT,                        -- Service d'affectation
    telephone_mobile TEXT,                           -- Numéro de téléphone mobile
    -- Confidentiality
    consent_syndicat INTEGER NOT NULL DEFAULT 0,   -- 1 = consentement syndicat donné, 0 = non
    is_confidential INTEGER NOT NULL DEFAULT 1,     -- 1 = confidentiel (défaut), 0 = public
    -- State management
    etat            TEXT NOT NULL DEFAULT 'nouveau'   -- 'nouveau'|'en_cours'|'traite'|'reouvert'|'abandonne'
                        CHECK (etat IN ('nouveau','en_cours','traite','reouvert','abandonne')),
    -- Respondent (superviseur who handled the report)
    repondant_id    INTEGER,                         -- FK to users (nullable)
    date_reponse    TEXT,                            -- When supervisor responded
    reponse         TEXT,                            -- Supervisor's response text
    -- Attachment (mono-file, stored as BLOB)
    attachment_blob  BLOB,                           -- File content (max ~10 MB)
    attachment_name  TEXT,                            -- Original filename (e.g. "photo_danger.jpg")
    attachment_mime  TEXT,                            -- MIME type (e.g. "image/jpeg")
    -- Timestamps
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (declarant_id) REFERENCES users(id),
    FOREIGN KEY (pour_compte_de) REFERENCES users(id),
    FOREIGN KEY (repondant_id) REFERENCES users(id),
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

-- ============================================================
-- Table: report_responses
-- Tracks multiple responses/updates to a report (audit trail).
-- ============================================================
CREATE TABLE IF NOT EXISTS report_responses (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    report_uuid     TEXT NOT NULL,                   -- FK to reports(uuid)
    user_id         INTEGER,                          -- FK to users (the supervisor) — nullable for RGPD anonymization (audit #8)
    reponse         TEXT NOT NULL,                   -- Response text
    nouvel_etat     TEXT,                            -- State change if any: 'en_cours'|'traite'
    attachment_blob BLOB,                            -- Optional file attached to response
    attachment_name TEXT,                            -- Original filename
    attachment_mime TEXT,                            -- MIME type
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================================
-- Table: notification_settings
-- Email addresses for notifications per site and globally.
-- ============================================================
CREATE TABLE IF NOT EXISTS notification_settings (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id         INTEGER,                         -- FK to sites. NULL = global setting
    type            TEXT NOT NULL,                   -- 'site'|'global'
    registry        TEXT NOT NULL,                   -- 'rsst'|'rami'|'dgi'|'all'
    email           TEXT NOT NULL,                   -- Email address
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

-- ============================================================
-- Table: report_sequence
-- Auto-incrementing sequence per registry+year for references.
-- ============================================================
CREATE TABLE IF NOT EXISTS report_sequence (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    type            TEXT NOT NULL,                   -- 'rsst'|'rami'|'dgi'
    year            INTEGER NOT NULL,                -- e.g. 2025
    last_sequence   INTEGER NOT NULL DEFAULT 0,      -- Last used sequence number
    UNIQUE(type, year)
);

-- ============================================================
-- Table: config_app
-- Application configuration (key-value store, editable via UI)
-- ============================================================
CREATE TABLE IF NOT EXISTS config_app (
    cle TEXT PRIMARY KEY,
    valeur TEXT,
    type TEXT DEFAULT 'text',        -- 'text', 'number', 'password', 'email'
    categorie TEXT DEFAULT 'app',     -- 'app', 'smtp'
    libelle TEXT,                     -- label affiché dans l'UI
    modifiable INTEGER DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Default configuration values
-- NOTE: app_version is NOT stored in the database — it is read from CHANGELOG.md by getAppVersion()
INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES
    ('app_nom_organisation', 'DREETS BFC', 'text', 'app', 'Nom de l''organisation', 1),
    ('app_nom_complet', 'DREETS Bourgogne-Franche-Comté', 'text', 'app', 'Nom complet', 1),
    ('app_label_unite', 'UR', 'text', 'app', 'Libellé des unités (UD, UR, etc.)', 1),
    ('app_superviseur_usernames', '', 'text', 'app', 'Logins Windows des superviseurs (séparés par virgule, ex: jean.martin, sophie.dupont). Ces utilisateurs seront automatiquement promus Superviseur lors de leur première connexion via IIS. Utile pour une première installation.', 1),
    ('app_agent_see_only_own', '0', 'text', 'app', 'Si activé (1), les agents ne voient que leurs propres signalements. (Obsolète : utilisez app_report_visibility)', 1),
    ('app_agent_visibility', 'agent_choice', 'text', 'app', 'Obsolète : utilisez app_report_visibility', 1),
    ('app_report_visibility', 'agent_choice', 'text', 'app', 'Visibilité des signalements : "confidential" (l''agent ne voit que ses propres signalements), "agent_choice" (l''agent choisit au cas par cas, confidentiel par défaut), "public" (tous les signalements du site sont visibles par tous les agents).', 1),
    ('smtp_host', '', 'text', 'smtp', 'Serveur SMTP', 1),
    ('smtp_port', '25', 'number', 'smtp', 'Port SMTP', 1),
    ('smtp_user', '', 'text', 'smtp', 'Utilisateur SMTP', 1),
    ('smtp_pass', '', 'password', 'smtp', 'Mot de passe SMTP', 1),
    ('smtp_from', '', 'email', 'smtp', 'Adresse d''expédition', 1),
    ('smtp_encryption', 'none', 'text', 'smtp', 'Chiffrement (none, tls, starttls)', 1),
    ('app_report_visibility_rsst', 'public', 'text', 'app', 'Visibilité des signalements RSST : "confidential", "agent_choice" ou "public". Par défaut "public" conformément au décret 82-453 art. 3-2 (registre consultable par tout agent).', 1),
    ('app_report_visibility_rami', '', 'text', 'app', 'Visibilité des signalements RAMI. Laisser vide pour utiliser la visibilité globale. Valeurs : "confidential", "agent_choice" ou "public".', 1),
    ('app_report_visibility_dgi', '', 'text', 'app', 'Visibilité des signalements DGI. Laisser vide pour utiliser la visibilité globale. Valeurs : "confidential", "agent_choice" ou "public".', 1),
    ('app_retention_years', '0', 'number', 'app', 'Durée de conservation des signalements traités/abandonnés (en années). 0 = désactivé (conservation illimitée). Doit être fixé après validation du DPO.', 1),
    ('app_dpo_contact', '', 'text', 'app', 'Coordonnées du Délégué à la Protection des Données (DPO) — affichées dans la mention RGPD du préambule. Ex : dpo@dreets-bfc.gouv.fr', 1),
    ('app_alert_delay_days', '0', 'number', 'app', 'Délai d''alerte en jours pour les signalements restés à l''état « Nouveau ». 0 = désactivé. Si > 0, un e-mail est envoyé aux superviseurs du site lorsqu''un signalement dépasse ce délai (via tools/check_delays.php en CRON).', 1);


-- ============================================================
-- Table: registries
-- Dynamic registry definitions (RSST, RAMI, DGI, and custom).
-- The 'rsst' registry is always system=1 (cannot be deleted).
-- New registries are created by duplicating RSST as template.
-- ============================================================
CREATE TABLE IF NOT EXISTS registries (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    code                TEXT NOT NULL UNIQUE,            -- 'rsst', 'rami', 'dgi', or custom
    label               TEXT NOT NULL,                   -- full name: 'Registre de Santé...'
    short_label         TEXT NOT NULL,                   -- short: 'RSST'
    description         TEXT,                            -- for home cards
    icon                TEXT NOT NULL DEFAULT '📋',
    color_theme         TEXT NOT NULL DEFAULT 'rsst',    -- CSS theme key (10 themes)
    btn_label           TEXT,                            -- button label on home cards (nullable = default)
    is_enabled          INTEGER NOT NULL DEFAULT 1,
    is_system           INTEGER NOT NULL DEFAULT 0,      -- 1 = cannot be deleted
    sort_order          INTEGER NOT NULL DEFAULT 0,
    default_visibility  TEXT NOT NULL DEFAULT 'agent_choice',
    notify_chsct        INTEGER NOT NULL DEFAULT 0,      -- DGI-style CHSCT notification
    legal_note          TEXT,
    -- Modular-audit P2.1 — RegistryPolicy columns (business logic is now configurable)
    requires_pour_compte INTEGER NOT NULL DEFAULT 0,      -- 1 = requires pour_compte_nom/prenom fields (RAMI-style)
    has_dgi_warning     INTEGER NOT NULL DEFAULT 0,      -- 1 = shows L4131-2 warning panel (DGI-style)
    lieu_label_override TEXT,                            -- override the "Lieu" label (e.g. "Lieu / Mesures de protection" for DGI)
    created_at          TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at          TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- Table: registry_fields
-- Custom fields per registry (ex: nature_auteur for RAMI).
-- Rendered dynamically in report forms via HTML5 attributes.
-- ============================================================
CREATE TABLE IF NOT EXISTS registry_fields (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    registry_id     INTEGER NOT NULL,
    field_code      TEXT NOT NULL,               -- 'nature_auteur', 'type_acte', etc.
    label           TEXT NOT NULL,               -- display label
    field_type      TEXT NOT NULL DEFAULT 'text', -- 'text', 'select', 'textarea', 'checkbox'
    options         TEXT,                        -- JSON for select: {"value":"label",...}
    is_required     INTEGER NOT NULL DEFAULT 0,  -- HTML5 required attribute
    sort_order      INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (registry_id) REFERENCES registries(id) ON DELETE CASCADE,
    UNIQUE(registry_id, field_code)
);

-- ============================================================
-- Table: schema_version
-- Tracks which migration versions have been applied.
-- Prevents re-running migrations and provides auditability.
-- ============================================================
CREATE TABLE IF NOT EXISTS schema_version (
    version     INTEGER PRIMARY KEY,           -- Sequential version number
    description TEXT NOT NULL,                  -- Human-readable description
    applied_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Record the baseline version (current schema as of this file)
INSERT INTO schema_version (version, description) VALUES (1, 'Baseline — initial schema with all tables and indexes');

-- ============================================================
-- Table: audit_log
-- General-purpose audit trail for all significant actions.
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER,                         -- FK to users (NULL for system actions)
    username        TEXT NOT NULL,                    -- Denormalized for speed + survives user deletion
    category        TEXT NOT NULL,                    -- 'auth'|'report'|'user'|'site'|'config'|'export'|'backup'|'gdpr'
    action          TEXT NOT NULL,                    -- 'create'|'edit'|'delete'|'reactivate'|'role_change'|'login'|'logout'|etc.
    target_id       INTEGER,                         -- ID of the affected entity (nullable, for integer-keyed entities)
    target_type     TEXT,                            -- 'report'|'user'|'site'|'config'|etc.
    target_uuid     TEXT,                            -- UUID of the affected entity (for report entries)
    details         TEXT NOT NULL,                    -- Human-readable description
    context         TEXT,                            -- JSON-encoded additional context
    ip_address      TEXT,                            -- Client IP address
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_audit_log_category ON audit_log(category);
CREATE INDEX IF NOT EXISTS idx_audit_log_user_id ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_target ON audit_log(target_type, target_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_target_uuid ON audit_log(target_uuid);
CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log(created_at);

-- ============================================================
-- Indexes
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_reports_type ON reports(type);
CREATE INDEX IF NOT EXISTS idx_reports_etat ON reports(etat);
CREATE INDEX IF NOT EXISTS idx_reports_site_id ON reports(site_id);
CREATE INDEX IF NOT EXISTS idx_reports_declarant_id ON reports(declarant_id);
CREATE INDEX IF NOT EXISTS idx_reports_created_at ON reports(created_at);
CREATE INDEX IF NOT EXISTS idx_reports_type_etat ON reports(type, etat);
CREATE INDEX IF NOT EXISTS idx_reports_type_site ON reports(type, site_id);
CREATE INDEX IF NOT EXISTS idx_reports_type_site_etat ON reports(type, site_id, etat);
CREATE INDEX IF NOT EXISTS idx_reports_type_declarant_etat ON reports(type, declarant_id, etat);
CREATE INDEX IF NOT EXISTS idx_reports_type_date_evenement ON reports(type, date_evenement);
CREATE INDEX IF NOT EXISTS idx_reports_is_confidential ON reports(is_confidential);

-- Full-text search index (uuid/objet/description). content=reports /
-- content_rowid=rowid: external-content table, no data duplication — the
-- FTS index just points back to the reports rows.
--
-- Kept in sync via triggers (the standard SQLite-recommended pattern for
-- external-content FTS5 tables — see https://sqlite.org/fts5.html#the_content_option),
-- NOT manually in application code. An external-content FTS5 table does
-- NOT auto-sync with its content table — without these triggers, any raw
-- INSERT/UPDATE/DELETE on reports (application code that forgets to call
-- a sync helper, a migration, a test fixture's cleanup query, a future
-- admin tool) leaves reports_fts referencing rows that no longer match,
-- and the FTS5 module's own internal consistency check then throws
-- "database disk image is malformed" on the next write — not actual file
-- corruption, just FTS5 detecting its shadow index disagrees with the
-- content table because something wrote to reports outside the expected
-- path.
CREATE VIRTUAL TABLE IF NOT EXISTS reports_fts USING fts5(uuid, objet, description, content=reports, content_rowid=rowid);

CREATE TRIGGER IF NOT EXISTS reports_fts_ai AFTER INSERT ON reports BEGIN
    INSERT INTO reports_fts(rowid, uuid, objet, description) VALUES (new.rowid, new.uuid, new.objet, new.description);
END;

CREATE TRIGGER IF NOT EXISTS reports_fts_ad AFTER DELETE ON reports BEGIN
    INSERT INTO reports_fts(reports_fts, rowid, uuid, objet, description) VALUES ('delete', old.rowid, old.uuid, old.objet, old.description);
END;

CREATE TRIGGER IF NOT EXISTS reports_fts_au AFTER UPDATE ON reports BEGIN
    INSERT INTO reports_fts(reports_fts, rowid, uuid, objet, description) VALUES ('delete', old.rowid, old.uuid, old.objet, old.description);
    INSERT INTO reports_fts(rowid, uuid, objet, description) VALUES (new.rowid, new.uuid, new.objet, new.description);
END;

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_site_id ON users(site_id);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_report_responses_report_uuid ON report_responses(report_uuid);
CREATE INDEX IF NOT EXISTS idx_notification_settings_site_id ON notification_settings(site_id);

-- ============================================================
-- Table: report_access_log
-- Audit trail for consultations of confidential reports by supervisors/CHSCT.
-- Only logs when a supervisor/CHSCT views a confidential report they did not file.
-- ============================================================
CREATE TABLE IF NOT EXISTS report_access_log (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    report_uuid     TEXT NOT NULL,                    -- FK to reports(uuid)
    user_id         INTEGER NOT NULL,                 -- FK to users (the supervisor/CHSCT who accessed)
    role            TEXT NOT NULL,                     -- Role at time of access
    accessed_at     TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_report_access_log_report_uuid ON report_access_log(report_uuid);
CREATE INDEX IF NOT EXISTS idx_report_access_log_user_id ON report_access_log(user_id);
CREATE INDEX IF NOT EXISTS idx_report_access_log_accessed_at ON report_access_log(accessed_at);

-- ============================================================
-- Table: report_state_history
-- Audit trail for report state transitions (especially reopening).
-- Required for legal compliance (Code du travail D4132-1, L4711-3).
-- ============================================================
CREATE TABLE IF NOT EXISTS report_state_history (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    report_uuid     TEXT NOT NULL,                    -- FK to reports(uuid)
    etat_precedent  TEXT NOT NULL,                    -- Previous state
    etat_suivant    TEXT NOT NULL,                    -- New state
    user_id         INTEGER NOT NULL,                 -- FK to users (who triggered the transition)
    motif           TEXT,                             -- Reason for transition (required for reopening)
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_state_history_report ON report_state_history(report_uuid);
CREATE INDEX IF NOT EXISTS idx_state_history_created ON report_state_history(created_at);

-- Agents linked to a report (many-to-many)
CREATE TABLE IF NOT EXISTS report_agents (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    report_uuid TEXT NOT NULL,
    user_id     INTEGER NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE(report_uuid, user_id)
);
CREATE INDEX IF NOT EXISTS idx_report_agents_uuid ON report_agents(report_uuid);
CREATE INDEX IF NOT EXISTS idx_report_agents_user ON report_agents(user_id);

-- Agent link invitations (pending confirmation)
CREATE TABLE IF NOT EXISTS report_agent_invites (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    report_uuid TEXT NOT NULL,
    email       TEXT NOT NULL,
    token       TEXT NOT NULL,
    confirmed   INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    confirmed_at TEXT DEFAULT NULL,
    FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
    UNIQUE(token)
);
CREATE INDEX IF NOT EXISTS idx_agent_invites_uuid ON report_agent_invites(report_uuid);
CREATE INDEX IF NOT EXISTS idx_agent_invites_token ON report_agent_invites(token);
