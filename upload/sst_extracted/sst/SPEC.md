# SPECIFICATION — Application SST DREETS BFC

> Plateforme des Registres en Santé et Sécurité au Travail
> DREETS Bourgogne-Franche-Comté
> Version 1.0 — Specification for build agents

---

## Table of Contents

1. [Overview](#1-overview)
2. [File Tree](#2-file-tree)
3. [Database Schema](#3-database-schema)
4. [Routing](#4-routing)
5. [Page Specifications](#5-page-specifications)
6. [Security Measures](#6-security-measures)
7. [Auth System Design](#7-auth-system-design)
8. [CSS Architecture](#8-css-architecture)
9. [Report Reference Format](#9-report-reference-format)
10. [Business Rules](#10-business-rules)
11. [Error Handling](#11-error-handling)

---

## 1. Overview

### Purpose
Web application for three workplace health & safety registries at DREETS BFC (French regional labor authority). Replaces the original DIRECCTE Auvergne-Rhône-Alpes version.

### Three Registries
| Code | Full Name | Color | Description |
|------|-----------|-------|-------------|
| RSST | Registre de Santé et de Sécurité au Travail | Blue (#2E5C8A) | General health & safety reports |
| RAMI | Registre des Actes d'Agressions, de Menaces et d'Incivilités | Grey (#6C6C6C) | Aggression/threat/incivility reports |
| DGI | Registre de signalement d'un Danger Grave et Imminent | Red (#B22222) | Immediate danger reports |

### Three Roles
| Role | Code | Permissions |
|------|------|-------------|
| Agent | `agent` | Create reports, view own site reports, modify/abandon own reports |
| Superviseur | `superviseur` | Agent perms + respond to reports, synthesis, export, stats, settings, user management |
| Membre CHSCT | `chsct` | View all sites (like Superviseur) + stats/synthesis/export, read-only on settings |

### Report States
```
Nouveau → En cours → Traité
  └──→ Abandonné (soft delete, can happen from any state)
```

### Unités Départementales (UD / Sites)
The DREETS BFC region covers the following départements:
- UD 21 — Côte-d'Or
- UD 25 — Doubs
- UD 39 — Jura
- UD 58 — Nièvre
- UD 70 — Haute-Saône
- UD 71 — Saône-et-Loire
- UD 89 — Yonne
- UD 90 — Territoire de Belfort

These are stored in the `sites` table. The "Siège" (headquarters) in Dijon is also a site.

---

## 2. File Tree

```
sst-app/
├── public/                          # Web root (document root for server)
│   ├── index.php                    # Entry point — router/dispatcher
│   ├── .htaccess                    # Apache fallback (optional, for Apache deploys)
│   ├── css/
│   │   └── style.css                # Single CSS file (~400 lines max)
│   ├── img/
│   │   └── logo-dreets.png          # DREETS BFC logo (copied manually)
│   └── favicon.ico                  # Favicon
│
├── src/
│   ├── config.php                   # Constants, DB path, LDAP config, app settings
│   ├── database.php                 # PDO connection singleton + schema init
│   ├── auth.php                     # Auth logic: mock auth (dev), LDAP (prod)
│   ├── session.php                  # Session start, CSRF token generation/validation
│   ├── helpers.php                  # Utility functions: e(), redirect(), formatDate(), etc.
│   ├── queries/
│   │   ├── report_queries.php       # All SQL queries related to reports
│   │   ├── user_queries.php         # All SQL queries related to users
│   │   ├── site_queries.php         # All SQL queries related to sites
│   │   └── stats_queries.php        # All SQL queries for statistics/export/synthesis
│   └── middleware/
│       ├── require_auth.php         # Redirects to login if not authenticated
│       └── require_role.php         # Functions to check role access
│
├── templates/
│   ├── header.php                   # Top blue bar: logo, app title, user name, logout
│   ├── sidebar.php                  # Left dark grey sidebar navigation
│   ├── footer.php                   # Closing tags, no JS needed
│   ├── pagination.php               # Reusable pagination component
│   ├── report_form.php              # Shared form for RSST/RAMI/DGI creation/edit
│   ├── report_card.php              # Shared display for a single report view
│   ├── alert.php                    # Flash message display (success/error/warning)
│   └── confirm_dialog.php           # Confirmation for abandon/delete actions
│
├── pages/
│   ├── login.php                    # Login page (dev only — mock auth form)
│   ├── home.php                     # Dashboard / Accueil — 3 colored cards
│   ├── preamble.php                 # Préambule info page
│   ├── report_create.php            # Create report (RSST/RAMI/DGI)
│   ├── report_list.php              # List reports with filters
│   ├── report_view.php              # View single report detail
│   ├── report_edit.php              # Edit own report
│   ├── report_print.php             # Print-friendly view of a report
│   ├── report_abandon.php           # Abandon (soft-delete) a report
│   ├── report_respond.php           # Superviseur responds to a report
│   ├── synthesis.php                # Synthesis table across registries
│   ├── export.php                   # Export page with filters + CSV download
│   ├── statistics.php               # KPI cards + table by UD
│   ├── settings.php                 # Notification settings per site + global
│   ├── users.php                    # User management (list + edit)
│   └── access_denied.php            # 403 page
│
├── handlers/
│   ├── report_create_handler.php    # POST handler: create report
│   ├── report_edit_handler.php      # POST handler: edit report
│   ├── report_abandon_handler.php   # POST handler: abandon report
│   ├── report_respond_handler.php   # POST handler: respond to report
│   ├── export_handler.php           # POST/GET handler: generate CSV
│   ├── settings_handler.php         # POST handler: save notification settings
│   └── user_edit_handler.php        # POST handler: edit user role/site
│
├── data/
│   └── sst.db                       # SQLite database file (auto-created)
│
└── SPEC.md                          # This file
```

### File Purpose Details

| File | Purpose |
|------|---------|
| `public/index.php` | Single entry point. Parses `$_GET['page']` to determine which page to render. Includes header/sidebar, renders the page, includes footer. All page includes are relative to `pages/` directory. |
| `src/config.php` | Defines `APP_NAME`, `APP_VERSION`, `DB_PATH`, `LDAP_HOST`, `LDAP_PORT`, `LDAP_BASE_DN`, `DEV_MODE`, `SITE_NAME`, `MAX_OBJECT_LENGTH` (100), `ITEMS_PER_PAGE` (20). |
| `src/database.php` | Returns a PDO instance. On first run, executes schema creation SQL. Sets `PRAGMA foreign_keys = ON;` and `PRAGMA journal_mode = WAL;`. |
| `src/auth.php` | `getAuthenticatedUser()`: In DEV_MODE, reads `$_SESSION['mock_user']`. In prod, reads `$_SERVER['AUTH_USER']` and does LDAP lookup. Returns array with keys: `id`, `username`, `nom`, `prenom`, `email`, `role`, `site_id`. |
| `src/session.php` | `startSession()`: `session_start()`. `generateCsrfToken()`: stores token in `$_SESSION['csrf_token']` and returns it. `validateCsrfToken($token)`: compares against session token. `setFlash($type, $message)`: stores flash message. `getFlash()`: retrieves and clears flash message. |
| `src/helpers.php` | `e($string)`: `htmlspecialchars($string, ENT_QUOTES, 'UTF-8')`. `redirect($url)`: `header('Location: ' . $url); exit;`. `formatDateFR($date)`: formats ISO date to `d/m/Y`. `formatDateTimeFR($date)`: formats to `d/m/Y à H:i`. `generateReference($type, $year, $seq)`: creates reference string. |
| `src/queries/report_queries.php` | Functions: `createReport()`, `getReportById()`, `getReportsByRegistry()`, `getReportsBySite()`, `updateReport()`, `abandonReport()`, `respondToReport()`, `countReportsByState()`, `getReportSequence()`. |
| `src/queries/user_queries.php` | Functions: `getUserByUsername()`, `getUserById()`, `getAllUsers()`, `updateUserRole()`, `updateUserSite()`, `createUser()`, `deleteUser()`. |
| `src/queries/site_queries.php` | Functions: `getAllSites()`, `getSiteById()`, `getSiteByName()`. |
| `src/queries/stats_queries.php` | Functions: `getSynthesisData()`, `getExportData()`, `getStatisticsKPIs()`, `getStatsByUD()`, `countReportsByRegistryAndSite()`. |
| `src/middleware/require_auth.php` | Checks `$_SESSION['user']` is set. If not, redirects to login page. |
| `src/middleware/require_role.php` | `requireRole($roles)`: takes array of role strings. If `$_SESSION['user']['role']` not in array, renders `access_denied.php` and exits. |

---

## 3. Database Schema

### Entity Relationship

```
sites ──1:N── users
sites ──1:N── reports
users ──1:N── reports (declarant)
users ──1:N── reports (respondent)
reports ──1:N── report_responses
sites ──1:N── notification_settings
```

### CREATE TABLE Statements

```sql
-- ============================================================
-- Table: sites
-- Stores the Unités Départementales (UD) and Siège
-- ============================================================
CREATE TABLE IF NOT EXISTS sites (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    code            TEXT NOT NULL UNIQUE,           -- e.g. "UD21", "UD25", "SIEGE"
    nom             TEXT NOT NULL,                   -- e.g. "UD Côte-d'Or"
    departement     TEXT,                            -- e.g. "Côte-d'Or"
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- Table: users
-- Application users. Synced from LDAP or created in dev.
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    username        TEXT NOT NULL UNIQUE,            -- Windows login (e.g. "jean.martin")
    nom             TEXT NOT NULL,                   -- Last name
    prenom          TEXT NOT NULL,                   -- First name
    email           TEXT,                            -- Email address
    role            TEXT NOT NULL DEFAULT 'agent',   -- 'agent'|'superviseur'|'chsct'
    site_id         INTEGER NOT NULL,                -- FK to sites
    is_active       INTEGER NOT NULL DEFAULT 1,      -- Soft delete: 0 = deactivated
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

-- ============================================================
-- Table: reports
-- Core table for all three registries.
-- ============================================================
CREATE TABLE IF NOT EXISTS reports (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
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
    site_id         INTEGER NOT NULL,                -- FK to sites (UD where event occurred)
    -- State management
    etat            TEXT NOT NULL DEFAULT 'nouveau', -- 'nouveau'|'en_cours'|'traite'|'abandonne'
    -- Respondent (superviseur who handled the report)
    repondant_id    INTEGER,                         -- FK to users (nullable)
    date_reponse    TEXT,                            -- When supervisor responded
    reponse         TEXT,                            -- Supervisor's response text
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
-- The first response also populates reports.reponse/repondant_id.
-- ============================================================
CREATE TABLE IF NOT EXISTS report_responses (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id       INTEGER NOT NULL,                -- FK to reports
    user_id         INTEGER NOT NULL,                -- FK to users (the supervisor)
    reponse         TEXT NOT NULL,                   -- Response text
    nouvel_etat     TEXT,                            -- State change if any: 'en_cours'|'traite'
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
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
-- Indexes
-- ============================================================
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
CREATE INDEX IF NOT EXISTS idx_report_responses_report_id ON report_responses(report_id);
CREATE INDEX IF NOT EXISTS idx_notification_settings_site_id ON notification_settings(site_id);
```

### Initial Seed Data

```sql
-- Sites
INSERT INTO sites (code, nom, departement) VALUES
    ('SIEGE', 'Siège DREETS BFC', 'Côte-d''Or'),
    ('UD21', 'UD Côte-d''Or', 'Côte-d''Or'),
    ('UD25', 'UD Doubs', 'Doubs'),
    ('UD39', 'UD Jura', 'Jura'),
    ('UD58', 'UD Nièvre', 'Nièvre'),
    ('UD70', 'UD Haute-Saône', 'Haute-Saône'),
    ('UD71', 'UD Saône-et-Loire', 'Saône-et-Loire'),
    ('UD89', 'UD Yonne', 'Yonne'),
    ('UD90', 'UD Territoire de Belfort', 'Territoire de Belfort');

-- Dev admin user (password: not used — mock auth)
INSERT INTO users (username, nom, prenom, email, role, site_id) VALUES
    ('admin.dev', 'Administrateur', 'Dev', 'admin.dev@dreets.gouv.fr', 'superviseur', 1);

-- Dev agent user
INSERT INTO users (username, nom, prenom, email, role, site_id) VALUES
    ('agent.dev', 'Dupont', 'Jean', 'agent.dev@dreets.gouv.fr', 'agent', 2);
```

---

## 4. Routing

### Architecture

All requests go through `public/index.php`. The URL pattern is:

```
/index.php?page={page_name}&id={optional_id}&type={optional_type}
```

### Route Table

| URL (`?page=`) | File Included | Method | Auth Required | Roles |
|-----------------|---------------|--------|---------------|-------|
| `login` | `pages/login.php` | GET | No | — |
| `login` | `handlers/login_handler.php` (inline) | POST | No | — |
| `logout` | `handlers/logout_handler.php` (inline) | GET | Yes | — |
| `home` | `pages/home.php` | GET | Yes | All |
| `preamble` | `pages/preamble.php` | GET | Yes | All |
| `report_create` | `pages/report_create.php` | GET | Yes | All |
| `report_create` | `handlers/report_create_handler.php` | POST | Yes | All |
| `report_list` | `pages/report_list.php` | GET | Yes | All |
| `report_view` | `pages/report_view.php` | GET+`&id=N` | Yes | All (see rules) |
| `report_edit` | `pages/report_edit.php` | GET+`&id=N` | Yes | Owner only |
| `report_edit` | `handlers/report_edit_handler.php` | POST+`&id=N` | Yes | Owner only |
| `report_print` | `pages/report_print.php` | GET+`&id=N` | Yes | All (see rules) |
| `report_abandon` | `handlers/report_abandon_handler.php` | POST+`&id=N` | Yes | Owner only |
| `report_respond` | `pages/report_respond.php` | GET+`&id=N` | Yes | superviseur |
| `report_respond` | `handlers/report_respond_handler.php` | POST+`&id=N` | Yes | superviseur |
| `synthesis` | `pages/synthesis.php` | GET | Yes | superviseur, chsct |
| `export` | `pages/export.php` | GET | Yes | superviseur, chsct |
| `export` | `handlers/export_handler.php` | POST | Yes | superviseur, chsct |
| `statistics` | `pages/statistics.php` | GET | Yes | superviseur, chsct |
| `settings` | `pages/settings.php` | GET | Yes | superviseur |
| `settings` | `handlers/settings_handler.php` | POST | Yes | superviseur |
| `users` | `pages/users.php` | GET | Yes | superviseur |
| `user_edit` | `pages/user_edit.php` | GET+`&id=N` | Yes | superviseur |
| `user_edit` | `handlers/user_edit_handler.php` | POST+`&id=N` | Yes | superviseur |
| (default/missing) | `pages/home.php` | GET | Yes | All |

### Router Logic (index.php)

```php
<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';

startSession();

// Whitelist of valid pages
$publicPages = ['login'];
$validPages = [
    'home', 'preamble',
    'report_create', 'report_list', 'report_view', 'report_edit',
    'report_print', 'report_abandon', 'report_respond',
    'synthesis', 'export', 'statistics',
    'settings', 'users', 'user_edit',
    'logout'
];

$page = $_GET['page'] ?? 'home';

// If not authenticated and not on public page, redirect to login
if (!isset($_SESSION['user']) && !in_array($page, $publicPages)) {
    redirect('index.php?page=login');
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handlerMap = [
        'report_create'  => __DIR__ . '/../handlers/report_create_handler.php',
        'report_edit'    => __DIR__ . '/../handlers/report_edit_handler.php',
        'report_abandon' => __DIR__ . '/../handlers/report_abandon_handler.php',
        'report_respond' => __DIR__ . '/../handlers/report_respond_handler.php',
        'export'         => __DIR__ . '/../handlers/export_handler.php',
        'settings'       => __DIR__ . '/../handlers/settings_handler.php',
        'user_edit'      => __DIR__ . '/../handlers/user_edit_handler.php',
    ];
    if (isset($handlerMap[$page])) {
        require $handlerMap[$page];
        exit;
    }
}

// Handle special GET routes
if ($page === 'logout') {
    session_destroy();
    redirect('index.php?page=login');
}

// Validate page
if (!in_array($page, $validPages)) {
    $page = 'home';
}

// Render
require_once __DIR__ . '/../src/middleware/require_auth.php';
require_once __DIR__ . '/../src/middleware/require_role.php';

require __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/sidebar.php';

$currentPage = $page;
$pageFile = __DIR__ . '/../pages/' . $page . '.php';
if (file_exists($pageFile)) {
    require $pageFile;
} else {
    require __DIR__ . '/../pages/home.php';
}

require __DIR__ . '/../templates/footer.php';
```

---

## 5. Page Specifications

### 5.1 Login Page (`pages/login.php`)

**URL**: `index.php?page=login`
**Access**: Public (no auth required)
**Only shown in DEV_MODE**. In production, IIS handles authentication before PHP runs.

#### Display
- Centered white card on grey background
- Title: "Application SST — DREETS BFC"
- Subtitle: "Connexion (mode développement)"
- Form fields:
  - **Nom d'utilisateur** — `<input type="text" name="username" required>`
  - **Mot de passe** — `<input type="password" name="password">` (cosmetic only, not checked)
- Button: "Se connecter" (blue, `type="submit"`)
- No CSRF token on login (session not yet established)

#### POST Handler (inline in index.php or separate)
- Looks up `$_POST['username']` in `users` table
- If found: sets `$_SESSION['user']` with user data array
- If not found: creates a new user with role `agent` and default site, then logs in
- Redirects to `index.php?page=home`

---

### 5.2 Home Page (`pages/home.php`)

**URL**: `index.php?page=home`
**Access**: All authenticated users
**Method**: GET

#### Display
- Page title: "Accueil"
- Three cards in a row (flexbox, wrap on mobile):

**Card 1 — RSST (Blue)**
- Background: `var(--rsst-color)` with white text
- Icon: 📋 (or SVG clipboard icon)
- Title: "Registre de Santé et de Sécurité au Travail"
- Subtitle: "RSST"
- Button: `<a href="index.php?page=report_create&type=rsst">Inscrire un signalement</a>` — white outlined button
- Bottom stat: "X signalements enregistrés" (count from `SELECT COUNT(*) FROM reports WHERE type='rsst'`)

**Card 2 — RAMI (Grey)**
- Background: `var(--rami-color)` with white text
- Icon: ⚠️ (or SVG warning icon)
- Title: "Registre des Actes d'Agressions, de Menaces et d'Incivilités"
- Subtitle: "RAMI"
- Button: `<a href="index.php?page=report_create&type=rami">Inscrire un signalement</a>`
- Bottom stat: "X signalements enregistrés"

**Card 3 — DGI (Red)**
- Background: `var(--dgi-color)` with white text
- Icon: 🔴 (or SVG danger icon)
- Title: "Registre de signalement d'un Danger Grave et Imminent"
- Subtitle: "DGI"
- Button: `<a href="index.php?page=report_create&type=dgi">Inscrire un signalement</a>`
- Bottom stat: "X signalements enregistrés"

#### SQL Queries
```sql
SELECT COUNT(*) as count FROM reports WHERE type = 'rsst' AND etat != 'abandonne';
SELECT COUNT(*) as count FROM reports WHERE type = 'rami' AND etat != 'abandonne';
SELECT COUNT(*) as count FROM reports WHERE type = 'dgi' AND etat != 'abandonne';
```

For agents, add `AND site_id = :user_site_id`.
For superviseur/chsct, no site filter.

---

### 5.3 Preamble Page (`pages/preamble.php`)

**URL**: `index.php?page=preamble`
**Access**: All authenticated users
**Method**: GET

#### Display
- Page title: "Préambule"
- Static informational text about the SST registries:
  - Legal basis (Code du travail articles)
  - Purpose of each registry
  - Who can file a report
  - Confidentiality notice
  - How reports are processed
- This is a static content page — no dynamic data, no forms
- Content is hardcoded in French in the PHP template

#### SQL Queries
None.

---

### 5.4 Report Create Page (`pages/report_create.php`)

**URL**: `index.php?page=report_create&type={rsst|rami|dgi}`
**Access**: All authenticated users
**Method**: GET (display form), POST (submit via handler)

#### Display
- Page title varies by type:
  - RSST: "Inscrire un signalement — Registre de Santé et de Sécurité au Travail"
  - RAMI: "Inscrire un signalement — Registre RAMI"
  - DGI: "Inscrire un signalement — Registre DGI"
- Color accent strip at top of form matching registry color
- Form fields:

| Field | Type | Required | Max Length | Default | Notes |
|-------|------|----------|------------|---------|-------|
| `date_evenement` | `<input type="date">` | Yes | — | Today's date | Date of the event |
| `heure_evenement` | `<input type="time">` | No | — | Current time | Time of the event |
| `lieu` | `<input type="text">` | No | 200 | — | Location (e.g. "Bureau 204, UD25") |
| `objet` | `<input type="text">` | Yes | 100 | — | Subject line |
| `description` | `<textarea rows="8">` | Yes | 5000 | — | Full description |
| `site_id` | `<select>` | Yes | — | User's site | Dropdown of sites. Agent sees only their site. Superviseur sees all. |

**RAMI-specific fields** (shown only when `type=rami`):

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `pour_compte` | `<input type="checkbox">` | No | "Signaler pour le compte d'un autre agent" |
| `pour_compte_nom` | `<input type="text">` | Conditional | Shown when `pour_compte` is checked |
| `pour_compte_prenom` | `<input type="text">` | Conditional | Shown when `pour_compte` is checked |

**Declarant info** (auto-filled, read-only):
- Nom: `$_SESSION['user']['nom']` (readonly)
- Prénom: `$_SESSION['user']['prenom']` (readonly)

- Hidden fields:
  - `<input type="hidden" name="type" value="{rsst|rami|dgi}">`
  - `<input type="hidden" name="csrf_token" value="{token}">`

- Buttons:
  - "Valider" (submit, registry-colored)
  - "Annuler" (link to `index.php?page=home`, grey)

#### POST Handler (`handlers/report_create_handler.php`)

**Validation**:
1. CSRF token must match `$_SESSION['csrf_token']`
2. `type` must be one of: `rsst`, `rami`, `dgi`
3. `date_evenement` must be a valid date, not in the future
4. `objet` must not be empty, max 100 characters
5. `description` must not be empty, max 5000 characters
6. `site_id` must exist in sites table
7. If RAMI and `pour_compte` checked, `pour_compte_nom` and `pour_compte_prenom` must not be empty
8. If `site_id` does not match user's site and user is `agent`, reject

**Processing**:
1. Generate reference: call `generateReference($type, date('y'), getNextSequence($type, date('Y')))`
2. Insert into `reports` table
3. Set flash: "Signalement enregistré avec la référence {reference}"
4. Redirect to `index.php?page=report_view&id={new_report_id}`

**SQL**:
```sql
-- Get next sequence number
INSERT INTO report_sequence (type, year, last_sequence)
VALUES (:type, :year, 1)
ON CONFLICT(type, year) DO UPDATE SET last_sequence = last_sequence + 1;

SELECT last_sequence FROM report_sequence WHERE type = :type AND year = :year;

-- Insert report
INSERT INTO reports (reference, type, objet, description, date_evenement, heure_evenement,
    lieu, declarant_id, declarant_nom, declarant_prenom, pour_compte_de, pour_compte_nom,
    pour_compte_prenom, site_id, etat)
VALUES (:reference, :type, :objet, :description, :date_evenement, :heure_evenement,
    :lieu, :declarant_id, :declarant_nom, :declarant_prenom, :pour_compte_de, :pour_compte_nom,
    :pour_compte_prenom, :site_id, 'nouveau');
```

---

### 5.5 Report List Page (`pages/report_list.php`)

**URL**: `index.php?page=report_list&type={rsst|rami|dgi}`
**Access**: All authenticated users (Agent sees own site only; others see all)
**Method**: GET

#### Display
- Page title: "Liste des signalements — {RSST|RAMI|DGI}"
- Filter bar (top):
  - **État**: `<select name="etat">` — Options: Tous, Nouveau, En cours, Traité, Abandonné
  - **Site (UD)**: `<select name="site">` — Options: Tous + list of sites (only for superviseur/chsct; agent sees only their site)
  - **Recherche**: `<input type="text" name="q" placeholder="Rechercher...">` — searches in `objet` and `description`
  - Button: "Filtrer"
- Results table:

| Column | Width | Content |
|--------|-------|---------|
| Réf. | 100px | `report.reference` |
| Date | 100px | `formatDateFR(report.date_evenement)` |
| Objet | flexible | `e(report.objet)`, truncated to 50 chars with "…" |
| Déclarant | 150px | `report.declarant_nom` + `report.declarant_prenom` |
| UD | 80px | `site.code` |
| État | 100px | Badge with color: Nouveau=blue, En cours=orange, Traité=green, Abandonné=grey |
| Actions | 150px | Buttons (see below) |

- Action buttons per row:
  - **Voir** — always shown, link to `report_view&id={id}`
  - **Modifier** — shown only if current user is the declarant AND etat is `nouveau` or `en_cours`
  - **Répondre** — shown only if role is superviseur AND etat is `nouveau` or `en_cours`
  - **Abandonner** — shown only if current user is the declarant AND etat is NOT `abandonne` AND etat is NOT `traite`

- Pagination at bottom (20 items per page):
  - "Précédent" / "Suivant" + page numbers

#### SQL Queries
```sql
-- Count total for pagination
SELECT COUNT(*) as total
FROM reports r
LEFT JOIN sites s ON r.site_id = s.id
WHERE r.type = :type
  AND r.etat != 'abandonne'  -- unless filter includes abandonne
  AND (:site_id IS NULL OR r.site_id = :site_id)
  AND (:etat IS NULL OR r.etat = :etat)
  AND (:q IS NULL OR r.objet LIKE '%' || :q || '%' OR r.description LIKE '%' || :q || '%')
  AND (r.site_id = :user_site_id OR :is_superviseur = 1)

-- Fetch page
SELECT r.*, s.code as site_code, s.nom as site_nom
FROM reports r
LEFT JOIN sites s ON r.site_id = s.id
WHERE r.type = :type
  AND (:site_id IS NULL OR r.site_id = :site_id)
  AND (:etat IS NULL OR r.etat = :etat)
  AND (:q IS NULL OR r.objet LIKE '%' || :q || '%' OR r.description LIKE '%' || :q || '%')
  AND (r.site_id = :user_site_id OR :is_superviseur = 1)
ORDER BY r.created_at DESC
LIMIT :limit OFFSET :offset
```

---

### 5.6 Report View Page (`pages/report_view.php`)

**URL**: `index.php?page=report_view&id={report_id}`
**Access**: All authenticated users. Agent can only see reports from their own site.
**Method**: GET

#### Access Control
- If agent and report's `site_id` !== user's `site_id`, show access denied
- If report's `etat` is `abandonne` and user is not the declarant and not superviseur/chsct, show "Ce signalement a été abandonné"

#### Display
- Page title: "Signalement — {reference}"
- Color accent strip matching registry type
- Report detail card:

| Label | Value |
|-------|-------|
| Référence | `report.reference` |
| Registre | RSST / RAMI / DGI (with colored badge) |
| Date de l'événement | `formatDateFR(report.date_evenement)` |
| Heure de l'événement | `report.heure_evenement` or "—" |
| Lieu | `e(report.lieu)` or "—" |
| Objet | `e(report.objet)` |
| Description | `nl2br(e(report.description))` |
| Déclarant | `report.declarant_prenom` `report.declarant_nom` |
| UD | `site.nom` |
| État | Badge: Nouveau/En cours/Traité/Abandonné |
| Date de création | `formatDateTimeFR(report.created_at)` |

- **RAMI-specific section** (if `report.pour_compte_nom` is not null):
  - "Déclaré pour le compte de : `report.pour_compte_prenom` `report.pour_compte_nom`"

- **Response section** (if `report.reponse` is not null):
  - Label: "Réponse"
  - Response text: `nl2br(e(report.reponse))`
  - Respondent: `repondant_prenom` `repondant_nom`
  - Date: `formatDateTimeFR(report.date_reponse)`

- **Response history** (if any entries in `report_responses`):
  - Table: Date | Répondant | Nouvel état | Réponse

- Action buttons (bottom):
  - **Modifier** — if current user is declarant AND etat is nouveau/en_cours
  - **Répondre** — if role is superviseur AND etat is nouveau/en_cours
  - **Abandonner** — if current user is declarant AND etat is not abandonne/traite
  - **Imprimer** — link to `report_print&id={id}`, always shown
  - **Retour à la liste** — link to `report_list&type={type}`

#### SQL Queries
```sql
SELECT r.*, s.code as site_code, s.nom as site_nom,
       rep.nom as repondant_nom, rep.prenom as repondant_prenom
FROM reports r
LEFT JOIN sites s ON r.site_id = s.id
LEFT JOIN users rep ON r.repondant_id = rep.id
WHERE r.id = :id;

SELECT rr.*, u.nom, u.prenom
FROM report_responses rr
LEFT JOIN users u ON rr.user_id = u.id
WHERE rr.report_id = :id
ORDER BY rr.created_at ASC;
```

---

### 5.7 Report Edit Page (`pages/report_edit.php`)

**URL**: `index.php?page=report_edit&id={report_id}`
**Access**: Only the declarant of the report, and only if etat is `nouveau` or `en_cours`
**Method**: GET (display form), POST (via handler)

#### Access Control
- Load report by ID
- If `report.declarant_id !== $_SESSION['user']['id']`, redirect with error "Vous ne pouvez modifier que vos propres signalements"
- If `report.etat` is `traite` or `abandonne`, redirect with error "Ce signalement ne peut plus être modifié"

#### Display
- Same form as `report_create.php` but pre-filled with existing data
- Date/heure/lieu/objet/description fields are editable
- `type` and declarant info are read-only
- RAMI `pour_compte` fields are editable if originally set
- Site (UD) is NOT editable after creation

- Buttons:
  - "Enregistrer" (submit, registry-colored)
  - "Annuler" (link back to `report_view&id={id}`)

#### POST Handler (`handlers/report_edit_handler.php`)

**Validation**: Same as create, plus:
1. Verify CSRF token
2. Verify ownership (declarant_id matches session user)
3. Verify etat is still nouveau/en_cours (re-check from DB, not form)

**Processing**:
```sql
UPDATE reports
SET objet = :objet, description = :description,
    date_evenement = :date_evenement, heure_evenement = :heure_evenement,
    lieu = :lieu, pour_compte_nom = :pour_compte_nom,
    pour_compte_prenom = :pour_compte_prenom,
    updated_at = datetime('now')
WHERE id = :id AND declarant_id = :user_id AND etat IN ('nouveau', 'en_cours');
```

- If 0 rows affected, set flash error
- Redirect to `report_view&id={id}`

---

### 5.8 Report Print Page (`pages/report_print.php`)

**URL**: `index.php?page=report_print&id={report_id}`
**Access**: All authenticated users (same visibility rules as report_view)
**Method**: GET

#### Display
- **No header, no sidebar** — standalone print-friendly page
- White background, black text, no navigation
- CSS class `print-only` that triggers `@media print` styles

Content:
- Header: "DREETS Bourgogne-Franche-Comté" + logo
- Title: "Signalement — {reference}"
- Same information as report_view, formatted as a clean document
- Footer: "Document généré le {current_date} — Application SST DREETS BFC"
- No action buttons
- Browser print button hint: "Utilisez Ctrl+P pour imprimer"

#### SQL Queries
Same as report_view.

---

### 5.9 Report Abandon Handler (`handlers/report_abandon_handler.php`)

**URL**: `index.php?page=report_abandon&id={report_id}` (POST only)
**Access**: Only the declarant, and only if etat is `nouveau` or `en_cours`
**Method**: POST

#### Processing
1. Verify CSRF token from hidden field
2. Load report, verify ownership and state
3. Update state:

```sql
UPDATE reports
SET etat = 'abandonne', updated_at = datetime('now')
WHERE id = :id AND declarant_id = :user_id AND etat IN ('nouveau', 'en_cours');
```

4. Set flash: "Signalement {reference} abandonné"
5. Redirect to `report_list&type={type}`

**Confirmation**: The report_view page shows a "Abandonner" button that links to a confirm dialog (via `confirm_dialog.php` template) which POSTs to this handler.

---

### 5.10 Report Respond Page (`pages/report_respond.php`)

**URL**: `index.php?page=report_respond&id={report_id}`
**Access**: superviseur only
**Method**: GET (display form), POST (via handler)

#### Access Control
- Verify role is `superviseur`
- Verify report etat is `nouveau` or `en_cours`

#### Display
- Page title: "Répondre au signalement — {reference}"
- Summary of the report (read-only): reference, objet, declarant, date, description
- Form fields:

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `nouvel_etat` | `<select>` | Yes | Options: "En cours", "Traité" |
| `reponse` | `<textarea rows="6">` | Yes | Response text, max 5000 chars |

- Hidden: `csrf_token`, `report_id`
- Buttons:
  - "Envoyer la réponse" (submit, blue)
  - "Annuler" (link to `report_view&id={id}`)

#### POST Handler (`handlers/report_respond_handler.php`)

**Validation**:
1. CSRF token
2. Role check
3. `nouvel_etat` must be `en_cours` or `traite`
4. `reponse` must not be empty, max 5000 chars

**Processing**:
```sql
-- Update the report
UPDATE reports
SET etat = :nouvel_etat,
    reponse = :reponse,
    repondant_id = :user_id,
    date_reponse = datetime('now'),
    updated_at = datetime('now')
WHERE id = :id AND etat IN ('nouveau', 'en_cours');

-- Insert into response history
INSERT INTO report_responses (report_id, user_id, reponse, nouvel_etat)
VALUES (:id, :user_id, :reponse, :nouvel_etat);
```

- Set flash: "Réponse enregistrée pour le signalement {reference}"
- Redirect to `report_view&id={id}`

---

### 5.11 Synthesis Page (`pages/synthesis.php`)

**URL**: `index.php?page=synthesis`
**Access**: superviseur, chsct
**Method**: GET

#### Display
- Page title: "Synthèse des signalements"
- Filter bar:
  - **Année**: `<select>` — Options: current year, previous years (from `SELECT DISTINCT strftime('%Y', created_at) FROM reports`)
  - **Site**: `<select>` — Tous + list of sites
- Summary table:

| UD | RSST Nouveau | RSST En cours | RSST Traité | RSST Total | RAMI Nouveau | RAMI En cours | RAMI Traité | RAMI Total | DGI Nouveau | DGI En cours | DGI Traité | DGI Total | Total |
|----|------|------|------|------|------|------|------|------|------|------|------|------|------|

- Each cell is a count of reports for that UD/registry/state combination
- Last row is totals across all UDs
- Cells with count > 0 are bold; cells with 0 are dimmed (lighter text)

#### SQL Queries
```sql
SELECT s.id as site_id, s.code, s.nom,
    r.type,
    SUM(CASE WHEN r.etat = 'nouveau' THEN 1 ELSE 0 END) as nouveau,
    SUM(CASE WHEN r.etat = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
    SUM(CASE WHEN r.etat = 'traite' THEN 1 ELSE 0 END) as traite,
    SUM(CASE WHEN r.etat = 'abandonne' THEN 1 ELSE 0 END) as abandonne,
    COUNT(*) as total
FROM sites s
LEFT JOIN reports r ON r.site_id = s.id
    AND strftime('%Y', r.created_at) = :year
    AND (:site_id IS NULL OR r.site_id = :site_id)
GROUP BY s.id, r.type
ORDER BY s.code, r.type;
```

---

### 5.12 Export Page (`pages/export.php`)

**URL**: `index.php?page=export`
**Access**: superviseur, chsct
**Method**: GET (display form), POST (via handler, returns CSV)

#### Display
- Page title: "Export des données"
- Filter form:

| Field | Type | Required | Options |
|-------|------|----------|---------|
| `type` | `<select>` | No | Tous, RSST, RAMI, DGI |
| `site_id` | `<select>` | No | Tous + list of sites |
| `declarant_id` | `<select>` | No | Tous + list of users |
| `date_from` | `<input type="date">` | No | Start date |
| `date_to` | `<input type="date">` | No | End date |
| `etat` | `<select multiple>` | No | Nouveau, En cours, Traité, Abandonné |

- Hidden: `csrf_token`
- Button: "Exporter en CSV" (blue)

#### POST Handler (`handlers/export_handler.php`)

**Validation**: CSRF token only. All filters are optional.

**Processing**:
1. Build SQL query with dynamic WHERE clauses based on submitted filters
2. Execute query
3. Set headers for CSV download:
   ```php
   header('Content-Type: text/csv; charset=utf-8');
   header('Content-Disposition: attachment; filename="export_sst_' . date('Y-m-d_His') . '.csv"');
   ```
4. Output BOM for Excel UTF-8 compatibility: `echo "\xEF\xBB\xBF";`
5. Output header row, then data rows
6. Exit (no redirect — this is a file download)

**CSV Columns**:
Référence, Registre, Objet, Description, Date événement, Heure événement, Lieu, Déclarant nom, Déclarant prénom, UD, État, Réponse, Répondant nom, Répondant prénom, Date réponse, Pour le compte de (nom), Pour le compte de (prénom), Date création

**SQL Query**:
```sql
SELECT r.reference, r.type, r.objet, r.description, r.date_evenement,
    r.heure_evenement, r.lieu, r.declarant_nom, r.declarant_prenom,
    s.code as site_code, r.etat, r.reponse,
    rep.nom as repondant_nom, rep.prenom as repondant_prenom,
    r.date_reponse, r.pour_compte_nom, r.pour_compte_prenom, r.created_at
FROM reports r
LEFT JOIN sites s ON r.site_id = s.id
LEFT JOIN users rep ON r.repondant_id = rep.id
WHERE 1=1
    AND (:type IS NULL OR r.type = :type)
    AND (:site_id IS NULL OR r.site_id = :site_id)
    AND (:declarant_id IS NULL OR r.declarant_id = :declarant_id)
    AND (:date_from IS NULL OR r.date_evenement >= :date_from)
    AND (:date_to IS NULL OR r.date_evenement <= :date_to)
    AND (:etats IS NULL OR r.etat IN (...))
ORDER BY r.created_at DESC
```

---

### 5.13 Statistics Page (`pages/statistics.php`)

**URL**: `index.php?page=statistics`
**Access**: superviseur, chsct
**Method**: GET

#### Display
- Page title: "Statistiques"
- **KPI cards row** (4 cards):

| Card | Value | Color |
|------|-------|-------|
| Signalements inscrits | Total count (all registries) | Blue |
| Signalements RSST | Count RSST | Blue |
| Signalements RAMI | Count RAMI | Grey |
| Signalements DGI | Count DGI | Red |

Each card also shows a sub-stat: "dont X nouveau, X en cours, X traité, X abandonné"

- **Table: Répartition par UD**

| UD | RSST | RAMI | DGI | Total | Nouveau | En cours | Traité | Abandonné |
|----|------|------|-----|-------|---------|----------|--------|-----------|

- Filter: Year selector (same as synthesis)

#### SQL Queries
```sql
-- KPI counts
SELECT type, etat, COUNT(*) as count
FROM reports
WHERE strftime('%Y', created_at) = :year
GROUP BY type, etat;

-- By UD
SELECT s.code, s.nom,
    SUM(CASE WHEN r.type = 'rsst' THEN 1 ELSE 0 END) as rsst,
    SUM(CASE WHEN r.type = 'rami' THEN 1 ELSE 0 END) as rami,
    SUM(CASE WHEN r.type = 'dgi' THEN 1 ELSE 0 END) as dgi,
    COUNT(r.id) as total,
    SUM(CASE WHEN r.etat = 'nouveau' THEN 1 ELSE 0 END) as nouveau,
    SUM(CASE WHEN r.etat = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
    SUM(CASE WHEN r.etat = 'traite' THEN 1 ELSE 0 END) as traite,
    SUM(CASE WHEN r.etat = 'abandonne' THEN 1 ELSE 0 END) as abandonne
FROM sites s
LEFT JOIN reports r ON r.site_id = s.id
    AND strftime('%Y', r.created_at) = :year
GROUP BY s.id
ORDER BY s.code;
```

---

### 5.14 Settings Page (`pages/settings.php`)

**URL**: `index.php?page=settings`
**Access**: superviseur only
**Method**: GET (display), POST (via handler)

#### Display
- Page title: "Paramètres — Notifications"
- Two tabs (implemented as anchor links that show/hide divs):

**Tab 1: "Notifications par site"**
- For each site, a section with:
  - Site name as heading
  - Table with columns: Registre | Email
  - Rows: RSST, RAMI, DGI, Tous
  - Each row has an `<input type="email">` field pre-filled from DB
  - Multiple emails separated by semicolons

**Tab 2: "Notifications globales"**
- Same structure but for global settings (no site_id)
- Rows: RSST, RAMI, DGI, Tous
- Email input fields

- Hidden: `csrf_token`
- Button: "Enregistrer" (blue)

#### POST Handler (`handlers/settings_handler.php`)

**Validation**: CSRF token. Each email field is optional but must be valid email(s) if filled.

**Processing**:
1. Delete all existing notification_settings rows
2. Insert new rows from form data
3. Set flash: "Paramètres de notification enregistrés"
4. Redirect to `settings`

```sql
DELETE FROM notification_settings;

-- For each non-empty email field:
INSERT INTO notification_settings (site_id, type, registry, email)
VALUES (:site_id, :type, :registry, :email);
```

---

### 5.15 Users Page (`pages/users.php`)

**URL**: `index.php?page=users`
**Access**: superviseur only
**Method**: GET

#### Display
- Page title: "Gestion des utilisateurs"
- Filter: `<input type="text" name="q" placeholder="Rechercher un utilisateur...">`
- Table:

| Column | Content |
|--------|---------|
| Nom | `user.nom` |
| Prénom | `user.prenom` |
| Email | `user.email` |
| Rôle | Badge: agent=blue, superviseur=red, chsct=purple |
| Site | `site.code` — `site.nom` |
| Actif | "Oui" (green) / "Non" (red) |
| Actions | "Modifier" button → `user_edit&id={id}` |

- Pagination (20 per page)

#### SQL Queries
```sql
SELECT u.*, s.code as site_code, s.nom as site_nom
FROM users u
LEFT JOIN sites s ON u.site_id = s.id
WHERE u.is_active = 1
    AND (:q IS NULL OR u.nom LIKE '%' || :q || '%' OR u.prenom LIKE '%' || :q || '%' OR u.email LIKE '%' || :q || '%')
ORDER BY u.nom, u.prenom
LIMIT :limit OFFSET :offset;
```

---

### 5.16 User Edit Page (`pages/user_edit.php`)

**URL**: `index.php?page=user_edit&id={user_id}`
**Access**: superviseur only
**Method**: GET (display), POST (via handler)

#### Display
- Page title: "Modifier l'utilisateur — {prenom} {nom}"
- Form fields:

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `nom` | `<input type="text">` | Yes | Read-only (from LDAP) |
| `prenom` | `<input type="text">` | Yes | Read-only (from LDAP) |
| `email` | `<input type="email">` | No | Read-only (from LDAP) |
| `role` | `<select>` | Yes | Options: agent, superviseur, chsct |
| `site_id` | `<select>` | Yes | All sites |

- Hidden: `csrf_token`, `user_id`
- Buttons:
  - "Enregistrer" (blue)
  - "Annuler" (link to `users`)

#### POST Handler (`handlers/user_edit_handler.php`)

**Validation**: CSRF, role must be valid, site_id must exist.

**Processing**:
```sql
UPDATE users
SET role = :role, site_id = :site_id, updated_at = datetime('now')
WHERE id = :id;
```

- Set flash: "Utilisateur {prenom} {nom} modifié"
- Redirect to `users`

---

### 5.17 Access Denied Page (`pages/access_denied.php`)

**URL**: Rendered inline by middleware
**Access**: N/A (shown when access is denied)

#### Display
- Centered message: "Accès refusé"
- Explanation: "Vous n'avez pas les droits nécessaires pour accéder à cette page."
- Link: "Retour à l'accueil" → `index.php?page=home`

---

## 6. Security Measures

### 6.1 CSRF Protection
- Every form includes a hidden `<input type="hidden" name="csrf_token" value="...">`
- Token is generated in `session.php` and stored in `$_SESSION['csrf_token']`
- Every POST handler validates: `if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) { die('CSRF token mismatch'); }`
- Token is regenerated on each login

### 6.2 SQL Injection Prevention
- ALL queries use PDO prepared statements with named parameters (`:param`)
- NO string concatenation in SQL queries
- Example pattern:
  ```php
  $stmt = $pdo->prepare('SELECT * FROM reports WHERE id = :id');
  $stmt->execute([':id' => $id]);
  ```

### 6.3 XSS Prevention
- ALL user-supplied data displayed in HTML passes through `e()` function:
  ```php
  function e($string) {
      return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
  }
  ```
- Used in templates: `<?= e($report['objet']) ?>`
- `nl2br()` is applied AFTER `e()` for multiline descriptions: `<?= nl2br(e($report['description'])) ?>`

### 6.4 Authentication Checks
- `require_auth.php` is included on every page (except login)
- Checks `isset($_SESSION['user'])`
- If not set, redirects to login

### 6.5 Authorization Checks
- Role-based access enforced in each page/handler:
  ```php
  requireRole(['superviseur', 'chsct']); // for synthesis, export, stats
  requireRole(['superviseur']); // for settings, users
  ```
- Ownership checks for edit/abandon: `report.declarant_id === $_SESSION['user']['id']`
- Site visibility: agents only see their own site's reports

### 6.6 Input Validation
- HTML5 `required`, `maxlength`, `type="date"`, `type="email"` attributes
- Server-side validation in handlers (never trust client-side alone)
- `date_evenement`: validated with `checkdate()`, must not be in the future
- `objet`: max 100 chars, trimmed
- `description`: max 5000 chars, trimmed
- `type`: must be in whitelist `['rsst', 'rami', 'dgi']`
- `etat`: must be in whitelist `['nouveau', 'en_cours', 'traite', 'abandonne']`
- `role`: must be in whitelist `['agent', 'superviseur', 'chsct']`
- `site_id`: must exist in sites table

### 6.7 Session Security
- `session.cookie_httponly = 1` — cookies not accessible via JavaScript
- `session.cookie_samesite = 'Strict'` — CSRF mitigation
- `session.use_strict_mode = 1` — reject uninitialized session IDs
- In production: `session.cookie_secure = 1` — cookies only over HTTPS

### 6.8 Database File Protection
- SQLite database file (`data/sst.db`) is OUTSIDE the web root (`public/`)
- `.htaccess` in `data/` directory: `Deny from all`
- For IIS: `web.config` with `<authorization><deny users="*" /></authorization>` for data folder

### 6.9 Report Integrity
- Once a report is `traite` or `abandonne`, it cannot be edited
- The `updated_at` field tracks last modification
- The `report_responses` table provides an audit trail of all supervisor actions

---

## 7. Auth System Design

### 7.1 Development Mode (DEV_MODE = true)

**Flow**:
1. User navigates to `index.php?page=login`
2. Login form displayed with username/password fields
3. User submits form
4. Handler looks up username in `users` table:
   ```php
   $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username AND is_active = 1');
   $stmt->execute([':username' => $_POST['username']]);
   $user = $stmt->fetch(PDO::FETCH_ASSOC);
   ```
5. If found: set `$_SESSION['user']` with user data
6. If not found: auto-create with default role `agent` and first site, then log in
7. Password is NOT checked in dev mode (it's cosmetic)

**Dev Users** (pre-seeded):
- `admin.dev` — role: superviseur, site: Siège
- `agent.dev` — role: agent, site: UD21

**Switching users in dev**: Logout, then login with different username.

### 7.2 Production Mode (DEV_MODE = false)

**Flow**:
1. IIS with Windows Authentication intercepts the request before it reaches PHP
2. IIS sets `$_SERVER['AUTH_USER']` with the Windows login (e.g. `DREETS\jean.martin`)
3. PHP reads `$_SERVER['AUTH_USER']`, strips domain prefix:
   ```php
   $username = strtolower(str_replace('DREETS\\', '', $_SERVER['AUTH_USER']));
   ```
4. Look up user in `users` table
5. If not found: auto-provision via LDAP lookup, create user with role `agent`
6. Set `$_SESSION['user']`

**LDAP Lookup** (for auto-provisioning):
```php
function ldapLookup($username) {
    $conn = ldap_connect(LDAP_HOST, LDAP_PORT);
    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

    // Bind with service account or user's credentials
    $bind = ldap_bind($conn, LDAP_SERVICE_DN, LDAP_SERVICE_PASSWORD);

    $search = ldap_search($conn, LDAP_BASE_DN, "(sAMAccountName=$username)", [
        'sn', 'givenName', 'mail', 'department'
    ]);
    $entries = ldap_get_entries($conn, $search);

    if ($entries['count'] > 0) {
        return [
            'nom' => $entries[0]['sn'][0] ?? '',
            'prenom' => $entries[0]['givenname'][0] ?? '',
            'email' => $entries[0]['mail'][0] ?? '',
        ];
    }
    return null;
}
```

### 7.3 Transition Between Dev and Prod

The ONLY difference between dev and prod is in `src/auth.php`:
- `DEV_MODE = true`: Uses `$_SESSION['mock_user']` from login form
- `DEV_MODE = false`: Uses `$_SERVER['AUTH_USER']` from IIS

Everything else (session management, role checks, page access) is identical.

In `config.php`:
```php
define('DEV_MODE', getenv('APP_ENV') !== 'production');
```

Set environment variable `APP_ENV=production` on the IIS server.

### 7.4 Session Data Structure

```php
$_SESSION['user'] = [
    'id'        => 42,
    'username'  => 'jean.martin',
    'nom'       => 'Martin',
    'prenom'    => 'Jean',
    'email'     => 'jean.martin@dreets.gouv.fr',
    'role'      => 'agent',
    'site_id'   => 3,
    'site_code' => 'UD25',
    'site_nom'  => 'UD Doubs',
];
```

---

## 8. CSS Architecture

### 8.1 File: `public/css/style.css` (~400 lines)

### 8.2 CSS Custom Properties (Variables)

```css
:root {
    /* Brand colors */
    --color-primary: #2E5C8A;          /* DREETS blue */
    --color-primary-dark: #1E3F5E;
    --color-primary-light: #3D7AB5;

    /* Registry colors */
    --rsst-color: #2E5C8A;             /* Blue */
    --rami-color: #6C6C6C;             /* Grey */
    --dgi-color: #B22222;              /* Red */

    /* State colors */
    --state-nouveau: #2E5C8A;          /* Blue */
    --state-en-cours: #E67E22;         /* Orange */
    --state-traite: #27AE60;           /* Green */
    --state-abandonne: #95A5A6;        /* Grey */

    /* Role badge colors */
    --role-agent: #2E5C8A;
    --role-superviseur: #B22222;
    --role-chsct: #8E44AD;

    /* Layout */
    --sidebar-width: 220px;
    --header-height: 60px;
    --content-padding: 24px;

    /* Greys */
    --grey-50: #FAFAFA;
    --grey-100: #F5F5F5;
    --grey-200: #EEEEEE;
    --grey-300: #E0E0E0;
    --grey-400: #BDBDBD;
    --grey-500: #9E9E9E;
    --grey-600: #757575;
    --grey-700: #616161;
    --grey-800: #424242;
    --grey-900: #212121;

    /* Sidebar */
    --sidebar-bg: #333333;
    --sidebar-text: #CCCCCC;
    --sidebar-hover: #444444;
    --sidebar-active: var(--color-primary);

    /* Misc */
    --border-radius: 4px;
    --shadow: 0 2px 4px rgba(0,0,0,0.1);
    --font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    --font-size-base: 14px;
}
```

### 8.3 Layout Classes

```css
/* Reset & Base */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-family); font-size: var(--font-size-base); color: var(--grey-900); background: var(--grey-100); }

/* Header — 60px blue bar at top */
.header { height: var(--header-height); background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; position: fixed; top: 0; left: 0; right: 0; z-index: 100; }
.header__logo { display: flex; align-items: center; gap: 12px; }
.header__logo img { height: 40px; }
.header__title { font-size: 18px; font-weight: 600; }
.header__user { display: flex; align-items: center; gap: 16px; font-size: 13px; }
.header__logout { color: white; text-decoration: none; opacity: 0.8; }
.header__logout:hover { opacity: 1; text-decoration: underline; }

/* Sidebar — 220px dark grey, fixed */
.sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); color: var(--sidebar-text); position: fixed; top: var(--header-height); left: 0; bottom: 0; overflow-y: auto; }
.sidebar__nav { list-style: none; padding: 8px 0; }
.sidebar__item { display: block; padding: 10px 20px; color: var(--sidebar-text); text-decoration: none; font-size: 14px; border-left: 3px solid transparent; }
.sidebar__item:hover { background: var(--sidebar-hover); }
.sidebar__item--active { background: var(--sidebar-hover); border-left-color: var(--sidebar-active); color: white; font-weight: 600; }
.sidebar__icon { margin-right: 10px; width: 18px; text-align: center; }

/* Main content area */
.main { margin-left: var(--sidebar-width); margin-top: var(--header-height); padding: var(--content-padding); min-height: calc(100vh - var(--header-height)); }

/* Page title */
.page-title { font-size: 22px; font-weight: 600; color: var(--grey-900); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--grey-200); }
```

### 8.4 Component Classes

```css
/* Cards */
.card { background: white; border-radius: var(--border-radius); box-shadow: var(--shadow); padding: 20px; margin-bottom: 20px; }
.card--rsst { border-top: 4px solid var(--rsst-color); }
.card--rami { border-top: 4px solid var(--rami-color); }
.card--dgi { border-top: 4px solid var(--dgi-color); }

/* Home page registry cards */
.registry-card { border-radius: 8px; padding: 30px 24px; color: white; text-align: center; min-height: 220px; display: flex; flex-direction: column; justify-content: space-between; }
.registry-card--rsst { background: var(--rsst-color); }
.registry-card--rami { background: var(--rami-color); }
.registry-card--dgi { background: var(--dgi-color); }
.registry-card__title { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
.registry-card__subtitle { font-size: 24px; font-weight: 700; margin-bottom: 12px; }
.registry-card__stat { font-size: 13px; opacity: 0.85; margin-top: 12px; }
.registry-card__btn { display: inline-block; margin-top: 16px; padding: 10px 24px; border: 2px solid white; border-radius: var(--border-radius); color: white; text-decoration: none; font-weight: 600; transition: background 0.2s; }
.registry-card__btn:hover { background: rgba(255,255,255,0.2); }
.registry-cards { display: flex; gap: 20px; flex-wrap: wrap; }
.registry-cards > * { flex: 1; min-width: 250px; }

/* Buttons */
.btn { display: inline-block; padding: 8px 18px; border: none; border-radius: var(--border-radius); font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; text-align: center; transition: opacity 0.2s; }
.btn:hover { opacity: 0.85; }
.btn--primary { background: var(--color-primary); color: white; }
.btn--success { background: var(--state-traite); color: white; }
.btn--warning { background: var(--state-en-cours); color: white; }
.btn--danger { background: var(--dgi-color); color: white; }
.btn--secondary { background: var(--grey-500); color: white; }
.btn--outline { background: transparent; border: 1px solid var(--grey-400); color: var(--grey-700); }
.btn--sm { padding: 4px 10px; font-size: 12px; }

/* Forms */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; margin-bottom: 4px; font-weight: 500; color: var(--grey-700); font-size: 13px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid var(--grey-300); border-radius: var(--border-radius); font-size: 14px; font-family: var(--font-family); }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 2px rgba(46,92,138,0.2); }
.form-group input[readonly] { background: var(--grey-100); color: var(--grey-600); }
.form-group .form-hint { font-size: 12px; color: var(--grey-500); margin-top: 2px; }

/* Tables */
.table-wrapper { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; background: white; }
th { background: var(--grey-100); text-align: left; padding: 10px 12px; font-weight: 600; font-size: 13px; color: var(--grey-700); border-bottom: 2px solid var(--grey-300); white-space: nowrap; }
td { padding: 10px 12px; border-bottom: 1px solid var(--grey-200); font-size: 14px; vertical-align: middle; }
tr:hover td { background: var(--grey-50); }

/* Badges */
.badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; color: white; }
.badge--nouveau { background: var(--state-nouveau); }
.badge--en-cours { background: var(--state-en-cours); }
.badge--traite { background: var(--state-traite); }
.badge--abandonne { background: var(--state-abandonne); }
.badge--agent { background: var(--role-agent); }
.badge--superviseur { background: var(--role-superviseur); }
.badge--chsct { background: var(--role-chsct); }
.badge--rsst { background: var(--rsst-color); }
.badge--rami { background: var(--rami-color); }
.badge--dgi { background: var(--dgi-color); }

/* KPI cards */
.kpi-grid { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
.kpi-card { flex: 1; min-width: 200px; background: white; border-radius: var(--border-radius); box-shadow: var(--shadow); padding: 20px; text-align: center; border-top: 4px solid var(--color-primary); }
.kpi-card--rsst { border-top-color: var(--rsst-color); }
.kpi-card--rami { border-top-color: var(--rami-color); }
.kpi-card--dgi { border-top-color: var(--dgi-color); }
.kpi-card__value { font-size: 32px; font-weight: 700; color: var(--grey-900); }
.kpi-card__label { font-size: 13px; color: var(--grey-600); margin-top: 4px; }
.kpi-card__detail { font-size: 11px; color: var(--grey-500); margin-top: 8px; }

/* Alerts / Flash messages */
.alert { padding: 12px 16px; border-radius: var(--border-radius); margin-bottom: 16px; font-size: 14px; }
.alert--success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert--error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert--warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
.alert--info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

/* Pagination */
.pagination { display: flex; gap: 4px; justify-content: center; margin-top: 20px; }
.pagination__link, .pagination__current { display: inline-block; padding: 6px 12px; border: 1px solid var(--grey-300); border-radius: var(--border-radius); font-size: 13px; text-decoration: none; color: var(--grey-700); }
.pagination__current { background: var(--color-primary); color: white; border-color: var(--color-primary); }
.pagination__link:hover { background: var(--grey-200); }

/* Filter bar */
.filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; padding: 16px; background: white; border-radius: var(--border-radius); box-shadow: var(--shadow); }
.filter-bar .form-group { margin-bottom: 0; }

/* Tabs */
.tabs { display: flex; border-bottom: 2px solid var(--grey-200); margin-bottom: 20px; }
.tabs__link { padding: 10px 20px; font-size: 14px; font-weight: 500; color: var(--grey-600); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.tabs__link--active { color: var(--color-primary); border-bottom-color: var(--color-primary); }

/* Print view */
.print-view { max-width: 800px; margin: 0 auto; padding: 40px; }
.print-view__header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid var(--grey-900); padding-bottom: 20px; }
.print-view__title { font-size: 20px; font-weight: 700; margin-top: 12px; }
.print-view__field { margin-bottom: 12px; }
.print-view__label { font-weight: 600; color: var(--grey-600); font-size: 13px; }
.print-view__value { font-size: 14px; margin-top: 2px; }
.print-hint { text-align: center; color: var(--grey-500); font-size: 13px; margin-bottom: 20px; }

/* Confirm dialog (inline, not modal) */
.confirm-box { background: #fff3cd; border: 1px solid #ffeeba; border-radius: var(--border-radius); padding: 20px; text-align: center; margin: 20px 0; }
.confirm-box p { margin-bottom: 16px; font-weight: 500; }
.confirm-box__actions { display: flex; gap: 12px; justify-content: center; }
```

### 8.5 Print Styles

```css
@media print {
    .header, .sidebar, .filter-bar, .btn, .pagination, .print-hint { display: none !important; }
    .main { margin: 0; padding: 0; }
    body { background: white; }
    .print-view { padding: 0; }
    table { font-size: 11px; }
    .badge { border: 1px solid #333; color: #333 !important; background: transparent !important; }
}
```

### 8.6 Responsive

```css
@media (max-width: 768px) {
    .sidebar { display: none; }
    .main { margin-left: 0; }
    .registry-cards { flex-direction: column; }
    .kpi-grid { flex-direction: column; }
    .filter-bar { flex-direction: column; }
}
```

---

## 9. Report Reference Format

### Format
```
{type}-{year}-{sequence}
```

### Examples
```
rsst-25-001    — First RSST report of 2025
rami-25-042    — 42nd RAMI report of 2025
dgi-25-007     — 7th DGI report of 2025
```

### Generation Logic

```php
function generateReference(string $type, string $year2, int $sequence): string {
    // type: 'rsst'|'rami'|'dgi'
    // year2: 2-digit year, e.g. '25'
    // sequence: zero-padded 3-digit number
    return $type . '-' . $year2 . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT);
}
```

### Sequence Generation

```php
function getNextSequence(PDO $pdo, string $type, int $year): int {
    // Try to insert a new row
    $stmt = $pdo->prepare("
        INSERT INTO report_sequence (type, year, last_sequence)
        VALUES (:type, :year, 1)
        ON CONFLICT(type, year) DO UPDATE SET last_sequence = last_sequence + 1
    ");
    $stmt->execute([':type' => $type, ':year' => $year]);

    // Read the new value
    $stmt = $pdo->prepare("
        SELECT last_sequence FROM report_sequence WHERE type = :type AND year = :year
    ");
    $stmt->execute([':type' => $type, ':year' => $year]);
    return (int) $stmt->fetchColumn();
}
```

**Thread safety note**: In SQLite, this is safe because SQLite serializes writes. If concurrency were a concern, wrap in a transaction.

**Year rollover**: When the year changes, a new sequence starts at 1 automatically because of the `UNIQUE(type, year)` constraint on `report_sequence`.

---

## 10. Business Rules

### 10.1 Report Ownership
- A report belongs to the `declarant_id` user
- Only the declarant can edit or abandon their report
- Exception: RAMI reports filed "pour le compte de" another agent still belong to the filer (`declarant_id`), but `pour_compte_de` references the actual victim

### 10.2 Report State Machine
```
Nouveau ──(agent edits)──→ Nouveau (stays, updated_at changes)
Nouveau ──(superviseur responds with "En cours")──→ En cours
Nouveau ──(superviseur responds with "Traité")──→ Traité
Nouveau ──(agent abandons)──→ Abandonné
En cours ──(agent edits)──→ En cours (stays)
En cours ──(superviseur responds with "Traité")──→ Traité
En cours ──(agent abandons)──→ Abandonné
Traité ──→ (no further transitions)
Abandonné ──→ (no further transitions)
```

### 10.3 Site Visibility
- **Agent**: Can only see reports from their own `site_id`
- **Superviseur/CHSCT**: Can see reports from ALL sites
- This applies to: report list, statistics, synthesis, export
- On the home page, agent counts reflect only their site

### 10.4 RAMI "Pour le compte de" Feature
- Only available on RAMI reports
- When checked, the agent filing the report is the `declarant_id`
- The actual victim is recorded in `pour_compte_nom` and `pour_compte_prenom`
- The `pour_compte_de` field is nullable and may reference a user ID if the victim is in the system
- This feature allows an agent to file on behalf of a colleague who may be too distressed

### 10.5 Notification Settings
- Only `superviseur` can edit notification settings
- `chsct` can view settings but not edit
- Emails are stored per site + registry combination
- Global notifications apply to all sites (site_id = NULL)
- When a new report is created, the system should send an email to the configured addresses (NOTE: email sending is a future enhancement — for now, settings are stored but no emails are sent)

### 10.6 User Management
- Only `superviseur` can manage users
- User names and emails are read-only (managed by LDAP in production)
- Only `role` and `site_id` are editable
- Users are never hard-deleted; they are deactivated (`is_active = 0`)
- Auto-provisioning: When a new user authenticates (via LDAP or dev login), a `users` record is created with default role `agent` and the first site in the list

### 10.7 Pagination
- All list pages use pagination with 20 items per page (configurable via `ITEMS_PER_PAGE` in config)
- Page parameter: `$_GET['p']` (default 1)
- Offset calculation: `($page - 1) * ITEMS_PER_PAGE`

---

## 11. Error Handling

### 11.1 Database Errors
- PDO is set to throw exceptions: `$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);`
- Wrap critical operations in try/catch
- On error: log to PHP error log, show generic error message to user
- Never expose SQL errors to the user

### 11.2 404 Handling
- If `$_GET['page']` is not in the valid pages list, redirect to home
- If a report ID doesn't exist, show "Signalement introuvable" message

### 11.3 403 Handling
- If role check fails, render `access_denied.php` template
- If ownership check fails, show error flash message and redirect

### 11.4 Validation Errors
- On POST validation failure, redirect back to the form page
- Store submitted values in `$_SESSION['form_data']` to repopulate the form
- Store error messages in `$_SESSION['form_errors']` array
- Display errors above the relevant form fields

### 11.5 Flash Messages
```php
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
```

Types: `success`, `error`, `warning`, `info`

---

## Appendix A: Sidebar Menu Items

| Order | Label | Icon (Unicode) | Page | Roles |
|-------|-------|---------|------|-------|
| 1 | Accueil | 🏠 | `home` | All |
| 2 | RSST | 📋 | `report_list&type=rsst` | All |
| 3 | RAMI | ⚠️ | `report_list&type=rami` | All |
| 4 | DGI | 🔴 | `report_list&type=dgi` | All |
| 5 | Synthèse | 📊 | `synthesis` | superviseur, chsct |
| 6 | Export | 📥 | `export` | superviseur, chsct |
| 7 | Statistiques | 📈 | `statistics` | superviseur, chsct |
| 8 | Utilisateurs | 👥 | `users` | superviseur |
| 9 | Paramètres | ⚙️ | `settings` | superviseur |

Items hidden based on role — not shown at all, not just greyed out.

## Appendix B: French Labels for States

| DB Value | Display Label | Badge Class |
|----------|---------------|-------------|
| `nouveau` | Nouveau | badge--nouveau |
| `en_cours` | En cours | badge--en-cours |
| `traite` | Traité | badge--traite |
| `abandonne` | Abandonné | badge--abandonne |

## Appendix C: French Labels for Registries

| DB Value | Display Label | Short Label |
|----------|---------------|-------------|
| `rsst` | Registre de Santé et de Sécurité au Travail | RSST |
| `rami` | Registre des Actes d'Agressions, de Menaces et d'Incivilités | RAMI |
| `dgi` | Registre de signalement d'un Danger Grave et Imminent | DGI |

## Appendix D: French Labels for Roles

| DB Value | Display Label |
|----------|---------------|
| `agent` | Agent |
| `superviseur` | Superviseur |
| `chsct` | Membre CHSCT |

## Appendix E: Complete SQL Query Reference

### Report Queries

```sql
-- Create report
INSERT INTO reports (reference, type, objet, description, date_evenement, heure_evenement,
    lieu, declarant_id, declarant_nom, declarant_prenom, pour_compte_de, pour_compte_nom,
    pour_compte_prenom, site_id, etat)
VALUES (:reference, :type, :objet, :description, :date_evenement, :heure_evenement,
    :lieu, :declarant_id, :declarant_nom, :declarant_prenom, :pour_compte_de, :pour_compte_nom,
    :pour_compte_prenom, :site_id, 'nouveau');

-- Get report by ID
SELECT r.*, s.code as site_code, s.nom as site_nom,
       rep.nom as repondant_nom, rep.prenom as repondant_prenom
FROM reports r
LEFT JOIN sites s ON r.site_id = s.id
LEFT JOIN users rep ON r.repondant_id = rep.id
WHERE r.id = :id;

-- Get reports list (with filters)
SELECT r.*, s.code as site_code, s.nom as site_nom
FROM reports r
LEFT JOIN sites s ON r.site_id = s.id
WHERE r.type = :type
    AND (:etat IS NULL OR r.etat = :etat)
    AND (:site_id IS NULL OR r.site_id = :site_id)
    AND (:q IS NULL OR r.objet LIKE '%' || :q || '%' OR r.description LIKE '%' || :q || '%')
    AND (r.site_id = :user_site_id OR :show_all = 1)
ORDER BY r.created_at DESC
LIMIT :limit OFFSET :offset;

-- Update report
UPDATE reports
SET objet = :objet, description = :description,
    date_evenement = :date_evenement, heure_evenement = :heure_evenement,
    lieu = :lieu, pour_compte_nom = :pour_compte_nom,
    pour_compte_prenom = :pour_compte_prenom,
    updated_at = datetime('now')
WHERE id = :id AND declarant_id = :user_id AND etat IN ('nouveau', 'en_cours');

-- Abandon report
UPDATE reports
SET etat = 'abandonne', updated_at = datetime('now')
WHERE id = :id AND declarant_id = :user_id AND etat IN ('nouveau', 'en_cours');

-- Respond to report
UPDATE reports
SET etat = :nouvel_etat, reponse = :reponse, repondant_id = :user_id,
    date_reponse = datetime('now'), updated_at = datetime('now')
WHERE id = :id AND etat IN ('nouveau', 'en_cours');

-- Insert response history
INSERT INTO report_responses (report_id, user_id, reponse, nouvel_etat)
VALUES (:report_id, :user_id, :reponse, :nouvel_etat);

-- Get response history
SELECT rr.*, u.nom, u.prenom
FROM report_responses rr
LEFT JOIN users u ON rr.user_id = u.id
WHERE rr.report_id = :report_id
ORDER BY rr.created_at ASC;

-- Count by registry
SELECT COUNT(*) as count FROM reports WHERE type = :type AND etat != 'abandonne' AND (site_id = :site_id OR :show_all = 1);

-- Get next sequence
INSERT INTO report_sequence (type, year, last_sequence) VALUES (:type, :year, 1)
ON CONFLICT(type, year) DO UPDATE SET last_sequence = last_sequence + 1;
SELECT last_sequence FROM report_sequence WHERE type = :type AND year = :year;
```

### User Queries

```sql
-- Get user by username
SELECT u.*, s.code as site_code, s.nom as site_nom
FROM users u LEFT JOIN sites s ON u.site_id = s.id
WHERE u.username = :username AND u.is_active = 1;

-- Get all users (with search)
SELECT u.*, s.code as site_code, s.nom as site_nom
FROM users u LEFT JOIN sites s ON u.site_id = s.id
WHERE u.is_active = 1
    AND (:q IS NULL OR u.nom LIKE '%' || :q || '%' OR u.prenom LIKE '%' || :q || '%' OR u.email LIKE '%' || :q || '%')
ORDER BY u.nom, u.prenom
LIMIT :limit OFFSET :offset;

-- Update user role and site
UPDATE users SET role = :role, site_id = :site_id, updated_at = datetime('now') WHERE id = :id;

-- Deactivate user
UPDATE users SET is_active = 0, updated_at = datetime('now') WHERE id = :id;

-- Create user (auto-provisioning)
INSERT INTO users (username, nom, prenom, email, role, site_id)
VALUES (:username, :nom, :prenom, :email, 'agent', :site_id);
```

### Site Queries

```sql
-- Get all sites
SELECT * FROM sites ORDER BY code;

-- Get site by ID
SELECT * FROM sites WHERE id = :id;
```

### Stats/Export/Synthesis Queries

```sql
-- Synthesis data
SELECT s.id as site_id, s.code, s.nom, r.type,
    SUM(CASE WHEN r.etat = 'nouveau' THEN 1 ELSE 0 END) as nouveau,
    SUM(CASE WHEN r.etat = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
    SUM(CASE WHEN r.etat = 'traite' THEN 1 ELSE 0 END) as traite,
    SUM(CASE WHEN r.etat = 'abandonne' THEN 1 ELSE 0 END) as abandonne,
    COUNT(r.id) as total
FROM sites s
LEFT JOIN reports r ON r.site_id = s.id AND strftime('%Y', r.created_at) = :year
GROUP BY s.id, r.type
ORDER BY s.code, r.type;

-- KPI counts
SELECT type, etat, COUNT(*) as count
FROM reports WHERE strftime('%Y', created_at) = :year
GROUP BY type, etat;

-- Stats by UD
SELECT s.code, s.nom,
    SUM(CASE WHEN r.type = 'rsst' THEN 1 ELSE 0 END) as rsst,
    SUM(CASE WHEN r.type = 'rami' THEN 1 ELSE 0 END) as rami,
    SUM(CASE WHEN r.type = 'dgi' THEN 1 ELSE 0 END) as dgi,
    COUNT(r.id) as total,
    SUM(CASE WHEN r.etat = 'nouveau' THEN 1 ELSE 0 END) as nouveau,
    SUM(CASE WHEN r.etat = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
    SUM(CASE WHEN r.etat = 'traite' THEN 1 ELSE 0 END) as traite,
    SUM(CASE WHEN r.etat = 'abandonne' THEN 1 ELSE 0 END) as abandonne
FROM sites s
LEFT JOIN reports r ON r.site_id = s.id AND strftime('%Y', r.created_at) = :year
GROUP BY s.id ORDER BY s.code;

-- Export data
SELECT r.reference, r.type, r.objet, r.description, r.date_evenement,
    r.heure_evenement, r.lieu, r.declarant_nom, r.declarant_prenom,
    s.code as site_code, r.etat, r.reponse,
    rep.nom as repondant_nom, rep.prenom as repondant_prenom,
    r.date_reponse, r.pour_compte_nom, r.pour_compte_prenom, r.created_at
FROM reports r
LEFT JOIN sites s ON r.site_id = s.id
LEFT JOIN users rep ON r.repondant_id = rep.id
WHERE 1=1
    AND (:type IS NULL OR r.type = :type)
    AND (:site_id IS NULL OR r.site_id = :site_id)
    AND (:declarant_id IS NULL OR r.declarant_id = :declarant_id)
    AND (:date_from IS NULL OR r.date_evenement >= :date_from)
    AND (:date_to IS NULL OR r.date_evenement <= :date_to)
ORDER BY r.created_at DESC;
```

### Notification Settings Queries

```sql
-- Get all settings
SELECT ns.*, s.code as site_code, s.nom as site_nom
FROM notification_settings ns
LEFT JOIN sites s ON ns.site_id = s.id
ORDER BY ns.type, s.code, ns.registry;

-- Delete and re-insert (on save)
DELETE FROM notification_settings;
INSERT INTO notification_settings (site_id, type, registry, email) VALUES (:site_id, :type, :registry, :email);
```
