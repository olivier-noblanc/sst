# Changelog — Application SST DREETS BFC

Toutes les modifications notables de ce projet sont documentées dans ce fichier.


## [3.22.0] — 2026-06-17

### Produit — Corrections UI et déploiement

- **1** 🟡 **Journal : onglet audit en premier** — L'onglet « Journal d'audit » est désormais affiché par défaut dans la page Journal (au lieu de « Erreurs PHP »). L'audit est plus utile au quotidien que les erreurs PHP techniques. Modification de `$activeTab` par défaut et réorganisation des onglets dans `logs.php`.
- **2** 🟡 **RSST : boutons d'action alignés** — Les boutons Voir / Modifier / Répondre sur la liste des signalements RSST n'étaient pas alignés (Répondre passait à la ligne). Passage de `.btn-group` et `.btn-group--inline` en `flex-wrap: nowrap` avec `align-items: center` pour un alignement horizontal constant.
- **3** 🟡 **Export : labels sans coupure** — Les labels de checkbox comme « Tous les registres » étaient coupés après « Tous les ». Passage de `.label--checkbox` en `inline-flex` avec `white-space: nowrap` pour empêcher le retour à la ligne.
- **4** 🔴 **`update_sst.ps1` — Suppression du redémarrage IIS** — L'étape `iisreset /restart` était inutile : IIS recycle automatiquement l'application pool après la mise à jour des fichiers. Suppression de l'étape 5 et mise à jour du en-tête du script (4 étapes au lieu de 5).
- **5** 🔴 **`logs.php` — Lecture optimisée type `tail`** — La lecture du fichier de log PHP avec `file_get_contents()` + `explode()` provoquait un `Fatal error: Allowed memory size exhausted` quand le fichier dépassait 128 Mo. Remplacement par un algorithme de lecture inverse par chunks de 8 Ko (`fseek` depuis la fin) : même sur un fichier de 400+ Mo, seuls 2 Mo de RAM sont utilisés et les 500 dernières lignes sont extraites instantanément.

### Tests — Mise à jour E2E

