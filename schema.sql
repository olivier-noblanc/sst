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
    type            TEXT NOT NULL,                   -- 'rsst'|'rami'|'dgi'
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
    pour_compte_de  INTEGER,                         -- FK to users (nullable, RAMI only)
    pour_compte_nom TEXT,                            -- Denormalized name of the other agent
    pour_compte_prenom TEXT,                         -- Denormalized first name
    -- Assignment
    site_id         INTEGER NOT NULL,                -- FK to sites (UR where event occurred, always set)
    -- Confidentiality
    is_confidential INTEGER NOT NULL DEFAULT 1,     -- 1 = confidentiel (défaut), 0 = public
    -- State management
    etat            TEXT NOT NULL DEFAULT 'nouveau', -- 'nouveau'|'en_cours'|'traite'|'abandonne'
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
    user_id         INTEGER NOT NULL,                -- FK to users (the supervisor)
    reponse         TEXT NOT NULL,                   -- Response text
    nouvel_etat     TEXT,                            -- State change if any: 'en_cours'|'traite'
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
    ('smtp_encryption', 'none', 'text', 'smtp', 'Chiffrement (none, tls, starttls)', 1);


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
    target_id       INTEGER,                         -- ID of the affected entity (nullable)
    target_type     TEXT,                            -- 'report'|'user'|'site'|'config'|etc.
    details         TEXT NOT NULL,                    -- Human-readable description
    context         TEXT,                            -- JSON-encoded additional context
    ip_address      TEXT,                            -- Client IP address
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_audit_log_category ON audit_log(category);
CREATE INDEX IF NOT EXISTS idx_audit_log_user_id ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_target ON audit_log(target_type, target_id);
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
CREATE INDEX IF NOT EXISTS idx_reports_is_confidential ON reports(is_confidential);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_site_id ON users(site_id);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_report_responses_report_uuid ON report_responses(report_uuid);
CREATE INDEX IF NOT EXISTS idx_notification_settings_site_id ON notification_settings(site_id);
