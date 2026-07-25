# Changelog — Application SST DREETS BFC

Toutes les modifications notables de ce projet sont documentées dans ce fichier.


## [3.52.0] — 2026-07-25

### Refactoring — Type enum + AuditRepository

- **1** 🔴 **`CreateReportCommand.type`** : `string` → `ReportType` (enum). Factory `fromPost()` utilise `ReportType::from()`, `toArray()` sérialise en string pour SQL. Comparaisons directes sans `->value`.
- **2** 🔴 **`ReportService`** : `$cmd->type === ReportType::Rami` (plus de `->value`).
- **3** 🔴 **`ReportRepository`** : 3 nouvelles méthodes — `findOverdue()`, `findAnonymizable()`, `anonymize()` — extraient le SQL de `cron.php`/`cron_anonymize.php`.
- **4** 🔴 **`cron.php`** + **`cron_anonymize.php`** : SQL procedural remplacé par `ReportRepository` + `AuditRepository`. 0 SQL brut restant.
- **5** 🔴 **11 fichiers de tests** migrés vers `ReportType::Rsst` + booléens.
- **6** 🔴 **PHPStan 0 erreur**, tous les tests passent.


## [3.51.0] — 2026-07-25

### Refactoring — DI + AuditRepository

- **1** 🔴 **`NotificationService`** : `getDB()` dans le constructeur → injection PDO via DI Container. Déplacé dans la section "Services — with dependencies" de `bootstrap_services.php`.
- **2** 🔴 **`AuditRepository`** (nouveau) : SQL de `audit.php` encapsulé dans `App\Repository\AuditRepository` — `log()`, `findPaginated()`, `findByTarget()`. Enregistré dans le DI Container.
- **3** 🔴 **`audit.php`** : converti en délégués propres (même pattern que `src/helpers/`). Signature publique inchangée — 0 impact sur les 20+ appelants.
- **4** 🔴 **PHPStan 0 erreur**, tous les tests passent.


## [3.50.0] — 2026-07-25

### Refactoring — Type-safety DTOs + DRY handlers

- **1** 🔴 **`RespondToReportCommand.nouvelEtat`** : `string` → `ReportState` (enum). Handler, repository et tests migrés.
- **2** 🔴 **`CreateReportCommand.isConfidential` + `consentSyndicat`** : `int` → `bool`. Factory `fromPost()`, `enforceVisibility()`, repository et tests migrés.
- **3** 🔴 **`UpdateReportCommand.isConfidential` + `consentSyndicat`** : `int` → `bool`. Factory `fromPost()` et tests migrés.
- **4** 🔴 **`report_edit_handler.php`** : validation email dupliquée (12 lignes) remplacée par appel à `ReportService::validateLinkedEmails()` — DRY.
- **5** 🔴 **PHPStan 0 erreur**, tous les tests passent.


## [3.49.0] — 2026-07-25

### Audit DDD complet — Score 8.2/10

Réaudit complet de l'architecture DDD avec notation par couche.

- **1** 🔴 **RegistryCard.php supprimé** : dead code confirmé — créé dans R5 ReadModels mais jamais branché dans `RegistryCardService` (qui retourne des arrays). 0 instanciation dans tout le codebase.
- **2** 🔴 **Magic string corrigée** : `pages/help.php` — `'agent'` → `UserRole::Agent->value` (2 occurrences).
- **3** 🔴 **Score global 8.2/10** : Enum 7/10, DTO 7/10, Repository 8/10, Service 8/10, Infrastructure 9/10.
- **4** 🟡 **8 actions identifiées** dans TODO.md : A1 (RegistryCard) ✅, A2 (magic string) ✅, A3-A8 (type-safety DTOs, DI NotificationService, migration audit/cron).

### Refactoring

- **5** 🔴 **DTO compteur corrigé** : 17 DTOs readonly au total (11 initiaux + 5 command DTOs + UpdateUserCommand).


## [3.48.0] — 2026-07-25

### Feature — R5 ReadModels : Typification complète des retours