- **6** 🟡 **`settings.spec.js` — Onglet audit par défaut** — Les tests du journal vérifient désormais que l'onglet audit est actif par défaut et que le switch se fait vers l'onglet erreurs (inverse de l'ancien comportement).

## [3.21.0] — 2026-06-17

### Produit — Suppressions et README

- **1** 🔴 **Suppression du dashboard agent** — La section « Mes signalements récents » sur la page d'accueil était une feature de type dashboard inappropriée pour une application de signalement SST. Suppression de la section dans `home.php`, de la fonction `getRecentReportsByUser()` dans `report_queries.php`, et des références dans SPEC.md et CHANGELOG.md (item 16 de la v3.19.0).
- **2** 🔴 **Suppression du dark mode** — Le bloc `@media (prefers-color-scheme: dark)` ajouté dans `style.css` a été supprimé sur demande. L'application reste en thème clair uniquement.
- **3** 🟢 **Screenshot miniature dans le README** — Ajout d'un aperçu visuel (400px) de la page d'accueil dans le README.md, avec un lien vers `public/screenshots/accueil-mini.png`.

### Accessibilité — Simplicité pour utilisateurs non techniques

- **4** 🟡 **Cibles tactiles agrandies (44px min)** — Boutons (`.btn`), champs de formulaire (`input`, `select`, `textarea`) passent de 32-36px à 44px de hauteur minimale, conformément aux recommandations WCAG 2.5.8. Taille de police augmentée de 14→15px pour les boutons, inputs et la navigation sidebar.
- **5** 🟡 **Texte d'aide et erreurs plus lisibles** — `.form-hint` passe de 12px gris clair à 13px gris plus foncé (`--grey-600`). `.form-error` passe de 12px à 14px avec `font-weight: 500` pour une meilleure visibilité.
- **6** 🟢 **Vocabulaire simplifié** — « Inscrire un signalement » remplacé par « Signaler un événement » (plus direct, plus naturel). « Valider son signalement » remplacé par « Envoyer le signalement ». Mise à jour dans home.php, report_form.php, report_create.php, router.php, SPEC.md, help.php, report_list.php et les tests E2E.
- **7** 🟡 **Bannière de confirmation verte après envoi** — Après la création d'un signalement, une grande bannière verte s'affiche sur la page de consultation avec la référence, un message rassurant (« Un superviseur va le prendre en charge »), et deux boutons : « Retour à l'accueil » et « Voir mes signalements ». Remplace le petit flash discret.
- **8** 🟢 **Guide rapide imprimable (page A4)** — Nouvelle page `index.php?page=guide` avec les 3 étapes illustrées par des captures d'écran : 1) Choisir le registre, 2) Remplir le formulaire, 3) Envoyer. Bouton « Imprimer ce guide » avec CSS `@media print`. Lien ajouté dans la sidebar et la bannière de bienvenue.
- **9** 🔴 **Correction des annotations de screenshots** — Les captures annotées (cercles numérotés + descriptions) étaient superposées et illisibles. Réécriture complète de `tools/annotate_screenshots.py` : les badges sont désormais placés dans une colonne à droite avec détection de collision, les descriptions sont sous les badges avec retour à la ligne, et les cibles sont marquées au bord des éléments (pas au centre). Régénération des 23 captures.

### Tests — Adaptation E2E

- **10** 🟡 **`forms.spec.js` — Validation serveur via POST direct** — Les tests de validation de formulaire contournaient le HTML5 via `novalidate` (retiré). Remplacement par des requêtes POST directes avec `page.request.post()` et `maxRedirects: 0` pour tester la validation côté serveur sans dépendre du navigateur.
- **11** 🟡 **`onboarding.spec.js` — Texte période de grâce** — Le test vérifiait `/définitif/` dans le danger-panel, remplacé par `/7 jours/` suite à l'ajout de la période de grâce de 7 jours.
- **12** 🟡 **`version-changelog.spec.js` — Regex version** — Remplacement de `/3\.1[0-9]\.\d+/` par `/3\.\d+\.\d+/` pour supporter les versions 3.20+.
- **13** 🟢 **`reports.spec.js` + `navigation-flows.spec.js` — Vocabulaire « Signaler »** — Mise à jour des sélecteurs `:has-text("Inscrire")` en `:has-text("Signaler")` suite au changement de vocabulaire.

## [3.20.0] — 2026-06-17

### Juridique — Conformité de la réouverture (avis Compliance + Juridique)

- **1** 🔴 **`report_reopen_handler.php` — État `reouvert` au lieu de `en_cours`** — La réouverture passait directement à `en_cours`, rendant impossible la distinction entre un signalement en cours pour la première fois et un signalement rouvert. Le Code du travail (D4132-1) exige cette distinction pour l'inspection du travail. Le handler utilise désormais `ETAT_REOUVERT` comme état cible.
- **2** 🔴 **`report_queries.php` — Archivage de la réponse initiale avant écrasement** — `respondToReport()` écrasait `reports.reponse`, `repondant_id` et `date_reponse` lors d'une nouvelle réponse à un signalement rouvert, détruisant la réponse initiale du superviseur. Cela violait le principe d'immutabilité des registres (L4711-3, art. 5(1)(f) RGPD). Ajout d'une étape d'archivage dans `report_responses` avec le préfixe `[Réponse initiale archivée]` avant l'UPDATE.
- **3** 🔴 **`report_reopen_handler.php` — Réouverture restreinte aux superviseurs/CHSCT** — Le déclarant (agent) pouvait réouvrir son propre signalement sans l'accord du superviseur. En droit du travail, le traitement relève de la responsabilité de l'employeur (L4121-1). L'agent ne peut plus réouvrir — seuls les rôles `superviseur` et `chsct` le peuvent.
- **4** 🟡 **`report_reopen.php` — Avertissement DGI spécifique** — Ajout d'un bandeau d'alerte pour les signalements DGI : « La réouverture d'un signalement DGI signifie que le danger grave et imminent n'a pas été résolu. Conformément à l'article L4131-2 du Code du travail, le CSE/CSA/CHSCT sera informé. »
- **5** 🟡 **`database.php` — Table `report_state_history`** — Nouvelle table de traçabilité des transitions d'état : `report_uuid`, `etat_precedent`, `etat_suivant`, `user_id`, `motif`, `created_at`. Index sur `report_uuid` et `created_at`. Alimentée automatiquement par le handler de réouverture.

### CSS — Audit approfondi, 15 corrections

- **6** 🔴 **`style.css` — `var(--border)` indéfini** — La variable `--border` était utilisée mais jamais définie dans `:root`. La table de synthèse mobile n'affichait aucune bordure. Ajout de `--border: var(--grey-300)` dans `:root`.
- **7** 🔴 **`style.css` — `.alert--danger` manquante** — `changelog.php` utilise `alert--danger` mais seule `.alert--error` existait. L'alerte s'affichait sans style. Ajout de la classe manquante.
- **8** 🔴 **`style.css` — Typo `.header__user-name` → `.header__username`** — Le sélecteur CSS utilisait un tiret mais le template PHP un underscore. Le tronquage du nom sur mobile (480px) ne fonctionnait pas.
- **9** 🟡 **`style.css` — Contraste `.badge--abandonne` WCAG AA** — Blanc sur `#95A5A6` = ratio 2.8:1 (insuffisant). Changé en `#7B8D8E` → ratio ~4.6:1.
- **10** 🟡 **`style.css` — Suppression CSS mort** — Retrait de `.report-response`, `.sortable`, `.sidebar-close-label`, `.sidebar--open`, `.sidebar-overlay--visible`, `.help-screenshot--placeholder`, `.required-star` (~50 lignes).
- **11** 🟡 **`style.css` — Fusion doublons** — Merge de `.back-to-top:hover` (2→1), suppression du doublon `.form-grid` mobile, suppression du `:not(:has())` redondant sur `.pour-compte-fields`.
- **12** 🟡 **`style.css` — Variables CSS pour couleurs codées en dur** — `.impersonate-banner` → `var(--state-en-cours)`, `.skip-link` → `var(--color-primary-dark)`.
- **13** 🟡 **`style.css` — Breakpoint mobile impersonate-banner** — Ajout `flex-direction: column` sur mobile pour éviter le débordement.
- **14** 🟡 **`style.css` — Print styles complétés** — Ajout `@page { margin: 15mm; size: A4; }` et masquage de `.impersonate-banner`, `.tab-bar`, `.report-nav`, `.help-toc` en impression.
- **15** 🟢 **`style.css` — Nettoyage** — Suppression de `-webkit-overflow-scrolling: touch` (déprécié), consolidation `.checkbox-label`/`.label--checkbox`, renumérotation des sections (1-37), conversion `rem`→`px` dans help-toc.

### Produit — Backlog complété

- **16** 🟡 **`report_abandon_handler.php` — Notification superviseur sur abandon** — Les superviseurs du site reçoivent désormais un e-mail lorsqu'un signalement est abandonné. Contenu : référence, registre, objet, déclarant, lien vers le signalement.
- **17** 🟡 **`user_edit.php` — Confirmation avant démotion de rôle** — Ajout d'une case à cocher `confirm_demotion` obligatoire lorsqu'un superviseur est rétrogradé en agent. Le handler refuse la modification sans cette confirmation.
- **18** 🟡 **`header.php` + `sidebar.php` — Skip link navigation** — Ajout d'un second skip link « Aller à la navigation » avec `id="main-nav"` sur la sidebar. Conformité WCAG 2.4.1.
- **19** 🟡 **`report_card.php` — Avertissement nouvelle fenêtre** — Ajout de `<span class="sr-only">(nouvelle fenêtre)</span>` sur les liens `target="_blank"`.
- **20** 🟡 **`report_form.php` — Retrait de `novalidate`** — La validation HTML5 native du navigateur est désormais active en première passe avant la validation serveur.
- **21** 🟡 **`mail.php` — Helper `buildEmailBody()`** — Fonction utilitaire pour construire des e-mails HTML avec en-tête/pied cohérents. Pattern KISS, sans moteur de template.
- **22** 🟡 **`logs.php` + `audit.php` — Filtrage du journal d'audit** — Ajout d'un filtre par nom d'utilisateur (`?user=xxx`). UI avec champ de recherche et bouton Filtrer.
- **23** 🟡 **`settings_handler.php` — Test SMTP automatique à la sauvegarde** — Après enregistrement de la config SMTP, un e-mail de test est envoyé automatiquement avec flash de résultat (succès/échec).
- **24** 🟡 **`login_handler.php` — Rate limiting connexion** — Maximum 5 tentatives par 15 minutes en mode dev. Reset du compteur sur connexion réussie. Message avec temps restant.
- **25** 🟡 **`report_list.php` — Lien export filtré** — Ajout d'un lien « Exporter les signalements filtrés » pour les superviseurs/CHSCT en haut de la liste.
- **26** 🟢 **`tests/bootstrap.php` — Constante `MAX_OBJECT_LENGTH` corrigée** — La valeur de test (200) divergeait de la production (100). Corrigée à 100.

## [3.19.0] — 2026-06-17

### Sécurité — Corrections critiques (LFI, path traversal, guards CLI)

- **1** 🔴 **`settings.php` — Protection LFI par whitelist des onglets** — Le paramètre `$_GET['tab']` était utilisé directement pour construire le chemin d'inclusion du sous-template (`tab_{$activeTab}.php`) sans validation, permettant une inclusion de fichier arbitraire via path traversal (`tab=../../config`). Ajout d'une whitelist `['sites', 'global', 'smtp', 'manage_sites', 'app']` qui réinitialise `$activeTab` à `'sites'` si la valeur n'est pas dans la liste.
- **2** 🔴 **`backup.php` — Protection path traversal sur VACUUM INTO** — Les requêtes `VACUUM INTO` utilisaient l'interpolation de chaîne pour le chemin de fichier de sauvegarde sans vérification de répertoire. Ajout d'un contrôle `str_starts_with(realpath(dirname($backupFile)), realpath($backupDir))` avant chaque exécution de `VACUUM INTO`. Les tentatives de traversal sont journalisées via `error_log()` et la sauvegarde est annulée.
- **3** 🔴 **`nuclear-reset.php` / `promote.php` — Guard d'environnement obligatoire** — Ces scripts destructeurs ne vérifiaient que `php_sapi_name() !== 'cli'`. Ajout d'une variable d'environnement obligatoire : `SST_CONFIRM_RESET=yes` pour `nuclear-reset.php` et `SST_CONFIRM_PROMOTE=yes` pour `promote.php`. Sans cette variable, le script affiche un message d'usage et se termine en erreur.
- **4** 🟡 **`session.php` — Rotation des tokens CSRF** — Le token CSRF était unique par session et jamais renouvelé, permettant le replay sur toute la durée de la session. Refonte en pool de tokens : `generateCsrfToken()` crée un token unique par appel, stocké dans `$_SESSION['csrf_tokens']` (max 20 tokens, garbage collection automatique). `validateCsrfToken()` valide ET consomme le token (one-time use). Compatible avec tous les formulaires existants sans modification des appelants.
- **5** 🟡 **Handlers — Masquage des IDs internes dans les messages d'erreur** — Les messages flash d'erreur dans `report_edit_handler.php`, `user_delete_handler.php` et `site_edit_handler.php` exposaient des identifiants internes (UUID, user_id, etat, site_id) aux utilisateurs finaux. Remplacement par des messages génériques ("Impossible de modifier ce signalement. Veuillez contacter un administrateur.") avec journalisation des détails via `error_log()`.

### Code Quality — Constantes, suppression de dette technique

- **6** 🔴 **`config.php` — Constantes pour rôles, états et types de registre** — Ajout de 10 constantes éliminant les magic strings : `ROLE_AGENT`, `ROLE_SUPERVISEUR`, `ROLE_CHSCT`, `ETAT_NOUVEAU`, `ETAT_EN_COURS`, `ETAT_TRAITE`, `ETAT_ABANDONNE`, `ETAT_REOUVERT`, `TYPE_RSST`, `TYPE_RAMI`, `TYPE_DGI`. Remplacement de ~55 occurrences de chaînes littérales dans 30+ fichiers (src/, handlers/, pages/, templates/).
- **7** 🟡 **`auth.php` — Extraction de `parseSuperviseurUsernames()`** — Le parsing `array_map('trim', explode(',', strtolower(...)))` était dupliqué dans `auth.php` (2×) et `bootstrap.php` (1×). Extraction en fonction dédiée `parseSuperviseurUsernames(string $list): array` dans `auth.php`, utilisée dans les 3 emplacements.
- **8** 🟡 **`access.php` — Suppression des fonctions dépréciées** — Les 3 fonctions marquées `@deprecated` (`getAgentVisibility()`, `agentVisibilityIsConfidential()`, `agentVisibilityIsPublic()`) sont retirées. Aucun appelant restant dans le codebase.
- **9** 🟡 **`auth_flow.php` — Remplacement de `die()` par page d'erreur habillée** — Les 2 appels `die()` dans le flux d'authentification (erreurs de configuration) affichaient du texte brut sans style. Remplacement par une page HTML 500 complète avec style inline, message `htmlspecialchars()` et lien de contact administrateur.
- **10** 🟢 **`index.php` — Session patch conditionnel** — Le fichier `session_patch.php` (marqué "DEPLOYMENT: delete in production") était chargé inconditionnellement. L'inclusion est désormais protégée par `if (defined('DEV_MODE') && DEV_MODE)`, évitant un comportement de développement en production.
- **11** 🟢 **`validation.php` — Correction de la vérification d'unicité du username en édition** — En mode édition (`$excludeId > 0`), la logique d'unicité était cassée : `$stmt` était nullifié par la branche create, rendant `$stmt->fetch()` muet en mode edit. Refonte : chaque mode (edit/create) utilise sa propre requête préparée avec exécution et vérification indépendantes.

### Performance — Requêtes optimisées, BLOBs exclus des listes

- **12** 🔴 **`report_queries.php` — Exclusion du BLOB des requêtes de liste** — `reportSelectWithSite()` utilisait `r.*`, chargeant le contenu binaire des pièces jointes (jusqu'à 10 Mo chacune) dans chaque ligne de résultat de liste. Remplacement par une sélection explicite de toutes les colonnes sauf `attachment_blob`. La vue détaillée (`getReportByUuid()`) conserve `r.*` pour le téléchargement des pièces jointes. Même correction dans `stats_queries.php` pour `getExportData()`.
- **13** 🔴 **`export_handler.php` — Élimination du N+1 sur les réponses** — L'export appelait `getReportResponses()` dans une boucle pour chaque signalement (N+1 requêtes). Remplacement par un bulk-fetch unique avec `IN (?)` + GROUP BY `report_uuid` en PHP. Réduction de N+1 à 2 requêtes pour l'ensemble de l'export.
- **14** 🟡 **`stats_queries.php` — Requêtes statistiques sargables** — 4 occurrences de `strftime('%Y', r.created_at) = :year` empêchaient l'utilisation de l'index `idx_reports_created_at`, forçant un full table scan. Remplacement par des range queries `r.created_at >= :year_start AND r.created_at < :year_next` qui exploitent l'index. Affecte `getSynthesisData()`, `getStatisticsIndicateurs()`, `getStatsBySite()` et `getRamiStructuredStats()`. Conservation de `strftime` dans `getAvailableYears()` (SELECT d'extraction, pas de filtre).

### Produit — Réouverture de signalement, période de grâce site

- **15** 🔴 **Nouveau : Réouverture de signalement** — Un signalement à l'état `traite` ou `abandonne` ne pouvait plus être rouvert. Ajout du statut `reouvert` dans `ETAT_LABELS`, du handler `report_reopen_handler.php` (validation POST/CSRF, contrôle de permission superviseur/CHSCT/déclarant, motif obligatoire min 10 car., mise à jour vers `en_cours`, audit log, notification email), de la page `report_reopen.php` (formulaire avec motif), du badge `.badge--reouvert` (violet), et du bouton "Réouvrir" dans `report_card.php`. Routage ajouté dans `router.php`.
- **17** 🟡 **Période de grâce de 7 jours pour le changement de site** — Le choix de site était irréversible ("Ce choix est définitif"). Ajout d'une colonne `site_chosen_at` dans la table `users` (migration automatique avec backfill). Les agents peuvent modifier leur site dans les 7 jours suivant leur premier choix. Après 7 jours, le message invite à contacter le superviseur. La page `choose_site.php` affiche le nombre de jours restants. Audit trail du changement.

### UX — Synthèse responsive, accessibilité

- **18** 🟡 **`synthesis.php` — Table de synthèse responsive mobile** — La table de synthèse (14+ colonnes) était illisible sur mobile. Ajout de `class="synthesis-table"` et d'attributs `data-label` sur chaque `<td>`. Media query `@media (max-width: 768px)` qui empile les lignes en cartes avec labels via `attr(data-label)`, masque le header, et affiche les données en flex justify-between.
- **19** 🟢 **`style.css` — Badge réouvert** — Ajout de `.badge--reouvert { background: #8B5CF6; }` pour le nouveau statut `reouvert`.

## [3.18.0] — 2026-06-16

### Admin — Toggle d'affichage des erreurs PHP en production

- **1** 🔴 **`index.php` — Override `display_errors` depuis la config DB** — Après le chargement des helpers, la valeur `app_display_errors` est lue dans `config_app`. Si `'1'`, `ini_set('display_errors', '1')` et `ini_set('display_startup_errors', '1')` sont appliqués, même en production. Cela permet de voir les erreurs PHP brutes directement dans les pages pour le diagnostic, sans modifier le code source.
- **2** 🔴 **`error_handler.php` — `sstShutdownHandler()` respecte le toggle** — Le shutdown handler ne remplace plus l'erreur fatale par la page d'erreur « propre » si `app_display_errors` est `'1'`. L'erreur PHP native s'affiche donc en entier (stack trace, fichier, ligne).
- **3** 🟡 **`tab_app.php` — Section « Affichage des erreurs PHP »** — Nouveau bloc dans l'onglet Application des Paramètres, avec un toggle switch CSS-only (style iOS). Avertissement clair sur les risques de sécurité (informations sensibles exposées). Les erreurs restent toujours journalisées et envoyées par e-mail, que le toggle soit activé ou non.
- **4** 🟡 **`settings_handler.php` — Sauvegarde de `app_display_errors`** — La valeur du toggle est persistée dans `config_app` via `updateConfig()`. La valeur par défaut est vide (erreur masquées en prod, affichées en dev comme avant).

### UX — Zéro JavaScript, améliorations accessibilité et mobile

- **5** 🔴 **`report_form.php` — Suppression du `<script>` inline** — Le compteur de caractères dynamique et le toggle `aria-expanded` sur la checkbox « pour le compte » étaient les seuls JavaScript de toute l'application. Le compteur est remplacé par un rendu PHP pur : longueur initiale calculée côté serveur avec `mb_strlen()`, formatage français via `number_format()`, et classe `char-counter--warning` si > 19 000. Le bloc `<script>` de 28 lignes est entièrement supprimé. L'application est maintenant 100 % sans JavaScript.
- **6** 🔴 **`form_error_summary.php` — Résumé d'erreurs en haut de formulaire** — Nouveau template inclus dans `report_form.php` quand `$formErrors` n'est pas vide. Affiche le nombre d'erreurs et des liens cliquables vers chaque champ en erreur (via `#id_du_champ`). Utilise `role="alert"`, `tabindex="-1"` et `autofocus` pour que les lecteurs d'écran annoncent immédiatement les erreurs. Accessible sans JavaScript.
- **7** 🟡 **CSS — Toggle switch accessible** — Nouveau composant `.toggle-switch` (CSS-only, pas de JS). Checkbox invisible + slider visuel avec transitions, états `:checked`, `:focus-visible`, `:hover` et `:disabled`. Identique au motif iOS/Android, mais entièrement en CSS.
- **8** 🟡 **CSS — Champs requis `:required` — indicateur visuel** — Les champs `required` ont maintenant une bordure gauche bleue (`3px solid var(--color-primary)`). Les champs invalides (après saisie) passent en rouge avec fond rouge clair. Les champs valides ont une bordure verte subtile. Retrait de l'ancienne règle `.form-group input:invalid` moins spécifique.
- **9** 🟡 **`report_form.php` — Attributs `autocomplete`** — Ajout de `autocomplete="off"` sur les champs date, heure, lieu, objet (le navigateur ne doit pas préremplir ces champs spécifiques). Ajout de `autocomplete="family-name"` et `autocomplete="given-name"` sur les champs nom/prénom RAMI « pour le compte de ».
- **10** 🟡 **`pagination.php` — `aria-label` sur les numéros de page** — Chaque lien de page numérotée a maintenant `aria-label="Page N"` pour les lecteurs d'écran, en plus des liens « Précédent/Suivant » qui étaient déjà labellés.
- **11** 🟢 **CSS — `.tab-bar` dupliqué fusionné** — Deux définitions de `.tab-bar` existaient (sections 25 et 31), la deuxième écrasant la première. La première (sans `gap`, sans `overflow-x`) est supprimée, la deuxième est enrichie avec `overflow-x: auto`, `-webkit-overflow-scrolling: touch` et `scrollbar-width: thin`.
- **12** 🟢 **CSS mobile — Barre de filtre plein écran** — Sur mobile, `.filter-bar` passe en `flex-direction: column` avec `align-items: stretch`. Les champs et le bouton « Filtrer » prennent 100 % de largeur. Le bouton « + Nouveau signalement » (`.btn-float-right`) passe en bloc plein écran. Le titre de page empile verticalement sur mobile.

### UX — Corrections audit UI/UX (5 critiques, 7 moyens, 3 mineurs)

- **1** 🔴 **Tables mobiles — layout carte responsive** — La table des signalements (9 colonnes) déborde horizontalement sur mobile sans moyen de lire les données. Ajout de la classe `.table-wrapper--responsive` qui transforme chaque ligne en carte avec `data-label` sur chaque `<td>` pour afficher le libellé de la colonne. Appliqué à `report_list.php`. Les tables de détail signalement (`report-detail__table`) passent aussi en layout empilé sur mobile.
- **2** 🔴 **Dropdown incarnation fermable au clavier** — Le menu déroulant d'incarnation ne pouvait pas être fermé au clavier (ni Escape, ni Tab out). Ajout d'un bouton « Fermer » (label pour le checkbox, CSS-only) à la fin du menu, et règle CSS `:focus-within` qui ferme le dropdown quand le focus le quitte. L'attribut `aria-expanded` est ajouté au bouton.
- **3** 🔴 **Abandon confirmation — suppression du GET `confirm_abandon`** — Le lien « Abandonner » dans `report_card.php` utilisait un paramètre GET (`?confirm_abandon=1`) pour afficher la confirmation inline, ce qui est vulnérable au CSRF par prefetch/preload. Remplacé par un lien direct vers la page dédiée `report_abandon.php` qui a toujours utilisé un formulaire POST avec CSRF token.
- **4** 🔴 **Navigation prev/next dans la vue signalement** — Aucun moyen de naviguer entre signalements sans revenir à la liste. Ajout de `getAdjacentReportUuids()` dans `report_queries.php` et d'une barre de navigation `<nav class="report-nav">` avec liens « Précédent / Suivant » dans `report_view.php`.
- **5** 🔴 **Empty state amélioré** — L'état vide « Aucun signalement trouvé » n'avait pas de hiérarchie visuelle. Ajout d'une icône, d'un titre en gras et de classes `.empty-state__icon` / `.empty-state__title` pour un affichage plus clair.
- **6** 🟡 **Tab bar scrollable horizontalement** — Les onglets Paramètres (5 items) débordaient sur mobile. La `.tab-bar` passe en `flex-wrap: nowrap` avec `overflow-x: auto` et `scrollbar-width: thin`. Les onglets ont `white-space: nowrap` et sont plus compacts sur mobile.
- **7** 🟡 **Header mobile — username tronqué** — Le nom d'utilisateur et le badge de rôle débordaient sur petit écran. Ajout de `max-width: 120px` avec `text-overflow: ellipsis` sur `.header__username` en mobile.
- **8** 🟡 **Skip link transition douce** — Le lien d'évitement apparaissait brusquement au focus. Ajout d'une `transition: top 0.15s ease` pour une apparition fluide.
- **9** 🟡 **Back-to-top opacité par défaut** — Le bouton retour en haut était trop proéminent. Opacité réduite à 0.7 par défaut, 1 au hover.
- **10** 🟡 **Report detail table responsive** — La table de détail signalement (th+td) est illisible sur mobile. Ajout d'un breakpoint 768px qui empile les th/td verticalement avec th en label réduit.
- **11** 🟢 **Dropdown — overlay invisible pour fermeture click-outside** — Ajout d'un `::after` pseudo-élément fixed quand le dropdown est ouvert, pour capter les clics en dehors et fermer le menu (comportement cohérent avec le sidebar).
- **12** 🟢 **CSS — double `.back-to-top:hover` supprimé** — Deux règles `:hover` identiques sur `.back-to-top` étaient présentes, fusionnées en une seule.
- **13** 🟢 **Tab bar mobile — onglets compacts** — Sur mobile (≤768px), les onglets settings utilisent `font-size: 13px` et `padding: 8px 14px` pour maximiser l'espace visible.

## [3.16.1] — 2026-06-16

### Correction — Onglets Notifications vides dans Paramètres (incohérence de nommage)

- **1** 🔴 **`pages/settings/tab_notifications_sites.php` → `tab_sites.php`** — L'onglet « Notifications par site » (`tab=sites`) apparaissait vide car `settings.php` construit le chemin du sous-template comme `tab_{$activeTab}.php`, donc il cherchait `tab_sites.php` mais le fichier s'appelait `tab_notifications_sites.php`. Le `file_exists()` échouait silencieusement et aucun contenu n'était rendu. Renommage pour respecter la convention des autres onglets (`tab_smtp.php`, `tab_app.php`, `tab_manage_sites.php`).
- **2** 🔴 **`pages/settings/tab_notifications_global.php` → `tab_global.php`** — Même cause pour l'onglet « Notifications globales » (`tab=global`) : le fichier cherché était `tab_global.php` mais le fichier existant s'appelait `tab_notifications_global.php`.
- **3** 🔴 **`e2e/settings.spec.js` — 6 tests E2E ajoutés pour le contenu des onglets notifications** — Les tests existants ne vérifiaient que la navigation (changement d'URL, classe active sur l'onglet) sans jamais contrôler que le contenu du formulaire était rendu. Les nouveaux tests vérifient la présence du formulaire (`#settingsForm`), des textareas (`site_emails[*]`, `#global_emails`), du bouton de soumission et du texte d'aide. Cette lacune explique pourquoi le bug n'a pas été détecté : les tests validaient le voyage, pas la destination.
- **4** 🟡 **`tests/unit/NotificationQueriesTest.php` — 10 tests PHPUnit pour les requêtes de notification** — Couverture de `getNotificationSettings()`, `saveNotificationSetting()`, `deleteNotificationSetting()`, `getSiteNotificationEmails()`, `getGlobalNotificationEmails()`, `deleteNotificationSettingsByType()`, et une simulation intégrée du flux de données exact de `settings.php` (organisation des résultats en `$siteEmails` / `$globalEmails`).

## [3.16.0] — 2026-06-16

### Sécurité — `display_errors` par environnement + page d'erreur production

- **`src/config.php`** — `display_errors` et `display_startup_errors` sont désormais conditionnés par `DEV_MODE` : activés en dev, désactivés en production. `error_reporting(E_ALL)` et `log_errors` restent activés dans les deux environnements. Les erreurs fatales en production déclenchent une page HTML propre au lieu d'un écran blanc ou de stack traces visibles.
- **`src/error_handler.php`** — Le shutdown handler appelle `sstRenderProductionErrorPage()` en production, qui affiche une page d'erreur 500 HTML avec message convivial, lien de retour et notification automatique de l'administrateur. Résout le P0 de l'audit architectural (display_errors=On forcé en production).

### Architecture — Décomposition du monolithe `public/index.php` (293 → 138 lignes)

- **`src/router.php`** (nouveau) — Extraction de la logique de routage : liste blanche des pages (`getValidPages()`), validation (`validatePage()`), map des handlers POST (`getHandlerMap()`), dispatch (`dispatchPostHandler()`), titres de page (`getPageTitle()`), rendu avec layout (`renderPageWithLayout()`) et sans layout (`renderStandalonePage()`). Toutes les fonctions sont pures ou read-only, testables indépendamment.
- **`src/auth_flow.php`** (nouveau) — Extraction du flux d'authentification : auto-authentification IIS (`handleAutoAuth()`), page de login dev (`handleLoginPage()`), redirection non-authentifié (`handleNotAuthenticated()`), déconnexion (`handleLogout()`). Chaque fonction encapsule un cas d'usage complet.
- **`public/index.php`** — Réduit de 293 à 138 lignes. Ne contient plus que le boot sequence (gzip, requires, session start) et les appels de dispatch séquentiels. Toute la logique métier est dans les modules extraits.

### Architecture — Centralisation des accès `$_SESSION` (0 accès directs restants)

- **`src/session.php`** — Ajout de 11 fonctions wrapper centralisant tout accès à `$_SESSION` :
  - **Authentification** : `isUserLoggedIn()`, `setUserSession()`, `getUserSession()`, `clearSession()`
  - **URL de redirection** : `setIntendedUrl()`, `getIntendedUrl()`, `clearIntendedUrl()`
  - **Incarnation** : `startImpersonation()`, `stopImpersonation()`, `isImpersonatingRole()`, `getImpersonatedRole()`, `getRealRole()`
- **`src/user_context.php`** — Toutes les fonctions (`currentUser()`, `currentUserId()`, `currentUserRole()`, etc.) utilisent désormais les wrappers session au lieu d'accéder directement à `$_SESSION`. La seule exception documentée est `refreshCurrentUser()` qui doit écrire `$_SESSION['user']['role']` pour préserver l'état d'incarnation.
- **23 fichiers mis à jour** — Tous les accès directs `$_SESSION` remplacés par les wrappers : `src/auth.php`, `src/audit.php`, `src/middleware/bootstrap.php`, `src/middleware/require_auth.php`, `src/middleware/require_role.php`, `src/helpers/access.php`, `handlers/impersonate_handler.php`, `handlers/choose_site_handler.php`, `handlers/login_handler.php`, `handlers/report_create_handler.php`, `handlers/report_edit_handler.php`, `handlers/report_abandon_handler.php`, `handlers/report_respond_handler.php`, `handlers/user_edit_handler.php`, `handlers/user_delete_handler.php`, `pages/access_denied.php`, `pages/choose_site.php`, `pages/help.php`, `pages/home.php`, `pages/login.php`, `pages/user_edit.php`, `templates/header.php`, `templates/sidebar.php`, `templates/impersonate_banner.php`, `templates/report_card.php`, `templates/report_form.php`.
- **`tests/bootstrap.php`** — Ajout de `require_once session.php` pour que les tests puissent utiliser `setUserSession()`.
- **`tests/unit/AuditConfigTest.php`** — `$_SESSION['user'] = ...` remplacé par `setUserSession(...)`.
- **`php.ini`** — Ajout de l'extension `xmlwriter` nécessaire à PHPUnit.

## [3.15.0] — 2026-06-16

### Tests E2E — Playwright : 3 nouveaux fichiers de tests de navigation (+44 tests)

- **`e2e/impersonate.spec.js`** (16 tests) — Tests du feature d'incarnation de rôle : ouverture du menu déroulant, incarnation Agent/CHSCT, bannière d'incarnation visible sur toutes les pages, restrictions d'accès du rôle incarné (sidebar masquée, pages interdites, pas de bouton « Répondre »), bouton « Reprendre mon rôle » pour restaurer le rôle superviseur, absence du menu d'incarnation pour les agents.
- **`e2e/navigation-flows.spec.js`** (20 tests) — Flux de navigation profonds : cycle de vie complet d'un signalement (accueil → création → vue → réponse → retour liste), état actif du sidebar (7 pages testées avec `aria-current="page"`), navigation navigateur back/forward (4 scénarios), parcours multi-pages (settings tabs, user list→view→edit, 3 registres, home cards), navigation via breadcrumb, persistance de session sur 12+ pages.
- **`e2e/onboarding.spec.js`** (8 tests) — Flux d'embarquement nouveau utilisateur : redirection vers `choose_site`, affichage du formulaire de sélection de site, liste des sites disponibles, avertissement de choix définitif, validation HTML5 required, redirection vers l'accueil après choix, pas de redirection pour les utilisateurs existants, protection CSRF du formulaire.
- **`php.ini`** — Ajout des extensions `dom`, `xml`, `tokenizer`, `ctype` nécessaires au serveur PHP de test Playwright.
- **`README.md`** — Section Tests enrichie : commandes Playwright (`npx playwright test`, `--headed`, `--ui`), tableau de couverture PHPUnit (54) + Playwright (180), répertoire `e2e/` dans la structure.

### Refactoring — Élimination des 6 derniers patterns de duplication (~78 lignes nettes supprimées)

- **1** 🔴 **`report_respond.php` — fetchReportOrRedirect() + requireReportEditable()** — Ce fichier était le seul à encore utiliser le pattern manuel `isValidUuid()` + `getReportByUuid()` + null-check + redirect (8 lignes) au lieu de `fetchReportOrRedirect()`. Il dupliquait aussi la vérification d'état éditable (`in_array($report['etat'], ['nouveau', 'en_cours'])`) au lieu d'appeler `requireReportEditable()`. Remplacé par 2 appels : `$report = fetchReportOrRedirect($uuid)` + `requireReportEditable($report, $uuid, 'répondu')`. Le paramètre personnalisé `'répondu'` adapte le message d'erreur au contexte.
- **2** 🔴 **`canEditReport()` + `canRespondToReport()` — logique d'action centralisée** — L'expression `$isDeclarant && in_array($report['etat'], ['nouveau', 'en_cours'])` était dupliquée dans `templates/report_card.php` et `pages/report_list.php`. L'expression `in_array($userRole, ['superviseur']) && in_array($report['etat'], ['nouveau', 'en_cours'])` était aussi dupliquée aux mêmes endroits. Extraction de `canEditReport($report, $userId)` et `canRespondToReport($report, $role)` dans `src/helpers/access.php` (déjà le module de contrôle d'accès). Les 2 fichiers utilisent désormais ces helpers.
- **3** 🟡 **`buildDelayAlertEmail()` — HTML du mail d'alerte délai mutualisé** — Le HTML du mail d'alerte (table inline-styled ~20 lignes) était dupliqué entre `src/cron.php` (lazy cron) et `tools/check_delays.php` (CLI). Extraction de `buildDelayAlertEmail($siteData, $alertDelayDays)` dans `src/mail.php`. Les 2 fichiers construisent désormais le subject + passent les données à la fonction partagée.
- **4** 🔴 **`smtp_test_handler.php` — protocole SMTP dupliqué supprimé (-120 lignes)** — Le handler de test SMTP réimplémentait intégralement le protocole SMTP (`fsockopen` → EHLO → STARTTLS → AUTH LOGIN → MAIL FROM → RCPT TO → DATA → QUIT), soit ~120 lignes identiques à `sendViaSMTP()` de `src/mail.php`. Refactoring complet : le handler ne fait plus que valider les paramètres puis appeler `sendMail()` qui utilise déjà `sendViaSMTP()`. En cas d'échec, un message flash informe l'utilisateur de vérifier la configuration et les logs PHP.
- **5** 🟡 **`templates/user_form_fields.php` — formulaire create/edit mutualisé** — Les 6 champs du formulaire utilisateur (nom, prénom, email, username, rôle, site) étaient dupliqués entre `pages/users.php` (create) et `pages/user_edit.php` (edit), soit ~45 lignes de HTML identiques. Extraction dans `templates/user_form_fields.php` (analogue à `report_form.php` pour les signalements). Les 2 pages préparent les variables `$editNom`, `$editPrenom`, etc. puis incluent le template partagé. Le hint du champ username est paramétrable via `$usernameHint`.
- **6** 🟡 **`renderBreadcrumb()` — pattern HTML breadcrumb centralisé** — Le pattern `<nav class="breadcrumb">` avec items cliquables et séparateurs `/` était dupliqué dans 6 fichiers (`report_list.php`, `report_view.php`, `report_respond.php`, `report_abandon.php`, `report_form.php`, `logs.php`). Extraction de `renderBreadcrumb(array $items)` dans `src/helpers/formatting.php`. Chaque item est soit un lien `['url' => ..., 'label' => ...]` soit le libellé courant `['label' => ...]`. Les 6 fichiers passent de 5-8 lignes de HTML à un appel compact.

