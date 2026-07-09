# Changelog — Application SST DREETS BFC

Toutes les modifications notables de ce projet sont documentées dans ce fichier.


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