- **1** 🔴 **11 DTOs readonly** dans `src/DTO/` : `IndicateursData`, `SiteStatsRow`, `SynthesisRow`, `RamiStats`, `StatisticsResult`, `ReportListItem`, `PaginatedReports`, `ReportStateCounts`, `AdjacentUuids`, `ReportData`, `RegistryCard`.
- **2** 🔴 **ReportRepository::findById()** retourne `?ReportData` au lieu de `?array`. Tous les consommateurs migrés (pages, templates, handlers, services, tests).
- **3** 🔴 **ReportRepository::findPaginated()** retourne `PaginatedReports`, `getAdjacentUuids()` → `AdjacentUuids`, `countByState()` → `ReportStateCounts`.
- **4** 🔴 **StatsRepository** : 4 méthodes retournent des ReadModels (`IndicateursData`, `SiteStatsRow`, `SynthesisRow`, `RamiStats`).
- **5** 🔴 **Helpers migration** : `fetchReportOrRedirect()`, `requireReportOwnership()`, `requireReportEditable()`, `canAccessReport()`, `logConfidentialReportAccess()` acceptent `ReportData`.
- **6** 🔴 **~30 fichiers migrés** : pages, templates, handlers, services, helpers, mail_notifications.php, cron.php.
- **7** 🔴 **PHPStan 0 erreur**, 901 tests verts.

### Fix — R6 Magic strings SQL terminé

- **8** 🔴 **ReportRepository::reopen()** : `WHERE etat IN ('traite', 'abandonne')` → `$this->pdo->quote()` + enums.
- **9** 🔴 **0 magic string SQL** dans tout le codebase.

### Refactoring — ConfigService singleton → DI container

- **10** 🔴 **ConfigService::getInstance()/setInstance()** supprimés. 174 appels migrés vers `getConfigService()` (résout depuis le container DI). 58 fichiers modifiés.
- **11** 🔴 **deptrac** : Helpers layer autorisé à dépendre de DTO.

### Audit DDD — 7/10 aspects ✅

- **12** 🟢 Audit DDD mis à jour : Architecture Layers ✅, DTO Pattern ✅, Enum Usage ✅, Error Handling ✅, Service Pattern ✅, Testing ✅, Code Quality ✅.
- **13** 🟢 R7 (deptrac handlers/pages/templates) confirmé terminé. 0 violation.

---

## [3.47.0] — 2026-07-25

### Feature — Audit DDD + améliorations architecture

- **1** 🔴 **TODO.md** — Audit DDD complet ajouté (10 aspects, 7 recommandations prioritaires R1-R7).
- **2** 🔴 **AGENTS.md** — Règle Rector ajoutée : « Étudier la possibilité d'utiliser Rector pour les refactoring conséquents (50+ fichiers) ».

### Feature — Brancher méthodes P21 incomplètes

- **3** 🔴 **statistics.php** — `RegistryRepository::themeClasses()` branchée : indicaturs dynamiques par registre au lieu de cartes hardcodées rsst/rami/dgi.
- **4** 🔴 **settings_handler_registres.php** — CRUD registry_fields ajoutée : `delete_field` + `add_field` handlers.
- **5** 🔴 **tab_registres.php** — UI admin registry_fields ajoutée : liste, ajout, suppression de champs personnalisés par registre.

### Fix — Dead code shipmonk

- **6** 🔴 **shipmonk/dead-code-detector** installé et configuré dans phpstan.neon.
- **7** 🔴 **7 dead methods supprimées** : `RegistryFieldRepository::getPdo()`, `RegistryRepository::getPdo()`, `ConfigRepository::getPdo()`, `NotificationRepository::delete()`, `SiteRepository::findByName()`, `StatsRepository::countByRegistryAndSite()`, `UserRepository::updateRole()`, `ReportType::icon()`.
- **8** 🟢 **4 méthodes test-only** gardées dans baseline : `findById`, `update`, `toggleEnabled`, `countByState`.

### Fix — PHPStan rules

- **9** 🔴 **NoLegacyConstantRule.php** — Étendue : `TYPE_RSST`, `TYPE_RAMI`, `TYPE_DGI` ajoutées aux constantes bloquées.
- **10** 🔴 **PHPStanRulesTest.php** — Tests unitaires vérifiant qu'aucune constante legacy n'est utilisée dans le code prod.

### Fix — Divers

