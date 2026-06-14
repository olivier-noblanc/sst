# SPECIFICATION — Application SST DREETS BFC

> Plateforme des Registres en Santé et Sécurité au Travail
> DREETS Bourgogne-Franche-Comté
> Version 2.7.0 — Specification technique

---

## Table of Contents

1. [Présentation générale](#1-présentation-générale)
2. [Arborescence des fichiers](#2-arborescence-des-fichiers)
3. [Schéma de la base de données](#3-schéma-de-la-base-de-données)
4. [Routage](#4-routage)
5. [Spécifications des pages](#5-spécifications-des-pages)
6. [Système d'authentification](#6-système-dauthentification)
7. [Contrôle d'accès et visibilité](#7-contrôle-daccès-et-visibilité)
8. [Règles métier](#8-règles-métier)
9. [Architecture CSS](#9-architecture-css)
10. [Format des références](#10-format-des-références)
11. [Notifications par e-mail](#11-notifications-par-e-mail)
12. [Référence des fonctions](#12-référence-des-fonctions)
13. [Configuration applicative](#13-configuration-applicative)

---

## 1. Présentation générale

### Objet

Application web pour la gestion de trois registres de santé et sécurité au travail au sein de la DREETS Bourgogne-Franche-Comté (direction régionale de l'économie, de l'emploi, du travail et des solidarités).

### Trois registres

| Code | Nom complet | Couleur | Description |
|------|-------------|---------|-------------|
| RSST | Registre de Santé et de Sécurité au Travail | Bleu (`var(--rsst-color)`) | Signalements généraux de santé et sécurité |
| RAMI | Registre des Actes d'Agressions, de Menaces et d'Incivilités | Gris (`var(--rami-color)`) | Signalements d'agressions, menaces ou incivilités |
| DGI | Registre de signalement d'un Danger Grave et Imminent | Rouge (`var(--dgi-color)`) | Signalements de dangers graves et imminents |

### Trois rôles

| Rôle | Code | Permissions |
|------|------|-------------|
| Agent | `agent` | Créer des signalements, consulter les signalements (selon visibilité), modifier ses propres signalements |
| Superviseur | `superviseur` | Permissions agent + répondre aux signalements, abandonner des signalements, synthèse, export, statistiques, gestion des utilisateurs, paramètres |
| Membre CSA/CHSCT | `chsct` | Consulter tous les signalements (tous sites), synthèse, export, statistiques — lecture seule, pas de réponse ni d'abandon |

> **Note** : Il n'y a pas de rôle « Manager ». Les trois seuls rôles sont `agent`, `superviseur` et `chsct`.

### États d'un signalement

```
Nouveau → En cours → Traité
  └──→ Abandonné (soft delete, possible depuis n'importe quel état non traité)
```

| État | Code | Badge CSS |
|------|------|-----------|
| Nouveau | `nouveau` | `badge--nouveau` |
| En cours | `en_cours` | `badge--en-cours` |
| Traité | `traite` | `badge--traite` |
| Abandonné | `abandonne` | `badge--abandonne` |

### Sites (Unités Régionales — UR)

Les sites sont stockés dans la table `sites`. Par défaut, deux sites sont créés lors de l'initialisation :

| Code | Nom | Département |
|------|-----|-------------|
| UR21 | UR Côte-d'Or | Côte-d'Or |
| UR25 | UR Doubs | Doubs |

D'autres sites peuvent être ajoutés via l'interface de paramétrage (onglet « Gestion des sites »). Le libellé des unités (« UR », « UD », etc.) est configurable via `app_label_unite`.

### Constantes de l'application

| Constante | Valeur | Description |
|-----------|--------|-------------|
| `APP_NAME` | `Application SST — DREETS BFC` | Nom affiché dans l'en-tête |
| `APP_VERSION` | `2.7.0` | Version de l'application |
| `REPORT_VISIBILITY_MODES` | `['confidential', 'agent_choice', 'public']` | Modes de visibilité des signalements |
| `SITE_NAME` | `DREETS Bourgogne-Franche-Comté` | Nom complet du site |
| `APP_ENV` | `prod` (défaut) ou `dev` | Environnement d'exécution |
| `DEV_MODE` | `APP_ENV === 'dev'` | Mode développement (authentification mock) |
| `DB_PATH` | `__DIR__ . '/../data/sst.db'` | Chemin vers la base SQLite |
| `ITEMS_PER_PAGE` | `20` | Nombre d'éléments par page |
| `MAX_OBJECT_LENGTH` | `100` | Longueur max du champ objet |
| `MAX_DESCRIPTION_LENGTH` | `20000` | Longueur max du champ description |
| `MAX_LIEU_LENGTH` | `200` | Longueur max du champ lieu |
| `MAX_ATTACHMENT_SIZE` | `10485760` (10 Mo) | Taille max d'une pièce jointe |
| `ALLOWED_ATTACHMENT_MIMES` | `image/jpeg, image/png, image/gif, application/pdf` | Types MIME acceptés pour les pièces jointes |

---

## 2. Arborescence des fichiers

```
sst-app/
├── public/                              # Racine web (document root IIS)
│   ├── index.php                        # Point d'entrée unique — routeur/dispatcher
│   ├── router.php                       # Router pour le serveur PHP built-in (dev only)
│   ├── css/
│   │   └── style.css                    # Feuille de style unique
│   ├── img/
│   │   └── logo-dreets.png              # Logo DREETS BFC
│   └── favicon.ico                      # Favicon
│
├── src/
│   ├── config.php                       # Constantes, configuration applicative
│   ├── database.php                     # Connexion PDO singleton + initialisation schema + migrations
│   ├── auth.php                         # Authentification IIS Windows Auth / mock dev
│   ├── session.php                      # Gestion de session, CSRF, flash messages
│   ├── session_patch.php                # Correctif pour session_regenerate_id en dev
│   ├── helpers.php                      # Fonctions utilitaires : e(), redirect(), formatDateFR(), etc.
│   ├── mail.php                         # Envoi d'e-mails (SMTP ou mail() en fallback)
│   ├── lib/
│   │   └── fpdf/                         # FPDF 1.9 bundled (génération PDF)
│   │       ├── fpdf.php                  # Classe FPDF principale
│   │       └── font/                     # Polices TrueType (DejaVu Sans)
│   ├── queries/
│   │   ├── report_queries.php           # Requêtes SQL liées aux signalements
│   │   ├── user_queries.php             # Requêtes SQL liées aux utilisateurs
│   │   ├── site_queries.php             # Requêtes SQL liées aux sites
│   │   └── stats_queries.php            # Requêtes SQL pour statistiques/export/synthèse + notifications
│   └── middleware/
│       ├── require_auth.php             # Vérifie l'authentification
│       └── require_role.php             # Vérifie le rôle (requireRole, hasRole, hasAnyRole)
│
├── templates/
│   ├── header.php                       # Barre supérieure : logo, titre, nom utilisateur, déconnexion
│   ├── sidebar.php                      # Navigation latérale (menu adapté au rôle)
│   ├── footer.php                       # Fermeture des balises
│   ├── pagination.php                   # Composant de pagination réutilisable
│   ├── report_form.php                  # Formulaire partagé création/édition de signalement
│   ├── report_card.php                  # Affichage partagé d'un signalement détaillé
│   ├── alert.php                        # Affichage des messages flash (success/error/warning)
│   └── confirm_dialog.php               # Dialogue de confirmation (abandon, suppression)
│
├── pages/
│   ├── login.php                        # Page de connexion (dev only — formulaire mock)
│   ├── choose_site.php                  # Choix du site lors de la première connexion
│   ├── home.php                         # Tableau de bord / Accueil — 3 cartes colorées
│   ├── preamble.php                     # Page d'information « Préambule »
│   ├── help.php                         # Page « Documentation »
│   ├── report_create.php                # Créer un signalement (RSST/RAMI/DGI)
│   ├── report_list.php                  # Liste des signalements avec filtres
│   ├── report_view.php                  # Consultation d'un signalement
│   ├── report_edit.php                  # Modifier un signalement (déclarant uniquement)
│   ├── report_print.php                 # Télécharger un signalement en PDF (FPDF)
│   ├── report_attachment.php            # Télécharger/afficher une pièce jointe (BLOB depuis DB)
│   ├── report_abandon.php               # Abandonner un signalement (confirmation)
│   ├── report_respond.php               # Répondre à un signalement (superviseur uniquement)
│   ├── synthesis.php                    # Synthèse croisée des signalements
│   ├── export.php                       # Export CSV avec filtres
│   ├── statistics.php                   # Statistiques et KPI
│   ├── settings.php                     # Paramètres : notifications, SMTP, application, gestion des sites
│   ├── users.php                        # Gestion des utilisateurs (liste + inscription)
│   ├── user_edit.php                    # Édition d'un utilisateur
│   ├── user_view.php                    # Profil utilisateur
│   ├── site_edit.php                    # Édition d'un site
│   └── access_denied.php                # Page 403 — accès refusé
│
├── handlers/
│   ├── login_handler.php                # POST : authentification mock (dev)
│   ├── choose_site_handler.php          # POST : choix du site (première connexion)
│   ├── report_create_handler.php        # POST : création d'un signalement
│   ├── report_edit_handler.php          # POST : modification d'un signalement
│   ├── report_abandon_handler.php       # POST : abandon d'un signalement
│   ├── report_respond_handler.php       # POST : réponse à un signalement
│   ├── export_handler.php               # POST/GET : génération export CSV
│   ├── settings_handler.php             # POST : sauvegarde paramètres (notifications, SMTP, app, sites)
│   ├── smtp_test_handler.php            # POST : test d'envoi SMTP
│   ├── user_edit_handler.php            # POST : modification d'un utilisateur
│   ├── user_create_handler.php          # POST : création d'un utilisateur
│   ├── user_delete_handler.php          # POST : désactivation d'un utilisateur
│   ├── user_reactivate_handler.php      # POST : réactivation d'un utilisateur
│   └── site_edit_handler.php            # POST : modification d'un site
│
├── data/
│   └── sst.db                           # Base de données SQLite (créée automatiquement)
│
├── schema.sql                           # Schéma SQL complet (exécuté à la première connexion)
├── promote.php                          # Script CLI pour promouvoir un utilisateur superviseur
├── seed.php                             # Script CLI pour peupler la base de test
├── test_fpdf.php                        # Script de test FPDF (vérification polices + génération)
├── phpinfo.php                          # Page de diagnostic PHP
└── SPEC.md                              # Ce fichier
```

---

## 3. Schéma de la base de données

### Relations entre entités

```
sites ──1:N── users
sites ──1:N── reports
users ──1:N── reports (declarant)
users ──1:N── reports (repondant)
users ──1:N── reports (pour_compte_de)
reports ──1:N── report_responses
sites ──1:N── notification_settings
```

### Table `sites`

Stocke les Unités Régionales (UR). Deux sites par défaut, extensibles via l'UI.

```sql
CREATE TABLE IF NOT EXISTS sites (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    code            TEXT NOT NULL UNIQUE,           -- ex: "UR21", "UR25"
    nom             TEXT NOT NULL,                   -- ex: "UR Côte-d'Or"
    departement     TEXT,                            -- ex: "Côte-d'Or"
    is_active       INTEGER NOT NULL DEFAULT 1,      -- 0 = désactivé, n'apparaît plus dans les listes
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);
```

### Table `users`

Utilisateurs de l'application. Créés automatiquement à partir du login Windows (IIS) ou via le formulaire mock en dev.

```sql
CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    username        TEXT NOT NULL UNIQUE,            -- Login Windows (ex: "jean.martin")
    nom             TEXT NOT NULL,                   -- Nom de famille
    prenom          TEXT NOT NULL,                   -- Prénom
    email           TEXT,                            -- Adresse e-mail
    role            TEXT NOT NULL DEFAULT 'agent',   -- 'agent' | 'superviseur' | 'chsct'
    site_id         INTEGER,                         -- FK vers sites (NULL jusqu'au choix du site)
    is_active       INTEGER NOT NULL DEFAULT 1,      -- Soft delete : 0 = désactivé
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (site_id) REFERENCES sites(id)
);
```

> **Note** : `site_id` est nullable. Un nouvel utilisateur auto-provisionné n'a pas de site tant qu'il n'a pas choisi le sien sur la page `choose_site`.

### Table `reports`

Table principale pour les trois registres.

```sql
CREATE TABLE IF NOT EXISTS reports (
    uuid            TEXT PRIMARY KEY,                -- UUID v4 (ex: "a1b2c3d4-e5f6-7890-abcd-ef1234567890")
    reference       TEXT NOT NULL UNIQUE,            -- ex: "rsst-25-001"
    type            TEXT NOT NULL,                   -- 'rsst' | 'rami' | 'dgi'
    objet           TEXT NOT NULL,                   -- Objet du signalement, max 100 caractères
    description     TEXT NOT NULL,                   -- Description complète, max 20 000 caractères
    date_evenement  TEXT NOT NULL,                   -- Date de l'événement (ISO 8601)
    heure_evenement TEXT,                            -- Heure de l'événement (HH:MM)
    lieu            TEXT,                            -- Lieu de l'événement
    -- Déclarant (personne qui dépose le signalement)
    declarant_id    INTEGER NOT NULL,                -- FK vers users
    declarant_nom   TEXT NOT NULL,                   -- Nom dénormalisé pour performance
    declarant_prenom TEXT NOT NULL,                  -- Prénom dénormalisé
    -- « Pour le compte de » (RAMI uniquement)
    pour_compte_de  INTEGER,                         -- FK vers users (nullable, RAMI uniquement)
    pour_compte_nom TEXT,                            -- Nom de l'agent concerné
    pour_compte_prenom TEXT,                         -- Prénom de l'agent concerné
    -- Rattachement
    site_id         INTEGER NOT NULL,                -- FK vers sites (UR où l'événement s'est produit)
    -- Gestion d'état
    etat            TEXT NOT NULL DEFAULT 'nouveau', -- 'nouveau' | 'en_cours' | 'traite' | 'abandonne'
    is_confidential INTEGER NOT NULL DEFAULT 1,      -- 1 = confidentiel, 0 = public (mode confidential)
    -- Répondant (superviseur qui a traité le signalement)
    repondant_id    INTEGER,                         -- FK vers users (nullable)
    date_reponse    TEXT,                            -- Date de la réponse
    reponse         TEXT,                            -- Texte de la réponse
    -- Pièce jointe (mono-fichier, stockée en BLOB dans la base)
    attachment_blob BLOB,                            -- Contenu du fichier (max ~10 Mo)
    attachment_name TEXT,                            -- Nom du fichier original (ex: "photo_danger.jpg")
    attachment_mime TEXT,                            -- Type MIME (ex: "image/jpeg", "application/pdf")
    -- Horodatage
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (declarant_id) REFERENCES users(id),
    FOREIGN KEY (pour_compte_de) REFERENCES users(id),
    FOREIGN KEY (repondant_id) REFERENCES users(id),
    FOREIGN KEY (site_id) REFERENCES sites(id)
);
```

### Table `report_responses`

Historique des réponses à un signalement (audit trail). Chaque réponse du superviseur est enregistrée ici.

```sql
CREATE TABLE IF NOT EXISTS report_responses (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    report_uuid     TEXT NOT NULL,                    -- FK vers reports(uuid)
    user_id         INTEGER NOT NULL,                -- FK vers users (le superviseur)
    reponse         TEXT NOT NULL,                   -- Texte de la réponse
    nouvel_etat     TEXT,                            -- Changement d'état : 'en_cours' | 'traite'
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (report_uuid) REFERENCES reports(uuid) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### Table `notification_settings`

Adresses e-mail de notification par site et globales.

```sql
CREATE TABLE IF NOT EXISTS notification_settings (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id         INTEGER,                         -- FK vers sites. NULL = global
    type            TEXT NOT NULL,                   -- 'site' | 'global'
    registry        TEXT NOT NULL,                   -- 'rsst' | 'rami' | 'dgi' | 'all'
    email           TEXT NOT NULL,                   -- Adresse e-mail
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (site_id) REFERENCES sites(id)
);
```

### Table `report_sequence`

Séquence auto-incrémentée par registre et par année pour la génération des références.

```sql
CREATE TABLE IF NOT EXISTS report_sequence (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    type            TEXT NOT NULL,                   -- 'rsst' | 'rami' | 'dgi'
    year            INTEGER NOT NULL,                -- ex: 2025
    last_sequence   INTEGER NOT NULL DEFAULT 0,      -- Dernier numéro de séquence utilisé
    UNIQUE(type, year)
);
```

### Table `config_app`

Configuration applicative (magasin clé-valeur, éditable via l'UI).

```sql
CREATE TABLE IF NOT EXISTS config_app (
    cle             TEXT PRIMARY KEY,                -- Clé de configuration
    valeur          TEXT,                            -- Valeur
    type            TEXT DEFAULT 'text',             -- 'text' | 'number' | 'password' | 'email'
    categorie       TEXT DEFAULT 'app',              -- 'app' | 'smtp'
    libelle         TEXT,                            -- Libellé affiché dans l'UI
    modifiable      INTEGER DEFAULT 1,               -- 1 = modifiable via l'UI
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
);
```

### Index

```sql
CREATE INDEX IF NOT EXISTS idx_reports_type ON reports(type);
CREATE INDEX IF NOT EXISTS idx_reports_etat ON reports(etat);
CREATE INDEX IF NOT EXISTS idx_reports_site_id ON reports(site_id);
CREATE INDEX IF NOT EXISTS idx_reports_declarant_id ON reports(declarant_id);
CREATE INDEX IF NOT EXISTS idx_reports_created_at ON reports(created_at);
CREATE INDEX IF NOT EXISTS idx_reports_type_etat ON reports(type, etat);
CREATE INDEX IF NOT EXISTS idx_reports_type_site ON reports(type, site_id);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_site_id ON users(site_id);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_reports_uuid ON reports(uuid);
CREATE INDEX IF NOT EXISTS idx_report_responses_report_uuid ON report_responses(report_uuid);
CREATE INDEX IF NOT EXISTS idx_notification_settings_site_id ON notification_settings(site_id);
```

### Données d'initialisation (seed)

```sql
-- Sites par défaut
INSERT INTO sites (code, nom, departement) VALUES
    ('UR21', 'UR Côte-d''Or', 'Côte-d''Or'),
    ('UR25', 'UR Doubs', 'Doubs');

-- Utilisateurs de développement
INSERT INTO users (username, nom, prenom, email, role, site_id) VALUES
    ('admin.dev', 'Administrateur', 'Dev', 'admin.dev@dreets.gouv.fr', 'superviseur', 1),
    ('agent.dev', 'Dupont', 'Jean', 'agent.dev@dreets.gouv.fr', 'agent', NULL),
    ('chsct.dev', 'Bernard', 'Pierre', 'chsct.dev@dreets.gouv.fr', 'chsct', 2);

-- Configuration par défaut
INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable) VALUES
    ('app_nom_organisation', 'DREETS BFC', 'text', 'app', 'Nom de l''organisation', 1),
    ('app_nom_complet', 'DREETS Bourgogne-Franche-Comté', 'text', 'app', 'Nom complet', 1),
    ('app_label_unite', 'UR', 'text', 'app', 'Libellé des unités (UD, UR, etc.)', 1),
    ('app_superviseur_usernames', '', 'text', 'app', 'Logins Windows des superviseurs...', 1),
    ('app_agent_see_only_own', '0', 'text', 'app', 'Obsolète : utilisez app_report_visibility', 1),
    ('app_agent_visibility', 'confidential', 'text', 'app', 'Obsolète : utilisez app_report_visibility', 1),
    ('app_report_visibility', 'confidential', 'text', 'app', 'Mode de visibilité : confidential|agent_choice|public', 1),
    ('smtp_host', '', 'text', 'smtp', 'Serveur SMTP', 1),
    ('smtp_port', '25', 'number', 'smtp', 'Port SMTP', 1),
    ('smtp_user', '', 'text', 'smtp', 'Utilisateur SMTP', 1),
    ('smtp_pass', '', 'password', 'smtp', 'Mot de passe SMTP', 1),
    ('smtp_from', '', 'email', 'smtp', 'Adresse d''expédition', 1),
    ('smtp_encryption', 'none', 'text', 'smtp', 'Chiffrement (none, tls, starttls)', 1);
```

---

## 4. Routage

### Architecture

Toutes les requêtes passent par `public/index.php`. Le pattern d'URL est :

```
/index.php?page={page_name}[&uuid={uuid}][&id={id}][&type={type}][&tab={tab}]
```

### Table des routes

| URL (`?page=`) | Fichier inclus | Méthode | Auth requis | Rôles autorisés |
|-----------------|---------------|---------|-------------|-----------------|
| `login` | `pages/login.php` | GET | Non | — |
| `login` | `handlers/login_handler.php` | POST | Non | — |
| `logout` | (inline dans index.php) | GET | Oui | Tous |
| `choose_site` | `pages/choose_site.php` | GET | Oui | Tous (sans site) |
| `choose_site` | `handlers/choose_site_handler.php` | POST | Oui | Tous (sans site) |
| `home` | `pages/home.php` | GET | Oui | Tous |
| `preamble` | `pages/preamble.php` | GET | Oui | Tous |
| `help` | `pages/help.php` | GET | Oui | Tous |
| `report_create` | `pages/report_create.php` | GET | Oui | Tous |
| `report_create` | `handlers/report_create_handler.php` | POST | Oui | Tous |
| `report_list` | `pages/report_list.php` | GET | Oui | Tous (filtré par visibilité) |
| `report_view` | `pages/report_view.php` | GET+`&uuid={uuid}` | Oui | Déclarant, superviseur, CSA/CHSCT |
| `report_edit` | `pages/report_edit.php` | GET+`&uuid={uuid}` | Oui | Déclarant uniquement |
| `report_edit` | `handlers/report_edit_handler.php` | POST+`&uuid={uuid}` | Oui | Déclarant uniquement |
| `report_print` | `pages/report_print.php` | GET+`&uuid={uuid}` | Oui | Déclarant, superviseur, CSA/CHSCT |
| `report_attachment` | `pages/report_attachment.php` | GET+`&uuid={uuid}[&inline=1]` | Oui | Déclarant, superviseur, CSA/CHSCT |
| `report_abandon` | `pages/report_abandon.php` | GET+`&uuid={uuid}` | Oui | Superviseur uniquement |
| `report_abandon` | `handlers/report_abandon_handler.php` | POST+`&uuid={uuid}` | Oui | Superviseur uniquement |
| `report_respond` | `pages/report_respond.php` | GET+`&uuid={uuid}` | Oui | Superviseur uniquement |
| `report_respond` | `handlers/report_respond_handler.php` | POST+`&uuid={uuid}` | Oui | Superviseur uniquement |
| `synthesis` | `pages/synthesis.php` | GET | Oui | superviseur, chsct |
| `export` | `pages/export.php` | GET | Oui | superviseur, chsct |
| `export` | `handlers/export_handler.php` | POST | Oui | superviseur, chsct |
| `statistics` | `pages/statistics.php` | GET | Oui | superviseur, chsct |
| `settings` | `pages/settings.php` | GET | Oui | superviseur |
| `settings` | `handlers/settings_handler.php` | POST | Oui | superviseur |
| `smtp_test` | `handlers/smtp_test_handler.php` | POST | Oui | superviseur |
| `users` | `pages/users.php` | GET | Oui | superviseur |
| `user_edit` | `pages/user_edit.php` | GET+`&id=N` | Oui | superviseur |
| `user_edit` | `handlers/user_edit_handler.php` | POST+`&id=N` | Oui | superviseur |
| `user_view` | `pages/user_view.php` | GET+`&id=N` | Oui | superviseur |
| `user_create` | `handlers/user_create_handler.php` | POST | Oui | superviseur |
| `user_delete` | `handlers/user_delete_handler.php` | POST | Oui | superviseur |
| `user_reactivate` | `handlers/user_reactivate_handler.php` | POST | Oui | superviseur |
| `site_edit` | `handlers/site_edit_handler.php` | POST | Oui | superviseur |
| (défaut/inconnu) | `pages/home.php` | GET | Oui | Tous |

### Menu latéral (sidebar)

Le menu est construit dynamiquement selon le rôle de l'utilisateur :

| Élément | Icône | Page | Rôles |
|---------|-------|------|-------|
| Accueil | 🏠 | `home` | agent, superviseur, chsct |
| RSST | 📋 | `report_list&type=rsst` | agent, superviseur, chsct |
| RAMI | ⚠️ | `report_list&type=rami` | agent, superviseur, chsct |
| DGI | 🔴 | `report_list&type=dgi` | agent, superviseur, chsct |
| Synthèse | 📊 | `synthesis` | superviseur, chsct |
| Export | 📥 | `export` | superviseur, chsct |
| Statistiques | 📈 | `statistics` | superviseur, chsct |
| Utilisateurs | 👥 | `users` | superviseur |
| Paramètres | ⚙️ | `settings` | superviseur |

Liens du pied de sidebar (visibles par tous) :
- 📚 Documentation → `help`
- 📖 Préambule → `preamble`

---

## 5. Spécifications des pages

### 5.1 Page de connexion (`pages/login.php`)

**URL** : `index.php?page=login`
**Accès** : Public (uniquement en mode DEV)
**Méthode** : GET (affichage), POST (traitement)

En production, IIS gère l'authentification Windows avant que PHP ne s'exécute — cette page est donc inaccessible. En dev, un formulaire mock permet de simuler une connexion.

#### Affichage
- Carte centrée sur fond gris
- Titre : « Application SST — DREETS BFC »
- Sous-titre : « Connexion (mode développement) »
- Champ : **Nom d'utilisateur** — `<input type="text" name="username" required>`
- Champ : **Mot de passe** — `<input type="password" name="password">` (cosmétique, non vérifié)
- Bouton : « Se connecter »

#### Traitement POST (`handlers/login_handler.php`)
- Appelle `mockLogin($username)` qui appelle `findOrCreateUser($username)`
- Si l'utilisateur existe en base : connexion réussie
- Si l'utilisateur n'existe pas : création automatique avec rôle `agent` et `site_id = NULL`
- En cas de succès : `$_SESSION['user'] = $user` → redirection vers `home`
- Pas de jeton CSRF sur le login (session pas encore établie)

---

### 5.2 Page de choix de site (`pages/choose_site.php`)

**URL** : `index.php?page=choose_site`
**Accès** : Utilisateurs authentifiés sans `site_id`
**Méthode** : GET (affichage), POST (traitement)

Affichée automatiquement quand un utilisateur authentifié n'a pas encore choisi son site (site_id NULL). L'utilisateur doit sélectionner son UR parmi les sites actifs.

#### Traitement POST (`handlers/choose_site_handler.php`)
- Vérifie le jeton CSRF
- Met à jour `users.site_id` avec la valeur choisie
- Met à jour la session
- Redirige vers la page initialement demandée (ou `home`)

---

### 5.3 Page d'accueil (`pages/home.php`)

**URL** : `index.php?page=home`
**Accès** : Tous les utilisateurs authentifiés
**Méthode** : GET

#### Affichage
- Titre : « Accueil »
- Trois cartes côte à côte (flexbox) :

**Carte RSST** (fond bleu `var(--rsst-color)`, texte blanc)
- Icône : 📋
- Titre : « Registre de Santé et de Sécurité au Travail »
- Sous-titre : « RSST »
- Bouton : « Inscrire un signalement » → `report_create&type=rsst`
- Stat : « X signalements enregistrés »

**Carte RAMI** (fond gris `var(--rami-color)`, texte blanc)
- Icône : ⚠️
- Titre : « Registre des Actes d'Agressions, de Menaces et d'Incivilités »
- Sous-titre : « RAMI »
- Bouton : « Inscrire un signalement » → `report_create&type=rami`
- Stat : « X signalements enregistrés »

**Carte DGI** (fond rouge `var(--dgi-color)`, texte blanc)
- Icône : 🔴
- Titre : « Registre de signalement d'un Danger Grave et Imminent »
- Sous-titre : « DGI »
- Bouton : « Inscrire un signalement » → `report_create&type=dgi`
- Stat : « X signalements enregistrés »

Le compteur utilise `countActiveReports()`. Le filtrage dépend du mode de visibilité (`app_report_visibility`) :
- **`confidential`** (défaut) : filtré par `site_id` + `(is_confidential = 0 OR declarant_id = :user_id)`
- **`agent_choice`** : filtré par `site_id` + `(is_confidential = 0 OR declarant_id = :user_id)` (l'agent choisit la confidentialité au cas par cas)
- **`public`** : filtré par `site_id` (tous les signalements du site sont visibles)
- Pour superviseur/CSA/CHSCT : pas de filtre

---

### 5.4 Page Préambule (`pages/preamble.php`)

**URL** : `index.php?page=preamble`
**Accès** : Tous les utilisateurs authentifiés
**Méthode** : GET

Page d'information statique sur les registres SST. Contenu hardcoded en français :
- Base légale (articles du Code du travail)
- Objet de chaque registre
- Qui peut déposer un signalement
- Avis de confidentialité
- Traitement des signalements

Aucune donnée dynamique, aucun formulaire.

---

### 5.5 Création de signalement (`pages/report_create.php`)

**URL** : `index.php?page=report_create&type={rsst|rami|dgi}`
**Accès** : Tous les utilisateurs authentifiés
**Méthode** : GET (affichage), POST (traitement via handler)

#### Affichage
- Titre adapté au type : « Inscrire un signalement — RSST/RAMI/DGI »
- Bande de couleur en haut du formulaire correspondant au registre
- Champs du formulaire :

| Champ | Type | Requis | Max | Défaut | Notes |
|-------|------|--------|-----|--------|-------|
| `date_evenement` | `<input type="date">` | Oui | — | Date du jour | Date de l'événement |
| `heure_evenement` | `<input type="time">` | Non | — | Heure actuelle | Heure de l'événement |
| `lieu` | `<input type="text">` | Non | 200 | — | Lieu de l'événement |
| `objet` | `<input type="text">` | Oui | 100 | — | Objet du signalement |
| `description` | `<textarea rows="8">` | Oui | 20000 | — | Description complète |
| `attachment` | `<input type="file">` | Non | 10 Mo | — | Image (JPG, PNG, GIF) ou PDF |
| `site_id` | `<select>` | Oui | — | Site de l'utilisateur | L'agent voit son site seul, le superviseur voit tous les sites |

**Champs spécifiques RAMI** (affichés uniquement si `type=rami`) :

| Champ | Type | Requis | Notes |
|-------|------|--------|-------|
| `pour_compte` | `<input type="checkbox">` | Non | « Signaler pour le compte d'un autre agent » |
| `pour_compte_nom` | `<input type="text">` | Conditionnel | Affiché si `pour_compte` coché |
| `pour_compte_prenom` | `<input type="text">` | Conditionnel | Affiché si `pour_compte` coché |

**Informations déclarant** (auto-remplies, lecture seule) :
- Nom : `$_SESSION['user']['nom']`
- Prénom : `$_SESSION['user']['prenom']`

Champs cachés : `type`, `csrf_token`

Boutons : « Valider » (couleur du registre), « Annuler » (retour à l'accueil)

#### Traitement POST (`handlers/report_create_handler.php`)

**Validation** :
1. Jeton CSRF valide
2. `type` parmi : `rsst`, `rami`, `dgi`
3. `date_evenement` valide, pas dans le futur
4. `objet` non vide, max 100 caractères
5. `description` non vide, max 20 000 caractères
6. `site_id` existe dans la table sites
7. Si RAMI et `pour_compte` coché : `pour_compte_nom` et `pour_compte_prenom` requis

**Traitement** :
1. Générer la référence : `generateReference($type, date('y'), getNextSequence($pdo, $type, date('Y')))`
2. Insérer dans la table `reports`
3. Flash : « Signalement enregistré avec la référence {reference} »
4. Redirection vers `report_view&uuid={new_report_uuid}`

---

### 5.6 Liste des signalements (`pages/report_list.php`)

**URL** : `index.php?page=report_list&type={rsst|rami|dgi}`
**Accès** : Tous les utilisateurs authentifiés (filtré par visibilité agent)
**Méthode** : GET

#### Affichage
- Titre : « Liste des signalements — RSST/RAMI/DGI »
- Barre de filtres :
  - **État** : `<select>` — Tous, Nouveau, En cours, Traité, Abandonné
  - **Site** : `<select>` — Tous + liste des sites (superviseur/CSA/CHSCT uniquement ; agent voit son site)
  - **Recherche** : `<input type="text">` — recherche dans `objet` et `description`
  - Bouton « Filtrer »
- Tableau des résultats :

| Colonne | Contenu |
|---------|---------|
| Réf. | `report.reference` |
| Date | `formatDateFR(report.date_evenement)` |
| Objet | `truncate(e(report.objet), 50)` |
| Déclarant | `report.declarant_prenom` `report.declarant_nom` |
| Site | `site.code` |
| État | Badge coloré |
| Actions | Boutons contextuels |

- Boutons d'action par ligne :
  - **Voir** — toujours affiché → `report_view&uuid={uuid}`
  - **Modifier** — si utilisateur = déclarant ET état `nouveau` ou `en_cours`
  - **Répondre** — si rôle superviseur ET état `nouveau` ou `en_cours`
  - **Abandonner** — si rôle superviseur ET état non `abandonne` ni `traite`

- Pagination en bas (20 éléments par page)

---

### 5.7 Consultation d'un signalement (`pages/report_view.php`)

**URL** : `index.php?page=report_view&uuid={report_uuid}`
**Accès** : Déclarant, superviseur, CSA/CHSCT (via `canAccessReport()`)
**Méthode** : GET

#### Contrôle d'accès

La fonction `canAccessReport()` centralise les règles :
- **Déclarant** : toujours accès à son propre signalement
- **Superviseur** : accès à tous les signalements
- **CSA/CHSCT** : accès à tous les signalements
- **Agent** (non déclarant) : selon `getReportVisibility()` :
  - `'confidential'` → accès si signalement non confidentiel (`is_confidential = 0`) ET même site, ou si déclarant
  - `'agent_choice'` → accès si signalement non confidentiel (`is_confidential = 0`) ET même site, ou si déclarant (l'agent déclarant choisit la confidentialité au cas par cas)
  - `'public'` → accès si `report.site_id === user.site_id`

Si le signalement est `abandonne` et que l'utilisateur n'est ni le déclarant ni superviseur/CSA/CHSCT, un avertissement est affiché.

#### Affichage
Utilise le template `report_card.php` :
- Carte avec bande de couleur du registre
- Tableau de détail : référence, date/heure, lieu, objet, description, déclarant, site, état, date de création
- Section « Pour le compte de » (RAMI uniquement, si renseigné)
- Section « Réponse » (si `report.reponse` non null) : texte + répondant + date
- Section « Historique des réponses » (si entrées dans `report_responses`) : tableau Date | Répondant | Nouvel état | Réponse

Boutons d'action :
- **Modifier** — si déclarant ET état `nouveau`/`en_cours`
- **Répondre** — si superviseur ET état `nouveau`/`en_cours`
- **Abandonner** — si superviseur ET état non `abandonne`/`traite`
- **Télécharger en PDF** — toujours affiché (lien vers `report_print&uuid={uuid}`)
- **Retour à la liste** — lien vers `report_list&type={type}`

---

### 5.8 Modification d'un signalement (`pages/report_edit.php`)

**URL** : `index.php?page=report_edit&uuid={report_uuid}`
**Accès** : Déclarant uniquement, et uniquement si état `nouveau` ou `en_cours`
**Méthode** : GET (affichage), POST (traitement)

#### Contrôle d'accès
- Charger le signalement par UUID
- Si `report.declarant_id !== user.id` → erreur + redirection
- Si `report.etat` est `traite` ou `abandonne` → erreur + redirection

#### Affichage
- Même formulaire que `report_create.php` pré-rempli avec les données existantes
- Champs modifiables : date, heure, lieu, objet, description
- Champs en lecture seule : type, déclarant, site
- Champs RAMI `pour_compte` modifiables si initialement renseignés

#### Traitement POST (`handlers/report_edit_handler.php`)

**Validation** : même que création, plus :
1. Jeton CSRF
2. Vérification de propriété (declarant_id)
3. Vérification d'état (re-check depuis la DB, pas le formulaire)

```sql
UPDATE reports
SET objet = :objet, description = :description,
    date_evenement = :date_evenement, heure_evenement = :heure_evenement,
    lieu = :lieu, pour_compte_nom = :pour_compte_nom,
    pour_compte_prenom = :pour_compte_prenom,
    updated_at = datetime('now')
WHERE uuid = :uuid AND declarant_id = :user_id AND etat IN ('nouveau', 'en_cours');
```

---

### 5.9 Télécharger en PDF (`pages/report_print.php`)

**URL** : `index.php?page=report_print&uuid={report_uuid}`
**Accès** : Déclarant, superviseur, CSA/CHSCT (même contrôle que `report_view` via `canAccessReport()`)
**Méthode** : GET

#### Génération PDF côté serveur (FPDF 1.9)

La page ne produit plus de HTML imprimable mais génère un fichier PDF téléchargeable côté serveur via **FPDF 1.9** (bibliothèque bundlée dans `src/lib/fpdf/`).

#### Contenu du PDF

- **En-tête** : nom de l'organisation (`app_nom_organisation`) + référence du signalement
- **Badges colorés** : registre (RSST/RAMI/DGI) et état (Nouveau/En cours/Traité/Abandonné) avec fond de couleur
- **Détail du signalement** : date/heure événement, lieu, objet, description, déclarant, site, confidentialité
- **Section « Pour le compte de »** (RAMI uniquement, si renseigné)
- **Pièce jointe** : nom du fichier + mention « image embarquée ci-dessous » si c'est une image
- **Image jointe embarquée** (si l'attachment est une image JPG/PNG/GIF) : l'image est intégrée directement dans le PDF, avec un fond gris clair et des dimensions proportionnelles (max 180 mm de large, max 120 mm de haut). Un fichier temporaire est utilisé (FPDF `Image()` nécessite un chemin fichier), puis supprimé immédiatement. Les PDF en pièce jointe ne sont pas embarqués (nom du fichier uniquement).
- **Tableau de l'historique des réponses** (si entrées dans `report_responses`) : Date | Répondant | Nouvel état | Réponse
- **Pied de page** : pagination (`Page N / Total`) + date de génération

#### Polices et encodage

- Utilise les polices **DejaVu Sans** (TrueType) pour le support des caractères accentués français
- Conversion UTF-8 → cp1252 via `mb_convert_encoding($text, 'cp1252', 'UTF-8')` pour FPDF
- Polices chargées : `DejaVuSans`, `DejaVuSans-Bold`, `DejaVuSans-Oblique`

#### Téléchargement

- En-tête HTTP : `Content-Type: application/pdf`
- En-tête HTTP : `Content-Disposition: attachment; filename="signalement-{reference}.pdf"`
- Le PDF est généré en mémoire et envoyé directement en téléchargement (pas de fichier temporaire)

#### Dépendances

- FPDF 1.9 bundlée (`src/lib/fpdf/fpdf.php`)
- Polices DejaVu Sans (`src/lib/fpdf/font/`)
- Aucune dépendance Composer externe

---

### 5.10 Abandon d'un signalement (`pages/report_abandon.php`)

**URL** : `index.php?page=report_abandon&uuid={report_uuid}`
**Accès** : Superviseur uniquement, état `nouveau` ou `en_cours`
**Méthode** : GET (confirmation), POST (traitement)

#### Affichage
- Page de confirmation avant abandon
- Résumé du signalement : référence, objet, date, état
- Utilise le template `confirm_dialog.php`

#### Traitement POST (`handlers/report_abandon_handler.php`)

1. Vérifier le jeton CSRF
2. Charger le signalement
3. Vérifier que l'utilisateur est superviseur
4. Vérifier l'état (`nouveau` ou `en_cours`)
5. Mettre à jour :

```sql
UPDATE reports
SET etat = 'abandonne', updated_at = datetime('now')
WHERE uuid = :uuid AND etat IN ('nouveau', 'en_cours');
```

6. Flash : « Signalement {reference} abandonné »
7. Redirection vers `report_list&type={type}`

---

### 5.11 Réponse à un signalement (`pages/report_respond.php`)

**URL** : `index.php?page=report_respond&uuid={report_uuid}`
**Accès** : Superviseur uniquement, état `nouveau` ou `en_cours`
**Méthode** : GET (affichage), POST (traitement)

#### Affichage
- Titre : « Répondre au signalement — {reference} »
- Résumé du signalement (lecture seule) : référence, registre, date, déclarant, site, objet, description, état
- Historique des réponses précédentes (si existant)
- Formulaire :

| Champ | Type | Requis | Notes |
|-------|------|--------|-------|
| `nouvel_etat` | `<select>` | Oui | « En cours » / « Traité » |
| `reponse` | `<textarea rows="6">` | Oui | Max 20 000 caractères |

Champs cachés : `csrf_token`, `report_uuid`

#### Traitement POST (`handlers/report_respond_handler.php`)

**Validation** :
1. Jeton CSRF
2. Rôle superviseur
3. `nouvel_etat` parmi `en_cours`, `traite`
4. `reponse` non vide, max 20 000 caractères

**Traitement** :

```sql
-- Mettre à jour le signalement
UPDATE reports
SET etat = :nouvel_etat,
    reponse = :reponse,
    repondant_id = :user_id,
    date_reponse = datetime('now'),
    updated_at = datetime('now')
WHERE uuid = :uuid AND etat IN ('nouveau', 'en_cours');

-- Insérer dans l'historique des réponses
INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat)
VALUES (:uuid, :user_id, :reponse, :nouvel_etat);
```

- Flash : « Réponse enregistrée pour le signalement {reference} »
- Redirection vers `report_view&uuid={uuid}`

---

### 5.12 Synthèse (`pages/synthesis.php`)

**URL** : `index.php?page=synthesis`
**Accès** : superviseur, chsct
**Méthode** : GET

#### Affichage
- Titre : « Synthèse des signalements »
- Filtres : Année + Site
- Tableau croisé : Site × Registre × État

| Site | RSST Nouv. | RSST En cours | RSST Traité | RSST Total | RAMI ... | DGI ... | Total |
|------|------|------|------|------|------|------|------|

Chaque cellule contient le nombre de signalements pour la combinaison site/registre/état. Les cellules à 0 sont atténuées (texte plus clair). Dernière ligne = totaux.

---

### 5.13 Export (`pages/export.php`)

**URL** : `index.php?page=export`
**Accès** : superviseur, chsct
**Méthode** : GET (formulaire), POST (génération CSV)

#### Formulaire de filtres

| Champ | Type | Options |
|-------|------|---------|
| `type` | `<select>` | Tous, RSST, RAMI, DGI |
| `site_id` | `<select>` | Tous + liste des sites |
| `declarant_id` | `<select>` | Tous + liste des utilisateurs |
| `date_from` | `<input type="date">` | Date de début |
| `date_to` | `<input type="date">` | Date de fin |
| `etat` | `<select multiple>` | Nouveau, En cours, Traité, Abandonné |

Bouton : « Exporter en CSV »

#### Traitement (`handlers/export_handler.php`)
- Construction dynamique de la requête SQL avec clauses WHERE
- En-têtes CSV avec noms de colonnes en français
- Téléchargement du fichier avec `Content-Type: text/csv`

---

### 5.14 Statistiques (`pages/statistics.php`)

**URL** : `index.php?page=statistics`
**Accès** : superviseur, chsct
**Méthode** : GET

#### Affichage
- Titre : « Statistiques »
- Filtres : Année + Site
- Cartes KPI : total signalements, nouveaux, en cours, traités, abandonnés, par registre
- Tableau par site : Site | Total | Nouveau | En cours | Traité | Abandonné | RSST | RAMI | DGI

---

### 5.15 Paramètres (`pages/settings.php`)

**URL** : `index.php?page=settings[&tab={tab}]`
**Accès** : superviseur uniquement
**Méthode** : GET (affichage), POST (traitement)

#### Onglets

| Onglet | Paramètre `tab` | Contenu |
|--------|------------------|---------|
| 📍 Notifications par site | `sites` | Adresses e-mail de notification par site |
| 🌐 Notifications globales | `global` | Adresses e-mail globales (tous sites, tous registres) |
| 📧 Configuration SMTP | `smtp` | Paramètres SMTP + test d'envoi |
| 🏢 Gestion des sites | `manage_sites` | Ajout/désactivation/réactivation/suppression de sites |
| ⚙️ Paramètres de l'application | `app` | Nom organisation, libellé unités, superviseurs, visibilité agents |

#### Onglet « Notifications par site »
Pour chaque site actif, champ de saisie d'adresses e-mail (système de tags). Utilise des inputs cachés pour stocker les valeurs.

#### Onglet « Notifications globales »
Adresses e-mail recevant des notifications pour tous les sites et tous les registres.

#### Onglet « Configuration SMTP »

| Champ | Clé config | Type | Défaut |
|-------|-----------|------|--------|
| Serveur SMTP | `smtp_host` | text | — |
| Port SMTP | `smtp_port` | number | 25 |
| Utilisateur SMTP | `smtp_user` | text | — |
| Mot de passe SMTP | `smtp_pass` | password | — |
| Adresse d'expédition | `smtp_from` | email | — |
| Chiffrement | `smtp_encryption` | text | none |

Bouton « Envoyer un e-mail de test » avec champ de saisie du destinataire (requête AJAX POST vers `smtp_test`).

#### Onglet « Gestion des sites »
- Formulaire d'ajout : code, nom, département
- Tableau des sites existants : code, nom, département, nombre d'agents, nombre de signalements, statut, actions
- Actions : désactiver/réactiver (toggle `is_active`), supprimer (uniquement si 0 agents et 0 signalements)

#### Onglet « Paramètres de l'application »

| Champ | Clé config | Description |
|-------|-----------|-------------|
| Nom de l'organisation | `app_nom_organisation` | Affiché dans l'en-tête et les e-mails |
| Nom complet | `app_nom_complet` | Nom complet de l'organisation |
| Libellé des unités | `app_label_unite` | Ex: UR, UD, Direction... Utilisé partout dans l'UI |
| Logins Windows des superviseurs | `app_superviseur_usernames` | Liste séparée par virgules (ex: `jean.martin, sophie.dupont`) — auto-promotion superviseur |
| Visibilité des signalements | `app_report_visibility` | Sélecteur : « Confidentiel » (`confidential`, défaut) / « Choix de l'agent » (`agent_choice`) / « Public » (`public`) |

> **Note** : L'ancienne clé `app_agent_visibility` est dépréciée. Elle est migrée automatiquement vers `app_report_visibility` lors de la première exécution. Les valeurs `confidential` et `public` sont conservées, la valeur `agent_choice` est nouvelle.

Un avertissement réglementaire s'affiche si la visibilité est restreinte : les registres SST sont consultables par tous les agents par principe de transparence (Code du travail).

---

### 5.16 Gestion des utilisateurs (`pages/users.php`)

**URL** : `index.php?page=users[&tab={tab}]`
**Accès** : superviseur uniquement
**Méthode** : GET (affichage), POST via handlers

#### Onglet « Liste des utilisateurs »
- Barre de recherche (nom, prénom, e-mail, identifiant, site)
- Tableau : Nom | Prénom | Email | Rôle (badge) | Site | Statut | Actions (Voir, Éditer)
- Les utilisateurs inactifs sont affichés en atténué (opacité réduite)

#### Onglet « Inscrire un utilisateur »
Formulaire de création avec champs : nom, prénom, email, identifiant Windows, rôle (sélecteur `ROLE_LABELS`), site.

Rôles disponibles dans le sélecteur : Agent, Superviseur, Membre CSA/CHSCT.

---

## 6. Système d'authentification

### Architecture

L'application utilise exclusivement l'authentification Windows intégrée d'IIS. **Il n'y a pas de LDAP, pas de formulaire de login en production.**

### Flux en production (`DEV_MODE = false`)

1. IIS authentifie l'utilisateur via Windows Authentication **avant** que PHP ne s'exécute
2. `$_SERVER['AUTH_USER']` est toujours défini (format : `DOMAIN\username` ou `username@domain`)
3. `getAuthenticatedUser()` lit `AUTH_USER`, extrait le nom d'utilisateur via `extractUsername()`
4. `findOrCreateUser()` cherche l'utilisateur en base ou le crée automatiquement
5. L'utilisateur est stocké dans `$_SESSION['user']`

### Détection de l'environnement (APP_ENV)

La constante `APP_ENV` est déterminée par un système de priorité à 3 niveaux :

1. **`APP_ENV_FORCE`** (priorité maximale) : si cette constante est définie dans `config.php` (ex: `'dev'` ou `'prod'`), elle est utilisée sans vérification supplémentaire
2. **`getenv('APP_ENV')`** (priorité intermédiaire) : variable d'environnement du serveur, utile pour les déploiements sans modification de code
3. **Auto-détection via `$_SERVER['AUTH_USER']`** (priorité minimale, fallback) : si `AUTH_USER` est disponible → `prod`, sinon → `dev`

```php
// Ordre de résolution :
defined('APP_ENV_FORCE') ? APP_ENV_FORCE : (getenv('APP_ENV') ?: (isset($_SERVER['AUTH_USER']) ? 'prod' : 'dev'))
```

### Flux en développement (`DEV_MODE = true`)

1. IIS n'est pas utilisé — `AUTH_USER` n'est pas disponible
2. Un formulaire mock de connexion est affiché (`pages/login.php`)
3. `mockLogin($username)` appelle `findOrCreateUser($username)` et stocke en session

### Extraction du nom d'utilisateur

`extractUsername(string $authUser): string` gère les formats IIS :
- `DREETS-BFC\jean.martin` → `jean.martin`
- `jean.martin@dreets-bfc.gouv.fr` → `jean.martin`
- `jean.martin` → `jean.martin`
- Résultat toujours en minuscules

### Auto-provisionnement

`findOrCreateUser(PDO $pdo, string $username): ?array`
- Si l'utilisateur existe en base : retourne ses données
- Si l'utilisateur n'existe pas : appelle `autoProvisionUser()`

`autoProvisionUser(PDO $pdo, string $username): ?array`
- Génère le nom d'affichage à partir du username (ex: `jean.martin` → Jean Martin)
- Détermine le rôle via `determineProvisionRole()`
- Crée l'utilisateur avec `site_id = NULL` (choix du site à la première connexion)
- Email auto-généré : `{username}@dreets.gouv.fr`

### Attribution du rôle superviseur

Deux méthodes :

1. **Via l'interface** : un superviseur peut modifier le rôle d'un utilisateur via la page `user_edit` → `updateUser()`

2. **Via la liste de configuration** (`app_superviseur_usernames`) :
   - Liste séparée par virgules de logins Windows (ex: `jean.martin, sophie.dupont`)
   - `determineProvisionRole()` vérifie cette liste lors de l'auto-provisionnement
   - `checkAndPromoteUser()` vérifie la liste **à chaque requête** (pas uniquement à la connexion) et promeut automatiquement un agent existant si son username y figure
   - Utile pour la première installation : permet de désigner les premiers superviseurs sans accès à la base de données
   - **Pas de mécanisme de préfixe** (l'ancien système auto-admin par préfixe n'existe plus)

---

## 7. Contrôle d'accès et visibilité

### Fonction centrale : `canAccessReport()`

`canAccessReport(array $report, array $user): bool`

Centralise toutes les règles d'accès à un signalement. Un utilisateur peut consulter un signalement si et seulement si :

- Il est le **déclarant** du signalement (`report.declarant_id === user.id`), OU
- Il a le rôle **superviseur**, OU
- Il a le rôle **CSA/CHSCT**

Pour les agents (non déclarants du signalement consulté) :
- Si `getReportVisibility() === 'confidential'` : accès si `report.is_confidential = 0` ET `report.site_id === user.site_id`
- Si `getReportVisibility() === 'agent_choice'` : accès si `report.is_confidential = 0` ET `report.site_id === user.site_id` (l'agent déclarant choisit au cas par cas)
- Si `getReportVisibility() === 'public'` : accès si `report.site_id === user.site_id`

### Visibilité des signalements

`getReportVisibility(): string` — retourne le mode de visibilité pour les agents :

| Valeur | Description | Remarque |
|--------|-------------|----------|
| `confidential` | Signalements confidentiels par défaut, l'agent ne voit que les siens + les non confidentiels de son site | **Par défaut** |
| `agent_choice` | L'agent déclarant choisit la confidentialité au cas par cas (`is_confidential`), les autres agents voient les non confidentiels de leur site | Compromis flexibilité/transparence |
| `public` | Tous les signalements du site sont visibles par tous les agents | Conforme au principe de transparence |

Configurée via `app_report_visibility` dans `config_app`. Les superviseurs et CSA/CHSCT voient toujours tous les sites (`canSeeAllSites() === true`).

- `'confidential'` (défaut) : l'agent voit les signalements publics de son site + ses propres signalements (même confidentiels). Chaque signalement a un flag `is_confidential` (défaut : 1). L'agent peut décocher ce flag lors de la création.
- `'agent_choice'` : même comportement que `confidential` mais l'interface indique explicitement à l'agent qu'il peut choisir la visibilité de son signalement.
- `'public'` : l'agent voit tous les signalements de son site. Le flag `is_confidential` est ignoré dans ce mode.

Fonctions :
- `getReportVisibility()` : retourne `'confidential'`, `'agent_choice'`, `'public'` ou `'all'` (superviseur/CSA/CHSCT)
- `reportVisibilityIsConfidential()` : `getReportVisibility() === 'confidential'`
- `reportVisibilityIsAgentChoice()` : `getReportVisibility() === 'agent_choice'`
- `reportVisibilityIsPublic()` : `getReportVisibility() === 'public'`

Compatibilité ascendante :
- Ancienne valeur `'site'` → `'public'`
- Ancienne valeur `'own'` → `'confidential'`
- Ancienne valeur `'0'` → `'public'`
- Ancienne valeur `'1'` → `'confidential'`
- `app_agent_visibility` → migrée vers `app_report_visibility`
- `agentVisibilityIsConfidential()` : alias déprécié, appelle `reportVisibilityIsConfidential()`
- `agentVisibilityIsPublic()` : alias déprécié, appelle `reportVisibilityIsPublic()`

### Matrice des permissions par rôle

| Action | agent | superviseur | chsct |
|--------|-------|-------------|-------|
| Créer un signalement | ✅ | ✅ | ✅ |
| Voir ses propres signalements | ✅ | ✅ | ✅ |
| Voir les signalements de son site | ✅ (si visibilité `public` ou `agent_choice`) | ✅ (tous sites) | ✅ (tous sites) |
| Voir tous les signalements | ❌ | ✅ | ✅ |
| Modifier un signalement | ✅ (déclarant, état nouv./en cours) | ✅ (déclarant, état nouv./en cours) | ❌ |
| Répondre à un signalement | ❌ | ✅ (état nouv./en cours) | ❌ |
| Abandonner un signalement | ❌ | ✅ (état nouv./en cours) | ❌ |
| Imprimer/Télécharger PDF un signalement | ✅ | ✅ | ✅ |
| Synthèse | ❌ | ✅ | ✅ |
| Export CSV | ❌ | ✅ | ✅ |
| Statistiques | ❌ | ✅ | ✅ |
| Gestion des utilisateurs | ❌ | ✅ | ❌ |
| Paramètres | ❌ | ✅ | ❌ |

### Middleware de contrôle de rôle

- `requireRole(array $roles): void` — Vérifie que l'utilisateur a l'un des rôles requis. Sinon, affiche `access_denied.php` et termine l'exécution.
- `hasRole(string $role): bool` — Vérifie un rôle sans terminer l'exécution.
- `hasAnyRole(array $roles): bool` — Vérifie l'appartenance à l'un des rôles.

---

## 8. Règles métier

### Génération de référence

Format : `{type}-{YY}-{NNN}`

- `type` : `rsst`, `rami` ou `dgi`
- `YY` : année sur 2 chiffres (ex: `25`)
- `NNN` : numéro séquentiel sur 3 chiffres avec zéro-padding (ex: `001`)

Exemples : `rsst-25-001`, `rami-25-015`, `dgi-25-003`

La séquence est gérée par la table `report_sequence` avec un UPSERT atomique :

```sql
INSERT INTO report_sequence (type, year, last_sequence) VALUES (:type, :year, 1)
ON CONFLICT(type, year) DO UPDATE SET last_sequence = last_sequence + 1;
```

### « Pour le compte de » (RAMI)

Dans le registre RAMI, un signalement peut être déposé pour le compte d'un autre agent. Les champs `pour_compte_nom` et `pour_compte_prenom` sont renseignés. Si l'agent concerné est trouvé en base, une notification e-mail lui est envoyée via `notifyPourCompte()`.

### Désactivation de compte (soft delete)

Les utilisateurs sont désactivés (`is_active = 0`) et non supprimés physiquement. Un utilisateur désactivé :
- Ne peut plus se connecter (`findOrCreateUser` filtre `is_active = 1`)
- N'apparaît plus dans la liste des utilisateurs actifs
- Ses signalements existants sont conservés

### Gestion des sites

- Les sites peuvent être ajoutés via l'onglet « Gestion des sites »
- Un site désactivé n'apparaît plus dans les listes de choix (pour les nouveaux agents)
- Les signalements existants d'un site désactivé restent accessibles
- Un site ne peut être supprimé définitivement que s'il n'a ni utilisateurs ni signalements rattachés

---

## 9. Architecture CSS

### Variables CSS (custom properties)

Les couleurs des registres sont définies via des variables CSS :
- `--rsst-color` : bleu (#2E5C8A)
- `--rami-color` : gris (#6C6C6C)
- `--dgi-color` : rouge (#B22222)

### Classes de badges

| Badge | Classe CSS |
|-------|-----------|
| RSST | `badge--rsst` |
| RAMI | `badge--rami` |
| DGI | `badge--dgi` |
| Agent | `badge--agent` |
| Superviseur | `badge--superviseur` |
| CSA/CHSCT | `badge--chsct` |
| Nouveau | `badge--nouveau` |
| En cours | `badge--en-cours` |
| Traité | `badge--traite` |
| Abandonné | `badge--abandonne` |

### Cartes de registre

| Carte | Classe CSS |
|-------|-----------|
| RSST | `card--rsst` |
| RAMI | `card--rami` |
| DGI | `card--dgi` |

### Fonctions utilitaires CSS

- `getRegistryColor(string $type): string` → retourne la variable CSS de couleur
- `getEtatBadgeClass(string $etat): string` → classe badge pour un état
- `getRegistryBadgeClass(string $type): string` → classe badge pour un registre
- `getRoleBadgeClass(string $role): string` → classe badge pour un rôle

---

## 10. Format des références

`generateReference(string $type, string $year2, int $seq): string`

Construit la référence : `{type}-{year2}-{seq_padded}`

```php
generateReference('rsst', '25', 1)   // → "rsst-25-001"
generateReference('rami', '25', 15)  // → "rami-25-015"
generateReference('dgi', '25', 3)    // → "dgi-25-003"
```

`getNextSequence(PDO $pdo, string $type, int $year): int`

Utilise un UPSERT atomique sur `report_sequence` pour obtenir le numéro séquentiel suivant.

---

## 11. Notifications par e-mail

### Module d'envoi (`src/mail.php`)

- `sendMail(string $to, string $subject, string $body, string $from): bool` — Envoi via SMTP configuré, fallback vers `mail()` PHP
- `sendViaSMTP(string $to, string $subject, string $body, string $headers): bool` — Envoi via socket SMTP brut (pas de dépendance externe), supporte TLS et STARTTLS
- `notifyNewReport(PDO $pdo, string $reportUuid, string $type, int $siteId): void` — Notifie les destinataires configurés d'un nouveau signalement
- `notifyReportResponse(PDO $pdo, string $reportUuid, int $respondentId): void` — Notifie le déclarant qu'une réponse a été apportée
- `notifyPourCompte(PDO $pdo, string $reportUuid): void` — Notifie l'agent pour lequel un signalement RAMI a été déposé
- `getNotificationRecipients(PDO $pdo, int $siteId): array` — Rassemble les e-mails par site + globaux (dédoublonnés)
- `getBaseUrl(): string` — Construit l'URL de base pour les liens dans les e-mails

### Configuration des notifications

Les notifications sont gérées via la table `notification_settings` :
- Notifications par site : `type = 'site'`, `site_id` renseigné
- Notifications globales : `type = 'global'`, `site_id = NULL`

### Test SMTP

L'interface de paramétrage offre un bouton « Envoyer un e-mail de test » qui envoie une requête AJAX POST vers `handlers/smtp_test_handler.php`.

---

## 12. Référence des fonctions

### `src/config.php`

Pas de fonctions. Définit les constantes et tableaux :
- `APP_NAME`, `APP_VERSION`, `SITE_NAME`, `APP_ENV`, `DEV_MODE`
- `DB_PATH`, `ITEMS_PER_PAGE`, `MAX_OBJECT_LENGTH`, `MAX_DESCRIPTION_LENGTH`, `MAX_LIEU_LENGTH`
- `REPORT_VISIBILITY_MODES` : `['confidential', 'agent_choice', 'public']`
- `REGISTRY_LABELS` : `[rsst => ..., rami => ..., dgi => ...]`
- `REGISTRY_SHORT_LABELS` : `[rsst => 'RSST', rami => 'RAMI', dgi => 'DGI']`
- `ROLE_LABELS` : `[agent => 'Agent', superviseur => 'Superviseur', chsct => 'Membre CSA/CHSCT']`
- `ETAT_LABELS` : `[nouveau => 'Nouveau', en_cours => 'En cours', traite => 'Traité', abandonne => 'Abandonné']`

### `src/auth.php`

| Fonction | Signature | Description |
|----------|-----------|-------------|
| `getAuthenticatedUser` | `(): ?array` | Retourne l'utilisateur authentifié (session, puis AUTH_USER en prod) |
| `extractUsername` | `(string $authUser): string` | Extrait le username depuis AUTH_USER (supprime domaine, minuscules) |
| `findOrCreateUser` | `(PDO $pdo, string $username): ?array` | Cherche ou crée un utilisateur en base |
| `autoProvisionUser` | `(PDO $pdo, string $username): ?array` | Crée un nouvel utilisateur avec nom déduit du username |
| `mockLogin` | `(string $username): ?array` | Connexion mock (dev uniquement) |
| `determineProvisionRole` | `(PDO $pdo, string $username): string` | Détermine le rôle à l'auto-provisionnement (vérifie `app_superviseur_usernames`) |
| `checkAndPromoteUser` | `(PDO $pdo, array $user, string $username): array` | Vérifie et promeut un agent existant en superviseur si dans la liste config |

### `src/session.php`

| Fonction | Signature | Description |
|----------|-----------|-------------|
| `startSession` | `(): void` | Démarre la session avec paramètres sécurisés |
| `generateCsrfToken` | `(): string` | Génère et stocke un jeton CSRF en session |
| `validateCsrfToken` | `(string $token): bool` | Valide un jeton CSRF (hash_equals) |
| `setFlash` | `(string $type, string $message): void` | Stocke un message flash |
| `getFlash` | `(): ?array` | Récupère et efface le message flash |
| `setFormData` | `(array $data): void` | Stocke les données de formulaire en session |
| `getFormData` | `(): array` | Récupère et efface les données de formulaire |
| `setFormErrors` | `(array $errors): void` | Stocke les erreurs de formulaire en session |
| `getFormErrors` | `(): array` | Récupère et efface les erreurs de formulaire |
| `getFieldError` | `(array $errors, string $field): ?string` | Récupère l'erreur d'un champ spécifique |

### `src/helpers.php`

| Fonction | Signature | Description |
|----------|-----------|-------------|
| `e` | `(?string $string): string` | Échappement HTML (`htmlspecialchars` avec ENT_QUOTES + UTF-8) |
| `redirect` | `(string $url): void` | Redirection HTTP + exit |
| `setCookieSafe` | `(string $name, string $value, int $expires, string $path, bool $httpOnly, string $sameSite): void` | Définit un cookie (compatible web + CLI/proxy) |
| `formatDateFR` | `(?string $date): string` | Formate une date ISO en `d/m/Y` |
| `formatDateTimeFR` | `(?string $datetime): string` | Formate un datetime ISO en `d/m/Y à H:i` |
| `generateReference` | `(string $type, string $year2, int $seq): string` | Génère une référence de signalement |
| `getNextSequence` | `(PDO $pdo, string $type, int $year): int` | Numéro séquentiel suivant (UPSERT atomique) |
| `getRegistryColor` | `(string $type): string` | Variable CSS de couleur du registre |
| `getEtatBadgeClass` | `(string $etat): string` | Classe CSS badge pour un état |
| `getRegistryBadgeClass` | `(string $type): string` | Classe CSS badge pour un registre |
| `getRoleBadgeClass` | `(string $role): string` | Classe CSS badge pour un rôle |
| `canSeeAllSites` | `(): bool` | L'utilisateur peut-il voir tous les sites ? (superviseur/CSA/CHSCT uniquement) |
| `getReportVisibility` | `(): string` | Mode de visibilité signalements : `'confidential'`, `'agent_choice'` ou `'public'` |
| `reportVisibilityIsConfidential` | `(): bool` | Mode confidentiel ? |
| `reportVisibilityIsAgentChoice` | `(): bool` | Mode choix de l'agent ? |
| `reportVisibilityIsPublic` | `(): bool` | Mode public ? |
| `getAgentVisibility` | `(): string` | **Déprécié** — alias de `getReportVisibility()` |
| `agentVisibilityIsConfidential` | `(): bool` | **Déprécié** — alias de `reportVisibilityIsConfidential()` |
| `agentVisibilityIsPublic` | `(): bool` | **Déprécié** — alias de `reportVisibilityIsPublic()` |
| `truncate` | `(string $string, int $length): string` | Tronque avec ellipsis |
| `getConfig` | `(string $cle, string $default): string` | Lit une valeur de `config_app` (avec cache statique) |
| `updateConfig` | `(PDO $pdo, string $cle, string $valeur): void` | Met à jour une valeur dans `config_app` (UPSERT) |
| `clearConfigCache` | `(): void` | Invalide le cache de `getConfig()` |
| `assetUrl` | `(string $path): string` | URL d'un asset statique |
| `url` | `(string $page, array $params): string` | Construit une URL interne |
| `todayISO` | `(): string` | Date du jour en format ISO (Y-m-d) |
| `nowTime` | `(): string` | Heure actuelle en format HH:MM |

### `src/database.php`

| Fonction | Signature | Description |
|----------|-----------|-------------|
| `getDB` | `(): PDO` | Connexion PDO singleton (crée le schéma si nouveau) |
| `seedDefaultData` | `(PDO $pdo): void` | Insère les données initiales (sites + utilisateurs dev) |
| `migrateSchema` | `(PDO $pdo): void` | Auto-migration : crée les tables manquantes + index |
| `migrateConfigKeys` | `(PDO $pdo): void` | Auto-migration : ajoute les clés config manquantes |

### `src/queries/report_queries.php`

| Fonction | Signature | Description |
|----------|-----------|-------------|
| `createReport` | `(PDO $pdo, array $data): string` | Crée un signalement, retourne l'UUID |
| `getReportByUuid` | `(PDO $pdo, string $uuid): ?array` | Signalement par UUID avec site et répondant |
| `getReportById` | `(PDO $pdo, int $id): ?array` | **Déprécié** — utiliser `getReportByUuid()` |
| `getReportsByRegistry` | `(PDO $pdo, string $type, array $filters, int $userSiteId, bool $seeAllSites, int $page, int $perPage): array` | Liste filtrée et paginée par registre |
| `getReportsBySite` | `(PDO $pdo, int $siteId): array` | Signalements par site |
| `updateReport` | `(PDO $pdo, string $uuid, array $data, int $userId): bool` | Modification par le déclarant |
| `abandonReport` | `(PDO $pdo, string $uuid, int $userId): bool` | Abandon (soft delete) |
| `respondToReport` | `(PDO $pdo, string $uuid, int $userId, string $reponse, string $nouvelEtat): bool` | Réponse du superviseur + historique |
| `countReportsByState` | `(PDO $pdo, string $type, int $siteId, bool $seeAllSites): array` | Comptage par état |
| `getReportResponses` | `(PDO $pdo, string $reportUuid): array` | Historique des réponses |
| `countActiveReports` | `(PDO $pdo, string $type, int $siteId): int` | Comptage des signalements actifs |
| `countActiveReportsForUser` | `(PDO $pdo, string $type, int $userId): int` | Comptage des signalements actifs d'un utilisateur |

### `src/queries/user_queries.php`

| Fonction | Signature | Description |
|----------|-----------|-------------|
| `getUserByUsername` | `(PDO $pdo, string $username): ?array` | Utilisateur par username (actifs uniquement) |
| `getUserById` | `(PDO $pdo, int $id): ?array` | Utilisateur par ID |
| `getAllUsers` | `(PDO $pdo, int $siteId, bool $active): array` | Liste des utilisateurs (filtrable par site et activité) |
| `updateUserRole` | `(PDO $pdo, int $id, string $role): bool` | Mise à jour du rôle |
| `updateUserSite` | `(PDO $pdo, int $id, int $siteId): bool` | Mise à jour du site |
| `createUser` | `(PDO $pdo, array $data): int` | Création d'un utilisateur |
| `updateUser` | `(PDO $pdo, int $id, array $data): bool` | Mise à jour complète du profil |
| `countActiveUsers` | `(PDO $pdo): int` | Nombre d'utilisateurs actifs |
| `deactivateUser` | `(PDO $pdo, int $id): bool` | Désactivation (soft delete) |
| `reactivateUser` | `(PDO $pdo, int $id): bool` | Réactivation |

### `src/queries/site_queries.php`

| Fonction | Signature | Description |
|----------|-----------|-------------|
| `getAllSites` | `(PDO $pdo): array` | Tous les sites |
| `getActiveSites` | `(PDO $pdo): array` | Sites actifs uniquement |
| `getSiteById` | `(PDO $pdo, int $id): ?array` | Site par ID |
| `getSiteByCode` | `(PDO $pdo, string $code): ?array` | Site par code |
| `getSiteByName` | `(PDO $pdo, string $nom): ?array` | Site par nom |
| `createSite` | `(PDO $pdo, string $code, string $nom, string $departement): int` | Création d'un site |
| `updateSite` | `(PDO $pdo, int $id, string $code, string $nom, string $departement): bool` | Mise à jour d'un site |
| `toggleSiteActive` | `(PDO $pdo, int $id, bool $active): bool` | Activation/désactivation |
| `countUsersBySite` | `(PDO $pdo, int $id): int` | Nombre d'utilisateurs actifs par site |
| `countReportsBySite` | `(PDO $pdo, int $id): int` | Nombre de signalements par site |
| `deleteSite` | `(PDO $pdo, int $id): bool` | Suppression définitive (si aucun user ni report) |

### `src/queries/stats_queries.php`

| Fonction | Signature | Description |
|----------|-----------|-------------|
| `getSynthesisData` | `(PDO $pdo, string $year, int $siteId): array` | Données de synthèse par site/registre/état |
| `getExportData` | `(PDO $pdo, array $filters): array` | Données pour export CSV avec filtres dynamiques |
| `getStatisticsKPIs` | `(PDO $pdo, string $year, int $siteId): array` | KPI statistiques globaux |
| `getStatsBySite` | `(PDO $pdo, string $year, int $siteId): array` | Statistiques par site |
| `countReportsByRegistryAndSite` | `(PDO $pdo, string $type, int $siteId): int` | Comptage par registre et site |
| `getAvailableYears` | `(PDO $pdo): array` | Années disponibles dans les signalements |
| `getNotificationSettings` | `(PDO $pdo): array` | Paramètres de notification |
| `saveNotificationSetting` | `(PDO $pdo, ?int $siteId, string $type, string $registry, string $email): int` | Sauvegarde d'un paramètre de notification |
| `deleteNotificationSetting` | `(PDO $pdo, int $id): bool` | Suppression d'un paramètre de notification |
| `deleteNotificationSettingsByType` | `(PDO $pdo, string $type): int` | Suppression par type ('site' ou 'global') |
| `getSiteNotificationEmails` | `(PDO $pdo, int $siteId): array` | E-mails de notification par site |
| `getGlobalNotificationEmails` | `(PDO $pdo): array` | E-mails de notification globaux |

### `src/middleware/require_role.php`

| Fonction | Signature | Description |
|----------|-----------|-------------|
| `requireRole` | `(array $roles): void` | Vérifie le rôle, affiche access_denied si non autorisé |
| `hasRole` | `(string $role): bool` | Vérifie un rôle sans exit |
| `hasAnyRole` | `(array $roles): bool` | Vérifie l'appartenance à l'un des rôles |

### `src/session_patch.php`

| Fonction | Signature | Description |
|----------|-----------|-------------|
| `safeSessionRegenerate` | `(): void` | Regénère l'ID de session (true en prod, false en dev) |

---

## 13. Configuration applicative

La table `config_app` stocke les paramètres modifiables via l'interface. L'accès se fait par `getConfig($cle, $default)` et `updateConfig($pdo, $cle, $valeur)`.

### Clés de configuration

| Clé | Catégorie | Type | Défaut | Description |
|-----|-----------|------|--------|-------------|
| `app_nom_organisation` | app | text | DREETS BFC | Nom de l'organisation (en-tête, e-mails) |
| `app_nom_complet` | app | text | DREETS Bourgogne-Franche-Comté | Nom complet |
| `app_label_unite` | app | text | UR | Libellé des unités (UR, UD, etc.) |
| `app_superviseur_usernames` | app | text | (vide) | Logins Windows des superviseurs, séparés par virgules — auto-promotion à chaque requête |
| `app_agent_see_only_own` | app | text | 0 | **Obsolète** — utiliser `app_report_visibility` |
| `app_agent_visibility` | app | text | confidential | **Déprécié** — utiliser `app_report_visibility` (migré automatiquement) |
| `app_report_visibility` | app | text | confidential | Mode de visibilité des signalements : `confidential` \| `agent_choice` \| `public` |
| `smtp_host` | smtp | text | (vide) | Serveur SMTP |
| `smtp_port` | smtp | number | 25 | Port SMTP |
| `smtp_user` | smtp | text | (vide) | Utilisateur SMTP |
| `smtp_pass` | smtp | password | (vide) | Mot de passe SMTP |
| `smtp_from` | smtp | email | (vide) | Adresse d'expédition |
| `smtp_encryption` | smtp | text | none | Chiffrement : `none`, `tls`, `starttls` |

### Cache de configuration

`getConfig()` utilise un cache statique interne. Après un `updateConfig()`, le cache est invalidé via `clearConfigCache()` (mécanisme de flag global `$_config_cache_cleared`).

### Auto-migration des clés

`migrateConfigKeys(PDO $pdo)` est appelée à chaque requête et ajoute automatiquement les clés manquantes dans les bases existantes. Pour `app_report_visibility`, la migration convertit les anciennes valeurs : `'site'` → `'public'`, `'own'` → `'confidential'`, `app_agent_see_only_own = '1'` → `'confidential'`. L'ancienne clé `app_agent_visibility` est migrée vers `app_report_visibility` si cette dernière n'existe pas encore. La colonne `is_confidential` est ajoutée automatiquement à la table `reports` si elle n'existe pas. La colonne `uuid` (TEXT PRIMARY KEY) remplace l'ancien `id` (INTEGER PRIMARY KEY AUTOINCREMENT) — la migration ajoute la colonne `uuid` et la peuple avec des UUID v4 si elle n'existe pas, puis la promeut en PRIMARY KEY.
