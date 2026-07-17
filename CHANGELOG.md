# Changelog — Application SST DREETS BFC

Toutes les modifications notables de ce projet sont documentées dans ce fichier.


## [3.41.0] — 2026-07-17

### Pipeline qualité — Gate parallélisée + E2E Playwright Firefox

- **1** 🔴 **Gate parallélisée** — PHPStan + PHPUnit + E2E s'exécutent en parallèle (Start-Job / background bash). Le lint PHP reste séquentiel. (`update_sst.ps1`)
- **2** 🔴 **E2E Playwright Firefox** — 15 specs Playwright intégrés au gate + pre-push hook. Détection auto Python (préféré) ou npx. Config chromium → Firefox. (`playwright.config.js`)
- **3** 🔴 **Gate fail-closed** — La gate plante si un outil manque (PHPStan/PHPUnit/Playwright) avec le message d'installation explicite. (`update_sst.ps1`)
- **4** 🔴 **Fatal error `getSettings()`** — `NotificationRepository::getSettings()` remplacé par `findAll()`. (`pages/settings.php`)
- **5** 🟡 **Word cloud tous profils** — Suppression du gate `if ($rsstCount > 0)`. Tests non-régression (8 tests). (`pages/home.php`, `tests/unit/WordCloudRegressionTest.php`)

### Refactoring — Cast int/string fixes (69 erreurs corrigées)

- **6** 🔴 **user_view.php** — 24 erreurs cast → 0. Variables user typées extraites (`$userPrenom`, `$userNom`, etc.). (`pages/user_view.php`)
- **7** 🔴 **users.php** — 17 erreurs cast → 0. `@var` intermédiaires pour `$_GET` + variables user dans foreach. (`pages/users.php`)
- **8** 🟡 **user_edit.php** — 7 erreurs cast → 0. (`pages/user_edit.php`)
- **9** 🟡 **AccessService.php** — 12 erreurs cast → 0. Variables intermédiaires pour `$report`/`$user` offsets. (`src/Services/AccessService.php`)
- **10** 🟡 **FormattingService.php** — 9 erreurs cast → 0. `@var string` après null check. (`src/Services/FormattingService.php`)

### Refactoring — Nettoyage `mixed` PHPStan (session complète)

Baseline PHPStan : **950 → 669 erreurs** (-281, -29.6%). 857 tests, 1542 assertions.

- **6** 🔴 **Container `@template T`** — `@template T` / `@param class-string<T>` / `@return T` sur `get()`. (`src/Container/Container.php`)
- **7** 🔴 **Repositories guards** — `is_array()` sur tous les `fetch()`/`fetchAll()` des 5 repositories. (`src/Repository/*.php`)
- **8** 🔴 **DTOs typés** — `@param array<string, string>` sur `fromPost()`/`fromGet()`. (`src/DTO/*.php`)
- **9** 🔴 **Handlers `@var`** — `@var array<string, string> $_POST` sur 19 handlers. (`handlers/*.php`)
- **10** 🟡 **Templates + Help + Pages `@var`** — Annotations scope sur ~20 fichiers. (`templates/*.php`, `pages/help/*.php`, `pages/*.php`)
- **11** 🟡 **Services/Helpers annotations** — `missingType.iterableValue` sur ~40 fichiers. (`src/**/*.php`)
- **12** 🟡 **Tests invariant** — 22 tests vérifiant les types de retour. (`tests/unit/RepositoryInvariantTest.php`)


## [3.40.0] — 2026-07-17

### Refactoring — Nettoyage `mixed` PHPStan (3 phases)

Réduction de la dette technique `mixed` sur le pipeline d'analyse statique. Le baseline PHPStan passe de **950 à 859 erreurs** (-91), 827 tests passent, PHPStan level 10 propre.

