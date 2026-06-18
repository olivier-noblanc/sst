-- ============================================================
-- Migration v2.7.0 — Feature toggles, syndicat, agents rattachés
-- ============================================================

-- 1. Toggle RAMI / DGI : activé par défaut
INSERT OR IGNORE INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES
    ('app_registry_rami_enabled', '1', 'text', 'app', 'Activer le registre RAMI', 1),
    ('app_registry_dgi_enabled', '1', 'text', 'app', 'Activer le registre DGI', 1);

-- 2. Noms de rôles personnalisables
INSERT OR IGNORE INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES
    ('app_role_label_agent', 'Agent', 'text', 'app', 'Nom du rôle Agent', 1),
    ('app_role_label_superviseur', 'Superviseur', 'text', 'app', 'Nom du rôle Superviseur', 1),
    ('app_role_label_chsct', 'Membre FS/CSA', 'text', 'app', 'Nom du rôle FS/CSA', 1);

-- 3. Consentement transmission organisations syndicales
ALTER TABLE reports ADD COLUMN consent_syndicat INTEGER DEFAULT 0;

-- 4. Agents rattachés (table many-to-many)
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

-- 5. Enregistrement de la migration
INSERT OR IGNORE INTO schema_version (version, description) VALUES
    (2, 'v2.7.0 — Toggle RAMI/DGI, noms rôles personnalisables, consentement syndicat, agents rattachés');
