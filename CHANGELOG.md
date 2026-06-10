# Changelog — Application SST DREETS BFC

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

## [2.4.0] — 2026-06-11

### Fonctionnalités — Système de visibilité des signalements en 3 modes

Passage d'un système à 2 modes (confidentiel / public) à un système à **3 modes** configurable par le superviseur dans Paramètres → Application :

- **Mode « Confidentiel »** (le plus restrictif) : l'agent ne voit que ses propres signalements. Les autres agents ne voient rien, pas même le titre. Les superviseurs et membres du CHSCT voient tout.
- **Mode « Choix de l'agent »** (confidentiel par défaut) : l'agent choisit la visibilité de chaque signalement lors de la création (public ou confidentiel). Par défaut, le signalement est confidentiel. L'agent voit les signalements publics de son site ainsi que ses propres signalements (même confidentiels).
- **Mode « Visibilité publique »** : tous les signalements du site sont visibles par tous les agents du site.

### Technique — Changements

- `src/config.php` : ajout de `REPORT_VISIBILITY_MODES` (constante), version 2.4.0
- `src/helpers.php` : `getReportVisibility()` remplace `getAgentVisibility()` (3 valeurs : `confidential`, `agent_choice`, `public`). Nouvelles fonctions `reportVisibilityIsConfidential()`, `reportVisibilityIsAgentChoice()`, `reportVisibilityIsPublic()`. Anciennes fonctions conservées comme alias dépréciés.
- `schema.sql` : nouvelle clé `app_report_visibility` (défaut `agent_choice`), clé `app_agent_visibility` marquée obsolète
- `handlers/settings_handler.php` : validation des 3 valeurs pour `app_report_visibility`, synchronisation avec les anciennes clés
- `pages/settings.php` : 3 radios au lieu de 2 pour la visibilité des signalements
- `pages/report_list.php` : filtre `own_only` pour le mode confidentiel strict, filtre `confidential_filter` pour le mode choix agent
- `src/queries/report_queries.php` : clause `own_only` (declarant_id = userId) pour le mode confidentiel
- `templates/report_form.php` : toggle confidentiel uniquement en mode « Choix de l'agent », badge + hidden input dans les autres modes
- `handlers/report_create_handler.php` : force `is_confidential` selon le mode (1 en confidentiel, 0 en public, choix en agent_choice)
- `handlers/report_edit_handler.php` : même logique que la création
- `pages/report_view.php` : contrôle d'accès pour les 3 modes (bloque même le titre en mode confidentiel)
- `pages/report_print.php` : contrôle d'accès pour les 3 modes
- `pages/home.php` : compteurs adaptés au mode (own only / public+own / all)
- `pages/preamble.php` : wording mis à jour pour les 3 modes
- `pages/help.php` : tableau de visibilité par mode (3 lignes) au lieu de par rôle

### Nettoyage du dépôt Git

- Restructuration du dépôt : le projet (`pdf_docs/sst/`) déplacé à la racine du repo
- Suppression de `download/` (script audit non lié), `upload/` (doublon du projet), `.env` (sécurité)
- Suppression de `data/sst.db` du suivi git
- `.gitignore` racine fusionné avec les règles du projet (vendor, data, .env, IDE, OS, pdf, zip)

---

## [2.3.0] — 2026-06-11

### Fonctionnalités — Confidentialité des signalements par défaut, choix de l'agent

- **Mode « Confidentiel par défaut »** : les signalements sont confidentiels par défaut. L'agent peut choisir de rendre son signalement public lors de la création ou de la modification en décochant la case « Signalement confidentiel ». En mode confidentiel, un agent voit les signalements publics de son site + ses propres signalements (même confidentiels).
- **Mode « Visibilité publique »** : tous les signalements du site sont visibles par tous les agents du site. Conforme au principe de transparence des registres SST. La case confidentiel n'est pas affichée dans ce mode.
- **Badge « 🔒 Confidentiel »** : affiché sur la vue détaillée et le PDF d'un signalement confidentiel.
- **Paramétrage admin** : le superviseur choisit le mode de visibilité dans Paramètres → Application. L'ancien réglage site/own est remplacé par confidentiel/public.
- **Migration automatique** : les bases existantes sont migrées automatiquement — colonne `is_confidential` ajoutée, ancien mode `site` → `public`, ancien mode `own` → `confidential`, les signalements existants conservent leur visibilité précédente.
- **Superviseurs et CHSCT** : voient tous les signalements y compris confidentiels, quel que soit le mode.