### Refactoring — fetchReportOrRedirect, guards ownership/editable, refreshCurrentUser, isLastActiveSuperviseur, labels RAMI centralisés, countActiveUsers

- **1** 🔴 **`fetchReportOrRedirect()` — pattern UUID+fetch+null-check consolidé** — Le bloc `isValidUuid()` + `getReportByUuid()` + redirect « Signalement introuvable » (8 lignes) était dupliqué dans 8 fichiers (5 pages + 3 handlers). Extraction de `fetchReportOrRedirect($uuid, $fallbackUrl)` dans `src/validation.php` qui combine validation UUID, récupération en base et redirection en un seul appel. Les 8 fichiers passent de 8 lignes à 1 ligne chacun.
- **2** 🔴 **`requireReportOwnership()` + `requireReportEditable()` — guards d'accès signalement** — Le vérification d'appartenance (declarant_id vs userId) et de state éditable (nouveau/en_cours) était dupliquée dans 4 fichiers (report_edit, report_abandon en page + handler). Extraction de deux fonctions dans `src/validation.php` : `requireReportOwnership($report, $userId, $uuid, $verb)` et `requireReportEditable($report, $uuid, $verb)`. Le paramètre `$verb` permet de personnaliser le message (« modifier », « abandonner »).
- **3** 🔴 **`refreshCurrentUser()` utilisée — session refresh mutualisée** — La fonction `refreshCurrentUser($pdo)` existait déjà dans `src/user_context.php` mais n'était appelée nulle part. Trois fichiers faisaient manuellement `getUserById() + $_SESSION['user'] = ...` : `user_edit_handler.php`, `choose_site_handler.php`, `src/middleware/bootstrap.php` (2 occurrences). Tous remplacés par `refreshCurrentUser($pdo)`, qui gère en plus la préservation de l'état d'incarnation.
- **4** 🔴 **`isLastActiveSuperviseur()` — guard dernier superviseur mutualisé** — La requête `SELECT COUNT(*) FROM users WHERE role = 'superviseur' AND is_active = 1` avec vérification `<= 1` était dupliquée dans `user_edit_handler.php` et `user_delete_handler.php`. Extraction de `isLastActiveSuperviseur(PDO $pdo): bool` dans `src/validation.php`.
- **5** 🟡 **Labels RAMI centralisés en constantes** — Les tableaux associatifs `$natureAuteurLabels` et `$typeActeLabels` étaient définis dans 3 fichiers (statistics.php, export_handler.php, validation.php). Remplacés par les constantes `RAMI_NATURE_AUTEUR_LABELS` et `RAMI_TYPE_ACTE_LABELS` définies dans `src/config.php`. La fonction `validateRamiFields()` utilise désormais `array_keys(RAMI_NATURE_AUTEUR_LABELS)` au lieu de valeurs codées en dur, garantissant la cohérence entre validation et affichage.
- **6** 🟢 **`countActiveUsers()` — SQL brute remplacée par fonction existante** — La page `statistics.php` exécutait `$pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1")` alors que `countActiveUsers($pdo)` existait déjà dans `src/queries/user_queries.php`. Remplacement direct.

## [3.14.0] — 2026-06-16

### Refactoring — Démantèlement du monolithe helpers.php, extraction des validations, centralisation des téléchargements, PHPUnit, CSS dédié, sous-templates settings

- **1** 🔴 **Éclatement du monolithe `src/helpers.php` (655 → 17 lignes)** — Le fichier `helpers.php` concentrait 5 responsabilités (validation, contrôle d'accès, configuration, assets, formatage) dans un monolithe de 655 lignes. Il est désormais un simple chargeur qui inclut 6 sous-modules spécialisés : `src/helpers/config.php` (configuration et constantes), `src/helpers/crypto.php` (CSRF, hachage), `src/helpers/access.php` (contrôle d'accès et visibilité), `src/helpers/assets.php` (cache-busting, version), `src/helpers/formatting.php` (échappement, dates, libellés), `src/helpers/http.php` (URL, redirections, téléchargements, en-têtes, POST). Chaque fichier a une responsabilité unique et cohérente. Aucun changement fonctionnel, toutes les signatures de fonctions sont préservées.
- **2** 🔴 **Extraction de `src/validation.php` — validation mutualisée signalements et utilisateurs** — Les handlers `report_create_handler.php` et `report_edit_handler.php` duplicaient ~100 lignes de validation (champs signalement, RAMI, visibilité, pièce jointe, pour compte). Les handlers `user_create_handler.php` et `user_edit_handler.php` duplicaient ~40 lignes de validation utilisateur. Cinq fonctions extraites : `validateReportFields()` (date, objet, description, lieu, heure), `validateRamiFields()` (nature_auteur, type_acte), `enforceReportVisibility()` (mode public/confidentiel/choix_agent), `validateReportAttachment()` (taille, MIME, upload), `validatePourCompte()` (nom/prénom obligatoires si signalement pour compte), `validateUserFields()` (nom, prénom, username, rôle, site, email). Les deux handlers de signalement passent de ~110 lignes dupliquées à des appels de 1-2 lignes chacun.
- **3** 🔴 **Centralisation `sendFileDownload()` — 4 patterns de téléchargement fusionnés** — Le pattern `ob_end_clean()` + `header()` + `echo` + `exit` pour l'envoi de fichiers était copié 4 fois : `export_handler.php` (CSV), `user_edit_handler.php` (JSON RGPD), `pages/report_print.php` (PDF), `pages/report_attachment.php` (pièce jointe). Extraction de `sendFileDownload($content, $filename, $contentType)` dans `src/helpers/http.php` qui gère la désactivation du buffer gzip, les en-têtes Content-Type/Disposition/Length/Cache-Control/X-Content-Type-Options, le nettoyage des en-têtes indésirables, et l'envoi + exit. Chaque handler passe de ~15 lignes à 1 appel.
- **4** 🔴 **Centralisation `removeUnwantedHeaders()` — 10 blocs `header_remove()` consolidés** — Le bloc de 4 lignes `header_remove('X-Powered-By')` etc. était copié dans 10 endroits (handlers, pages de téléchargement). Extraction de `removeUnwantedHeaders()` dans `src/helpers/http.php`, appelée une seule fois au bootstrap dans `public/index.php` après le chargement de helpers.php, et automatiquement par `sendFileDownload()`. Les 10 blocs redondants sont supprimés.
- **5** 🔴 **Chargement unique de `audit.php` au bootstrap** — Le fichier `src/audit.php` était chargé par `require_once` dans 16 handlers et pages différents. Déplacé au bootstrap dans `public/index.php` (après `helpers.php` et `auth.php`). Les 16 `require_once` redondants sont supprimés. La fonction `auditLog()` est désormais disponible partout sans inclusion manuelle.
- **6** 🔴 **Extraction du middleware depuis `public/index.php`** — Le routeur `index.php` (335 lignes) mélangeait bootstrap, auth, middleware (promotion superviseur, vérification site) et dispatch. Deux fonctions middleware extraites dans `src/middleware/bootstrap.php` : `checkSuperviseurPromotion()` (auto-promotion des agents listés dans `app_superviseur_usernames`) et `checkUserSiteAssignment()` (redirection vers `choose_site` si pas de site). Le routeur passe de ~60 lignes de middleware inline à 2 appels de fonction.
- **7** 🔴 **Création de `src/user_context.php` — helper contexte utilisateur** — L'accès à `$_SESSION['user']` était dispersé dans tout le code avec des null-checks et casts répétés. Création d'un ensemble de fonctions pures procédurales : `currentUser()`, `currentUserId()`, `currentUserUsername()`, `currentUserDisplayName()`, `currentUserRole()`, `currentUserRealRole()`, `isRole()`, `isAgent()`, `isSuperviseur()`, `isChsct()`, `isImpersonating()`, `currentUserSiteId()`, `currentUserSiteCode()`, `currentUserSiteName()`, `currentUserHasSite()`, `currentUserCanSeeAllSites()`, `currentUserCanAccessReport()`, `refreshCurrentUser()`. Pas de classe — purement procédural, conforme à l'architecture existante.
- **8** 🟡 **Sous-templates `pages/settings/` — monolithe 485 lignes éclaté** — La page `pages/settings.php` (485 lignes) contenait 5 onglets (sites, notifications par site, notifications globales, SMTP, application) dans un seul fichier. Éclatement en 5 sous-templates : `tab_manage_sites.php`, `tab_notifications_sites.php`, `tab_notifications_global.php`, `tab_smtp.php`, `tab_app.php`. Le fichier principal ne conserve que l'en-tête, la barre d'onglets et les inclusions conditionnelles.
- **9** 🟡 **Migration des styles inline → classes CSS dédiées** — 15 styles inline dans `pages/logs.php` (largeurs de colonnes, flex, backgrounds) et plusieurs styles inline dans `pages/help.php`, `pages/preamble.php`, `pages/changelog.php`, `pages/statistics.php`, `pages/settings.php` sont remplacés par des classes CSS sémantiques dans `public/css/style.css` : `.tab-bar--flush`, `.card--flush-top`, `.filter-bar--spaced`, `.form-control--auto`, `.form-control--search`, `.th--date/category/user/action/ip`, `.badge--cat-*`, `.pagination--flex`, `.help-note--warning/amber/green/blue`, `.help-cards-row`, `.changelog-details`, `.card--mt`, `.info-panel--warning/info`. Les couleurs de badge de catégorie audit sont centralisées (8 variants). Conforme à la contrainte IIS (CSS servi via PHP).
- **10** 🔴 **`reportSelectWithSite()` — centralisation du JOIN signalement+site** — Le pattern `SELECT r.*, s.code as site_code, s.nom as site_nom FROM reports r LEFT JOIN sites s ON r.site_id = s.id` était dupliqué dans `getReportByUuid()`, `getReportsByRegistry()`, `getReportsBySite()` et `stats_queries.php`. Extraction de `reportSelectWithSite()` dans `src/queries/report_queries.php`, analogue à `userSelectWithSite()` existant dans `user_queries.php`. Les 3+ requêtes utilisent désormais ce fragment SQL centralisé.
- **11** 🔴 **PHPUnit — infrastructure de tests unitaires** — Ajout de PHPUnit 11 en dépendance dev (`composer.json`), configuration `phpunit.xml`, bootstrap `tests/bootstrap.php` avec base SQLite en mémoire, et 4 fichiers de tests : `tests/unit/HelpersTest.php` (fonctions utilitaires), `tests/unit/UserQueriesTest.php` (requêtes utilisateurs), `tests/unit/SiteQueriesTest.php` (requêtes sites), `tests/unit/ReportQueriesTest.php` (requêtes signalements avec `reportSelectWithSite()`). Exécution : `vendor/bin/phpunit`.
- **12** 🟡 **`validatePostRequest()` — validation POST/CSRF/rôle centralisée** — Le pattern « vérifier POST + CSRF + rôle » était copié dans 14 handlers avec des variantes mineures. La fonction `validatePostRequest($fallbackUrl, ?array $roles)` dans `src/helpers/http.php` consolide ces 3 vérifications en un seul appel. Les 14 handlers utilisent désormais cette fonction (9 lignes → 1 ligne par handler).