- **1** 🔴 **Container `@template T`** — `Container::get()` utilise désormais un template PHPStan (`@template T` / `@return T`) avec `class-string<T>` pour inférer le type de retour. Les propriétés `$factories` et `$instances` sont typées. Tous les appels `$container->get(Foo::class)` retournent maintenant le bon type au lieu de `mixed`. (`src/Container/Container.php`)
- **2** 🔴 **Repositories — guards `is_array()`** — Tous les `fetch()`, `fetchAll()`, et `query()` des 5 repositories (Report, User, Site, Notification, Stats) sont protégés par des garde `is_array()` ou `is_array($stmt) !== false` avant utilisation. (`src/Repository/*.php`)
- **3** 🔴 **DTOs — typage `fromPost()`** — Les méthodes `fromPost()` et `fromGet()` de tous les DTOs sont typées avec `@param array<string, string>`. (`src/DTO/*.php`)
- **4** 🔴 **Bugs corrigés** — Garde `is_array($user)` ajoutée dans 3 handlers. `is_array()` redondant supprimé. Casts `mixed→string` sécurisés. (`handlers/*.php`, `src/Repository/ReportRepository.php`)
- **5** 🟡 **Baseline nettoyé** — 950 → 859 erreurs. (`phpstan-baseline.neon`)


## [3.39.0] — 2026-07-15

### Correction — Fatal error buildRegistryCards()

- **1** 🔴 **helpers.php require manquant** — Ajout de `require_once` pour `registry_card_renderer.php` dans `src/helpers.php`. Le fichier existait mais n'était jamais chargé en production (les tests le voyaient car `tests/bootstrap.php` le chargeait manuellement). (`src/helpers.php`)

### Prévention — Gate qualité + tests structurels

- **2** 🔴 **HelpersBootstrapTest** — Nouveau test qui vérifie que chaque fichier dans `src/helpers/` est requis par `src/helpers.php`. Empêche les fichiers orphelins d'arriver en prod. (`tests/unit/HelpersBootstrapTest.php`)
- **3** 🔴 **RegistryCardRendererTest** — 20 tests pour `buildRegistryCards()`, `renderRegistryCards()`, `renderRegistryCard()`, `getRegistryIcon()`. Teste le pipeline complet build→render, le pluriel/singulier, l'escaping HTML, et la présence des clés requises. (`tests/unit/RegistryCardRendererTest.php`)
- **4** 🟡 **update_sst.ps1 gate** — Ajout de la gate qualité au script de mise à jour : lint PHP (incremental, parallèle PS7+), PHPStan niveau 10, PHPUnit. Rollback automatique si la gate échoue. Hook pre-push inline. (`update_sst.ps1`)
- **5** 🟡 **Nettoyage bootstrap tests** — Suppression du `require_once` manuel de `registry_card_renderer.php` dans `tests/bootstrap.php` (le fichier est maintenant chargé via `helpers.php`).

### Nettoyage — Code mort supprimé

- **5** 🟡 **Fichiers orphelins** — Suppression de `src/helpers/rendering.php` (doublon de formatting.php), `src/helpers_bootstrap.php` (jamais requis), `templates/confirm_dialog.php` (remplacé par inline), `tools/_fix_repo.py` (chemin hardcodé autre dev), `fix_choose_site.py`, `fix_user_repo.py`, `diff.patch`.
- **6** 🟡 **phpstan-baseline.neon** — Nettoyage des entries pour les fichiers supprimés.
- **7** 🟡 **.gitignore** — Ajout de `data/infection-tmp/` (fichiers temporaires Infection PHP).


## [3.38.0] — 2026-07-12

### Corrections — Ultrareview 13 issues