### Technique — Fichiers modifiés

- `schema.sql` : colonne `is_confidential` (INTEGER NOT NULL DEFAULT 1) dans `reports`, config par défaut `confidential`
- `src/database.php` : migration auto — ALTER TABLE + UPDATE + index pour `is_confidential`, migration des valeurs de config
- `src/helpers.php` : `getAgentVisibility()` renvoie `confidential`/`public` au lieu de `site`/`own`, ajout de `agentVisibilityIsConfidential()` et `agentVisibilityIsPublic()`, `canSeeAllSites()` simplifié
- `src/queries/report_queries.php` : `createReport()` avec `is_confidential`, `updateReport()` avec `is_confidential`, `getReportsByRegistry()` avec filtre `confidential_filter`, `countActiveReports()` avec paramètres `$userId` et `$confidentialMode`
- `templates/report_form.php` : case à cocher « Signalement confidentiel » (cochée par défaut) en mode confidentiel, badge en mode public
- `templates/report_card.php` : badge « 🔒 Confidentiel »
- `handlers/report_create_handler.php` : sauvegarde de `is_confidential`
- `handlers/report_edit_handler.php` : sauvegarde de `is_confidential` lors de la modification
- `pages/settings.php` : radios confidentiel/public au lieu de site/own, info au lieu d'avertissement
- `handlers/settings_handler.php` : validation `confidential`/`public`
- `pages/report_view.php` : contrôle d'accès avec `is_confidential`
- `pages/report_print.php` : contrôle d'accès + badge confidentiel dans le PDF
- `pages/report_list.php` : filtres `confidential_filter` / `force_site_id` selon le mode
- `pages/home.php` : compteurs avec filtre confidentiel
- `pages/preamble.php` : wording « confidentiel par défaut, l'agent peut le rendre public »
- `pages/help.php` : tableau de visibilité par rôle et par mode
- `src/config.php` : version 2.3.0

---

## [2.2.0] — 2026-06-10

### Technique — Suppression du JavaScript personnalisé (zéro JS côté métier)

Objectif : éliminer tout JavaScript personnalisé de l'application. Les seuls `onclick` restants sont des appels natifs `showModal()` HTML5 pour ouvrir des `<dialog>` — aucun framework, aucune logique métier en JS.

- **synthesis.php** : les filtres année/site utilisaient `onchange="window.location.href=..."` → remplacés par un `<form method="GET">` avec bouton « Filtrer »
- **statistics.php** : le filtre année utilisait `onchange="window.location.href=..."` → remplacé par un `<form method="GET">` avec bouton « Filtrer »
- **export.php** : les checkboxes « Tous » utilisaient `onchange="...disabled=..."` → supprimés. Le handler côté serveur ignore déjà les selects quand la checkbox est cochée.
- **choose_site.php** : le `<script>` qui toggle le warning + `confirm()` → supprimé. Le warning est toujours visible, le select a `required` HTML5.
- **report_form.php** : le `<script>` qui toggle `pour_compte_fields` → remplacé par CSS `:has()` : `.form-grid:has(#pour_compte:checked) #pour_compte_fields { display: block; }`
- **settings.php — Tag input** : le système de tags avec `addTag()`, `syncHidden()`, `onclick`, `onkeydown` → remplacé par des `<textarea>` simples (une adresse e-mail par ligne). Le handler parse les lignes côté serveur.
- **settings.php — SMTP test** : le `fetch()` + `alert()` → remplacé par un formulaire POST classique. Le handler `smtp_test_handler.php` redirige avec flash message au lieu de retourner du JSON.
- **settings.php — Visibilité agent** : le `<script>` qui toggle le warning radio → remplacé par CSS `:has()` : `#visibility-radios:not(:has(input[value="site"]:checked)):not(:has(input[value="own"]:checked)) .agent-visibility-warning { display: none; }`
- **settings.php — Confirm suppressions** : les `onclick="return confirm(...)"` → remplacés par `<dialog>` HTML5 natif avec `showModal()`
- **user_edit.php** : le `onsubmit="return confirm(...)"` → remplacé par `<dialog>` HTML5 natif
- **report_card.php** + **confirm_dialog.php** : le `onclick="...style.display='block'"` → remplacé par `<dialog>` HTML5 natif

### Technique — Fichiers modifiés

