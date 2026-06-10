# Changelog — Application SST DREETS BFC

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

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