- **1** 🔴 **audit_log target_uuid** — Ajout colonne `target_uuid TEXT` à la table `audit_log` pour stocker l'UUID des signalements (les entrées d'audit avaient toujours `target_id = 0` car la table `reports` utilise `uuid` comme clé primaire). 6 handlers mis à jour. (`src/audit.php`, `src/migration_columns.php`, `schema.sql`)
- **2** 🔴 **Export CSV mémoire** — Remplacement de `stream_get_contents()` par `fpassthru()` pour un streaming O(1) au lieu de charger tout le fichier en mémoire (risque OOM avec 50k lignes). (`handlers/export_handler.php`)
- **3** 🔴 **Requêtes DB dans template** — Déplacement de `getLinkedAgents()`/`getPendingInvites()` du template `report_card.php` vers le contrôleur `report_view.php` (élimine le risque N+1 et la violation de couche). (`templates/report_card.php`, `pages/report_view.php`)
- **4** 🔴 **FormattingService 30x** — Un seul `$fmt` remplace 30+ instanciations de `FormattingService` par rendu de template. (`templates/report_card.php`)
- **5** 🔴 **WAL checkpoint** — Suppression du checkpoint forcé de `getDbFingerprint()` ; fingerprint inclut maintenant le fichier `-wal` pour détecter les changements sans I/O disque par requête. (`src/backup.php`)
- **6** 🔴 **Constantes état** — Remplacement de littéraux `'traite'`/`'abandonne'` par `ETAT_TRAITE`/`ETAT_ABANDONNE` dans `ReportService::reopen()`. (`src/Services/ReportService.php`)
- **7** 🟡 **CSRF choose_site** — Ajout de `validatePostRequest()` au handler POST `choose_site` qui contournerait la chaîne de middleware CSRF. (`public/index.php`)
- **8** 🟡 **Domaine email édition** — Validation du domaine des emails rattachés ajoutée au handler d'édition (fail-closed si email déclarant manquant). (`handlers/report_edit_handler.php`)
- **9** 🟡 **Injection CRLF SMTP** — Blocage des adresses email contenant `\r\n` dans `sendViaSMTP()` + sanitisation de `$appName` dans les en-têtes. (`src/mail.php`)
- **10** 🟡 **Précédence opérateur** — Correction du bug de précédence `&&`/`||` dans la logique `display_errors` (`!defined('DEV_MODE') || !DEV_MODE` entre-parenthèses). (`public/index.php`)
- **11** 🟡 **Audit UUID lookup** — `getAuditLogForTarget()` utilise `is_numeric()` pour distinguer UUIDs des IDs numériques HTTP. (`src/audit.php`)


## [3.37.0] — 2026-07-11

### Technique — Migration handlers report en thin controllers

- **1** 🔴 **report_create_handler** — Réécrit en thin controller : `CreateReportCommand::fromPost()` + `ReportService::create()`. Validation site/emails liés conservée dans le handler (domain-specific). (202 → 117 lignes, -42%)
- **2** 🔴 **report_edit_handler** — Réécrit en thin controller : `UpdateReportCommand::fromPost()` + `ReportService::update()`. Validation champs + RAMI ajoutée. (140 → 97 lignes, -31%)
- **3** 🔴 **report_reopen_handler** — Réécrit en thin controller : nouveau DTO `ReopenReportCommand` + `ReportService::reopen()` + `ReportRepository::reopen()` (transaction SQL). (136 → 77 lignes, -43%)
- **4** 🟡 **ReopenReportCommand DTO** — Nouveau DTO pour la réouverture de signalement. (`src/DTO/ReopenReportCommand.php`)
- **5** 🟡 **ReportService::reopen()** — Méthode ajoutée avec validation métier (état vérifiable, rôle superviseur/CHSCT). (`src/Services/ReportService.php`)
- **6** 🟡 **ReportRepository::reopen()** — Transaction SQL : historique état + update etat + insertion réponse. (`src/Repository/ReportRepository.php`)


## [3.36.0] — 2026-07-10

### Technique — Autoload custom + Migration OOP handlers/pages/templates