- `pages/synthesis.php` : `<form method="GET">` + bouton Filtrer
- `pages/statistics.php` : `<form method="GET">` + bouton Filtrer
- `pages/export.php` : suppression des `onchange`, retrait des `disabled`
- `pages/choose_site.php` : suppression du `<script>`, warning toujours visible, `required` HTML5
- `pages/settings.php` : textarea au lieu de tags, formulaire POST pour SMTP test, CSS `:has()` pour warning, `<dialog>` pour confirmations
- `pages/user_edit.php` : `<dialog>` au lieu de `onsubmit confirm()`
- `templates/report_card.php` : `<dialog>` au lieu de `div` masqué
- `templates/confirm_dialog.php` : contenu `<dialog>` avec `formmethod="dialog"` natif
- `templates/report_form.php` : CSS `:has()` au lieu de `<script>`
- `handlers/settings_handler.php` : parse textarea (une adresse/ligne) au lieu de tableaux
- `handlers/smtp_test_handler.php` : redirect + flash au lieu de JSON

---

## [2.1.0] — 2026-06-10

### Fonctionnalités — Changelog consultable dans l'UI

- **Page Changelog** : le numéro de version dans le footer est désormais un lien cliquable vers `?page=changelog`, qui affiche le contenu du fichier `CHANGELOG.md` rendu en HTML.
- **Parsedown** : ajout du parseur Markdown `Parsedown.php` dans `src/lib/` (fichier unique, sans Composer) pour le rendu du changelog.
- **Pas d'export PDF** : le changelog est en lecture seule, aucun bouton d'export.

### Fonctionnalités — Génération PDF des fiches de signalement

- **Impression PDF native** : `report_print.php` génère désormais un PDF côté serveur via mPDF au lieu d'une vue HTML + `window.print()`. Plus de JavaScript pour l'impression.
- **Bouton « Télécharger en PDF »** : remplace l'ancien bouton « Imprimer la fiche » dans la vue détaillée d'un signalement.
- **PDF professionnel** : en-tête (organisation + référence), pied de page (pagination + date de génération), badges colorés pour le registre et l'état, tableau d'historique des réponses.
- **mPDF** : ajout de la dépendance `mpdf/mpdf ^8.2` via Composer pour la génération PDF.

### Technique — Dépendances PHP

- **composer.json** : ajout du fichier avec la dépendance `mpdf/mpdf ^8.2`.
- **Autoloader Composer** : `vendor/autoload.php` chargé conditionnellement dans `public/index.php`.
- **vendor/ sécurisé** : ajout de `vendor` dans les hidden segments de `web.config` et les permissions IIS dans DEPLOY.md.
- **Extensions PHP requises** : ajout de `gd`, `xml`, `curl`, `zip` dans les prérequis de DEPLOY.md (nécessaires pour mPDF).

### Technique — Fichiers modifiés

- `pages/changelog.php` : nouvelle page — parse le CHANGELOG.md via Parsedown
- `pages/report_print.php` : réécrit — génération PDF mPDF au lieu de HTML + `window.print()`
- `pages/help.php` : CU8 mis à jour — « Télécharger en PDF » au lieu de « vue imprimable via le navigateur »
- `templates/footer.php` : version cliquable → lien vers `?page=changelog`
- `templates/report_card.php` : bouton « Imprimer la fiche » → « Télécharger en PDF »
- `public/index.php` : route `changelog`, titre page, autoload Composer
- `public/css/style.css` : styles `.footer-version` (lien cliquable dans le footer)
- `public/web.config` : hidden segment `vendor`
- `src/lib/Parsedown.php` : parseur Markdown (fichier unique)
- `composer.json` : dépendance `mpdf/mpdf ^8.2`
- `.gitignore` : exclusion de `vendor/`, `data/*.db`, IDE, OS
- `DEPLOY.md` : documentation Composer, extensions PHP, structure avec `vendor/`, section dépannage mPDF, mise à jour section superviseurs (suppression du mécanisme de préfixe obsolète)

---

## [2.0.0] — 2026-06-10

### Breaking Changes — Refonte du système de rôles

- **Rôle Manager supprimé** : le rôle `manager` n'existe plus dans l'application. Il a été retiré de tous les fichiers : config.php, helpers.php, sidebar.php, handlers, pages, seed.php, promote.php, database.php, schema.sql, style.css, help.php. Les fonctionnalités de consultation élargie (tous les sites, synthèse, export, stats) sont déjà couvertes par le rôle CHSCT.
- **Système d'auto-promotion par préfixe supprimé** : le mécanisme `app_admin_prefix` (par défaut `adm.`) qui promouvait automatiquement les logins commençant par ce préfixe est supprimé. Ce système était source de confusion et de faille de sécurité potentielle.
- **Clé de config renommée** : `app_admin_usernames` → `app_superviseur_usernames` — le nom reflète désormais clairement son usage : liste de logins Windows séparés par virgules qui seront automatiquement promus Superviseur. Utile pour une première installation.