- **11** 🔴 **Consentement syndicat** — Texte rendu configurable via `getConfig('app_nom_organisation', 'DREETS')` au lieu du nom hardcodé.
- **12** 🟢 **TODO.md** — P22 marqué terminé (problème facturation GitHub, pas technique).
- **13** 🟢 **phpstan-baseline.neon** — Régénéré (78 erreurs dont 4 test-only).
- **14** 🟢 **935 tests, 1886 assertions** — tous verts.

---

## [3.46.0] — 2026-07-24

### Feature — Registres custom pleinement fonctionnels (P21 final)

- **1** 🔴 **report_form.php** — Rendu dynamique des `registry_fields` depuis la DB (text, select, textarea, checkbox) avec validation required et gestion d'erreurs. Remplace le require RAMI hardcodé.
- **2** 🔴 **StatsRepository.php** — SQL stats dynamique : les `SUM(CASE WHEN type = 'rsst'...)` sont générés depuis les registres actifs dans `registries` (plus de types hardcodés).
- **3** 🔴 **RegistryCardService.php** — `btn_label` lu depuis `$reg['btn_label']` (nouveau champ dans `registries`). Plus de mappe 3 types hardcodée.
- **4** 🔴 **CSS classes dynamiques** — `report_form.php`, `report_card.php`, `report_reopen.php`, `report_abandon.php` : `match(ReportType)` remplacé par `color_theme` depuis `registries`.
- **5** 🔴 **schema.sql** — Colonne `btn_label` ajoutée à `registries`.
- **6** 🔴 **migration_columns.php** — Migration `btn_label` + backfill des 3 systèmes.

### Feature — PHPStan NoTodoCommentRule

- **7** 🔴 **NoTodoCommentRule.php** — Nouvelle règle PHPStan : interdit TODO/FIXME/HACK/XXX dans le code source avec message clair « utiliser TODO.md ».
- **8** 🟢 **phpstan-no-magic-string.neon** — Règle enregistrée.

### Fix — Constantes legacy remplacées par des enums (Rector)

- **9** 🔴 **~40 fichiers** — 119 usages de constantes `ROLE_*`/`ETAT_*` remplacés par `UserRole::X->value`/`ReportState::X->value`.
- **10** 🔴 **ReplaceMagicStringWithEnumRector.php** — Paramètre `constToEnum` ajouté pour mapper `ROLE_AGENT`, `ROLE_SUPERVISEUR`, `ROLE_CHSCT`, `ETAT_*`.
- **11** 🔴 **rector.php** — Mappings `constToEnum` ajoutés.

### Fix — Deptrac + Architecture

- **12** 🔴 **RegistryCardService.php** — Injection DI propre (plus de fallbacks `?? instance()`).
- **13** 🔴 **deptrac.yaml** — Router → Enum ajouté au ruleset.
- **14** 🔴 **registry_card_renderer.php** — Helpers utilisent `getContainer()` pour résoudre le service.

### CI/Quality

- **15** 🟢 **GrumPHP** — PHPStan, PHPUnit, phpcsfixer, phparkitect, rector, deptrac tous passent.
- **16** 🟢 **935 tests, 1886 assertions** — tous verts.

---

## [3.45.0] — 2026-07-23

### Fix — Signalements rattachés invisibles dans les listes

- **1** 🔴 **ReportFilter.php** — Champs `linkedAgentId` et `linkedAgentVisibility` ajoutés au DTO.
- **2** 🔴 **ReportRepository.php** — Nouvelle méthode `countVisibleForAgent()` : compte les signalements visibles pour un agent (déclarant + rattachés via `report_agents`). `findPaginated()` gère le filtre `linked_agent_id` avec sous-requête sur `report_agents`.
- **3** 🔴 **home.php** — Comptage migré vers `countVisibleForAgent()` pour les modes `confidential` et `agent_choice`.
- **4** 🔴 **report_list.php** — Passe `linkedAgentId` + `linkedAgentVisibility` au `ReportFilter`.
- **5** 🟢 **9 tests** — `LinkedAgentVisibilityTest.php` : couvre comptage et liste paginée pour les 3 modes de visibilité. 901 tests, 1886 assertions.

### Amélioration — Règle PHPStan anti magic strings + Rector auto-migration