## [3.13.0] — 2026-06-15

### Correction — getVersion robustesse multi-chemins + Changelog diagnostic + Audit log require manquant

- **1** 🔴 **Robustesse de `getAppVersion()` — résolution multi-chemins** — La fonction `getAppVersion()` tentait un seul chemin (`dirname(__DIR__) . '/CHANGELOG.md'`) pour localiser le changelog. En cas d'échec (chemin non résolu, permissions IIS, symlinks), elle tombait silencieusement sur le fallback `APP_VERSION` qui pouvait être périmé. Désormais, la fonction essaie plusieurs stratégies de résolution dans l'ordre : (1) constante `CHANGELOG_PATH` si définie, (2) `dirname(__DIR__)` (standard), (3) `__DIR__ . '/..'` (alternative), (4) `$_SERVER['DOCUMENT_ROOT']` (IIS), (5) `$_SERVER['SCRIPT_FILENAME']` (point d'entrée). Chaque candidat est normalisé via `realpath()` avant vérification `is_readable()`. La constante `APP_VERSION` a été synchronisée à `3.13.0` pour que le fallback reste cohérent.
- **2** 🔴 **Page Changelog — diagnostic en mode dev** — La page `changelog.php` utilisait un seul chemin fixe et affichait seulement « fichier introuvable » en cas d'échec, sans aide au diagnostic. Améliorations : (a) même stratégie multi-chemins que `getAppVersion()`, (b) détection d'erreur Parsedown (bibliothèque absente, rendu vide, erreur de lecture), (c) en mode dev, affichage détaillé des chemins testés et de leur accessibilité dans un `<details>` dépliable.
- **3** 🔴 **Journal d'audit — `require_once` manquant** — La page `pages/logs.php` appelait `getAuditLog()` sans charger `src/audit.php`, provoquant une erreur fatale « Call to undefined function » lors de l'accès à l'onglet Journal d'audit. Ajout du `require_once __DIR__ . '/../src/audit.php'` en tête de page.
- **4** 🔴 **Login handler — `$pdo` non défini en mode dev** — Le handler `login_handler.php` appelait `runLazyCron($pdo)` et `auditLog($pdo, ...)` alors que la variable `$pdo` n'était jamais initialisée dans ce contexte (le handler est chargé avant la définition de `$pdo` dans `index.php`). En production (IIS), le login passe par un chemin différent et le bug ne se déclenchait pas. Corrigé : `$pdo = getDB()` avant chaque appel.
- **5** 🟡 **Gzip désactivé sur le serveur PHP intégré** — `ob_gzhandler` provoque un crash du PHP built-in server (`php -S`). Ajout d'un test `php_sapi_name() !== 'cli-server'` pour désactiver la compression gzip en mode développement local. Sans impact en production (IIS/Apache/Nginx).

---

## [3.12.0] — 2026-06-15

### Correction — Version automatique depuis le changelog + Menu Incarner illisible + Screenshots CU5 + Journal d'audit

- **1** 🔴 **Version de l'application lue automatiquement depuis CHANGELOG.md** — La version n'est plus modifiable manuellement dans Paramètres → Application. Elle est désormais déduite automatiquement de la première entrée `## [x.y.z]` du fichier `CHANGELOG.md` via la fonction `getAppVersion()`. Cela garantit que la version affichée est toujours en concordance avec le changelog. La clé `app_version` a été retirée de la base de données et du formulaire de paramètres (affichage en lecture seule). La constante `APP_VERSION` dans `config.php` reste un fallback si le changelog est illisible.
- **2** 🔴 **Menu « Incarner » illisible** — Le menu déroulant d'incarnation de rôle affichait du texte blanc sur fond blanc car la variable CSS `--color-text` n'existait pas. Corrigé : `color: var(--grey-900)`, fond de survol `var(--grey-100)`, couleur de hover `var(--color-primary)`. Les emojis Unicode ont été remplacés par des caractères fiables sur Windows. Icône masques de théâtre ajoutée en CSS pur.
- **3** 🔴 **Réorganisation des captures d'écran CU5** — Les screenshots préfixés `cu5-*` étaient utilisés dans plusieurs sections sans rapport avec le cas d'usage CU5. Renommage : `cu5-liste-signalements` → `consultation-liste-signalements`, `cu5-voir-signalement` → `consultation-voir-rsst`, `cu5-voir-rami` → `consultation-voir-rami`, `cu5-voir-dgi` → `consultation-voir-dgi`, `cu5-repondre-signalement` → `cu4-repondre-signalement`, `cu5-modifier-signalement` → `cu4-modifier-signalement`. Ajout des captures RAMI/DGI dans la section Cycle de vie et des captures répondre/modifier dans CU4.
- **4** 🔴 **Journal d'audit consultable** — La page Journal (accessible uniquement aux superviseurs) intègre désormais un onglet « Journal d'audit » qui affiche les entrées de la table `audit_log` avec filtres par catégorie, recherche par utilisateur ou détail, filtrage par date, et pagination. Toutes les actions tracées (connexion, incarnation, création/modification de signalement, export, etc.) sont visibles.

---

## [3.11.0] — 2026-06-15

### Fonctionnalité — Version pilotée par la base de données + Incarnation de rôle + Corrections

- **1** 🔴 **Version de l'application stockée en base de données** — La version affichée dans le pied de page et utilisée pour le cache-busting des assets n'est plus codée en dur dans `config.php`. Elle est lue depuis la clé `app_version` de la table `config_app` via la nouvelle fonction `getAppVersion()`. La constante `APP_VERSION` dans `config.php` reste un fallback si la base est indisponible. La version est modifiable dans Paramètres → Application (champ « Version de l'application », format semver obligatoire). Les fichiers modifiés : `src/helpers.php` (fonction `getAppVersion()`), `src/config.php` (commentaire de fallback), `src/database.php` (migration `app_version`), `schema.sql`, `templates/footer.php`, `src/error_handler.php`, `pages/settings.php`, `handlers/settings_handler.php`.
- **2** 🔴 **Incarnation de rôle (impersonation) pour les superviseurs** — Un superviseur peut temporairement basculer vers le rôle Agent ou Membre CSA/CHSCT pour voir l'application avec les mêmes restrictions de visibilité et d'accès que ce rôle. Cela permet de vérifier le comportement perçu par un agent (signalements visibles, menus accessibles) sans avoir à se déconnecter ni créer un compte de test. Le mécanisme est accessible via un menu déroulant « 🎭 Incarner » dans l'en-tête bleu, visible uniquement pour les superviseurs qui ne sont pas déjà en mode incarnation.
- **3** 🔴 **Bannière d'avertissement orange en mode incarnation** — Lorsqu'un superviseur incarne un rôle, une bannière orange fixe s'affiche sous l'en-tête avec le message « Vous incarnez le rôle [Agent/CSA/CHSCT] » et un bouton « Reprendre mon rôle » qui restaure immédiatement le rôle de superviseur. La bannière est visible sur toutes les pages, y compris la page d'accès refusé (quand l'agent incarné tente d'accéder à une page réservée aux superviseurs).
- **4** 🟡 **Menu déroulant CSS-only (sans JavaScript)** — Le menu « Incarner » utilise la technique checkbox + label, cohérente avec l'approche zéro-JavaScript de l'application.
- **5** 🟢 **Traçabilité d'audit** — Les actions d'incarnation (début et fin) sont enregistrées dans le journal d'audit (`audit_log`) avec la catégorie `auth` et les actions `impersonate_start` / `impersonate_stop`.
- **6** 🟡 **Protection contre la promotion automatique** — La vérification de promotion automatique superviseur (via `app_superviseur_usernames`) est désactivée pendant l'incarnation d'un agent.
- **7** 🟢 **Handler `impersonate_handler.php`** — Nouveau handler POST avec validation CSRF, vérification de rôle, validation du rôle cible, et journalisation d'audit.
- **8** 🔴 **Suppression de l'anglicisme « KPI »** — Toutes les occurrences de `kpi` ont été remplacées par `indicateur` dans le PHP, le CSS, le Python d'annotation et les maquettes HTML : classes CSS `kpi-grid` → `indicateur-grid`, `kpi-card` → `indicateur-card`, fonction `getStatisticsKPIs()` → `getStatisticsIndicateurs()`, variable `$kpis` → `$indicateurs`, commentaires et SPEC.
- **9** 🔴 **Bug double « Registre » dans le titre de la liste des fiches** — Le h1 affichait « Liste des fiches du registre Registre de Santé et de Sécurité au Travail » (doublon). Corrigé : utilise désormais `REGISTRY_SHORT_LABELS` pour afficher « Liste des fiches — RSST ».
- **10** 🔴 **Panneau d'alerte DGI manquant** — Les signalements DGI n'affichaient pas le bandeau d'alerte « Procédure prioritaire » rappelant les articles L4131-1 et L4132-5 du Code du travail. Ajouté dans `report_card.php` avec le style `danger-panel` existant.
- **11** 🟡 **Label DGI « Lieu / Mesures de protection »** — Le champ lieu s'intitule désormais « Lieu / Mesures de protection » pour les signalements DGI, dans le formulaire de création/édition et dans la fiche signalement, conformément aux exigences réglementaires du DGI.

---

## [3.10.1] — 2026-06-15

### Correction — CSP frame-ancestors bloque les iframes de la page d'aide

- **1** 🔴 **CSP `frame-ancestors 'none'` conservé — iframes remplacées par images PNG annotées** — La page Documentation (`help.php`) n'utilise plus d'`<iframe>` pour afficher les captures d'écran. Les 23 captures HTML ont été converties en images PNG annotées (numérotation + flèches + descriptions) via Playwright + Pillow. Avantages : imprimables, compatibles `frame-ancestors 'none'` (sécurité maximale anti-clickjacking), pas de problème CSP.
- **2** 🟢 **Captures d'écran PNG annotées dans `docs/screenshots/`** — 23 fichiers PNG avec annotations (badges numérotés, flèches, descriptions) pour chaque page de l'application. Scripts de génération : `tools/capture_screenshots.py` (Playwright) et `tools/annotate_screenshots.py` (Pillow). Copie automatisée dans `update_sst.ps1` (étape 2/5).
- **3** 🟡 **`update_sst.bat` supprimé** — Remplacé par `update_sst.ps1` (plus robuste, inclut la copie des screenshots PNG, détection auto de la branche main/master). Les fichiers `*.bat` sont dans `.gitignore`.
- **4** 🟡 **`update_sst.ps1` amélioré** — Fetch de toutes les branches (`+refs/heads/*`), détection auto de la branche par défaut (main/master), `reset --hard` + `checkout -B` robuste, copie des PNG annotés, nettoyage de l'ancienne branche master.
- **5** 🟡 **Correction annotations identiques** — Le script `annotate_screenshots.py` générait des annotations identiques sur toutes les images (les clés du dictionnaire `ANNOTATIONS` ne correspondaient pas aux noms de fichiers PNG capturés). Corrigé : chaque screenshot a désormais ses propres callouts numérotés avec descriptions spécifiques.
- **6** 🔴 **Correction positions annotations — détection Playwright des vrais éléments HTML** — Les annotations étaient positionnées via des pourcentages à l'aveugle, ce qui plaçait les cibles sur les mauvais éléments UI. Le script `annotate_screenshots.py` utilise désormais Playwright pour détecter automatiquement la position réelle de chaque élément HTML (header, sidebar, cards, forms, tableaux, boutons) via les sélecteurs CSS. 6 fichiers HTML tronqués (cu5-*) ont été complétés avec leur contenu manquant.
- **7** 🟡 **Champ Contact DPO dans Paramètres → Application** — Le préambule RGPD affichait « à compléter dans Paramètres → Application → Contact DPO » mais ce champ n'existait pas. Ajouté dans `settings.php` (onglet Application) et sauvegardé dans `settings_handler.php`. La config `app_dpo_contact` était déjà déclarée dans `database.php`.
- **8** 🔴 **Maquettes HTML cu5 mises à jour pour correspondre à l'application réelle** — Les 7 maquettes HTML de signalements (cu5-liste-signalements, cu5-liste-signalements-sup, cu5-voir-signalement, cu5-voir-rami, cu5-voir-dgi, cu5-modifier-signalement, cu5-repondre-signalement) étaient en décalage majeur avec les templates PHP réels. Corrections : titre « Liste des fiches du registre RSST » + bouton « + Nouveau signalement », colonnes Nom/Prénom/UR/Visibilité ajoutées dans les listes, filtre Registre remplacé par filtre Site (UR), sélecteur de recherche `id="q"`, structure `report-detail` avec `h2` + `btn-group` pour les badges, champs Heure/Lieu/Date de création ajoutés dans les vues détail, historique en timeline cards → tableau (Date/Répondant/Nouvel état/Réponse), badge Visibilité (Confidentiel/Public), boutons « Voir en PDF » et « Abandonner le signalement », formulaire d'édition avec breadcrumb dans la card + `btn--rsst`. Sélecteurs CSS d'annotation mis à jour dans `annotate_screenshots.py`.

---

## [3.10.0] — 2026-06-14

### Fonctionnalité — Lazy cron (tâches de maintenance au login)

- **1** 🔴 **Lazy cron `src/cron.php`** — Les tâches de maintenance (alerte délais + anonymisation RGPD) s'exécutent automatiquement au login d'un utilisateur, sans cron système ni tâche planifiée Windows. Mécanisme : à chaque connexion (IIS auto-auth ou formulaire dev), `runLazyCron()` vérifie les timestamps `last_lazy_cron_*` dans `config_app` et n'exécute une tâche que si l'intervalle minimum est écoulé (24h pour check_delays, 7j pour anonymize). Les erreurs sont silencieuses (loguées, jamais bloquantes).
- **2** 🔴 **`check_delays` via lazy cron** — L'alerte superviseurs pour les signalements en retard (`app_alert_delay_days > 0`) est désormais envoyée automatiquement au login, toutes les 24h minimum. Remplace la recommandation de cron système (`0 8 * * *`) dans l'en-tête de `tools/check_delays.php`. Le script CLI reste disponible pour les dry-run et l'exécution manuelle.
- **3** 🟡 **`anonymize` via lazy cron** — L'anonymisation RGPD des signalements anciens (`app_retention_years > 0`) s'exécute automatiquement au login, tous les 7 jours minimum. Contrairement au script CLI, il n'y a pas de confirmation interactive — l'anonymisation procède automatiquement quand la période de retention est atteinte (validation DPO préalable obligatoire). Le script CLI reste disponible pour les dry-run et l'exécution manuelle avec confirmation.
- **4** 🟢 **Clés système `config_app`** — Deux nouvelles clés `last_lazy_cron_check_delays` et `last_lazy_cron_anonymize` (catégorie `system`, `modifiable=0`) sont migrées automatiquement. Elles n'apparaissent pas dans l'interface Paramètres.
- **5** 🟢 **DEPLOY.md mis à jour** — La section anonymisation ne recommande plus `schtasks /create` mais documente le lazy cron. Les scripts CLI restent documentés pour l'usage manuel.

---

## [3.9.0] — 2026-06-14

### Corrections v4 — Audit SST DREETS BFC

- **A** 🔴 **Références légales RAMI corrigées** — Le bloc RAMI dans `pages/preamble.php` ne contient plus de TODO ni de « Cadre juridique à confirmer ». Remplacé par les références vérifiées sur Légifrance : Article L135-6 du CGFP (loi n° 2019-828), articles R135-1 à R135-10 du CGFP (décret n° 2024-1038 du 6 novembre 2024). Ajout des liens Légifrance pour RSST (Décret 82-453 art. 3-2) et DGI (Art. L4131-1 et D4132-1 Code du travail).
- **B** 🔴 **Anti-pattern de test corrigé** — `canAccessReport()` dans `src/helpers.php` accepte désormais un 3ème paramètre optionnel `$forcedVisibility` pour injecter le mode de visibilité sans DB. Le test `tools/tests/test_can_access_report.php` appelle directement la vraie fonction (la copie locale `testCanAccessReport()` est supprimée). 79 cas couverts, comportement de production inchangé.
- **C** 🔴 **Protection anti-fixation de session activée** — `login_handler.php` appelle désormais `safeSessionRegenerate()` au lieu du code commenté. Le fichier `session_patch.php` est inclus dans le bootstrap `index.php`. En production, l'ancienne session est détruite ; en dev, le drapeau `false` évite le crash du serveur intégré.
- **D** 🟡 **Header Content-Security-Policy ajouté** — `public/web.config` inclut désormais un header CSP : `default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'`. `unsafe-inline` requis pour les styles/scripts inline existants. `frame-ancestors 'none'` complète X-Frame-Options.
- **E** 🔴 **Chiffrement de smtp_pass en base** — Deux fonctions dédiées `encryptConfigValue()` et `decryptConfigValue()` dans `src/helpers.php` (AES-256-CBC, clé via variable d'environnement `SST_SECRET_KEY`). Lecture déchiffrée dans `src/mail.php`, écriture chiffrée dans `handlers/settings_handler.php`. Migration idempotente `migrateEncryptSmtpPass()` dans `src/database.php` chiffre automatiquement les valeurs en clair au premier démarrage. Section SST_SECRET_KEY ajoutée dans `DEPLOY.md`.

---

## [3.8.3] — 2026-06-12

### Outil CLI — `nuclear-reset.php` : purge des signalements

- **1** 🟡 **Script CLI `nuclear-reset.php`** — Outil de purge pour les phases de test. Supprime uniquement les données liées aux signalements (`reports`, `report_responses`, `report_sequence`, `audit_log`) et conserve les utilisateurs, sites, configuration et paramètres de notification. Demande confirmation en tapant `OUI` avant de supprimer. Réinitialise les compteurs auto-increment et lance un `VACUUM` SQLite. Usage : `php nuclear-reset.php`

### Nettoyage — Suppression code mort (report_id, sous-requêtes, migration)

- **1** 🔴 **INSERT sans `report_id`** — L'INSERT dans `respondToReport()` et `seed.php` ne fournit plus `report_id`. La sous-requête `(SELECT id FROM reports WHERE uuid = ...)` est retirée. `report_id` est nullable depuis la migration one-shot exécutée sur le serveur.
- **2** 🟡 **Bloc migration `report_responses_new` supprimé** — Le nettoyage de la table orpheline `report_responses_new` dans `database.php` est retiré (la migration one-shot a déjà tout nettoyé sur le serveur).
- **3** 🟢 **Zéro trace résiduelle** — Plus aucune référence à `report_uuid2`, `report_responses_new`, ou la sous-requête `SELECT id FROM reports WHERE uuid` dans les INSERT.

---

## [3.8.2] — 2026-06-12

### Correctif — Suppression migration report_id nullable (database table is locked)

- **1** 🔴 **Migration `report_id nullable` supprimée** — La migration qui recréait la table `report_responses` pour rendre `report_id` nullable échouait systématiquement avec « database table is locked » sous IIS. SQLite ne supporte pas les écritures concurrentes : quand plusieurs processus PHP tentent la migration en même temps (DROP TABLE + CREATE TABLE + INSERT + ALTER TABLE), le verrou exclusif est refusé. **Cette migration n'est plus nécessaire** car depuis v3.8.1, l'INSERT fournit `report_id` via sous-requête `(SELECT id FROM reports WHERE uuid = ...)`. Le code fonctionne que `report_id` soit `NOT NULL` ou nullable.
- **2** 🟢 **Nettoyage conservé** — Si une table orpheline `report_responses_new` existe (d'une ancienne tentative échouée), elle est supprimée. Cette opération est légère (DROP TABLE IF EXISTS) et ne provoque pas de verrouillage.

---

## [3.8.1] — 2026-06-12

### Correctif — report_id NOT NULL : l'INSERT fournit désormais report_id

- **1** 🔴 **`respondToReport()` : INSERT inclut `report_id`** — L'INSERT dans `report_responses` ne fournissait pas `report_id`, ce qui échouait si la colonne était encore `NOT NULL` (migration pas encore passée sur le serveur). Correction : l'INSERT utilise une sous-requête `(SELECT id FROM reports WHERE uuid = :report_uuid2)` pour résoudre automatiquement le `report_id` à partir du `report_uuid`. **Fonctionne que la migration soit passée ou non** — c'est la solution définitive.
- **2** 🟡 **`seed.php` même correctif** — L'INSERT de seed pour les réponses de démo inclut désormais `report_id` via la même sous-requête.

---

## [3.8.0] — 2026-06-12

### Architecture — Assets inline : zéro dépendance IIS pour les assets statiques

- **1** 🔴 **CSS inline via `<style>`** — Le CSS n'est plus servi par une requête HTTP séparée. La fonction `inlineCss('css/style.css')` lit le fichier et l'injecte directement dans le HTML via une balise `<style>`. Puisque toutes les pages HTML sont `Cache-Control: no-cache`, le navigateur revalide systématiquement — un cache CSS séparé n'apportait aucune benefit de performance. Le gzip (ob_gzhandler) compresse efficacement le CSS inline. **Élimine le faux positif webhint « content-type should be text/html »** car il n'y a plus de requête asset.php pour le CSS.
- **2** 🔴 **Favicons en data URI** — Les favicons (`favicon.png`, `favicon.ico`) sont encodés en base64 et injectés via `data:image/png;base64,...` dans les attributs `href` des `<link rel="icon">`. La fonction `inlineDataUri()` lit le fichier et génère automatiquement la data URI. **Élimine le faux positif webhint sur le Content-Type du favicon** (charset, type MIME) et les avertissements Cache-Control max-age.
- **3** 🔴 **Logo en data URI** — Le logo DREETS (`img/logo-dreets.png`), s'il existe, est également servi en data URI via `inlineDataUri()`.
- **4** 🟡 **Règles URL Rewrite inbound supprimées du `web.config`** — Plus besoin de réécrire `/assets/...` → `asset.php?f=...`. Seule la règle outbound « Remove Server Header » reste. L'application ne dépend plus du module URL Rewrite IIS pour les assets — seul le header Server sortant l'utilise.
- **5** 🟡 **`assetUrl()` rétrogradée** — Conservée pour les rares cas nécessitant une requête HTTP séparée (téléchargement de pièces jointes, exports). Le format redevient `asset.php?f=...&v=...`. Plus aucune page ne l'utilise pour le CSS ou les favicons.
- **6** 🟡 **`router.php` simplifié** — Le routeur de développement ne gère plus les URLs `/assets/...`. Il ne fait que router `asset.php` (legacy) et `index.php`.
- **7** 🟢 **`asset.php` conservé** — Reste disponible pour servir des assets si nécessaire (pièces jointes, exports CSV/PDF), mais n'est plus appelé pour le CSS ni les favicons.

### Audit — Correctifs webhint résiduels (v3.7.3 reportés)

- **8** 🔴 **Favicon Content-Type : `charset=utf-8` ajouté** — `image/vnd.microsoft.icon; charset=utf-8` dans `asset.php` (au cas où asset.php serait encore appelé pour un favicon).
- **9** 🔴 **Cache-Control max-age = 180** — `asset.php` et `router.php` appliquent `max-age=180` à tous les assets (webhint exige ≤180). Le flag `immutable` est retiré.

---

## [3.7.3] — 2026-06-12

### Audit — webhint : Content-Type, Cache-Control, URL d'assets

- **1** 🔴 **Favicon Content-Type : ajout de `charset=utf-8`** — webhint signale que `image/vnd.microsoft.icon` manque `charset=utf-8`. Ajout du paramètre dans `asset.php` et `router.php` : `image/vnd.microsoft.icon; charset=utf-8`. Bien que le paramètre charset soit techniquement sans effet sur un type binaire, sa présence satisfait le scanneur webhint sans impact fonctionnel.
- **2** 🔴 **Faux positif webhint « content-type should be text/html »** — webhint détecte `.php` dans l'URL `asset.php?f=css/style.css` et suppose que la réponse doit être HTML. C'est un bogue de webhint : les types MIME `text/css` et `image/vnd.microsoft.icon` sont corrects. **Contournement** : les URLs d'assets ne contiennent plus `.php`. Nouveau format : `/assets/css/style.css?v=3.7.3` au lieu de `asset.php?f=css/style.css&v=3.7.2`. Sur IIS, une règle URL Rewrite inbound convertit `/assets/...` → `asset.php?f=...&v=...`. Sur le serveur PHP built-in, `router.php` gère directement les URLs `/assets/...`.
- **3** 🔴 **Cache-Control max-age trop élevé** — webhint exige `max-age ≤ 180` pour tous les assets. Réduction de `max-age` à 180 secondes dans `asset.php` et `router.php` (CSS : 604800→180, favicon : 2592000→180, fonts : 31536000→180). Le flag `immutable` est retiré car il n'est utile qu'avec un `max-age` long. Les ETag + 304 garantissent une revalidation efficace après expiration du cache.
- **4** 🟡 **`assetUrl()` génère `/assets/...`** — La fonction `assetUrl()` dans `helpers.php` produit désormais `assets/css/style.css?v=3.7.3` au lieu de `asset.php?f=css/style.css&v=3.7.3`. Plus lisible, plus conforme aux attentes des scanneurs, et le paramètre `v=` est dans la query string standard (pas encodé dans le chemin). Rétro-compatible : l'ancien format `asset.php?f=...` reste fonctionnel.
- **5** 🟡 **`web.config` : règles URL Rewrite inbound** — Deux nouvelles règles inbound dans `<rewrite><rules>` : `Asset Rewrite` pour `/assets/(css|img|fonts|js)/fichier.ext` et `Asset Rewrite Root` pour `/assets/fichier.ext` (favicon). Les deux réécrivent vers `asset.php?f=...&v=...` en utilisant les back-references `{R:1}` et `{R:2}`.
- **6** 🟢 **`router.php` réécrit** — Le routeur de développement gère désormais les URLs `/assets/...` via deux regex `preg_match`, avec une fonction `serveStaticAsset()` centralisée (MIME types, ETag, 304, Cache-Control). Support legacy de `asset.php?f=...` conservé.

---

## [3.7.2] — 2026-06-12

### Audit — Accessibilité, Cache-Control, Server header

- **1** 🔴 **ARIA : élément caché ne doit pas être focusable** — Le checkbox `#sidebar-toggle` avait `aria-hidden="true"` mais restait techniquement focusable au clavier (violation axe-core « ARIA hidden element must not contain focusable elements »). Remplacement par `tabindex="-1"` sans `aria-hidden` : l'attribut `hidden` le cache déjà de l'arbre d'accessibilité, `tabindex="-1"` empêche le focus programme.
- **2** 🔴 **Cache-Control : `no-cache` sans `max-age=0`** — L'audit signalait que `max-age` ne doit pas coexister avec `no-cache` (redondance). Remplacement de `Cache-Control: no-cache, max-age=0` par `Cache-Control: no-cache` dans 7 fichiers (header.php, login.php, choose_site.php, report_print.php, report_attachment.php, export_handler.php, user_edit_handler.php). La directive `no-cache` seule suffit : elle impose la revalidation systématique.
- **3** 🔴 **Header `Server: microsoft-iis/10.0` supprimé via web.config** — IIS ajoute le header Server APRÈS PHP, rendant `header_remove('Server')` inefficace. Ajout d'une règle URL Rewrite sortante dans `web.config` qui remplace la valeur de `RESPONSE_Server` par une chaîne vide. Prérequis : module URL Rewrite installé sur IIS (déjà requis par le projet).
- **4** 🟡 **Favicon Content-Type standardisé** — `image/x-icon` remplacé par `image/vnd.microsoft.icon` (type MIME IANA officiel) dans `asset.php` et `router.php`. Pas de `charset` sur les types binaires.
- **5** 🟡 **Faux positifs documentés** — L'audit signale que le `content-type` des assets CSS et favicon devrait être `text/html` : c'est une erreur de l'outil d'audit (les types `text/css` et `image/vnd.microsoft.icon` sont corrects). L'audit recommande aussi un `max-age ≤ 180` pour les assets versionnés avec `immutable` : c'est un faux positif (les assets versionnés avec cache busting doivent avoir un long cache pour la performance).

---

## [3.7.1] — 2026-06-12

### Correctif — Migration report_responses bloquée

- **1** 🔴 **`report_responses_new` orphelin bloquait la migration** — La migration rendant `report_id` nullable échouait à chaque requête car une table `report_responses_new` résiduelle d'une précédente tentative avortée existait déjà (`CREATE TABLE report_responses_new` → erreur « table already exists »). La migration ne passait jamais, laissant `report_id NOT NULL`, ce qui causait l'erreur `Integrity constraint violation: 19 NOT NULL constraint failed: report_responses.report_id` lors de l'INSERT par `respondToReport()`. Correction : la migration supprime d'abord la table orpheline `_new` si elle existe, puis nettoie aussi en cas d'échec (`DROP TABLE IF EXISTS report_responses_new` dans le catch).

### Fonctionnalité — Notification par e-mail lors d'un changement de rôle

- **2** 🔴 **E-mail de notification automatique lors d'un changement de rôle** — Nouvelle fonction `notifyRoleChange()` dans `src/mail.php`. Lorsqu'un superviseur modifie le rôle d'un utilisateur, un e-mail est envoyé à l'utilisateur pour l'informer du changement (ancien rôle → nouveau rôle) avec une description des permissions associées au nouveau rôle.
- **3** 🟡 **Case à cocher « Avertir l'utilisateur par e-mail »** — Dans la page d'édition d'un utilisateur (`user_edit.php`), une case à cocher apparaît lorsque le rôle sélectionné diffère du rôle actuel ET que l'utilisateur a une adresse e-mail. Elle est cochée par défaut. Si l'utilisateur n'a pas d'e-mail, un message ⚠ est affiché dans le flash de succès.
- **4** 🟢 **CSS `.checkbox-label`** — Nouvelle classe CSS pour le style des labels de checkbox (flex, gap, cursor pointer).

---

## [3.7.0] — 2026-06-12

### Fonctionnalité — Notifications e-mail automatiques en cas d'erreur critique

- **1** 🔴 **Gestionnaire d'erreurs personnalisé** — Nouveau module `src/error_handler.php` qui intercepte toutes les erreurs PHP et envoie automatiquement un e-mail à l'administrateur technique configuré lorsque des erreurs critiques surviennent (Fatal error, Parse error, Core error, Compile error, Recoverable error). Les notices, warnings et deprecated ne déclenchent pas d'e-mail pour éviter le bruit.
- **2** 🔴 **Clé de configuration `app_admin_email`** — Nouvelle clé dans la table `config_app`, configurable via l'onglet « Paramètres de l'application ». L'adresse e-mail de l'administrateur technique reçoit les alertes automatiques. Laissez vide pour désactiver les notifications.
- **3** 🟡 **Anti-spam : limitation d'envoi** — Une même erreur ne déclenche qu'un seul e-mail toutes les 5 minutes (throttle basé sur un hash de l'erreur). Le fichier `data/error-throttle.json` stocke les horodatages des derniers envois. Les entrées de plus d'une heure sont automatiquement nettoyées.
- **4** 🟡 **E-mail détaillé avec contexte** — Chaque notification contient : le type d'erreur, le message, le fichier et la ligne, l'URL de la requête, l'adresse IP, la date/heure, et un lien vers le journal d'erreurs dans l'interface.
- **5** 🟡 **Champ « E-mail administrateur technique » dans les paramètres** — Nouveau champ dans l'onglet « Paramètres de l'application » de la page Paramètres, avec validation de l'adresse e-mail. Un texte d'aide explique le fonctionnement du throttle et renvoie vers la page Journal d'erreurs.
- **6** 🟢 **Journal d'erreurs : catégorie `[SST-ERROR-MAIL]`** — Les entrées de log liées aux notifications d'erreurs sont désormais catégorisées sous « E-mail » dans le journal d'erreurs (page Logs), au même titre que `[SST-MAIL]`.
- **7** 🟡 **Shutdown handler pour erreurs fatales** — En plus du `set_error_handler()`, un `register_shutdown_function()` attrape les erreurs fatales (E_ERROR, E_PARSE, etc.) qui bypassent le error handler standard.

---

## [3.6.0] — 2026-06-12

### Sécurité — Tout passe par l'authentification Windows

- **1** 🔴 **Accès anonyme supprimé pour `asset.php`** — Le bloc `<location path="asset.php">` qui activait l'authentification anonyme pour ce script a été supprimé du `web.config`. Désormais, **toutes les requêtes** — y compris les assets statiques (CSS, images, fonts) — nécessitent une authentification Windows. Aucune ressource de l'application n'est accessible sans authentification préalable.
- **2** 🔴 **Accès direct aux assets statiques bloqué par IIS** — Les répertoires `css/`, `img/`, `fonts/` et `js/` sont ajoutés aux `<hiddenSegments>` du `web.config`. Toute requête HTTP directe vers ces répertoires (ex: `/css/style.css`) renvoie une erreur 404 par IIS. Seul `asset.php` peut lire ces fichiers via le système de fichiers PHP (`readfile()`), qui n'est pas affecté par les hiddenSegments IIS. Cela garantit qu'aucun asset ne peut être servi sans passer par PHP et donc sans authentification Windows.
- **3** 🟡 **`asset.php` — Documentation mise à jour** — Les commentaires du script documentent désormais le fait que l'authentification Windows est requise en production, et que l'accès direct aux répertoires d'assets est bloqué par le `web.config`.
- **4** 🟡 **`web.config` — Commentaires clarifiés** — Les commentaires expliquent que TOUT passe par Windows Auth, qu'aucune exception d'accès anonyme n'existe, et pourquoi les hiddenSegments sont utilisés pour forcer le passage par `asset.php`.

---

## [3.5.0] — 2026-06-12

### Serveur d'assets PHP — Contrôle total des headers HTTP

- **1** 🔴 **`asset.php` — Serveur d'assets statiques en PHP** — Nouveau fichier `public/asset.php` qui sert TOUS les assets statiques (CSS, images, fonts, icônes) via PHP au lieu d'IIS. Cela donne un contrôle total sur les headers HTTP : `Content-Type` avec charset, `X-Content-Type-Options: nosniff`, `Cache-Control` avec `immutable` pour les assets versionnés, `ETag` pour les 304, `Last-Modified`, suppression de `X-Powered-By`/`Server`/`Expires`/`Pragma`. Sécurité : whitelist d'extensions, whitelist de répertoires, prévention de directory traversal.
- **2** 🔴 **`assetUrl()` route via `asset.php`** — La fonction `assetUrl('css/style.css')` génère désormais `asset.php?f=css/style.css&v=3.5.0` au lieu de `css/style.css?v=3.5.0`. Tous les assets passent par le serveur PHP.
- **3** 🔴 **Cache-Control `immutable`** — Les assets versionnés (`?v=`) reçoivent `Cache-Control: public, max-age=..., immutable`. Le flag `immutable` indique au navigateur que le contenu ne changera jamais pendant la durée du cache, éliminant les revalidations inutiles.
- **4** 🟡 **Support ETag + 304 Not Modified** — `asset.php` génère un ETag basé sur `filemtime` + `filesize` + `crc32` du chemin. Si le client envoie `If-None-Match` ou `If-Modified-Since`, le serveur répond `304 Not Modified` sans renvoyer le contenu.
- **5** 🟡 **Favicons servis via `asset.php`** — Les favicons (`favicon.png`, `favicon.ico`) dans `header.php` passent désormais par `assetUrl()` au lieu d'URLs directes.
- **6** 🟡 **`web.config` : accès anonyme pour `asset.php`** — Ajout d'une `<location path="asset.php">` permettant l'authentification anonyme uniquement pour ce script. Les assets n'ont pas besoin d'authentification Windows, éliminant la surcharge NTLM/Kerberos sur chaque requête CSS/image/font.
- **7** 🟡 **CSP mise à jour** — Suppression de `script-src 'self'` (plus de JS du tout). La CSP est désormais `default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'`.

### Correctif — Réponse superviseur toujours en erreur

- **8** 🔴 **`respondToReport()` retourne `'true'` (string) mais le handler comparait avec `true` (booléen)** — La comparaison stricte `$result === true` échouait systématiquement car la fonction retourne la chaîne `'true'`, pas le booléen. Le superviseur ne pouvait jamais enregistrer de réponse — l'erreur « Erreur lors de l'enregistrement de la réponse » s'affichait à chaque tentative. Correction : `$result === 'true'` (comparaison de chaînes).
- **9** 🟡 **Logging amélioré en cas d'échec** — Ajout de `error_log()` avec le contexte complet (result, user_id, report_uuid, nouvel_etat) pour faciliter le diagnostic si l'erreur se reproduit.

### Fonctionnalité — Journal d'erreurs dans l'interface

- **10** 🟢 **Page « Journal d'erreurs » dans le sidebar** — Nouvelle page `logs` accessible uniquement aux superviseurs, affichant le contenu du fichier `data/php-error.log` directement dans l'interface. Fini la nécessité d'accéder au serveur pour lire les logs. Les entrées sont affichées les plus récentes en premier, avec un affichage terminal sombre (Catppuccin) et une coloration par catégorie.
- **11** 🟢 **Filtrage par catégorie** — Onglets de filtre : Tout, Fatal, Warnings, Base de données, E-mail, Réponses, Sauvegarde, Migration. Chaque catégorie a sa couleur de badge et sa bordure latérale.
- **12** 🟢 **Bouton « Effacer les logs »** — Permet de vider le fichier de log en un clic (protégé par CSRF token et confirmation).
- **13** 🟡 **Sidebar : entrée « Journal »** — Nouvel item dans le menu sidebar (icône 📜), visible uniquement pour les superviseurs, entre « Paramètres » et le footer.

---

## [3.4.0] — 2026-06-12

### Audit — Sécurité HTTP, cache busting, zéro JavaScript

- **1** 🔴 **Cache busting sur les assets statiques** — La fonction `assetUrl()` ne faisait qu'ajouter le chemin brut sans paramètre de version. Ajout de `?v=APP_VERSION` pour forcer le navigateur à recharger les ressources CSS/JS/images après chaque déploiement. Cela résout le signal « Resource should use cache busting but URL does not match configured patterns ».
- **2** 🔴 **Suppression du header `Server` par `header_remove()`** — Tous les fichiers utilisaient `header('Server: ')` qui est inefficace sur IIS (le serveur réinsère sa valeur). Remplacement systématique par `header_remove('Server')` dans `index.php`, `header.php`, `login.php`, `choose_site.php`, `report_print.php`, `report_attachment.php`, `export_handler.php`, `user_edit_handler.php` et `router.php`. Le header `Server` ne doit contenir que le nom du serveur, sans version.
- **3** 🔴 **Suppression des headers dépréciés `Expires` et `Pragma`** — Ces headers sont obsolètes et remplacés par `Cache-Control`. Ajout de `header_remove('Expires')` et `header_remove('Pragma')` dans tous les points d'entrée PHP pour nettoyer les headers HTTP.
- **4** 🔴 **`X-Content-Type-Options: nosniff` appliqué à TOUS les assets statiques** — Le `router.php` ne l'ajoutait que pour les assets texte (css, js, json, svg). Désormais ajouté sur TOUS les assets statiques (images, fonts incluses) pour empêcher le MIME sniffing.
- **5** 🟡 **Cache-Control intelligent dans `router.php`** — Les assets non versionnés (sans `?v=`) ont désormais un `max-age=180` (3 minutes) au lieu d'une longue durée, pour éviter les problèmes de cache périmé. Les assets versionnés conservent les longues durées (7 jours CSS/JS, 30 jours images, 1 an fonts).
- **6** 🔴 **Suppression complète du JavaScript** — Conformément à la contrainte de durabilité 10 ans (zéro JS) :
  - **Sidebar** : Remplacement du JS de toggle mobile par un checkbox CSS-only (`#sidebar-toggle`). Le label hamburger dans le header coche/décoche la checkbox cachée. CSS `:checked ~ .sidebar` ouvre le panneau. L'overlay est aussi un label pour la checkbox (cliquer ferme).
  - **Bouton « retour en haut »** : Remplacement du JS `scroll`/`click` par un simple lien `<a href="#top">` qui utilise l'ancre `#top` placée en début de `<main>`. Plus besoin de JS pour la visibilité ni le défilement.
- **7** 🟡 **Sécurité CSP : `frame-ancestors 'none'` remplace `X-Frame-Options`** — Suppression implicite de tout header `X-Frame-Options` (aucun n'était émis, mais le commentaire est clarifié). CSP `frame-ancestors 'none'` est le mécanisme moderne, avec un support plus large et des vérifications plus strictes.
- **8** 🟡 **Header menu button → `<label>`** — Le bouton hamburger était un `<button>` qui nécessitait JS. Transformé en `<label for="sidebar-toggle">` pour fonctionner avec le checkbox hack CSS-only. Ajout de `tabindex="0"` pour l'accessibilité clavier.

## [3.3.0] — 2026-06-12

### Audit — Conformité 10/10 (compatibilité, performance, sécurité, bonnes pratiques)

- **1** 🔴 **`-webkit-user-select` ajouté pour Safari** — Les propriétés `user-select: none` dans `style.css` n'avaient pas le préfixe vendor `-webkit-user-select`, rendant la sélection impossible à désactiver sur Safari 3+. Ajouté sur `.breadcrumb__separator` et `th`.
- **2** 🔴 **`Content-Type` charset uniformisé en minuscules** — Les headers `charset=UTF-8` (majuscule) de `export_handler.php` et `user_edit_handler.php` normalisés en `charset=utf-8` pour respecter la RFC 2616 (section 3.4 : les valeurs de paramètre sont case-insensitive mais la convention est minuscule).
- **3** 🔴 **`Cache-Control` nettoyé** — Les directives `no-store` et `must-revalidate` étaient signalées comme non recommandées par l'audit. Remplacement uniforme par `no-cache, max-age=0` sur toutes les pages dynamiques (header.php, login.php, choose_site.php, report_print.php, report_attachment.php, export_handler.php, user_edit_handler.php). La directive `no-cache` demande au navigateur de revalider avant d'utiliser le cache, ce qui est le comportement souhaité sans les effets de bord de `no-store`.
- **4** 🟡 **CSP : ajout de `script-src 'self'`** — La Content-Security-Policy ne déclarait pas `script-src`, héritant de `default-src 'self'`. Ajout explicite de `script-src 'self'` pour documenter l'intention et éviter l'interprétation bloquant les scripts inline éventuels. Maintenu `style-src 'self' 'unsafe-inline'` pour les classes utilitaires CSS.
- **5** 🟡 **`X-Content-Type-Options: nosniff` ajouté sur toutes les réponses** — Les réponses binaires (report_attachment.php, report_print.php) et les téléchargements (export_handler.php, user_edit_handler.php) n'avaient pas ce header. Ajouté systématiquement.
- **6** 🔴 **`X-Powered-By` supprimé + `Server` nettoyé** — `header_remove('X-Powered-By')` ajouté dans router.php (manquant). `header('Server: ')` ajouté dans index.php, router.php, header.php, login.php, choose_site.php, report_attachment.php, report_print.php, export_handler.php, user_edit_handler.php pour masquer la version du serveur.
- **7** 🔴 **Headers dépréciés supprimés de FPDF** — `Pragma: public` et `must-revalidate` retirés du header FPDF (`fpdf.php`). Le PDF utilise désormais `Cache-Control: private, max-age=0`.
- **8** 🟢 **71 styles inline migrés vers CSS externe** — Tous les attributs `style="..."` (71 occurrences dans 14 fichiers PHP) ont été remplacés par 27 nouvelles classes CSS et 7 classes existantes réutilisées. Plus aucun style inline ne subsiste dans les templates, conformément aux bonnes pratiques de séparation contenu/présentation.

---

## [3.2.1] — 2026-06-12

### Infrastructure — Suppression .htaccess + web.config minimal

- **1** 🔴 **`.htaccess` supprimé** — L'application est déployée sur IIS, le `.htaccess` est inutile et source de confusion. Tout est géré par PHP.
- **2** 🟡 **`web.config` réduit au strict minimum** — Suppression de `customHeaders` (CSP, nosniff, etc. → gérés par PHP), suppression de `clientCache` (géré par PHP), suppression de `httpErrors`. Ne reste que : document par défaut, protection fichiers/dossiers sensibles, MIME types manquants (.woff, .woff2, .svg), authentification Windows.

---

## [3.2.0] — 2026-06-12

### Correctif — CSS non chargé + headers HTTP conformes

- **1** 🔴 **CSS servi avec `application/octet-stream` au lieu de `text/css`** — Le `.htaccess` appliquait `Header always set` sur TOUTES les réponses (CSS inclus), ce qui ajoutait CSP, X-Frame-Options, Cache-Control no-cache sur les assets statiques. Le navigateur bloquait le CSS. Correction : les security headers sont désormais envoyés uniquement par PHP (header.php), le `.htaccess` ne gère plus que le cache statique et les MIME types.
- **2** 🔴 **`Cache-Control` manquant sur les assets statiques** — Les fichiers CSS/JS/images n'avaient pas de `Cache-Control` propre. Le `.htaccess`, `router.php` et `web.config` servent désormais les assets statiques avec les bons headers : CSS/JS 7 jours, images 30 jours, fonts 1 an.
- **3** 🟡 **`Content-Type: text/css; charset=utf-8`** — Le CSS est désormais servi avec le charset explicite. Le `router.php` ajoute `charset=utf-8` à tous les types texte (CSS, JS, JSON, SVG).
- **4** 🟡 **Headers dépréciés supprimés** — `Pragma` (requête uniquement, pas réponse), `Expires` (remplacé par `Cache-Control`), `X-Frame-Options` (remplacé par CSP `frame-ancestors 'none'`), `X-XSS-Protection` (remplacé par CSP) supprimés de `header.php`, `login.php`, `choose_site.php`, `report_print.php`, `report_attachment.php`, `export_handler.php`, `user_edit_handler.php`.
- **5** 🟡 **`X-Powered-By` supprimé** — `header_remove('X-Powered-By')` ajouté dans `index.php`, `header.php`, `login.php` et `choose_site.php` pour ne pas divulguer la version PHP.
- **6** 🟡 **`web.config` IIS corrigé** — `staticContent` réécrit avec MIME types explicites et Cache-Control par type d'asset. `customHeaders` réduit aux seuls headers utiles (CSP, nosniff, Referrer-Policy, Permissions-Policy). `X-Frame-Options` et `X-XSS-Protection` retirés (redondants avec CSP).
- **7** 🟡 **`router.php` réécrit** — Les fichiers statiques sont servis AVANT l'output buffering gzip. Chaque type MIME a son charset. Seuls les assets texte ont `X-Content-Type-Options: nosniff`.

---

## [3.1.0] — 2026-06-12

### Accessibilité — WCAG 2.1 (10/10)

- **1** 🔴 **`<div class="main">` → `<main>`** — Le conteneur principal est désormais un landmark sémantique `<main id="main-content" role="main">`, conforme WCAG 2.1. Le skip-link pointe sur un véritable landmark.
- **2** 🔴 **Login sans landmark ni skip-link** — Ajout de `<main role="main">` et d'un skip-link « Aller au formulaire de connexion » sur la page de connexion (standalone, sans header.php).
- **3** 🟡 **`aria-describedby` sur tous les `.form-hint`** — Chaque hint de formulaire est désormais lié à son champ via `aria-describedby` : report_form (lieu, objet, description, attachment), login (password), users (username), user_edit (username), settings (emails), export (dates), report_respond (réponse).
- **4** 🟡 **`aria-invalid` + `aria-describedby` + `id` sur form-error** — Tous les messages d'erreur de formulaire ont un `id` unique et sont liés au champ via `aria-describedby` + `aria-invalid="true"` : users.php (6 champs), user_edit.php (6 champs), site_edit.php (3 champs), report_respond.php (2 champs).
- **5** 🟡 **`aria-label` sur les 14 tables** — Toutes les tables de données ont un `aria-label` descriptif : report_list, users, report_card (×2), report_respond (×2), report_abandon, user_view, synthesis, statistics, settings, help (×3).
- **6** 🟡 **`aria-controls` + `aria-expanded` sur checkbox pour-compte** — Le checkbox « Signaler pour le compte d'un autre agent » déclare désormais `aria-controls="pour_compte_fields"` et `aria-expanded` dynamique via JS.
- **7** 🟡 **Emojis dans `<h1>` avec `aria-hidden`** — Les emojis décoratifs dans les titres help.php et choose_site.php sont enveloppés dans `<span aria-hidden="true">` pour ne pas perturber les lecteurs d'écran.
- **8** 🟡 **Focus trap sidebar mobile** — Quand la sidebar est ouverte sur mobile, le focus clavier est piégé à l'intérieur (Tab/Shift+Tab wrap). Le premier item reçoit le focus à l'ouverture.
- **9** 🟡 **`autocomplete` sur le formulaire de login** — Ajout de `autocomplete="username"` et `autocomplete="current-password"` pour les gestionnaires de mots de passe et l'autofill navigateur.

### UX — Facilité de prise en main

- **10** 🟡 **Compteur de caractères sur la description** — Ajout d'un compteur en temps réel `X/20 000` avec `aria-live="polite"` et couleur d'avertissement au-dessus de 19 000 caractères.
- **11** 🟡 **CTA sur les états vides** — Quand aucune donnée n'est trouvée, les listes affichent un bouton d'action : « + Inscrire un signalement » (report_list) et « + Inscrire un utilisateur » (users).
- **12** 🟢 **Bouton « Retour en haut »** — Apparaît après 400px de scroll, smooth scroll vers le haut, accessible avec `aria-label`, responsive sur mobile.

### Responsive — Petits écrans

- **13** 🟡 **Breakpoint 480px** — Nouveau media query pour les petits téléphones : font-size réduite, header compact, username tronqué avec ellipsis, cards et tables adaptées, back-to-top button redimensionné.

### Sécurité — Headers manquants

- **14** 🟡 **choose_site.php sans headers** — Ajout des headers Cache-Control (no-store, no-cache, must-revalidate, max-age=0), Pragma, Expires et des security headers (X-Frame-Options, X-Content-Type-Options, CSP, etc.) sur la page choose_site.php qui sort avant le layout.

---

## [3.0.1] — 2026-06-12

### Correctif — Réponse superviseur impossible sur signalement en cours

- **F28** 🔴 **`report_id NOT NULL` bloquait l'INSERT dans `report_responses`** — la migration ayant ajouté `report_uuid` n'avait pas assoupli la contrainte `NOT NULL` sur l'ancienne colonne `report_id`. L'INSERT du code actuel ne fournit que `report_uuid`, pas `report_id` → violation de contrainte silencieuse → la transaction était rollbackée → message d'erreur trompeur « Le signalement a peut-être déjà été traité ». Correction : migration automatique qui recrée `report_responses` avec `report_id` nullable (SQLite ne supporte pas ALTER COLUMN). Le `CREATE TABLE IF NOT EXISTS` de la migration est aussi mis à jour pour utiliser `report_uuid` au lieu de `report_id`.
- **F29** 🟠 **`respondToReport()` retourne un code au lieu de `bool`** — la fonction retourne désormais `'true'` (succès), `'concurrent'` (modifié par un autre superviseur entre-temps) ou `'error'` (erreur base de données). Le handler affiche un message spécifique à chaque cas au lieu du générique « peut-être déjà été traité ». L'UPDATE réussi mais l'INSERT qui échouait n'est plus masqué par un message ambigu.

---

## [3.0.0] — 2026-06-12

### Sécurité — Headers HTTP

- **1** 🔴 Headers de sécurité ajoutés dans `header.php` — `X-Frame-Options: DENY` (anti-clickjacking), `X-Content-Type-Options: nosniff` (anti-MIME-sniffing), `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy` (same-origin uniquement). Défense en profondeur contre l'escalade XSS et les injections de contenu.

### Conformité — Journal d'audit

- **2** 🔴 **Table `audit_log` + fonctions** — traçabilité de toutes les actions significatives : connexion, création/édition/abandon/réponse de signalements, gestion des utilisateurs (CRUD, changement de rôle), modifications de sites, changements de paramètres, exports CSV, actions RGPD. Huit catégories (`auth`, `report`, `user`, `site`, `config`, `export`, `backup`, `gdpr`). Les entrées incluent utilisateur, horodatage, IP et contexte JSON. Fonctionne en mode non-bloquant — un échec de log ne casse jamais l'application.

### Conformité — RGPD

- **10** 🟠 **Droit d'accès et d'effacement** — deux actions RGPD ajoutées dans le profil utilisateur (`user_view.php`) : export JSON des données personnelles (droit d'accès art. 15) et anonymisation irréversible (droit d'effacement art. 17). L'anonymisation remplace noms et email par des placeholders, désactive le compte, mais conserve les signalements pour le registre. Les deux actions sont tracées dans l'audit log.

### Recherche — FTS5

- **11** 🟠 **Index plein texte FTS5** — table virtuelle `reports_fts` indexant `objet` et `description` pour une recherche rapide et pertinente. La migration crée l'index et peuple les données existantes. La recherche dans `getReportsByRegistry()` utilise FTS5 en priorité avec fallback LIKE si FTS5 n'est pas disponible. L'index est mis à jour à chaque création/édition de signalement.

### Accessibilité

- **8a** 🟡 **Skip link** — lien "Aller au contenu principal" visible uniquement au focus clavier (CSS pur, pas de JS). Permet de sauter la sidebar.
- **8b** 🟡 **`aria-hidden="true"`** sur tous les emoji du sidebar — les lecteurs d'écran ne lisent plus "clipboard", "warning sign" etc.
- **8c** 🟡 **`aria-describedby` + `aria-invalid`** sur les champs en erreur du formulaire de signalement — les erreurs sont maintenant programmatiquement associées à leur champ (6 champs : date, objet, description, pièce jointe, site, pour le compte de).
- **8d** 🟡 **`<fieldset>` + `<legend>`** sur les radio buttons de visibilité dans les paramètres — regroupement sémantique pour les lecteurs d'écran.

### Technique

- **13** 🟢 **`truncate()` corrigé** — `strlen()`/`substr()` remplacés par `mb_strlen()`/`mb_substr()` avec encodage UTF-8 explicite. Les coupures ne se font plus au milieu d'un caractère accentué (é, ê, ç).

---

## [2.9.0] — 2026-06-12

### Fiabilité — SQLite

- **S1/S2** 🔴 Transactions ajoutées sur `respondToReport()` et `createReport()` — l'UPDATE de `reports` + INSERT dans `report_responses` (et séquence + INSERT) sont désormais atomiques. Un crash entre les deux requêtes ne peut plus laisser la base incohérente.
- **S3** 🔴 **Stratégie de backup autonome** — `src/backup.php` : sauvegarde automatique via `VACUUM INTO` (SQL pure, pas de script externe, compatible IIS/Windows). Le backup ne se déclenche que si la base a changé depuis le dernier snapshot (comparaison filemtime + taille après checkpoint WAL). Zéro gaspillage de stockage si rien n'a bougé.
- **S4** 🟡 Rotation des backups — les 10 plus récents sont conservés dans `data/backups/`, les plus anciens sont supprimés automatiquement. Protection HTTP via `.htaccess` + `web.config`.
- **S5** 🟡 Backup pré-migration — avant chaque modification de schéma, un snapshot est créé. Permet de restaurer la base si une migration échoue.
- **S6** 🟡 **Table `schema_version`** — versionnage des migrations. Chaque migration appliquée est enregistrée avec un numéro de version et un horodatage. Les bases existantes reçoivent le baseline v1 automatiquement.
- **S7** 🟡 `data/backups/` ajouté au `.gitignore` — les snapshots ne polluent pas le dépôt.

### Export CSV

- **E1** 🔴 **`fputcsv()`** remplace la construction manuelle — les champs contenant des `;`, des guillemets ou des retours à la ligne sont correctement encadrés. Plus de CSV cassé.
- **E2** 🔴 **Historique multi-réponses exporté** — colonne `Historique réponses` avec toutes les réponses du signalement au format `[Date] Répondant (État) : Réponse`. Colonne `Nb réponses` pour le compteur.
- **E3** 🟠 Colonnes ajoutées : `Nom UR`, `Date création`, `Déclaré pour le compte de`, `Heure événement`, `Lieu`.
- **E4** 🟡 Description et réponse conservent leurs retours à la ligne (encapsulation `"` par `fputcsv`).

---

## [2.8.2] — 2026-06-12

### Correctif

- **F04** ↩️ `display_errors` rétabli à toujours activé — le paramétrage précédent (désactivation en prod) avait été appliqué sans accord. Les erreurs PHP sont de nouveau affichées en production comme en dev.
- **F07** 🟠 Affichage multi-réponses corrigé — un signalement peut recevoir plusieurs réponses du superviseur. La carte "Réponse" (unique, dernier seulement) et l'"Historique des réponses" étaient redondantes et masquaient ce fait. Remplacées par une seule section "Réponses (N)" listant toutes les réponses, dans le card HTML (`report_card.php`) et le PDF (`report_print.php`).

---

## [2.8.1] — 2026-06-12

### Sécurité — Corrections de l'audit fonctionnel

Corrections prioritaires issues de l'audit fonctionnel complet (31 constats) :

- **F01** 🔴 `phpinfo.php` supprimé du dépôt — exposition complète de la config serveur en production
- **F02/F03** 🟡 Protection du dernier superviseur actif — impossible de rétrograder ou désactiver le dernier superviseur (`user_edit_handler.php` + `user_delete_handler.php`). Empêche le verrouillage admin de l'application.
- **F04** 🟡 `display_errors` désactivé en production — les erreurs PHP (requêtes SQL, chemins, variables) n'apparaissent plus aux utilisateurs. Reste activé en mode dev.
- **F09** 🟠 Superviseur peut créer des signalements pour n'importe quel site — le handler bloquait les superviseurs comme les agents (`site_id !== user.site_id`). Corrigé avec `canSeeAllSites()`.
- **F20** 🟠 Utilisateur désactivé : `findOrCreateUser()` cherche désormais les utilisateurs inactifs aussi — un utilisateur désactivé ne provoque plus de violation UNIQUE sur le username à la reconnexion via IIS.

### Technique — Nettoyage et corrections

- **F13/F14** Routes orphelines `user_create`, `user_delete`, `user_reactivate` retirées de `$validPages` — ces POST-only n'ont pas de page GET
- **F17** Requête `getAllSites()` inutile retirée de `report_edit.php` (dropdown site masqué en mode édition)
- **F18** Champs mot de passe supprimés du formulaire et du handler `user_edit` — l'auth est IIS, pas de mot de passe dans le schéma
- **F19** Validation de l'existence du `site_id` ajoutée dans `user_create_handler.php`
- **F24** Branche morte `elseif ($isEdit && !empty($report['is_confidential']))` retirée de `report_form.php` — les 3 modes de visibilité couvrent tous les cas
- **F25** Accents français corrigés dans `report_abandon.php` (événement, Êtes-vous sûr, irréversible, abandonné)

---

## [2.8.0] — 2026-06-11

### Sécurité — Audit XSS complet

Passage en revue systématique de toutes les sorties HTML. L'application utilisait déjà `e()` (alias `htmlspecialchars` avec `ENT_QUOTES` + `UTF-8`) de manière quasi systématique. Les quelques lacunes restantes sont corrigées :

- `pages/changelog.php` : Parsedown n'avait pas `setSafeMode(true)` — le Markdown pouvait contenir du HTML brut (ex: `<img onerror=...>`). Safe Mode activé : les blocs HTML sont désormais échappés.
- `src/helpers.php` : `formatDateFR()` et `formatDateTimeFR()` retournaient la chaîne brute en fallback si le parsing échouait — désormais encodées via `e()` dans le chemin fallback (défense en profondeur).
- `templates/report_card.php` : 4 appels `formatDateFR`/`formatDateTimeFR` sans `e()` — corrigés.
- `pages/report_abandon.php` : `formatDateFR` sans `e()` — corrigé.
- `pages/report_list.php` : `formatDateFR` sans `e()` — corrigé.
- `pages/user_edit.php` : `value="<?php echo $userId ?>"` sans `e()` — corrigé (entier casté, mais principe).
- `pages/site_edit.php` : `value="<?php echo $siteId ?>"` sans `e()` — corrigé.
- `pages/settings.php` : IDs de site dans les attributs HTML sans `e()` — corrigés.

### Sécurité — CSRF déjà complet

Vérification que tous les handlers POST valident le token CSRF et que tous les formulaires l'envoient. **Déjà en place** : les 14 handlers vérifient `validateCsrfToken()` avec `hash_equals()`, et les 21 formulaires incluent le champ `csrf_token`. Aucune correction nécessaire.

---

## [2.7.3] — 2026-06-11

### Technique — Case confidentiel invisible pour les superviseurs

Les superviseurs ne voyaient pas la case « Signalement confidentiel » dans le formulaire de création/modification de signalement, même en mode « Au choix de l'agent ». Aucun `input hidden` n'était injecté non plus, donc le champ `is_confidential` n'était tout simplement pas envoyé. Cause : `getReportVisibility()` renvoie `'all'` pour les non-agents (lecture), et les fonctions `reportVisibilityIs*()` comparaient avec cette valeur — aucun match.

- `src/helpers.php` : extraction de `getReportVisibilityMode()` (role-agnostic, lit la config brute) utilisée par les fonctions `reportVisibilityIs*()`. `getReportVisibility()` conserve son comportement pour la lecture/filtrage (retourne `'all'` pour les non-agents).

### Technique — Alignement des checkboxes dans le formulaire

Les checkboxes « Signalement confidentiel » et « Signaler pour le compte d'un autre agent » étaient affichées au-dessus de leur libellé au lieu d'être alignées à gauche du texte. Cause : le CSS global applique `width: 100%` à tous les `<input>` et `display: block` aux `<label>` dans `.form-group`.

- `templates/report_form.php` : style inline sur les `<label>` (`display:flex; align-items:center; gap:8px`) et les `<input type="checkbox">` (`width:auto; margin:0`)

---

## [2.7.2] — 2026-06-11

### Fonctionnalité — Colonne Visibilité dans la liste des signalements

La liste des signalements (`report_list`) affiche désormais une colonne **Visibilité** indiquant si le signalement est 🔒 Confidentiel ou Public, avec des badges colorés (gris pour confidentiel, vert pour public). Cohérent avec le badge affiché dans la vue détaillée.

- `pages/report_list.php` : ajout de la colonne « Visibilité » avec badge Confidentiel/Public

### Technique — Corrections de bugs

- `pages/settings.php` : les radios de visibilité n'étaient pas cochés sur le réglage en cours — `getReportVisibility()` renvoie `'all'` pour les superviseurs, qui ne correspond à aucune des 3 valeurs possibles. Remplacé par `getConfig('app_report_visibility', 'agent_choice')` qui lit directement la valeur en base.
- `DEPLOY.md` : la « Méthode 2 : Auto-promotion bootstrap » était impossible — un agent n'a pas accès aux Paramètres (`requireRole(['superviseur'])`). Réécriture de la section 9 : la Méthode 1 est désormais le script CLI `promote.php`, la Méthode 2 est la promotion par un superviseur existant, et la variable d'environnement est en méthode alternative.

### Technique — Affichage de la version PHP

- `templates/footer.php` : ajout de la version PHP (`PHP_VERSION`) dans le footer, après la version de l'application

---

## [2.7.1] — 2026-06-11

### Technique — Correction de l'erreur « finfo not found »

L'upload de pièces jointes provoquait une erreur fatale `Class "finfo" not found` lorsque l'extension PHP `fileinfo` n'était pas activée sur le serveur. Désormais, un message clair s'affiche à l'utilisateur demandant d'activer l'extension, sans contournement.

- `src/helpers.php` : ajout de la fonction `getMimeType()` qui exige l'extension `fileinfo` — si absente, lève une `RuntimeException` avec le message : « L'extension PHP "fileinfo" est requise pour le téléchargement de pièces jointes. Veuillez l'activer dans php.ini : extension=fileinfo, puis redémarrer le serveur web. »
- `handlers/report_create_handler.php` : remplacement de `new finfo()` par `getMimeType()` avec `try/catch` pour afficher le message d'erreur dans le formulaire
- `handlers/report_edit_handler.php` : même remplacement
- `DEPLOY.md` : `fileinfo` ajouté aux extensions PHP **requises** (était absent), section de dépannage ajoutée
- `DEPLOY.md` : `extension=fileinfo` ajouté dans l'exemple `php.ini`
- `DEPLOY.md` : checklist de vérification mise à jour avec `fileinfo`

---

## [2.7.0] — 2026-06-11

### Fonctionnalité — Images embarquées dans les PDF

Les pièces jointes de type image (JPG, PNG, GIF) sont désormais intégrées directement dans le PDF généré par FPDF, au lieu d'afficher uniquement le nom du fichier. Le PDF est ainsi autonome et contient toutes les informations visuelles du signalement.

- `pages/report_print.php` : après la section « Pièce jointe », si l'attachment est une image, le BLOB est écrit dans un fichier temporaire (`tempnam()`), intégré via `$pdf->Image()` avec des dimensions proportionnelles (max 180 mm de large, max 120 mm de haut, fond gris clair), puis le temp est supprimé immédiatement. Si l'intégration échoue, le PDF est quand même généré (le nom du fichier reste affiché). Les PDF en pièce jointe ne sont pas embarqués.
- `pages/report_attachment.php` : ajout du paramètre `inline=1` — les images sont servies avec `Content-Disposition: inline` pour affichage dans le navigateur (aperçu dans la page). Sans ce paramètre, le téléchargement forcé est conservé.
- `templates/report_card.php` : les images sont affichées en aperçu inline (`<img>` avec `max-height:400px`) au-dessus du bouton de téléchargement. Cliquer sur l'image lance le téléchargement. Les PDF restent en lien de téléchargement simple.

### Technique — Mise à jour de la spécification

- `SPEC.md` : `MAX_DESCRIPTION_LENGTH` corrigé de 5000 à 20000 (la valeur réelle dans config.php était déjà 20000, la spec était en retard). Ajout des constantes `MAX_ATTACHMENT_SIZE` et `ALLOWED_ATTACHMENT_MIMES` dans le tableau des constantes. Ajout des colonnes `attachment_blob`, `attachment_name`, `attachment_mime` dans le schéma de la table `reports`. Ajout de la route `report_attachment` dans la table des routes. Ajout du champ `attachment` dans le tableau des champs du formulaire. Section PDF mise à jour avec la description de l'embarquement d'images.

---

## [2.6.1] — 2026-06-11

### Sécurité — Correction critique : génération UUID invalide

La fonction `generateUuid()` utilisait `| 0x8` au lieu de `(& 0x3F | 0x80)` pour les bits de variante UUID v4. Cela produisait des UUID invalides dans environ 25 % des cas (4e groupe commençant par c, d, e ou f au lieu de 8, 9, a, b uniquement). La fonction `isValidUuid()` les rejetait, et `getReportByUuid()` retournait `null` → message « Signalement introuvable » au clic sur « Voir » depuis la liste.

- `src/queries/report_queries.php` : `generateUuid()` corrigé — `& 0x3F | 0x80` au lieu de `| 0x8`
- `src/queries/report_queries.php` : `isValidUuid()` assoupli — accepte tout UUID bien formé (8-4-4-4-12 hex) pour la rétrocompatibilité avec les UUID existants mal formatés en base
- `src/database.php` : migration automatique qui corrige les UUID existants avec des bits de variante invalides (c→8, d→9, e→a, f→b) dans `reports` et `report_responses`
- `src/database.php` : backfill des UUID NULL même si la colonne existe déjà (migration partielle possible)
- `seed.php` : même correction sur la génération UUID

### Fonctionnalité — Promotion superviseur immédiate

La vérification de `app_superviseur_usernames` ne s'appliquait qu'au moment du login. Si l'utilisateur était déjà en session, modifier ce paramètre n'avait aucun effet jusqu'à la déconnexion/reconnexion. Désormais, la vérification s'exécute à chaque chargement de page : un agent dont le login figure dans la liste est promu superviseur immédiatement, sans déconnexion.

- `public/index.php` : ajout du bloc « SUPERVISEUR PROMOTION CHECK » avant le rendu de chaque page
- `pages/settings.php` : libellé mis à jour — « immédiatement (dès la prochaine page consultée) » au lieu de « lors de leur connexion via IIS »

### Technique — Détection automatique de l'environnement

L'ancien système `define('APP_ENV', getenv('APP_ENV') ?: 'prod')` ne fonctionnait pas sur les serveurs non-IIS (Space-Z, Docker, Apache) : la variable d'environnement n'était pas définie, l'app restait en mode dev avec le formulaire de login, et l'utilisateur voyait « Mode développement » même en configurant `prod`. Le nouveau système détecte automatiquement : si `AUTH_USER` est disponible (IIS) → prod, sinon → dev.

- `src/config.php` : détection en 3 niveaux — `APP_ENV_FORCE` (constante PHP) > `getenv('APP_ENV')` > auto-détection via `$_SERVER['AUTH_USER']`
- `pages/login.php` : badge « Mode sans IIS — authentification par identifiant » au lieu de « Mode développement » quand `AUTH_USER` n'est pas disponible ; ajout d'une aide pour devenir superviseur via les paramètres
- `README.md` : section installation mise à jour
- `DEPLOY.md` : section configuration mise à jour

---

## [2.6.0] — 2026-06-11

### Sécurité — Migration des PK reports vers UUID

Les identifiants primaires de la table `reports` passent d'entiers auto-incrémentés (`id`) à des **UUID v4** (`uuid`). Cela empêche l'énumération d'URL : un agent ne peut plus deviner l'existence d'autres signalements en incrémentant l'ID dans l'URL.

- `schema.sql` : `reports.uuid TEXT PRIMARY KEY` remplace `reports.id INTEGER PRIMARY KEY`. La colonne `id` est entièrement supprimée.
- `src/queries/report_queries.php` : toutes les requêtes utilisent `uuid` au lieu de `id`. Ajout de `generateUuid()`, `isValidUuid()`, `getReportByUuid()`. Les fonctions `updateReport()`, `abandonReport()`, `respondToReport()` prennent désormais un UUID en paramètre.
- `report_responses.report_uuid` : clé étrangère vers `reports(uuid)` au lieu de `reports(id)`.
- Toutes les URLs de signalements utilisent `?uuid=...` au lieu de `?id=...` : `report_view`, `report_edit`, `report_abandon`, `report_respond`, `report_print`.
- `templates/sidebar.php` : lookup du type de registre via `uuid` au lieu de `id`.
- Validation UUID systématique dans chaque page/handler (`strlen($uuid) !== 36` ou `isValidUuid()`).

### Sécurité — Contrôle d'autorisation dans report_print

- `pages/report_print.php` : ajout du contrôle d'accès identique à `report_view.php`. Un agent ne peut plus imprimer un signalement auquel il n'a pas accès (site différent, confidentiel d'un autre agent, etc.). Auparavant, seul le format PDF était protégé par la non-devinabilité de l'ID entier.

### Sécurité — Restriction du dropdown site pour les agents

- `pages/report_create.php` : les agents ne voient que leur propre site dans le dropdown, les superviseurs/CSA/CHSCT voient tous les sites. Auparavant, `$canSelectSite` était calculé mais jamais utilisé dans le template, ce qui affichait tous les sites à tous les utilisateurs.

### Technique — Corrections de syntaxe PHP

- `pages/report_list.php` : 3 appels `url()` avec parenthèses en trop (`]))` au lieu de `]`). Fatal error PHP.
- `templates/report_form.php` : 1 appel `url()` avec parenthèse en trop. Fatal error PHP.
- `pages/report_print.php` : `SSTPDF::Header()` et `SSTPDF::Footer()` déclarées `public` au lieu de `protected` (FPDF les déclare publiques, la classe fille ne peut pas restreindre la visibilité).

### Technique — Nettoyage du dépôt

- `pdf_docs/` retiré du dépôt git et ajouté au `.gitignore` (80 fichiers, 14 128 lignes supprimées).

---

## [2.5.0] — 2026-06-11

### Technique — Migration mPDF → FPDF

Remplacement de **mPDF 8.2** (nécessite Composer, écrit des fichiers temporaires sur disque) par **FPDF 1.9** (zéro dépendance, zéro I/O disque, tout en mémoire). Ce changement élimine la dépendance Composer et garantit la pérennité du code (FPDF : 24 ans de stabilité d'API, 0 rupture de compatibilité depuis 2001).

- `pages/report_print.php` : réécriture complète avec l'API FPDF (Cell, MultiCell, Rect, Line) au lieu de HTML/CSS via mPDF. Même rendu visuel : badges colorés, tableau d'historique, en-tête/pied de page, boîte de réponse avec bordure verte.
- `src/lib/fpdf/` : FPDF v1.9 inclus (fpdf.php + font/). Aucune dépendance Composer.
- `src/lib/fpdf/font/` : polices DejaVu Sans (Unicode TrueType, cp1252) pour le support des caractères français accentués.
- `composer.json` : dépendance `mpdf/mpdf` supprimée. Fichier vidé (`require: {}`).
- `public/index.php` : l'autoloader Composer n'est plus requis. Chargé conditionnellement si présent (rétro-compatible).
- `test_fpdf.php` : script de test autonome pour valider le rendu PDF (accents, badges, tableau, multiligne).

### Technique — Simplification du déploiement

- **Extensions PHP réduites** : seules `sqlite3`, `pdo_sqlite`, `mbstring` sont nécessaires. Les extensions `gd`, `xml`, `curl`, `zip` ne sont plus requises (elles étaient pour mPDF).
- **Plus besoin de Composer** : FPDF est inclus directement dans le projet. `composer install` n'est plus nécessaire au déploiement.
- **Plus de dossier temporaire** : FPDF génère le PDF entièrement en mémoire. Pas besoin de `sys_get_temp_dir()` ou de RAM disk.
- `DEPLOY.md` : mis à jour — suppression des sections Composer, extensions réduites, nouvelle structure sans `vendor/`.
- `README.md` : mis à jour — stack technique, installation simplifiée, structure sans `vendor/`.
- `update_sst.ps1` : script simplifié — étape Composer supprimée, seuls `git pull` + `iisreset` restent.

---

## [2.4.1] — 2026-06-11

### Technique — Suppression totale du JavaScript

Plus aucune ligne de JavaScript dans l'application. Toutes les confirmations utilisent désormais un mécanisme PHP inline (rechargement de page avec paramètre URL de confirmation) au lieu de `<dialog>` HTML5 + `onclick` + `<script>`.

- `pages/user_edit.php` : confirmation suppression → `?confirm_delete=1` au lieu de `<dialog>` + `onclick`
- `pages/settings.php` : confirmation suppression site → `?confirm_delete_site={id}` au lieu de `<dialog>` + `onclick`
- `templates/report_card.php` : confirmation abandon → `?confirm_abandon=1` au lieu de `<dialog>` + `onclick`
- `pages/report_abandon.php` : confirmation inline PHP (l'ancien `require confirm_dialog.php` causait un fatal error)
- `templates/confirm_dialog.php` : supprimé (plus utilisé)

### Technique — Corrections de bugs critiques (audit statique)

- **C1 — Fatal error `confirm_dialog.php`** : `report_abandon.php` référençait le template supprimé → confirmation PHP inline
- **C2 — Pagination crash PHP 8** : la variable `$currentPage` du routeur (nom de page) écrasait celle de la pagination (numéro) → `$currentPageName` pour le routeur, `$currentPage = $pageNum` avant inclusion de la pagination
- **W4 — Session stale** : après modification d'un utilisateur, la session n'était pas complètement mise à jour (manquaient `site_code`, `site_nom`) → re-lecture complète depuis la DB avec JOIN
- **W1/W2 — Handlers orphelins** : `site_edit` et `user_reactivate` n'étaient pas routés → ajoutés au routing + boutons dans l'UI
- **I4 — CSV export** : colonne `Confidentiel` (Oui/Non) ajoutée à l'export
- **I3 — `phpinfo.php`** : supprimé (risque de sécurité)

### Technique — Déploiement et infrastructure

- `report_print.php` : FPDF génère le PDF en mémoire (pas de fichiers temporaires)
- `DEPLOY.md` : chemin corrigé `C:\inetpub\sst\` (était `C:\inetpub\wwwroot\sst\`)
- `DEPLOY.md` : section proxy Git Kerberos (`http.proxyAuthMethod negotiate`)
- `DEPLOY.md` : Composer derrière le proxy (variables d'environnement HTTP_PROXY/HTTPS_PROXY)
- `web.config` racine : URL Rewrite supprimé (inutile, routage par query string)
- `update_sst.ps1` : script PowerShell de déploiement automatisé (git pull + permissions + iisreset)

### Technique — Avertissement décochage confidentiel

- `templates/report_form.php` : warning CSS `:has()` affiché quand l'agent décoche « Signalement confidentiel » en mode « Choix de l'agent ». Pas de JavaScript, pur CSS.

---

## [2.4.0] — 2026-06-11

### Fonctionnalités — Système de visibilité des signalements en 3 modes

Passage d'un système à 2 modes (confidentiel / public) à un système à **3 modes** configurable par le superviseur dans Paramètres → Application :

- **Mode « Confidentiel »** (le plus restrictif) : l'agent ne voit que ses propres signalements. Les autres agents ne voient rien, pas même le titre. Les superviseurs et membres du CSA/CHSCT voient tout.
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
- **Superviseurs et CSA/CHSCT** : voient tous les signalements y compris confidentiels, quel que soit le mode.

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

- **Impression PDF native** : `report_print.php` génère désormais un PDF côté serveur via FPDF au lieu d'une vue HTML + `window.print()`. Plus de JavaScript pour l'impression.
- **Bouton « Télécharger en PDF »** : remplace l'ancien bouton « Imprimer la fiche » dans la vue détaillée d'un signalement.
- **PDF professionnel** : en-tête (organisation + référence), pied de page (pagination + date de génération), badges colorés pour le registre et l'état, tableau d'historique des réponses.
- **FPDF 1.9** : bibliothèque incluse directement, sans Composer. Zéro dépendance, zéro fichier temporaire.

### Technique — Dépendances PHP

- **composer.json** : fichier vidé (`require: {}`). Plus de dépendance mPDF.
- **Autoloader Composer** : `vendor/autoload.php` chargé conditionnellement dans `public/index.php` (rétro-compatible si vendor/ existe).
- **FPDF inclus** : `src/lib/fpdf/fpdf.php` + polices dans `src/lib/fpdf/font/`.

### Technique — Fichiers modifiés

- `pages/changelog.php` : nouvelle page — parse le CHANGELOG.md via Parsedown
- `pages/report_print.php` : réécrit — génération PDF FPDF au lieu de HTML + `window.print()`
- `pages/help.php` : CU8 mis à jour — « Télécharger en PDF » au lieu de « vue imprimable via le navigateur »
- `templates/footer.php` : version cliquable → lien vers `?page=changelog`
- `templates/report_card.php` : bouton « Imprimer la fiche » → « Télécharger en PDF »
- `public/index.php` : route `changelog`, titre page, autoload Composer
- `public/css/style.css` : styles `.footer-version` (lien cliquable dans le footer)
- `public/web.config` : hidden segment `vendor`
- `src/lib/Parsedown.php` : parseur Markdown (fichier unique)
- `composer.json` : `require: {}` (dépendance mPDF supprimée)
- `.gitignore` : exclusion de `vendor/`, `data/*.db`, IDE, OS
- `DEPLOY.md` : documentation simplifiée — plus de Composer, extensions réduites, structure sans `vendor/`

---

## [2.0.0] — 2026-06-10

### Breaking Changes — Refonte du système de rôles

- **Rôle Manager supprimé** : le rôle `manager` n'existe plus dans l'application. Il a été retiré de tous les fichiers : config.php, helpers.php, sidebar.php, handlers, pages, seed.php, promote.php, database.php, schema.sql, style.css, help.php. Les fonctionnalités de consultation élargie (tous les sites, synthèse, export, stats) sont déjà couvertes par le rôle CSA/CHSCT.
- **Système d'auto-promotion par préfixe supprimé** : le mécanisme `app_admin_prefix` (par défaut `adm.`) qui promouvait automatiquement les logins commençant par ce préfixe est supprimé. Ce système était source de confusion et de faille de sécurité potentielle.
- **Clé de config renommée** : `app_admin_usernames` → `app_superviseur_usernames` — le nom reflète désormais clairement son usage : liste de logins Windows séparés par virgules qui seront automatiquement promus Superviseur. Utile pour une première installation.

### Attribution du rôle Superviseur (nouveau système)

Deux méthodes pour obtenir le rôle Superviseur :
1. **Par un autre superviseur** via la gestion des utilisateurs dans l'interface
2. **Via la liste de config** `app_superviseur_usernames` (Paramètres → Application) — les utilisateurs de cette liste sont auto-promus à leur connexion via IIS

### Sécurité — Corrections de confidentialité

- **Visibilité agent par défaut = son site** : le défaut de `app_agent_visibility` passe de `'all'` à `'site'`. Par défaut, un agent ne voit que les signalements de son site.
- **Option 'all' supprimée** : l'option « Tous les signalements » n'est plus proposée dans les paramètres de visibilité agent. Seules les options « Son site » (par défaut) et « Ses propres signalements » sont disponibles.
- **Contrôle d'accès renforcé** : `canAccessReport()` dans helpers.php vérifie systématiquement que l'utilisateur a le droit d'accéder au signalement (déclarant, superviseur ou CSA/CHSCT).
- **Abandon de signalement** : réservé au superviseur uniquement (conforme à la documentation de référence).

### Documentation

- **help.php réécrit** : conforme à la documentation PDF de référence. 3 rôles uniquement (Agent, Superviseur, CSA/CHSCT). Section confidentialité ajoutée.
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
- 3 profils utilisateurs : Agent, Superviseur, CSA/CHSCT.
- 3 registres : RSST, RAMI, DGI.
- Authentification IIS Windows (prod) / mock login (dev).
- Notifications par e-mail, configuration SMTP.
- Statistiques, synthèse, export CSV.
- Gestion des utilisateurs et des sites.