### Attribution du rôle Superviseur (nouveau système)

Deux méthodes pour obtenir le rôle Superviseur :
1. **Par un autre superviseur** via la gestion des utilisateurs dans l'interface
2. **Via la liste de config** `app_superviseur_usernames` (Paramètres → Application) — les utilisateurs de cette liste sont auto-promus à leur connexion via IIS

### Sécurité — Corrections de confidentialité

- **Visibilité agent par défaut = son site** : le défaut de `app_agent_visibility` passe de `'all'` à `'site'`. Par défaut, un agent ne voit que les signalements de son site.
- **Option 'all' supprimée** : l'option « Tous les signalements » n'est plus proposée dans les paramètres de visibilité agent. Seules les options « Son site » (par défaut) et « Ses propres signalements » sont disponibles.
- **Contrôle d'accès renforcé** : `canAccessReport()` dans helpers.php vérifie systématiquement que l'utilisateur a le droit d'accéder au signalement (déclarant, superviseur ou CHSCT).
- **Abandon de signalement** : réservé au superviseur uniquement (conforme à la documentation de référence).

### Documentation

- **help.php réécrit** : conforme à la documentation PDF de référence. 3 rôles uniquement (Agent, Superviseur, CHSCT). Section confidentialité ajoutée.
- **SPEC.md réécrit** : suppression des références LDAP, des fonctions obsolètes, du rôle Manager. Documentation du système de rôles à 3 profils.
- **README.md mis à jour** : reflète le nouveau système de rôles et d'attribution.
- **CHANGELOG.md mis à jour** : ce fichier.

### Technique

- `src/auth.php` : suppression des fonctions `determineProvisionRole()` (prefix + list) et `checkAndPromoteUser()` (prefix) — remplacées par un mécanisme simplifié basé uniquement sur la liste `app_superviseur_usernames`
- `src/config.php` : `ROLE_LABELS` ne contient plus que agent, superviseur, chsct. `APP_VERSION` → 2.0.0
- `src/helpers.php` : `getRoleBadgeClass()` sans manager, `canSeeAllSites()` sans manager, `getAgentVisibility()` défaut 'site'
- `schema.sql` : rôle commenté `'agent'|'superviseur'|'chsct'`, clé `app_superviseur_usernames` au lieu de `app_admin_prefix`/`app_admin_usernames`
- `src/database.php` : seed sans manager.dev, config keys mises à jour
- Tous les handlers et pages : retraits des références au rôle manager
- `public/css/style.css` : retrait de `--role-manager` et `.badge--manager`
- `promote.php` : rôles valides = agent, superviseur, chsct

---

## [1.1.0] — 2026-06-10

### Sécurité — Corrections de confidentialité

- **Vulnérabilité critique corrigée** : le défaut de `app_agent_visibility` passe de `'all'` à `'site'`. Par défaut, un agent ne voit plus que les signalements de son site.
- **Contrôle d'accès renforcé** : ajout de `canAccessReport()` dans helpers.php.
- **Rôle Manager corrigé** : le manager ne peut plus répondre aux signalements.
- **Abandon de signalement corrigé** : l'abandon est désormais réservé au superviseur.
- **Option 'all' supprimée des paramètres**.

### Fonctionnalités métier ajoutées

- **Réactivation d'utilisateur** : bouton « Réactiver » dans la liste des utilisateurs.
- **Modification de site** : bouton « Modifier » dans l'onglet « Gestion des sites ».

### Code mort supprimé

- `updateUserRole()`, `updateUserSite()`, `agentSeesOnlyOwn()` supprimées.

---

## [1.0.0] — 2025-06-05

### Première version

- Application SST DREETS BFC complète.
- 3 profils utilisateurs : Agent, Superviseur, CHSCT.
- 3 registres : RSST, RAMI, DGI.
- Authentification IIS Windows (prod) / mock login (dev).
- Notifications par e-mail, configuration SMTP.
- Statistiques, synthèse, export CSV.
- Gestion des utilisateurs et des sites.