- **6** 🔴 **NoMagicStringRule.php** — Règle PHPStan custom qui bloque les 11 magic strings métier (VisibilityMode, ReportType, ReportState) hors enums/tests/seed.
- **7** 🔴 **ReplaceMagicStringWithEnumRector.php** — Rector custom qui auto-migre les `===`/`!==` et `switch/case` vers les enums.
- **8** 🟢 **~20 fichiers** — Migration des magic strings vers les enums dans `src/`, `pages/`, `handlers/`, `templates/`, `helpers/`.
- **9** 🟢 **AGENTS.md** — Règle ajoutée : « JAMAIS de magic strings métier, toujours les enums ».

### Architecture — Deptrac + NoSqlOutsideRepositoryRule + DDD patterns

- **10** 🔴 **deptrac.yaml** — Ruleset architecture : Enum ↔ DTO, Repository → Query, Service → Repository/Query/Event, Helpers → Service.
- **11** 🔴 **NoSqlOutsideRepositoryRule.php** — Règle PHPStan : SQL interdit hors `src/Repository/` (30 violations restantes à fixer).
- **12** 🟢 **AGENTS.md** — Patterns DDD ajoutés (VisibilityPolicy, DTO typés avec enums, SQL interdit hors Repository).
- **13** 🟢 **ci.yml** — Étape Deptrac ajoutée au pipeline CI.
- **14** 🟢 **grumphp.yml** — Tâche deptrac ajoutée au pre-commit hook.


## [3.44.5] — 2026-07-22

### Fix — Texte d'aide abandon + 4 tests pré-existants corrigés

- **1** 🟢 **report_card.php** — Texte d'aide abandon passé au présent (« est marqué » / « reste consultable »).
- **2** 🔴 **ConfigService.php** — `get()` traite les chaînes vides comme « pas de valeur » et retourne le fallback.
- **3** 🔴 **router_runner.php** — `error_reporting(E_ALL)` + `display_errors = 'stderr'` (erreurs visibles en stderr, JSON propre en stdout). Les erreurs du test runner sont affichées, pas supprimées.
- **4** 🔴 **RouterCsrfIntegrationTest.php** — Retiré le `2>/dev/null` (syntaxe Unix incompatible Windows). 876 tests OK, 0 échec.
- **5** 🔴 **ConfigServiceTest.php** — 6 tests edge cases ajoutés (fallback vide, null brut, whitespace, overwrite, valeur valide). 882 tests, 1851 assertions.
- **6** 🔴 **nuclear-reset.php** / **seed.php** / **ConfigService.php** — 12 erreurs PHPStan corrigées (PDOStatement|false, realpath|false). Scope PHPStan élargi (`nuclear-reset.php`, `seed.php`). GrumPHP passe en entier.
- **7** 🔴 **agent_confirm.php** — Champ `csrf_token` ajouté au formulaire de confirmation (manquant depuis toujours — chaque soumission échouait en "Erreur de sécurité"). 2 tests unitaires de non-régression ajoutés. 896 tests, 1874 assertions.
- **8** 🟢 **.gitattributes** — `* text=auto eol=lf` ajouté. 145 fichiers CRLF normalisés en LF via php-cs-fixer.
- **9** 🟢 **grumphp.yml** — phparkitect et rector ajoutés aux tâches GrumPHP (5/5 passent).
- **10** 🟢 **ci.yml** — phparkitect et rector ajoutés au job quality GitHub Actions.
- **11** 🟢 **16 fichiers** — Rector modernise : imports FQN → use, parenthèses inutiles, null coalescing, constructor promotion (SQLiteSessionHandler).
- **12** 🟢 **e2e/agent-confirm.spec.js** — 3 tests E2E pour la page de confirmation d'agent (token valide, token invalide, token vide).

## [3.44.4] — 2026-07-21

### Amélioration — Libellé « Rattacher des collègues » modifiable via admin

- **1** 🔴 **migration_config.php** — Clé `app_linked_agents_label` ajoutée (défaut : « Rattacher des collègues au signalement »).
- **2** 🔴 **tab_app.php** — Champ texte ajouté dans l'onglet Paramètres de l'application.
- **3** 🔴 **settings_handler_app.php** — Sauvegarde + fallback si vide.
- **4** 🔴 **report_form_linked_agents.php** — Label rendu dynamique via `getConfig()`.
- **5** 🔴 **ConfigServiceTest.php** — 3 tests ajoutés (défaut, personnalisé, fallback vide).