- **1** 🔴 **Autoload PSR-4 custom** — Nouveau fichier `src/autoload.php` avec un autoloader `spl_autoload_register` pour les classes `App\` + chargement ordonné des fichiers procéduraux (helpers, config, session, auth, queries). Supprime la dépendance à Composer en production (`vendor/` n'est plus nécessaire). (`src/autoload.php`)
- **2** 🔴 **Migration OOP handlers/pages/templates** — 32 fichiers migrés : les pages et templates utilisent désormais les services OOP (FormattingService, AccessService, SessionService, ConfigService, CryptoService, HttpService) via le DI Container au lieu des appels directs aux fonctions globales. (`pages/`, `templates/`)
- **3** 🟡 **SessionService singleton** — Ajout de la méthode `getInstance()` à `SessionService` pour un accès centralisé hors du DI Container (pages/templates). (`src/Services/SessionService.php`)
- **4** 🟡 **update_sst.ps1** — `composer install` complet si `vendor/` absent, clairage opcache. (`update_sst.ps1`)
- **5** 🟢 **AGENTS.md** — Instructions ajoutées : tests obligatoires avant push, interdiction de modifier `git config --global`. (`AGENTS.md`)


## [3.35.0] — 2026-07-09

### Technique — Refactoring OOP complet + modernisation

- **1** 🔴 **Services OOP** — 7 nouveaux services créés : `AccessService`, `ConfigService`, `CryptoService`, `FormattingService`, `HttpService`, `AssetService`, `SessionService`. Chaque service encapsule une responsabilité unique avec un singleton pour le cache. (`src/Services/`)
- **2** 🔴 **Repositories OOP** — 3 nouveaux repositories : `SiteRepository`, `NotificationRepository`, `StatsRepository`. Les 12 fichiers `queries/*.php` déléguent désormais aux repositories. (`src/Repository/`)
- **3** 🔴 **DI Container** — `bootstrap_services.php` connecte tous les services et repositories au conteneur d'injection de dépendances. (`src/bootstrap_services.php`)
- **4** 🔴 **PHPUnit 13** — Upgrade de PHPUnit 11 à 13 (PHAR dans `~\scoop\shims\`). `composer.json` nettoyé des outils dev en faveur des PHAR.
- **5** 🔴 **Rector PHP 8.3** — 71 fichiers modernisés : `readonly` properties, `str_contains`/`str_starts_with`, arrow functions, `#[Override]`, first-class callables.
- **6** 🟡 **Dead code supprimé** — `escaping.php`, `badges.php`, `wordcloud.php` (doublons de formatting.php), `auth_provision.php` (dead code).
- **7** 🟡 **Middleware casing** — Renommage `src/middleware/` → `src/Middleware/` pour conformité PSR-4.
- **8** 🟡 **update_sst.ps1** — Ajout de `composer install` après git pull pour régénérer l'autoload.
- **9** 🟢 **AGENTS.md** — Instructions ajoutées : skills `using-superpowers`, outils PHP en PHAR scoop shims.


## [3.34.0] — 2026-07-08

### Améliorations

- **1** 🔴 **Tests middlewares complétés** — 31 tests (40 assertions) pour CsrfMiddleware (8), RoleMiddleware (8), AuthMiddleware (6) et Pipeline (9). Le helper `middleware_runner.php` exécute les middlewares en subprocess pour gérer les `exit()` de `redirect()`. Couverture complète des cas happy-path et error (token CSRF manquant, rôle incorrect, non-authentifié, chaînage pipeline).

- **2** 🟡 **Nettoyage fichiers de test** — Suppression des fichiers temporaires de debug (csrf_single.txt, middleware_test_all.txt, etc.) et ajout de `middleware_runner.php` au dépôt.


## [3.33.0] — 2026-07-08

### Améliorations

- **1** 🔴 **Analyse qualité complète** — PHPStan level 9 : 330 erreurs (principalement warnings de typage `mixed`, pas de bugs réels). Code coverage : 18.39% global (100% Container/Event, 84% Router, 7% Services). Les classes OOP sont testées indirectement via les tests handlers ; les middlewares n'ont pas encore de tests unitaires dédiés.


## [3.32.0] — 2026-07-08

### Améliorations

- **1** 🔴 **Middleware Pipeline** — Intégration des middlewares (CsrfMiddleware, RoleMiddleware, AuthMiddleware) dans le Router. Le CSRF et la vérification de rôle sont désormais appliqués automatiquement par le Router avant l'exécution de chaque handler POST. Les 14 handlers n'appellent plus `validatePostRequest()` — c'est le Router qui gère la sécurité. Le Pipeline pattern permet d'empiler les middlewares par handler.

- **2** 🔴 **EventDispatcher sur tous les services** — UserService et AuthService dispatchent désormais des événements (user.created, user.updated, user.deactivated, user.reactivated, user.role_changed, user.provisioned, user.promoted). Le EventDispatcher est wiré dans le DI Container pour les 6 services.

- **3** 🟡 **Fix missing <?php tags** — Les 3 fichiers middleware créés par le subagent n'avaient pas de tag `<?php` d'ouverture, causant 46 erreurs d'autoloading en PHP 8.4 (et erreurs fatales en PHP 9).


## [3.31.0] — 2026-07-08

### Améliorations

- **1** 🔴 **SessionManager** — Nouvelle classe `App\Services\SessionManager` encapsulant toute la logique session (démarrage, CSRF, flash messages, données formulaire, impersonation, intended URL). Backward-compatible via délégation aux fonctions globales existantes.

- **2** 🔴 **NotificationService** — Nouvelle classe `App\Services\NotificationService` regroupant les 6 fonctions de notification email (nouveau signalement, réponse, abandon, réouverture, changement de rôle, notifications retard). Extrait de `mail_notifications.php`.

- **3** 🔴 **BackupService** — Nouvelle classe `App\Services\BackupService` encapsulant la logique de backup SQLite (fingerprint, vérification, VACUUM INTO, rotation). Extrait de `backup.php`.

- **4** 🟡 **Pipeline Middleware** — Nouvelle classe `App\Middleware\Pipeline` implémentant le pattern chain-of-responsibility pour les middlewares. Prête à remplacer les guards procéduraux.

- **5** 🟡 **Fix warnings PHP 9** — Ajout de guards `defined()` sur les 10 constantes `config.php` qui étaient redéfinies par `tests/bootstrap.php`. Suppression de 12 warnings PHP 8.x (qui deviendraient des erreurs fatales en PHP 9).


## [3.30.0] — 2026-07-08

### Améliorations

- **1** 🔴 **UserService + UserRepository** — Nouvelles classes OOP pour la gestion des utilisateurs. `App\Repository\UserRepository` encapsule toutes les requêtes DB (CRUD, queries, GDPR). `App\Services\UserService` contient la logique métier (validation, guards dernier superviseur, transactions). Les 4 handlers user (`user_create`, `user_edit`, `user_delete`, `user_reactivate`) utilisent désormais le service au lieu d'appels directs aux fonctions query.

- **2** 🔴 **AuthService** — Nouvelle classe `App\Services\AuthService` pour l'authentification. Encapsule l'authentification IIS, le mock login dev, l'auto-provisioning, la détection de rôle, et l'auto-promotion superviseur. Les DTOs `CreateUserCommand` et `UpdateUserCommand` structurent les données d'entrée.

- **3** 🟡 **DI Container étendu** — Le Container gère désormais 6 services : ReportRepository, UserRepository, ReportService, UserService, AuthService, EventDispatcher.


## [3.29.0] — 2026-07-08

### Améliorations

- **1** 🔴 **Routage unifié** — Suppression de la dualité routage procédural / class-based. Le `App\Router\Router` est désormais l'unique point d'entrée pour tout le dispatch (GET pages + POST handlers). Les fonctions procédurales `getHandlerMap()`, `dispatchPostHandler()`, `dispatchPage()`, `validatePage()`, `getPageTitle()`, `getValidPages()` ont été déplacées dans le Router class. `src/router.php` ne contient plus que les fonctions de rendu (`renderPageWithLayout()`, `renderStandalonePage()`). Nouveau fichier `src/Router/routes.php` centralise toutes les définitions de routes.


## [3.28.0] — 2026-07-08

### Améliorations

- **1** 🔴 **Autoload PSR-4** — Ajout de l'autoload Composer PSR-4 pour toutes les classes applicatives. Les 11 classes (Container, ReportRepository, ReportService, 4 DTOs, EventDispatcher, QueryFilterBuilder, Router, Route) sont désormais namespacées sous `App\` et chargées automatiquement par Composer, éliminant les `require_once` redondants dans les handlers, les tests et le bootstrap.

- **2** 🟡 **Espaces de noms (namespaces)** — Toutes les classes applicatives sont désormais dans des espaces de noms : `App\Container`, `App\Repository`, `App\Services`, `App\DTO`, `App\Event`, `App\Query`, `App\Router`. `QueryFilterBuilder` déplacé de `src/helpers/` vers `src/Query/` pour refléter sa nature de classe.

- **3** 🟡 **PHPStan avec autoload** — PHPScan utilise désormais `vendor/autoload.php` via `bootstrapFiles` au lieu de `scanDirectories`, permettant une analyse statique correcte des classes namespacées.


## [3.27.0] — 2026-07-08

### Corrections

- **1** 🔴 **Notifications mail robustes** — Les handlers ne crashent plus si une fonction de notification mail est indisponible ou génère une erreur PHP. Les blocs `try/catch` des notifications utilisent désormais `\Throwable` au lieu de `\Exception` pour attraper les erreurs PHP 7+ (`Error`, `TypeError`, etc.) en plus des exceptions classiques. Handlers concernés : `report_respond_handler.php`, `report_create_handler.php`, `report_abandon_handler.php`, `report_reopen_handler.php`, `user_edit_handler.php`.

- **2** 🔴 **Réponse au signalement : filet de sécurité** — Ajout d'un `catch (\Exception)` de dernier recours dans `report_respond_handler.php` pour rediriger l'utilisateur avec un message d'erreur en cas d'exception inattendue, au lieu d'afficher une page blanche.

- **3** 🟡 **Correction snake_case dans ReportRepository** — `ReportRepository::update()` convertit désormais les propriétés camelCase du DTO `UpdateReportCommand` en snake_case avant de les transmettre aux requêtes SQL, corrigeant un bug silencieux lors de la modification de signalements.


## [3.26.1] — 2026-07-09

### Technique — Modernisation PHP

- **1** 🔴 **PHPStan level 10** — Niveau d'analyse statique relevé de 6 à 10 (max) avec `treatPhpDocTypesAsCertain: false`. Baseline de 1626 erreurs existantes générée. Nouvelles violations bloquées en CI. (`phpstan.neon`, `phpstan-baseline.neon`)
- **2** 🔴 **PHP-CS-Fixer : migration @PSR12 → @PER-CS** — Le ruleset de codage mis à jour vers PER Coding Style (successeur officiel de PSR-12). 28 fichiers reformattés. (`.php-cs-fixer.dist.php`)
- **3** 🔴 **PSR-4 autoload** — Ajout de `"SST\\": "src/"` dans `composer.json` pour l'autoloading PSR-4. Classes de facade ajoutées (`App.php`). (`composer.json`, `src/App.php`)
- **4** 🟡 **Rector configuré** — Ajout de `rector.php` pour les migrations PHP 8.x automatisées (readonly, enums, typed properties). (`rector.php`)
- **5** 🟡 **Infection configuré** — Ajout de `infection.json` pour le mutation testing en mode PR-diff. (`infection.json`)
- **6** 🟡 **PHPArkitect configuré** — Ajout de `phparkitect.php` pour les tests d'architecture. (`phparkitect.php`)
- **7** 🟢 **Scripts Composer** — Ajout de `cs:fix`, `cs:check`, `phpstan`, `phpstan:baseline`, `rector`, `rector:check`, `phparkitect`, `test`, `test:coverage` dans `composer.json`.
- **8** 🟢 **PHPStan bootstrap FPDF/Parsedown** — Ajout des librairies tierces aux `bootstrapFiles` pour résoudre les erreurs `class.notFound`. (`phpstan.neon`)


## [3.26.0] — 2026-07-07