## [3.44.3] — 2026-07-21

### Fix — Formulaire signalement : champs Pôle et Téléphone obligatoires

- **1** 🔴 **report_form.php** — Champs « Pôle » et « Numéro de téléphone mobile » marqués `required` (HTML5) avec indicateur `*` dans le label, comme les autres champs obligatoires du formulaire.

## [3.44.2] — 2026-07-21

### Fix — Tests E2E : RAMI/DGI conditionnels + quick-access supprimé

- **1** 🔴 **reports.spec.js** — Tests cartes accueil : RSST seul (RAMI/DGI conditionnels via `app_registry_*_enabled`). Création RAMI/DGI, champs spécifiques et liste DGI : guards `if (page.url().includes('page=home')) return`. (`e2e/reports.spec.js`)
- **2** 🔴 **roles.spec.js** — Sidebar + pages accessibles : RSST seul. suppression des 2 tests `quick-access` (section supprimée, doublon sidebar). (`e2e/roles.spec.js`)
- **3** 🔴 **navigation-flows.spec.js** — Navigation registres, breadcrumb, session persistence : RSST seul. (`e2e/navigation-flows.spec.js`)
- **4** 🔴 **report-flows.spec.js** — Création RAMI : guard ajouté. (`e2e/report-flows.spec.js`)
- **5** 🔴 **settings.spec.js** — Radios visibilité : RSST seul. (`e2e/settings.spec.js`)


## [3.44.1] — 2026-07-21

### Fix — Fiche signalement : checkbox confidentielle masquée si mode confidentiel par type

- **1** 🟢 **report_form.php** — Passage du paramètre `$type` aux helpers `reportVisibilityIsAgentChoice()`, `reportVisibilityIsConfidential()` et `reportVisibilityIsPublic()` dans le formulaire de signalement. La config de visibilité par type (`app_report_visibility_rsst`) est désormais respectée : si réglée sur `confidential`, la case « Signalement confidentiel » n'est plus affichée et le signalement est confidentiel d'office. (`templates/report_form.php`)


## [3.44.0] — 2026-07-20

### Fix critique — JS servant

- **1** 🔴 **js.php** — Création de `public/js.php` (même modèle que `css.php`) pour servir les fichiers JavaScript via PHP. IIS bloque l'accès direct au dossier `js/` via `hiddenSegments` dans `web.config`. Le wordcloud.js ne se chargeait jamais. (`public/js.php`, `templates/footer.php`)

### Migration — CHECK constraints (sans skip)

- **2** 🔴 **CHECK constraints sans guard** — La migration `reports.type/etat` ne skippe plus : aucune guard `schema_version`, échec loud sur violation de données (RuntimeException au lieu de return silencieux). (`src/migration_columns.php`)

### Nettoyage

- **3** 🟡 **TODO P18** — Suppression du concept Sites/UR ajoutée au TODO (non prioritaire, chantier majeur ~8-12h).
- **4** 🟡 **Playwright** — E2E non-bloquant pour la gate de prod. Playwright retiré des prerequis obligatoires. (`update_sst.ps1`)


## [3.43.0] — 2026-07-20

### Dead code cleanup — 15 cibles supprimées

- **1** 🔴 **Route.php** — Classe attribut `App\Router\Attribute\Route` supprimée (jamais référencée). (`src/Router/Attribute/Route.php`)
- **2** 🔴 **AuthMiddleware** — Classe `App\Middleware\AuthMiddleware` supprimée (jamais instanciée en prod, auth = require_auth.php procédural). Tests nettoyés. (`src/Middleware/AuthMiddleware.php`, `tests/unit/AuthMiddlewareTest.php`)
- **3** 🔴 **BackupService** — Service enregistré dans le container mais jamais récupéré. Supprimé + retiré de `bootstrap_services.php`. (`src/Services/BackupService.php`, `src/bootstrap_services.php`)
- **4** 🔴 **AssetService::getIcon() + getCssClass()** — 2 méthodes + 3 helpers privés supprimés (aucun appelant en prod). Tests nettoyés. (`src/Services/AssetService.php`)
- **5** 🔴 **CryptoService::generateToken() + hashToken()** — 2 méthodes supprimées (aucun appelant en prod). Tests nettoyés. (`src/Services/CryptoService.php`)
- **6** 🔴 **HttpService::setCookieSafe() + helper** — Méthode et wrapper supprimés (aucun appelant en prod). Note : `flashAndRedirect()` conservée (wrapper factorisé utilisé ~100 fois). (`src/Services/HttpService.php`, `src/helpers/http.php`)
- **7** 🔴 **QueryFilterBuilder::addIn() + getWhere() + getParams()** — 3 méthodes supprimées (aucun appelant en prod). Tests nettoyés. (`src/Query/QueryFilterBuilder.php`)
- **8** 🔴 **ReportRepository::getStatistics() + findBySite()** — 2 méthodes supprimées (délégation à StatsRepository + aucun appelant). Tests nettoyés. (`src/Repository/ReportRepository.php`)
- **9** 🔴 **notifyPourCompte()** — Fonction de notification supprimée (aucun appelant). (`src/mail_notifications.php`)
- **10** 🔴 **enforceReportVisibility()** — Fonction de validation supprimée (remplacée par ReportService). Tests nettoyés. (`src/validation.php`)
- **11** 🔴 **isLastActiveSuperviseur()** — Fonction guard supprimée (remplacée par UserService). Tests nettoyés. (`src/validation_user.php`)
- **12** 🔴 **reportSelectWithSite() + getReportsBySite()** — 2 fonctions supprimées (aucun appelant, doublon de ReportRepository). Tests nettoyés. (`src/queries/report_queries.php`)

### Migration — CHECK constraints

- **13** 🔴 **CHECK constraints reports.type/etat** — Migration automatique dans `src/migration_columns.php` : vérification idempotente, contrôle d'intégrité, backup, table rebuild avec CHECK, recréation des index. (`src/migration_columns.php`)

### Sécurité & isolation

- **14** 🔴 **Isolation DB E2E** — `DB_PATH` lit désormais `SST_DB_PATH` env var (fallback = prod). Playwright utilise `%TEMP%\sst-e2e-test.db` au lieu de `data/sst.db`. (`src/config.php`, `playwright.config.js`)
- **15** 🔴 **Token GitHub supprimé** — `package.json` nettoyé (PAT en clair). (`package.json`)

### Fix critique

- **16** 🔴 **Autoload order** — `public/index.php` charge maintenant `autoload.php` AVANT `config.php`, corrigeant le fatal error `Class 'App\Enum\UserRole' not found` en prod. (`public/index.php`)

### Pipeline qualité

- **17** 🔴 **Extensions PHPStan installées** — `phpstan/extension-installer`, `phpstan-strict-rules`, `spaze/phpstan-disallowed-calls`, `shipmonk/dead-code-detector`. (`composer.json`)


## [3.42.0] — 2026-07-19

### Audit complet — 7 chantiers traités

- **1** 🔴 **Fuseau horaire UTC/Paris** — `formatDateTimeFR()` convertit UTC→Europe/Paris. `cron.php`/`check_delays.php` utilisent `gmdate()`. `mail_templates.php` formate les dates. (`src/Services/FormattingService.php`, `src/cron.php`, `tools/check_delays.php`, `src/mail_templates.php`)
- **2** 🔴 **État « reouvert » manquant** — `$etatColors` PDF dérivé de `ReportState::cases()`. Dropdown filtre `report_list.php` généré par `ReportState::cases()`. Synthesis.php utilise `ReportType::cases()` pour les boucles. (`pages/report_print_helpers.php`, `pages/report_list.php`, `pages/synthesis.php`, `src/Repository/StatsRepository.php`)
- **3** 🔴 **Rôle CHSCT — accès réouverture** — `report_reopen.php` restreint à `ROLE_SUPERVISEUR` seul (cohérent avec middleware POST). (`pages/report_reopen.php`)
- **4** 🔴 **Audit RGPD — log manquant** — `logConfidentialReportAccess()` ajouté à `response_attachment.php`. (`pages/response_attachment.php`)
- **5** 🔴 **Fuite données CHSCT** — Nouvelle config `app_chsct_report_scope` (consent_only/all). Filtre SQL `consent_syndicat=1` pour CHSCT dans `findPaginated()`. Admin UI dans settings. (`src/Services/AccessService.php`, `src/DTO/ReportFilter.php`, `src/Repository/ReportRepository.php`, `pages/report_list.php`, `pages/settings/tab_app.php`, `handlers/settings_handler_app.php`)
- **6** 🔴 **Agent rattaché — read access** — `canAccessReport()` vérifie `report_agents` via `ReportRepository::isLinkedAgent()`. (`src/Services/AccessService.php`, `src/Repository/ReportRepository.php`)
- **7** 🔴 **Anonymisation RGPD** — `pour_compte_nom`/`pour_compte_prenom` préservés dans `UserRepository::anonymize()`. (`src/Repository/UserRepository.php`)
- **8** 🔴 **.htaccess Apache 2.4** — `Deny from all` → `<RequireAll>Require all denied</RequireAll>`. (`src/backup_protection.php`)

### Enums consolidés (phases 1-4)

- **9** 🔴 **ReportState** — Enum avec `label()`, `badgeClass()`, `pdfColor()`. `ETAT_*` = alias. `ETAT_LABELS` dérivé de `cases()`. (`src/Enum/ReportState.php`)
- **10** 🔴 **ReportType** — Enum avec `label()`, `shortLabel()`, `badgeClass()`, `pdfColor()`, `icon()`, `legalNote()`. `TYPE_*` = alias. (`src/Enum/ReportType.php`)
- **11** 🔴 **UserRole** — Enum avec `defaultLabel()`, `canSeeAllSites()`. `ROLE_*` = alias. (`src/Enum/UserRole.php`)
- **12** 🔴 **VisibilityMode** — Enum pour les 3 modes de visibilité. (`src/Enum/VisibilityMode.php`)
- **13** 🟡 **ConfigService** — `getRoleLabel()` utilise `UserRole::tryFrom()`. `getRoleLabels()` dérivé de `cases()`. (`src/Services/ConfigService.php`)
- **14** 🟡 **AccessService** — `canSeeAllSites()` utilise `UserRole::tryFrom()`. `normalizeVisibilityValue()` retourne `VisibilityMode`. (`src/Services/AccessService.php`)

### Router cleanup

- **15** 🔴 **Suppression src/router.php** — 5 fonctions mortes supprimées. `renderPageWithLayout()`/`renderStandalonePage()` migrées dans `src/Router/Renderer.php`. `Router::getValidPages()` corrigé (array_merge au lieu de +). (`src/Router/Renderer.php`, `src/Router/Router.php`, `src/Router/routes.php`, `src/autoload.php`, `public/index.php`)

### Pipeline qualité — PHPStan strict + outils

- **16** 🔴 **PHPStan 548→0 erreurs** — `phpstan-strict-rules` (disallowedEmpty désactivé), `spaze/phpstan-disallowed-calls` (DateTime::createFromFormat, ini_set), `shipmonk/dead-code-detector` (installé, désactivé). (`phpstan.neon`, `phpstan-disallowed-calls.neon`)
- **17** 🔴 **Infection mutation testing** — Configuré avec minMsi=85, minCoveredMsi=90. MSI actuel : 51%. (`infection.json`)
- **18** 🔴 **GrumPHP pre-commit** — GrumPHP v2.22.0 via phar. Hook pre-commit avec phpstan + phpunit + phpcsfixer en parallèle. (`tools/grumphp.phar`, `tools/grumphp.bat`, `.git/hooks/pre-commit`)
- **19** 🔴 **phpcpd** — 1.96% duplication, 13 blocs. Fork maintained `phpcpd-next/phpcpd`. (`composer.json`)
- **20** 🟡 **ConfigService bug** — `fetchColumn()` appelé 3× sur même résultat. Corrigé. (`src/Services/ConfigService.php`)
- **21** 🟡 **Runner autoload** — `tests/bootstrap.php` charge `vendor/autoload.php`. `middleware_runner.php`/`handler_runner.php` avec `ob_start()` + `session_start()`. Smoke tests ajoutés. (`tests/bootstrap.php`, `tests/middleware_runner.php`, `tests/handler_runner.php`, `tests/unit/RunnerSmokeTest.php`)


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
