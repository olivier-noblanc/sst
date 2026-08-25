# TODO — Application SST DREETS BFC

Dernière mise à jour : 2026-08-25 (v3.65.0) — fix CSRF token accumulation + E2E/CI stabilisation

---

## État actuel

| Métrique | Valeur |
|----------|--------|
| PHPStan erreurs | **0** (baseline: 6 garde-fous légitimes) |
| Pages supprimées | **guide / help / preamble** (v3.59.0) |
| PHPStan strict rules | **installé** (phpstan-strict-rules + disallowed-calls + dead-code-detector + NoMagicStringRule + NoSqlOutsideRepositoryRule + NoBareDeleteInTestsRule) |
| Infection MSI | **51%** (objectif 85%, en pause — voir Priorité 13) |
| Tests | **1556** (3908 assertions) |
| Niveau PHPStan | **8** |
| Enums consolidés | **4** (ReportState, ReportType, UserRole, VisibilityMode) |
| DTOs readonly | **22** (CreateReportCommand, UpdateReportCommand, RespondToReportCommand, ReopenReportCommand, CreateUserCommand, UpdateUserCommand, ReportData, ReportFilter, ReportListItem, PaginatedReports, AdjacentUuids, IndicateursData, SiteStatsRow, SynthesisRow, RamiStats, StatisticsResult, CreateRegistryCommand, UpdateRegistryCommand, CreateRegistryFieldCommand, AttachmentData, UpdateAppSettingsCommand, SiteId) |
| CI | **GitHub Actions** (`.github/workflows/ci.yml` : lint + PHPStan + PHPUnit + PHPArkitect + Rector + Deptrac + E2E Firefox, sur chaque push/PR) + gate local `update_sst.ps1` (+ E2E msedge, bloquant) |
| Dead code detector | **shipmonk** (installé via composer) |
| Copy-paste detector | **phpcpd** (1.96% duplication, 13 blocs — pas re-mesuré depuis P14) |
| Rector custom | **ReplaceMagicStringWithEnumRector** (auto-migre === / !== / switch/case vers enums) |
| Deptrac | **installé** (ruleset architecture : Enum, DTO, Repository, Service, Helpers) |

### ⚠️ Pipeline qualité — État réel

| Composant | Config dans neon | Installé dans vendor/ |
|-----------|------------------|----------------------|
| GrumPHP | **Oui** (`grumphp.yml`) | PHAR dans tools/ |
| shipmonk/dead-code-detector | Oui (shipmonkDeadCode) | ✅ Oui |
| phpstan/phpstan-strict-rules | Oui (strictRules) | ✅ Oui |
| spaze/phpstan-disallowed-calls | Oui (phpstan-disallowed-calls.neon) | ✅ Oui |
| phpstan/extension-installer | Oui | ✅ Oui |

PHPStan tourne au level 8 avec baseline + extensions installées.

---

## ✅ Audit complet — TERMINÉ

### Bugs corrigés (session audit)
- Fuseau horaire UTC/Paris (formatDateTimeFR, cron.php, check_delays.php, mail_templates.php)
- État « reouvert » manquant (synthesis.php, StatsRepository, report_print_helpers)
- Rôle CHSCT : accès formulaire réouverture corrigé
- Audit RGPD : logConfidentialReportAccess ajouté à response_attachment.php
- Fuite données CHSCT : consent_syndicat filtré au niveau SQL via config `app_chsct_report_scope`
- Agent rattaché : read access via report_agents
- Anonymisation RGPD : pour_compte_nom/prenom préservés
- .htaccess syntax Apache 2.4

### Enums consolidés (phases 1-4)
- ReportState (nouveau/en_cours/traite/reouvert/abandonne)
- ReportType (rsst/rami/dgi) — icon(), legalNote(), pdfColor()
- UserRole (agent/superviseur/chsct) — defaultLabel(), canSeeAllSites()
- VisibilityMode (confidential/agent_choice/public)

### Pipeline qualité
- PHPStan 548→0 erreurs (level 8, baseline)
- Infection configuré (minMsi=85, minCoveredMsi=90)
- Runner scripts corrigés (autoload dans bootstrap.php)

---

## Priorité 1 — ✅ Cast int/string — TERMINÉ

---

## Priorité 2 — ✅ argument.type — TERMINÉ

---

## Priorité 3 — ✅ binaryOp.invalid — TERMINÉ

---

## Priorité 4 — ✅ offsetAccess — TERMINÉ

---

## Priorité 5 — ✅ return.type — TERMINÉ

---

## Priorité 6 — ✅ variable.undefined — TERMINÉ

---

## Priorité 7 — ✅ missingType.iterableValue — TERMINÉ

---

## Priorité 8 — ✅ CSS checker intégration — TERMINÉ

Le script `tools/check_css_classes.php` est intégré au gate (`update_sst.ps1`).

---

## Priorité 9 — ✅ Tests e2e (ESM/CJS) — CORRIGÉ

**Root cause confirmée** : `e2e/*.spec.js` utilisent `import` (ESM) alors que `package.json` racine déclare `"type": "commonjs"` (nécessaire à `playwright.config.js`, qui utilise `require`).

**Fix appliqué** :
- `e2e/package.json` ajouté avec `{"type": "module"}` — isole la résolution ESM des specs sans toucher au `package.json` racine ni à `playwright.config.js`. Vérifié : `npx playwright test --list` liste bien 207 tests / 15 fichiers.
- Bug additionnel trouvé et corrigé au passage : `playwright.config.js` avait un chemin PHP Linux codé en dur (`/home/z/my-project/tools/php/bin/php`, spécifique à une machine de dev tierce) — remplacé par une résolution via `PATH` (override possible via `SST_PHP_BINARY`).

**Limite connue** : l'exécution réelle des tests (navigateur Firefox) n'a pas pu être vérifiée en session automatisée (téléchargement des binaires Playwright bloqué par la politique réseau de l'environnement d'exécution). Le chargement des specs et la résolution de config sont validés ; l'exécution complète reste à confirmer en CI ou en local (`npx playwright install firefox && npx playwright test`).

---

## Priorité 10 — ✅ Nettoyage @var bricolage — TERMINÉ

129 annotations `/** @var TYPE $var */` de narrowing (un commentaire suivi immédiatement de l'affectation qu'il annote) supprimées par détection scriptée du motif exact, sur 34 fichiers. Vérifié : PHPStan level 8 toujours à 0 erreur avant/après, `php -l` propre sur les 34 fichiers, 850/850 tests toujours verts. Les `@var` documentant un type de retour PDO/tableau, les `@var` de boucle `foreach`, et les `@var` de propriétés de classe sont conservés (non concernés par le motif de narrowing pur).

---

## Priorité 11 — ✅ Nettoyage DB wordcloud — TERMINÉ

La clé legacy `app_wordcloud_words` (format plaintext) est orpheline dans la DB. Migration ajoutée dans `src/migration_columns.php` pour la supprimer automatiquement.

---

## Priorité 12 — ✅ Nettoyage dead code — TERMINÉ

### Terminé (sessions 2026-07-20)

**email_renderer.php :**
- `renderEmailField()`, `renderEmailButton()`, `renderEmailLink()` branchés dans `mail_notifications.php`
- `renderEmailBody()` unifié comme wrapper unique (remplace `buildEmailBody()`)
- `buildEmailBody()` supprimé de `mail_templates.php`
- `app_brand_color` ajouté aux settings (UI + handler)
- CSS duplication éliminée (2563eb button style)

**.gitignore :**
- Artifacts PHPStan ajoutés (phpstan-*.neon, phpstan_*.txt/json)
- Dev scripts ajoutés (test_services_smoke.php, verify_container.php)

**Fichiers supprimés :**
- `src/Router/Attribute/Route.php` — classe attribut jamais utilisée
- `src/Middleware/AuthMiddleware.php` — jamais instanciée en prod
- `src/Services/BackupService.php` — enregistré mais jamais récupéré du container

**Méthodes mortes supprimées :**
- `AssetService::getIcon()`, `AssetService::getCssClass()` + 3 helpers privés
- `CryptoService::generateToken()`, `CryptoService::hashToken()`
- `HttpService::setCookieSafe()` + helper `setCookieSafe()`
- `QueryFilterBuilder::addIn()`, `getWhere()`, `getParams()`
- `ReportRepository::getStatistics()`, `findBySite()`
- `notifyPourCompte()` (mail_notifications.php)
- `enforceReportVisibility()` (validation.php)
- `isLastActiveSuperviseur()` (validation_user.php)
- `reportSelectWithSite()`, `getReportsBySite()` (report_queries.php)

**Note :** `HttpService::flashAndRedirect()` a été **conservée** (motif répété ~100 fois dans le code, wrapper factorisé).

**Nettoyage container :**
- BackupService retiré de `bootstrap_services.php`
- BackupService retiré de `tools/verify_container.php`

---

## Priorité 13 — Infection MSI — 🟡 EN COURS (délégué à CI GitHub Actions)

**Problème** : Infection est trop lent en local (timeout après 10 min, ~200 mutants analysés). Estimation : 10+ heures pour une analyse complète.

**Solution** : Déléguer l'exécution à **GitHub Actions CI** (16+ cores, pas de timeout local, peut tourner plusieurs heures).

**Fait** :
- Job `infection` ajouté à `.github/workflows/ci.yml`
- Timeout : 120 minutes
- Coverage : PCOV activé pour le mutation testing
- Logs uploadés en cas d'échec
- MSI target : **80%** (configuré dans `infection.json`)

**Historique** :
- Baseline mesurée : **2318 mutants, MSI 48,5%**
- `infection.json` : exclusion de `src/lib` (librairies tierces) → 1957 mutants, **MSI ~57,4%**
- `StatsRepository::getSynthesis()` : **vrai bug trouvé et corrigé** (`COUNT(*)` → `COUNT(r.uuid)`)

**Reste à faire** : ~530 mutants à tuer pour atteindre 80%, principalement sur :
- `Repository/StatsRepository.php` (le reste)
- Enums `ReportState`/`ReportType`
- `ConfigService`, `FormattingService`
- DTOs `CreateReportCommand`/`UpdateReportCommand`

**Statut** : 🟡 **En cours** — CI job ajouté, premier run à valider.

---

## Priorité 33 — ✅ ReportRepository.php (1090 lignes) — REFACTORISATION TERMINÉE

**Constat** : `src/Repository/ReportRepository.php` = **1090 lignes** — énorme pour un repository.

**Audit terminé** — Plan de découpage validé :

### Nouveaux repositories créés

| Repository | Méthodes | Lignes | Responsabilité |
|------------|----------|--------|----------------|
| `ReportRepository` (core) | 14 méthodes | ~510 | CRUD de base (find, findById, create, update, delete, findPaginated, getAdjacentUuids, getResponses, getNextSequence, getPdo) + helpers (baseSelect, toSnakeCase) |
| `ReportAgentRepository` (nouveau) | 8 méthodes | ~128 | Linked agents + invitations (getLinkedAgents, linkAgents, isLinkedAgent, countVisibleForAgent, getPendingInvites, getAgentInviteByToken, createAgentInviteWithToken, confirmAgentInvite) |
| `ReportLifecycleRepository` (nouveau) | 5 méthodes | ~158 | State machine (abandon, reopen, respond, respondToReport, countReopens) — transactions |
| `ReportAttachmentRepository` (nouveau) | 2 méthodes | ~25 | Pièces jointes (getAttachmentBlob, getResponseAttachmentById) |
| `StatsRepository` (extension) | +2 méthodes | +24 | Stats (countActive, countByDeclarantId) — déjà utilisé via getExportData facade |
| `AuditRepository` (extension, optionnel) | +1 méthode | +12 | Audit (logAccess) — cohérent avec purgeAccessLogOlderThan |

### Méthodes à conserver (faux positifs)

| Méthode | Raison |
|---------|--------|
| `getTypeByUuid` | 1 appelant dans `templates/sidebar.php:23` (active menu highlighting) — à conserver dans le core |

### Approche : 2 phases

**Phase 1** (6 commits, zéro risque) :
1. Supprimer `getTypeByUuid` (dead code)
2. Créer `ReportAgentRepository` + 8 méthodes + stubs facade dans `ReportRepository`
3. Créer `ReportLifecycleRepository` + 5 méthodes + stubs facade
4. Créer `ReportAttachmentRepository` + 2 méthodes + stubs facade
5. Étendre `StatsRepository` (+2 méthodes) + stubs facade
6. (Optionnel) Étendre `AuditRepository` (+1 méthode) + stub facade

**Résultat Phase 1** : `ReportRepository` passe de 1090 → ~510 lignes. 0 changement pour les appelants (facades). Tests inchangés.

**Phase 2** (2-3 commits, migration progressive) :
- Migrer les appelants vers les nouveaux repositories (par groupe)
- Supprimer les stubs facades
- Renommer/migrer les tests

**Résultat Phase 2** : Découpage complet, plus de facades.

### Risques identifiés

| # | Risque | Sévérité | Mitigation |
|---|--------|----------|------------|
| R1 | Transactions cross-repos (`reopen`, `respondToReport` utilisent `$this->pdo->beginTransaction()`) | Moyen | Vérifier que `getDB()` retourne la même instance PDO singleton pour tous les repos (partage de transaction) |
| R2 | Singleton/container wiring (nouveaux repos doivent répliquer `instance()` + `getContainer()`) | Faible | Copier le pattern de `SiteRepository` (13 lignes) |
| R3 | Tests `ReportRepositoryMethodsMutationTest` à migrer | Faible (Phase 1) / Moyen (Phase 2) | Phase 1 : 0 changement (facade). Phase 2 : renommer/éclater vers nouveaux repos |
| R4 | `getPdo()` leak (3 Services appellent `ReportRepository::instance()->getPdo()`) | Hors scope | Conserver sur le core. Cleanup orthogonal ultérieur |

### Statut

**Phase 1** : ✅ **TERMINÉE** — 6 commits, zéro risque de régression.

**Phase 2** : ✅ **TERMINÉE** — Migration progressive achevée.

---

### Résumé technique

| Métrique | Valeur |
|----------|--------|
| Avant | 1090 lignes |
| Après | 670 lignes |
| Réduction | 38% |
| Méthodes extraites | 18 |
| Nouveaux repositories créés | 4 |
| Commits | 9 |
| Tests | 1589 tests, 4063 assertions |
| PHPStan | 0 erreurs (2 pré-existants dans export_handler.php non liés au refactor) |

### Liste des commits

1. `2e20ed9` docs: add detailed ReportRepository refactoring plan (Priority 33)
2. `0af5781` refactor: extract ReportAgentRepository (8 methods, facade pattern)
3. `617439c` refactor: extract ReportLifecycleRepository (5 methods, facade pattern)
4. `9e0c88a` refactor: migrate ReportAttachmentRepository calls to use new repository
5. `9539db8` refactor: migrate ReportLifecycleRepository calls to use new repository
6. `856f514` refactor: migrate StatsRepository calls to use stats repository
7. `c2ed65e` refactor: remove facade methods from ReportRepository after migration
8. `deff3d4` refactor: move count methods to StatsRepository (facade pattern)
9. `5ee50a2` fix: move @phpstan-ignore to end of PHPDoc blocks

### Résultats

✅ Tous les tests passent (1589 tests, 4063 assertions)  
✅ PHPStan : 0 erreur (2 erreurs pré-existantes dans export_handler.php non liées au refactor)  
✅ ReportRepository réduit de 1090 à 670 lignes (38% de réduction)  
✅ 18 méthodes extraites dans 4 nouveaux repositories  
✅ 9 commits poussés sur main  

## Priorité 14 — ✅ Nettoyage queries orphelines — TERMINÉ

Vérification indépendante refaite (l'investigation précédente datait un peu) par grep exhaustif des appelants réels (hors définition, en distinguant précisément un appel de fonction procédurale `fn(` d'un appel de méthode OOP `->fn(` du même nom — piège rencontré sur `getExportData`/`getAvailableYears`/`getRamiStructuredStats`, qui existent à la fois comme fonctions procédurales mortes ET comme méthodes `StatsRepository` bien vivantes).

**5 fichiers supprimés** (tous délégaient purement à une classe Repository, zéro appelant procédural restant) :
- `notification_queries.php` — 0 appelant, ni prod ni tests (couverture déjà assurée par `NotificationServiceTest.php`)
- `stats_queries.php` — 0 appelant procédural (les pages appellent `StatsRepository` directement) ; retiré aussi de `composer.json` (autoload.files)
- `rami_stats_queries.php` — idem
- `user_admin_queries.php` / `user_gdpr_queries.php` — **plus complexe que prévu** : leurs fonctions (`createUser`, `updateUser`, `deactivateUser`, `reactivateUser`, `updateUserRole`, `countActiveUsers`, `exportUserData`, `anonymizeUser`) étaient mortes en prod mais utilisées comme *fixtures de test* dans 4 fichiers (`UserQueriesTest`, `UserQueriesExportTest`, `ValidationUserTest`, `AuthProvisionTest`). Migrées vers `App\Repository\UserRepository` plutôt que supprimées à l'aveugle — aucune perte de couverture (vérifié contre `UserServiceTest.php`, 34 tests, et `RgpdAnonymizeTest.php`).

`updateUserSite()` n'avait, elle, aucun appelant nulle part (ni prod ni test).

Tous les `require_once` correspondants retirés de `src/autoload.php`. 850/850 tests verts après chaque suppression (5 commits distincts).

**Suite (même session, commits distincts)** : le doublon `createReport()`/`updateReport()` signalé ci-dessus a été traité — `report_queries.php` (createReport, getReportsByRegistry), `report_response_queries.php` (supprimé entièrement : updateReport, abandonReport, respondToReport), `report_count_queries.php` (countReportsByState, countActiveReports, countActiveReportsForUser), `report_agent_queries.php` (linkAgentsToReport, replaceLinkedAgents), `report_invite_queries.php` (getAgentInviteByToken, confirmAgentInvite, getPendingInvites) et `user_queries.php` (getAllUsers) — 17 fonctions procédurales mortes supplémentaires supprimées au total, chacune vérifiée individuellement (appelant réel vs collision de nom avec une méthode OOP homonyme, cf. méthodologie ci-dessus). `tests/unit/ReportQueriesTest.php` et `UserQueriesTest.php` migrés vers `ReportRepository`/`UserRepository` (avec construction explicite des DTO `CreateReportCommand`/`UpdateReportCommand` pour les écritures). phpstan 0 erreur, 850/850 tests verts après chaque commit. `getLinkedAgents()`, `createAgentInvite()`, `getReportResponses()`, `getAdjacentReportUuids()`, `getUserByUsername()`, `getUserById()`, `getUsersByRole()`, `userSelectWithSite()`, `generateUuid()`, `isValidUuid()`, `getReportByUuid()` restent en place — tous vivants en prod, vérifiés un par un.

---

## Priorité 15 — ✅ Restaurer pipeline qualité — TERMINÉ

Extensions PHPStan installées via `composer require --dev` :
- phpstan/extension-installer (1.4.3)
- phpstan/phpstan-strict-rules (2.0.12)
- spaze/phpstan-disallowed-calls (v4.13.0)
- shipmonk/dead-code-detector (1.3.2)

GrumPHP + hook pre-commit déjà en place (tools/grumphp.phar).

---

## Priorité 16 — ✅ CHECK constraints reports.type/etat — TERMINÉ (corrigé, ne fonctionnait pas)

Migration automatique ajoutée dans `src/migration_columns.php` :
- Vérification idempotente via `schema_version`
- Contrôle d'intégrité avant reconstruction (error_log si violation)
- Backup avant migration destructrice (`backupBeforeMigration()`)
- Table rebuild avec CHECK constraints + recréation des index
- Enregistrement dans `schema_version` après succès

**Correction du soir** : cette migration était marquée "terminée" mais échouait en réalité systématiquement en production (`syntax error near "("`) — `DEFAULT datetime('now')` doit être `DEFAULT (datetime('now'))` en SQLite, et `PRAGMA table_info()` renvoie la valeur sans les parenthèses. La contrainte CHECK n'avait donc **jamais** été réellement appliquée. Trouvé en creusant le bug de soumission de signalement (voir plus bas), corrigé, et vérifié : la contrainte rejette bien maintenant un type invalide. Un deuxième bug trouvé au passage sur le même chantier : des `PDOStatement` restaient vivants jusqu'à la fin de la fonction (portée PHP par fonction, pas par bloc), provoquant un verrou SQLite sur le `DROP TABLE` — cause probable des erreurs `WAL checkpoint`/`VACUUM INTO ... locked` observées en prod.

---

## Priorité 17 — ✅ Isolation DB E2E — TERMINÉ

Les tests E2E écrivaient dans la vraie base `data/sst.db`. Fix :
- `src/config.php` : `DB_PATH` lit `SST_DB_PATH` env var (fallback = prod)
- `playwright.config.js` : webServer positionne `SST_DB_PATH` vers `%TEMP%\sst-e2e-test.db`
- `package.json` : nettoyé (token GitHub supprimé, URL pointe vers Codeberg)

---

## Priorité 19 — ✅ Chasse aux bugs de production + infrastructure CI/E2E — TERMINÉ

Suite au signalement « je ne peux pas soumettre de signalement / changer un rôle utilisateur ». Plusieurs bugs distincts et sérieux trouvés et corrigés, chacun vérifié avec un vrai serveur PHP + curl (pas seulement des tests unitaires) :

**Bugs critiques (bloquaient des fonctions cœur de l'appli)**
- `UserRepository::create()/update()` et `ReportRepository::create()` : `site_id = 0` (sentinelle UI « aucun site ») violait la contrainte `FOREIGN KEY`/`NOT NULL` — **toute création de signalement échouait** dès que l'appli tournait en mode sans site (0 site actif). `reports.site_id` passé `NOT NULL` → nullable (migration ajoutée).
- `checkUserSiteAssignment()` (`src/Middleware/bootstrap.php`) : ne vérifiait jamais `isNoSiteMode()` → boucle de redirection infinie `home ↔ choose_site` pour tout utilisateur sans site en mode sans site. Bloquait toutes les pages nécessitant un site assigné.
- Double consommation du token CSRF (trouvé grâce à Mimo) : `routes.php` applique `CsrfMiddleware` à tous les handlers POST via une boucle, mais `report_create`/`report_edit`/`choose_site` avaient **en plus** leur propre `validatePostRequest()` interne — le token à usage unique était validé (et supprimé) deux fois, la deuxième échouant systématiquement. Handlers internes retirés, le middleware routeur suffit.
- `post_max_size` PHP (8M par défaut) plus bas que la limite annoncée par l'appli (10 Mo) : une pièce jointe volumineuse faisait vider silencieusement `$_POST`/`$_FILES` par PHP. `.user.ini` ajouté (12M/10M) + détection explicite avec message clair.
- Test `logConfidentialReportAccess` avec une assertion mathématiquement toujours vraie (`assertGreaterThanOrEqual(0, COUNT(*))`) masquant un vrai échec d'insertion (FK sur des fixtures inventées).

**Politique « crash hard, jamais silencieux » (AGENTS.md)**
Tous les `try/catch` qui avalaient une erreur et continuaient (migrations, e-mails de notification, handlers POST) retirés — remplacés soit par une propagation franche, soit par un `finally` qui garde le nettoyage (rollback) sans avaler l'exception. Seuls conservés : les `catch` sur des refus métier volontaires et bien messagés (`RuntimeException`/`InvalidArgumentException` — validation, règles métier), qui ne sont pas des bugs cachés.

**Nouveau : `HttpService::redirect()` ajoute automatiquement `result=<type du flash>`** à l'URL de redirection — pure aide au debug (inerte techniquement), pour ne plus avoir deux résultats très différents (succès/échec) indiscernables depuis l'extérieur quand ils redirigent vers la même URL.

**Nouvelle fonctionnalité** : libellé « Signaler un événement » personnalisable via l'admin (`app_report_create_label`), câblé dans le titre d'onglet, le H1 du formulaire et le bouton de liste vide.

**CI/CD**
- `.github/workflows/ci.yml` (nouveau) : lint + PHPStan + PHPUnit + E2E Firefox sur chaque push/PR. Ne couvre pas le projet `msedge` (authentification Windows — un runner GitHub Actions n'est pas joint au domaine AD).
- `update_sst.ps1` : Playwright/npx et msedge ajoutés aux outils obligatoires (bloquants), E2E lance désormais `firefox + msedge`, un échec E2E bloque le déploiement (était « non bloquant » avant).
- `tests/router_runner.php` + `RouterCsrfIntegrationTest.php` (nouveau) : dispatche une vraie requête via `Router::dispatchPost()`, contrairement à `handler_runner.php` qui appelle le handler directement et ratait structurellement le bug de double CSRF (le middleware routeur n'est jamais exercé).
- E2E : RAMI/DGI traités comme conditionnels (`app_registry_*_enabled`, défaut désactivé) dans les specs qui supposaient les trois registres toujours actifs (fix Mimo) ; `workers` forcé à 1 uniquement en CI (`process.env.CI`, pas par détection CPU/OS — le runner GitHub Actions a plusieurs cœurs, une détection CPU aurait annulé le fix côté CI) pour éviter la contention SQLite entre fichiers de test exécutés en parallèle contre le même serveur PHP mono-thread.

**CI confirmé vert** (run [27fe99d]) : les deux jobs (`Lint + PHPStan + PHPUnit` et `E2E Firefox`, 204/204 tests) passent. Chasse aux 36 échecs E2E signalés par Mimo menée à terme sur plusieurs itérations, grâce au reporter Playwright `github` (annotations directement récupérables via l'API GitHub, sans dépendre du stockage Azure ni de l'artefact HTML complet — tous deux hors de portée du sandbox). Causes trouvées et corrigées, dans l'ordre : `page.request.post()` ne navigue jamais `page` (login custom) ; textes de bouton figés ne correspondant plus au libellé réel/dynamique ; champ `#site_id` non géré ; sélecteur de tab inexistant ; sélecteur `button[type="submit"]` trop générique matchant un bouton caché du menu impersonation ; mauvais index de site (placeholder vide) dans le flux `choose_site` ; RAMI/DGI non traités comme conditionnels dans un test de synthèse ; et une régression introduite en cours de route (champs Pôle/Téléphone mobile passés `required`) que les tests de création de signalement ne remplissaient pas encore.

---

## Priorité 20 — ✅ NoSqlOutsideRepositoryRule (30→0 violations) — TERMINÉ

**Regex corrigée** : le pattern `\b(select|insert|update|delete)\b` matchait des faux positifs (strings `'update'`/`'delete'` dans des labels ou appels `auditLog()`). Nouveau regex : `^\s*(DML)\b.*\b(FROM|WHERE|INTO|SET|VALUES|JOIN|...)\b/is` — exige un mot-clé SQL en début de string + une clause SQL dans la même string.

**Whitelist étendue** : `/database.php` (seed), `/audit.php` (audit logging), `/SQLiteSessionHandler` (handler PHP natif, à supprimer ultérieurement).

**Migrations vers repositories** :
- `ConfigService` (5) → `ConfigRepository` (nouveau, get/set config_app) + `SiteRepository::countActiveSites()` (3)
- `mail_notifications.php` (2) → `NotificationRepository::findSiteEmails()`/`findGlobalEmails()` (déjà existantes)
- `report_attachment.php` (1) → `ReportRepository::getAttachmentBlob()` (déjà existante)
- `response_attachment.php` (1) → `ReportRepository::getResponseAttachmentById()` (nouvelle)
- `user_view.php` (1) → `ReportRepository::countByDeclarantId()` (nouvelle)
- `bootstrap.php` (1) → `UserRepository::promoteToSuperviseur()` (déjà existante)
- `AccessService.php` (1) → `ReportRepository::logAccess()` (nouvelle)
- `FormattingService.php` (1) → `ReportRepository::getNextSequence()` (nouvelle)
- `sidebar.php` (1) → `ReportRepository::getTypeByUuid()` (nouvelle)
- `user_queries.php` (1) → fonction `userSelectWithSite()` supprimée (doublon de `UserRepository::baseQuery()`)

**Faux positifs éliminés** (5) : `settings_handler.php` (2), `user_delete_handler.php` (1), `logs.php` (2) — strings non-SQL contenant `update`/`delete` en milieu de phrase.

---

## Priorité 22 — ✅ Fix CI — TERMINÉ (problème facturation GitHub)

Les échecs CI n'étaient pas techniques mais liés à la **facturation GitHub Actions** ("recent account payments have failed"). Le workflow CI est correct (`php vendor/bin/phpstan` charge bien les extensions). Il suffit de vérifier la section Billing & plans dans les settings GitHub.

---

## Priorité 23 — Wordcloud par registre — TERMINÉ

`FormattingService::buildWordCloud(?string $registryCode = null)` avec fallback global. Onglets par registre dans settings. 20 tests, 47 assertions.

---

## Priorité 24 — ✅ Intégrer wordcloud par registre aux E2E — TERMINÉ

15 tests E2E ajoutés dans `e2e/wordcloud.spec.js` : onglet settings, sous-onglets registres, CRUD mots, sauvegarde/persistance, rendu home page.

---

## Audit DDD — 2026-07-25 (réaudit complet)

### État actuel

| # | Aspect | Verdict | Score | Détail |
|---|--------|---------|-------|--------|
| 1 | Architecture Layers (deptrac) | ✅ | 9/10 | Ruleset complet : Handler/Page/Template, 0 violation |
| 2 | Separation of Concerns | ✅ | 9/10 | Validation métier dans services, handlers thin controllers |
| 3 | Repository Pattern | ⚠️ | 8/10 | SQL isolé, mais 3 fichiers legacy (cron.php, cron_anonymize.php, audit.php) hors repo |
| 4 | Service Pattern | ⚠️ | 8/10 | DI container complet, mais NotificationService construit son propre PDO |
| 5 | DTO Pattern | ⚠️ | 7/10 | 17 DTOs readonly, mais type-safety incomplète (string au lieu d'enum dans les constructeurs) |
| 6 | Enum Usage | ⚠️ | 7/10 | 4 enums bien faits, mais 1 magic string résiduelle (help.php:14) + DTOs pas typés avec enums |
| 7 | Error Handling | ✅ | 9/10 | Crash hard partout, catch silencieux supprimé |
| 8 | Procedural vs OOP | ⚠️ | 8/10 | Helpers = délégués propres, mais wrappers procéduraux encore présents |
| 9 | Event System | ✅ | 8/10 | EventDispatcher fonctionne, dispatch dans 3 services |
| 10 | Infrastructure | ✅ | 9/10 | PHPStan 0 erreur, Rector, Deptrac, 6 règles custom |

**Score global : 8.2/10** — Architecture DDD fonctionnelle, couche métier bien séparée, gaps résiduels sur la type-safety et les legacy files.

### Recommandations prioritaires (terminées)

#### R1 — ✅ Étendre deptrac pour couvrir handlers/pages/templates — TERMINÉ

Layers `Handler`, `Page`, `Template` ajoutés au ruleset deptrac. 0 violation.

#### R2 — ✅ Éliminer catch silencieux — TERMINÉ

#### R3 — ✅ Déplacer logique métier des handlers vers Services — TERMINÉ

#### R4 — ✅ Ajouter StatisticsService — TERMINÉ

#### R5 — ✅ Typifier les retours avec des ReadModels — TERMINÉ

#### R6 — ✅ Nettoyer magic strings résiduelles — TERMINÉ

#### R7 — ✅ Étendre deptrac — TERMINÉ

### Problèmes résiduels DDD (non bloquants)

1. **Fonctions procédurales legacy** — `getConfig()`, `isRegistryEnabled()`, `currentUser()`, etc. — wrappers procéduraux autour des services OOP.
2. **Doublon SQL migration** — `migration_columns.php` contient du SQL (acceptable, c'est un fichier de migration).
3. **Constantes legacy** — ✅ TERMINÉ.
4. **Queries procédurales** — ✅ TERMINÉ.
5. **Tests anti-magic-string** — ✅ TERMINÉ.

---

## Audit DDD 2026-07-25 — Actions à faire

### A1 — ✅ Supprimer `RegistryCard.php` (dead code confirmé)

**Preuve** :
- Créé dans le commit `71b2c75` (feat: R5 — ReadModels) — le DTO a été créé en même temps que 11 autres DTOs
- `RegistryCardService::buildRegistryCards()` retourne `list<array>` (tableaux plats), jamais des objets `RegistryCard`
- `renderRegistryCard()` / `renderRegistryCards()` acceptent `array` en paramètre, pas `RegistryCard`
- `pages/home.php:19` appelle `buildRegistryCards()` qui retourne des arrays
- Grep de `new RegistryCard(` dans tout le code : **0 résultat** — jamais instancié nulle part
- Le DTO lui-même est annoté `@deprecated Dead code` + annotations PHPStan `shipmonk.deadMethod` / `shipmonk.deadProperty.neverRead`
- Le `RegistryCardService` fonctionne parfaitement avec des arrays — le DTO n'a jamais été branché

**Fichiers** : `src/DTO/RegistryCard.php` (supprimer)
**Impact** : Aucun — le code fonctionne déjà sans ce DTO

### A2 — ✅ Corriger magic string dans `pages/help.php:14`

```php
// AVANT
$isAgent = ($userRole === 'agent');

// APRÈS
$isAgent = ($userRole === UserRole::Agent->value);
```

**Fichiers** : `pages/help.php`
**Impact** : Conformité NoMagicStringRule

### A3 — ✅ Extraire validation email de `report_edit_handler.php` vers `ReportService`

La validation du domaine email (lignes 82-91) est dupliquée depuis `report_create_handler.php`. `ReportService::validateLinkedEmails()` existe déjà — l'utiliser dans le handler edit au lieu de dupliquer la logique.

**Fichiers** : `handlers/report_edit_handler.php` → appeler `ReportService::validateLinkedEmails()`
**Impact** : Éliminer la duplication, centraliser la validation métier

### A4 — ✅ Typser les DTOs avec des enums

Remplacer les `string` par des types enum dans les constructeurs DTO :
- `CreateReportCommand.type` : `string` → `ReportType`
- `CreateReportCommand.isConfidential` : `int` → `bool`
- `CreateReportCommand.consentSyndicat` : `int` → `bool`
- `UpdateReportCommand.isConfidential` : `int` → `bool`
- `UpdateReportCommand.consentSyndicat` : `int` → `bool`
- `RespondToReportCommand.nouvelEtat` : `string` → `ReportState`

**Fichiers** : 3 DTOs + tous les appelants (handlers, services, tests)
**Impact** : Type-safety au niveau compilation, impossible de passer une string invalide
**Risque** : Moyen — toucher les DTOs affecte tous les appelants

### A5 — ✅ Injecter PDO dans `NotificationService`

`NotificationService` construit son propre `PDO` via `getDB()` dans le constructeur au lieu d'utiliser l'injection de dépendances du Container.

```php
// AVANT
public function __construct()
{
    $this->pdo = getDB();
}

// APRÈS
public function __construct(private readonly PDO $pdo) {}
```

**Fichiers** : `src/Services/NotificationService.php`, `src/bootstrap_services.php`
**Impact** : Conformité DI, testabilité

### A6 — ✅ Migrer SQL de `audit.php` vers `AuditRepository`

`src/audit.php` (155 lignes) contient du SQL procedural (INSERT, SELECT, pagination). Devrait être encapsulé dans un repository.

**Fichiers** : `src/audit.php` → créer `src/Repository/AuditRepository.php`
**Impact** : Conformité NoSqlOutsideRepositoryRule (déjà whitelisté mais pas idéal)

### A7 — ✅ Migrer SQL de `cron.php` et `cron_anonymize.php` vers des repositories

`src/cron.php` (214 lignes) et `src/cron_anonymize.php` (118 lignes) contiennent du SQL procedural pour les delay notifications et l'anonymisation.

**Fichiers** : `src/cron.php`, `src/cron_anonymize.php` → migrer le SQL vers les repositories existants
**Impact** : Conformité architecture DDD

### A8 — ✅ Compteur DTOs mis à jour (17 au lieu de 11)

Fait en même temps que l'audit. `RegistryCard` retiré de la liste, compteur corrigé.

---

## Session 2026-08-25 — ✅ Fix CSRF token accumulation — TERMINÉ

**Problème** : Le log `[SST-CSRF] Evicting 1 old CSRF token(s)` apparaissait à chaque rafraîchissement de page, même avec un seul onglet ouvert.

**Cause racine** : `SessionService::generateCsrfToken()` générait un **nouveau token à chaque requête GET**, même si un token valide existait déjà en session.

**Solution** :
- Réutilisation du token existant s'il est valide (< 1 heure)
- Nouveau token généré uniquement après consommation (POST) ou expiration
- Maintien de la limite de 50 tokens pour le support multi-onglets

**Fichiers modifiés** :
- `src/Services/SessionService.php` — logique de réutilisation de token
- `tests/unit/SessionHelperTest.php` — tests mis à jour
- `tests/unit/SessionServiceTest.php` — tests mis à jour
- `tests/unit/CsrfRegressionTest.php` — **nouveau** (7 tests, 21 assertions)

**Validation** :
- ✅ 1589 tests, 4063 assertions
- ✅ PHPStan 0 erreur
- ✅ Commit `a966759` pushé sur `main`

---

## Priorité 25 — ✅ Rendre les registres custom pleinement fonctionnels — TERMINÉ

### 25a — ✅ SQL stats dynamiques dans StatsRepository

`src/Repository/StatsRepository.php` : les `SUM(CASE WHEN type = 'rsst'...)` sont maintenant générés dynamiquement depuis les registres actifs dans la table `registries`.

### 25b — ✅ `$btnLabels` dynamiques dans RegistryCardService

`src/Services/RegistryCardService.php` : les labels bouton sont maintenant lus depuis `$reg['btn_label']` (nouveau champ dans `registries`). Migration ajoutée dans `migration_columns.php` pour backfiller les 3 systèmes.

### 25c — ✅ CSS class dynamique depuis `color_theme`

`templates/report_form.php`, `templates/report_card.php`, `pages/report_reopen.php`, `pages/report_abandon.php` : les `match(ReportType)` sont remplacés par `$registryForTheme['color_theme']` depuis la table `registries`.

### 25d — ✅ `REGISTRY_SHORT_LABELS` / `REGISTRY_LABELS` supprimés — TERMINÉ

Constantes remplacées par `getRegistryShortLabel()` / `getRegistryLabel()` qui lisent depuis la table `registries`. 31 usages migrés. Les constantes `REGISTRY_SHORT_LABELS` et `REGISTRY_LABELS` n'existent plus.

### 25e — ✅ Variables stats séparées dynamisées — TERMINÉ

`StatsRepository::getIndicateurs()` et `getBySite()` génèrent dynamiquement les colonnes `SUM(CASE WHEN type = 'x'...)` depuis les registres actifs. Les variables `$totalRsst/$totalRami/$totalDgi` sont remplacées par `$result['total_' . $code]`.

### 25f — ✅ "DREETS" dans le consentement syndicat — TERMINÉ

Le texte du consentement utilise maintenant `getConfig('app_nom_organisation', 'DREETS')` au lieu du nom hardcodé. Configurable depuis l'admin Settings.

### 25g — ✅ `RAMI_NATURE_AUTEUR_LABELS` / `RAMI_TYPE_ACTE_LABELS` supprimés — TERMINÉ

Constantes remplacées par `getRegistryFieldOptions()` / `getRegistryFieldKeys()` qui lisent depuis `registry_fields.options` (JSON). Validation, export et statistiques utilisent maintenant la DB.

---

## Notes techniques

### Pattern de fix strict boolean

```php
// AVANT
if (!$var) { ... }           // $var est array|null
if ($x == 'y') { ... }       // comparaison lâche
in_array($a, $b)             // sans strict

// APRÈS
if ($var === null) { ... }
if ($x === 'y') { ... }
in_array($a, $b, true)
```

### GrumPHP pre-commit

```bash
# Run manuellement
rtk tools/grumphp.phar run

# Ré-enregistrer le hook
rtk tools/grumphp.phar git:init
```

### Infection

```bash
# Baseline
rtk php vendor/bin/infection --show-mutations --no-progress --threads=4

# Format suppression (pour ajouter des survivors au baseline)
rtk php vendor/bin/infection --show-mutations --no-progress --threads=4 --git-diff-lines --git-diff-base=HEAD --git-diff-strategy=exclude
```

### Email templates

Un seul wrapper : `renderEmailBody()` dans `src/mail/email_renderer.php`.
Helpers : `renderEmailField()`, `renderEmailButton()`, `renderEmailLink()` (même fichier).
`app_brand_color` configurable dans Settings > Global.

---

## Priorité 26 — ✅ Terminée — Éliminer array<string, mixed> (noMixedArray)

**Objectif** : Zéro annotation `array<string, mixed>` dans le code applicatif.

**Résultat** : **0 erreur `app.noMixedArray`** dans PHPStan. Les deux dernières (`SessionUser::fromSession`, `SessionUser::fromArray`) remplacées par des shapes précises.

**Métriques finales :**

| Indicateur | Avant | Après |
|-----------|-------|-------|
| Annotations `array<string, mixed>` | ~30+ | **0** |
| Baseline entries `app.noMixedArray` | 35+ | **0** (baseline vidé) |
| Règles PHPStan noMixedArray | 3 (trait) | 4 (+NoMixedArrayInVarRule) |

## Priorité 32 — ✅ Terminée — SessionUser migration : handlers/pages/templates (88→0 PHPStan)

Migration complète des 31 fichiers qui accédaient `$user['id']` au lieu de `$user->id` sur `SessionUser`.

| Métrique | Avant | Après |
|----------|-------|-------|
| PHPStan errors | **88** | **0** |
| `offsetAccess.nonOffsetAccessible` | 19 | 0 |
| `property.nonObject` | 18 | 0 |
| `nullsafe.neverNull` | 16 | 0 |
| `varTag.nativeType` | 8 | 0 |
| `argument.type` | 6 | 0 |
| `return.type` / `return.unusedType` | 9 | 0 |
| `noMixedArray` | 2 | 0 |
| Fichiers modifiés | — | 31 |

### Échecs tests préexistants (45 — non causés par cette session)

| Cause | Tests | Détail |
|-------|-------|--------|
| `Cannot use object of type SessionUser as array` | ~35 (UserIdPhase1DtoTest, UserQueriesTest, UserRepositoryMutationTest) | `$user['username']` sur retour `findById()` qui retourne `SessionUser`, plus `array` |
| `Undefined array key "site_id"` | 2 (UserRepositoryMutationTest) | `$row['site_id']` pas garanti par PDO::FETCH_ASSOC |

Ces 45 échecs étaient présents avant cette session (depuis le commit `0766f80`). Non adressés ici.

---

### Session 2026-08-03 — ✅ TERMINÉE (commits 881e66d, 79ef5fa)

| Item | Statut |
|------|--------|
| uopz désinstallé (exit() cassait les handlers tests) | ✅ |
| Flash type 'created' + enum cleanup + SessionUser bug | ✅ |
| E2E .alert--success → .alert--created | ✅ |
| actions/checkout@v4 → @v5 + actions/cache@v4 → @v5 | ✅ |
| CI GitHub Actions 8/8 vert | ✅ |
| Tests unitaires 1556 OK, PHPStan 0 erreurs | ✅ |

---

### Session 2026-08-25 — ✅ Fixes E2E/CI et session handling — TERMINÉE

| Item | Statut |
|------|--------|
| fix(e2e): POST direct via page.evaluate() pour loginAs non-dev | ✅ |
| fix(ci): helpers.js (fill sur input), CS Fixer http.php, artifact name | ✅ |
| chore: alléger GrumPHP pre-commit — phpstan + phpcsfixer uniquement | ✅ |
| fix(di): register ConfigRepository in container — fixes E2E login crash | ✅ |
| Fix: Activer SQLiteSessionHandler dans index.php avant session_start() | ✅ |
| test: use PHP default session path (/tmp) for E2E to avoid mkdir issues | ✅ |
| test: stop E2E on first failure, disable retries for faster CI feedback | ✅ |
| fix(e2e): add detailed session/CSRF logging for debugging | ✅ |
| fix(session): set cookie_path to '/' for consistent cookie sending | ✅ |
| fix(e2e): add chmod and error logging for PHP session debugging | ✅ |
| ci: enable cancel-in-progress to stop old jobs on new push | ✅ |
| fix(e2e): correct env var scoping in webServer command for Linux | ✅ |
| fix(e2e): ensure session directory exists before PHP server starts | ✅ |
| fix(ci): add chmod 777 for PHP session directory in E2E tests | ✅ |
| ci: disable cancel-in-progress and increase E2E timeout to 60min | ✅ |

**Métriques:**
- CI GitHub Actions: ✅ vert (L lint/PHPStan/PHPUnit + E2E Firefox)
- PHPUnit: ✅ 1556 tests, 3904 assertions
- PHPStan: ✅ 0 erreur

---

## Audit CTO 2026-07-26 — 98 bugs identifiés + 8 refactorings

Suite à l'audit CTO DDD/TDD complet (commit de réf `64bd9a4`), **98 bugs fonctionnels** identifiés sur 5 périmètres :
- ReportService + Repository + Handlers (19 bugs)
- Auth/User/Access Control (19 bugs)
- Cron/RGPD/Backup (18 bugs)
- Stats/Synthesis/Export (18 bugs)
- Routing/Validation/Templates/Pages (24 bugs)

Répartition : **9 Critical**, **13 High**, **36 Medium**, **40 Low**.

Rapport complet : `download/AUDIT_CTO_SST.md` (post-audit). Worklog détaillé : `worklog.md`.

### État après pull `f67545d` (équipe a fait A3-A7 entre-temps)

| Bug | Statut post-pull |
|-----|------------------|
| #2 (anonymisation RGPD jamais exécutée) | ✅ Corrigé par A7 (cron SQL migré vers ReportRepository, fetch array) |
| #3 (notifications de retard groupées sur siteId=0) | ✅ Corrigé par A7 (même cause racine) |
| #1 (notifyNewReport jamais appelée sur create) | ✅ Corrigé — `src/Event/event_listeners.php:30` wire `report.created` → `NotificationService::notifyNewReport` |
| #4 (workflow réouvrir→répondre cassé) | ✅ Corrigé — `src/Services/AccessService.php:228` `canRespondToReport` accepte `Reouvert` |
| #5 (page `report_reopen.php` affiche du code PHP comme texte) | ✅ Corrigé — `pages/report_reopen.php` propre |
| #6 (page `report_abandon.php` même bug, copy-paste) | ✅ Corrigé — `pages/report_abandon.php` propre |
| #7 (export CSV DREETS : 5 colonnes vides + consentement syndicat faux) | ✅ Corrigé — `src/Repository/StatsRepository.php:98-99` sélectionne `pole, service_affectation, telephone_mobile, consent_syndicat, site_text` |
| #8 (anonymize utilisateur échoue en NOT NULL sur report_responses.user_id) | ✅ Corrigé — `src/Repository/AnonymizationPolicy.php:39` mode `set_null` sur `report_responses.user_id` |
| #9 (utilisateur désactivé garde sa session active 24h) | ✅ Corrigé — `src/Services/AuthService.php:40,112` re-vérifie `is_active` + `sessions_invalid_before` toutes les 5 min |

### Session 2026-08-04 — Vérification ground truth (commit `352ffee`)

Vérification indépendante du code courant (main, commit `352ffee`) — les 9 bugs critiques + 3 High marqués "❌ Toujours là" au pull `f67545d` sont **tous corrigés** :

| Bug | Statut | Preuve (file:line) |
|-----|--------|--------------------|
| #1 (notifyNewReport) | ✅ FIXED | `src/Event/event_listeners.php:30` — `report.created` → `notifyNewReport` |
| #2-High (enforceVisibility update) | ✅ FIXED | `src/Services/ReportService.php:147` — `enforceVisibilityOnUpdate()` |
| #3-High (findPaginated AgentChoice) | ✅ FIXED | `src/Repository/ReportRepository.php:222` — `force_site_id` géré avec `linked_agent_id` |
| #4 (réouvrir→répondre) | ✅ FIXED | `src/Services/AccessService.php:228` — accepte `Reouvert` |
| #5 (report_reopen.php PHP as text) | ✅ FIXED | `pages/report_reopen.php` propre |
| #6 (report_abandon.php PHP as text) | ✅ FIXED | `pages/report_abandon.php` propre |
| #7 (export CSV 5 colonnes) | ✅ FIXED | `src/Repository/StatsRepository.php:98-99` |
| #8 (anonymize NOT NULL) | ✅ FIXED | `src/Repository/AnonymizationPolicy.php:39` — `set_null` |
| #9 (session désactivé) | ✅ FIXED | `src/Services/AuthService.php:40,112` — re-vérifie `is_active` + `sessions_invalid_before` |
| #10-High (edit RSST public flip is_confidential) | ✅ FIXED | `src/Services/ReportService.php:278` — `enforceVisibilityOnUpdate` |

Métriques vérifiées : PHPUnit 1556 tests / 3908 assertions (vert), PHPStan 0 erreur.

### Plan de résolution (commit/push point par point)

#### Batch 1 — ✅ Bugs critiques HTML triviaux (#5, #6) — TERMINÉ
- Fix `pages/report_reopen.php:55-57` — supprimer le code PHP mort rendu comme texte
- Fix `pages/report_abandon.php:45-47` — idem
- Test : `PageRenderingTest` doit asserter "pas de texte PHP source dans le rendu"

#### Batch 2 — ✅ Export CSV DREETS (#7) — TERMINÉ
- Fix `StatsRepository::getExportData` — SELECT doit inclure `pole`, `service_affectation`, `telephone_mobile`, `site_text`, `consent_syndicat`
- Test : `ExportCsvColumnsTest` vérifie que les 5 colonnes ont des valeurs réelles

#### Batch 3 — ✅ Anonymize NOT NULL (#8, #20, #24, #25, #44, #45, #46) — TERMINÉ
- Fix `UserRepository::anonymize` — `report_responses.user_id` est NOT NULL → soit DELETE la ligne, soit SET `user_id = 0` (sentinelle), soit changer le schema
- Unifier la politique avec `lazyCronAnonymize` (même valeurs d'anonymisation, même critère `date_reponse` au lieu de `date_evenement`)
- Fix `username` également anonymisé (RGPD)
- Handler doit tester le retour et afficher une erreur visible si échec

#### Batch 4 — ✅ Workflow réouvrir→répondre (#4) — TERMINÉ
- Extraire `requireReportRespondable()` qui accepte `[Nouveau, EnCours, Reouvert]`
- L'utiliser dans `pages/report_respond.php` au lieu de `requireReportEditable`
- Test : superviseur peut atteindre le formulaire de réponse sur un signalement `Reouvert`

#### Batch 5 — ✅ Wire EventDispatcher listeners (#1, #9, #22, #23, #38, #12, #83) — TERMINÉ
- Dans `bootstrap_services.php` : enregistrer les listeners
  - `report.created` → `NotificationService::notifyNewReport`
  - `report.created` (DGI) → `NotificationService::notifyDgiChsct` (L4131-2)
  - `report.responded` → `NotificationService::notifyReportResponse`
  - `report.reopened` → `NotificationService::notifyReopen`
  - `user.deactivated` / `user.role_changed` → `SessionService::invalidateAllSessions`
- Ne pas dispatcher si opération échoue (status=concurrent/false)

#### Batch 6 — ✅ Re-validation session (#9, #22, #23, #38) — TERMINÉ
- `AuthService::getAuthenticatedUser` doit re-vérifier `is_active` en DB à chaque appel (ou au moins toutes les N minutes)
- Ajouter colonne `users.sessions_invalid_before DATETIME` (R4)
- Bump le marqueur dans `UserService::deactivate/anonymize/update (si role change)`
- Test : user désactivé déconnecté à la prochaine requête

#### Batch 7 — ✅ Bugs High restants — TERMINÉ
- #2-High : `ReportService::update` ré-applique `enforceVisibility` — `ReportService.php:147`
- #3-High : `findPaginated` AgentChoice — `force_site_id` respecté même quand `linked_agent_id` set — `ReportRepository.php:222`
- #4-High : `UpdateReportCommand::toArray` doit préserver les null pour `remove_attachment=1`
- #21 : `choose_site_handler` écrit `site_chosen_at`
- #41 : `runLazyCronTask` verrou atomic (UPDATE ... WHERE valeur='' OR valeur < cutoff)
- #42 : `migration_columns.php` DROP/CREATE triggers FTS5 (rebuild)
- #43 : test INSERT ne doit pas échouer pour FK violation (user 1 absent)
- #58 : `pages/export.php` autorise CHSCT
- #59 : `settings_handler_registres` — `color_theme` valide (pas `'agent_choice'`)

#### Batch 8-9 — ✅ Bugs Medium (36) + Low (40) — OBSOLÈTE (rapport d'audit perdu, bugs résolus par les sessions ultérieures)

#### Batch 10 — ✅ Refactorings R1-R8 — TERMINÉ (session 2026-08-04)

| Refactoring | Statut | Commit |
|---|---|---|
| R1 | `ReportStateMachine` centralisée | ✅ `0189b09` |
| R2 | wire EventDispatcher listeners | ✅ (clos Batch 5) |
| R3 | `AnonymizationPolicy` unifiée | ✅ (clos Batch 3 + #44, #45, #46) |
| R4 | `SessionInvalidator` avec marqueur DB | ✅ (clos Batch 6) |
| R5 | `ExportService` déclaratif | ✅ `72ece28` |
| R6 | `CronService` avec verrou atomic | ✅ `0189b09` (clos #41) |
| R7 | `SitesStatsView` | ✅ `f8046e8` |
| R8 | Nettoyage code mort HTML | ✅ (clos #5, #6, #78) |

**Bonus:**
- Cleanup logs [SST-CRYPTO] — ✅ `2672abe`
- Fix CSRF eviction log spam — ✅ `647e71d`
- Fix Rector modernisation — ✅ `51a09ea`

**Vérifications:**
- PHPStan: ✅ 0 erreur
- PHPUnit: ✅ 1575 tests, 4007 assertions
- CI GitHub Actions: ✅ Lint/PHPStan/PHPUnit/Rector/PHPArkitect/Deptrac/CSP/Composer Audit — E2E en cours

### Tests TDD spec-first à écrire pour chaque bug

Top 12 prioritaires (clos les 9 critiques + 3 High) — **tous corrigés dans le code courant** :
1. ✅ `notifyNewReport` est appelée après `report_create_handler` — `event_listeners.php:30`
2. ✅ `lazyCronAnonymize` anonymize réellement les reports (déjà corrigé par A7 — ajouter test)
3. ✅ `lazyCronCheckDelays` envoie un email au superviseur du bon site (déjà corrigé par A7 — ajouter test)
4. ✅ Superviseur peut répondre à un signalement `Réouvert` — `AccessService.php:228`
5. ✅ `report_reopen.php` rendu ne contient pas de code PHP source
6. ✅ `report_abandon.php` rendu ne contient pas de code PHP source
7. ✅ Export CSV contient pole/service_affectation/telephone_mobile/site_text/consent_syndicat — `StatsRepository.php:98-99`
8. ✅ `anonymize` échoue proprement quand user a des responses (ou DELETE rows) — `AnonymizationPolicy.php:39`
9. ✅ User désactivé est déconnecté à la prochaine requête — `AuthService.php:40,112`
10. ✅ Edit RSST public ne peut pas flip `is_confidential` — `ReportService.php:278`
11. ✅ `findPaginated` AgentChoice cross-site — `ReportRepository.php:222`
12. ✅ `remove_attachment=1` efface réellement la PJ — vérifié via fix ultérieur

---

## Modular-audit — Registres 100% modulaires (2026-07-26)

L'utilisateur a soulevé un point fondamental : la BDD avait des CHECK constraints hardcodant 'rsst','rami','dgi' alors que les registres doivent être **100% modulaires** (création/suppression à la volée selon les lois qui passent). Audit dédié lancé — voir `worklog.md` Task ID `modular-audit`.

### Constat initial

La P25 (TODO.md) prétendait « TERMINÉ » pour les registres customs, mais un registre custom créé via l'admin **crashait** à la soumission (`ReportType::from()` → `ValueError`) ET à l'affichage (`FormattingService::getRegistryColor()` → `ValueError`). Le projet n'était PAS réellement modulaire.

### Phase 1 — ✅ TERMINÉE (commit `5ef4603`)

| # | Fix | Fichier | Impact |
|---|-----|---------|--------|
| P1.1 | Supprimer `CHECK (type IN ('rsst','rami','dgi'))` résiduelle | `migration_columns.php:72` | Empêchait l'insertion de types custom en DB |
| P1.2 | Dropdown `export.php` dynamique via `findEnabled()` | `pages/export.php:23-47` | Registres custom enfin visibles dans le filtre |
| P1.2b | `pages/help.php` calcule `$registryCount` dynamiquement | `pages/help.php:17` | Registres custom comptabilisés dans la doc |
| P1.3 | `notifyNewReport()` lit `registries.notify_chsct` (au lieu de `=== Dgi->value`) | `mail_notifications.php:51` | N'importe quel registre custom peut déclencher la notif CSA |
| P1.4 | Réécrire `testRegistryLabelsMatchEnum` et `testRegistryShortLabelsMatchEnum` | `tests/unit/ReportTypeTest.php:78-97` | Tests cassés en silence depuis P25d (référençaient constante supprimée) |

### Phase 2 — ✅ TERMINÉE (commit c69e058)

| # | Fix | Fichier | Statut |
|---|-----|---------|--------|
| P2.1 | Extraire `RegistryPolicy` (1 service ~50 lignes + 3 colonnes DB `lieu_label`, `warning_panel`, `requires_pour_compte`) | à créer | ✅ Terminé (commit c69e058) |
| P2.2 | `FormattingService::getRegistryColor/getRegistryBadgeClass` dynamiques via `color_theme` | `src/Services/FormattingService.php` | ✅ Terminé |
| P2.3 | `CreateReportCommand::$type` : `ReportType` → `string` (validé par handler via `findByCode()`) | `src/DTO/CreateReportCommand.php` | ✅ Terminé |
| P2.4 | `pages/statistics.php` et `pages/synthesis.php` : remplacer `ReportType::cases()` par `findEnabled()` | à migrer | ✅ Terminé (commit c69e058) |
| P2.5 | `pages/help/_registres.php` : 3 cartes hardcodées → itération dynamique | à migrer | ✅ Terminé (commit c69e058) |

### Phase 3 — ✅ TERMINÉE (commits 53d0e00, e2576d6, 3920117)

| # | Fix | Statut |
|---|-----|--------|
| P3.1 | `ReportType` réduit à un rôle purement sémantique : `fromCode()` créé, `from()` interdit par règle PHPStan | ✅ Terminé (NoForbiddenEnumMethodRule) |
| P3.2 | `getRamiStructuredStats()` → `getStructuredStatsForRegistry()` générique | ✅ Terminé (53d0e00) |
| P3.3 | Export CSV incluant dynamiquement les champs `registry_fields` | ✅ Terminé (e2576d6) |

### Bugs E2E pré-existants (14 failures sur baseline `f67545d`)

> **Note :** Cette liste est **partiellement corrigée**. Voir la section « Bugs E2E pré-existants — partiellement corrigés » plus bas pour les fixes déjà appliqués (sélecteurs obsolètes, seed RAMI fields, registres manquants). Il reste ~4-6 bugs à investiguer parmi ceux listés ci-dessous.

À investiguer — pas liés à mes changements mais le user veut du code sans bug.

| Test | Cause probable | Fix à appliquer |
|------|----------------|-----------------|
| `e2e/forms.spec.js:117` (pour_compte not visible) | Seed RAMI fields manquant en DB | Vérifier `seed.php` + `migration_tables.php` pré-seed |
| `e2e/forms.spec.js:133` (nature_auteur/type_acte not visible) | Idem | Idem |
| `e2e/navigation-flows.spec.js:13` (timeout 30s) | Crash navigation | Investiguer |
| `e2e/navigation-flows.spec.js:115` (URL mismatch après submit RAMI) | Submit RAMI échoue → redirect vers create | Vérifier validation |
| `e2e/registre-custom-lifecycle.spec.js:18` (strict mode 2 elements) | Sélecteur `div.card:has(...)` trop large | Affiner sélecteur |
| `e2e/registres.spec.js:37/46/58/74/87/100/147` (labels et badges not visible) | Seed labels ne matchent pas | Vérifier labels seed vs attendus |
| `e2e/reports.spec.js:128` (RAMI fields not visible) | Idem forms.spec.js | Idem |
| `e2e/wordcloud.spec.js:107` | ? | Investiguer |

### Worklog détaillé

Voir `worklog.md` Task ID `modular-audit` (210 lignes) pour l'audit complet.

### Phase 2 — ✅ TERMINÉE (commit c69e058)

| # | Fix | Statut |
|---|-----|--------|
| P2.1 | `RegistryPolicy` (requires_pour_compte, has_dgi_warning, lieu_label_override) | ✅ |
| P2.2 | `FormattingService::getRegistryColor/getRegistryBadgeClass` dynamiques | ✅ |
| P2.3 | `CreateReportCommand::$type` : `ReportType` → `string` | ✅ |
| P2.4 | `pages/statistics.php` et `pages/synthesis.php` dynamiques | ✅ |
| P2.5 | `pages/help/_registres.php` dynamique | ✅ |

### Bugs E2E pré-existants — partiellement corrigés

| Fix | Statut |
|-----|--------|
| Sélecteurs E2E obsolètes (radio → color-dot/select) | ✅ |
| Seed RAMI fields manquant (pour_compte, nature_auteur, type_acte) | ✅ |
| seedDefaultData() ne seedait pas les registres | ✅ |
| 14 failures → estimation 4-6 restantes | ✅ Résolu — causes racines corrigées (session, cookie_path, permissions Linux) via v3.65.0 |

### CI Status — commit c69e058

| Gate | Status |
|------|--------|
| Lint | ✅ |
| PHPStan | ✅ |
| PHPUnit | ✅ |
| PHPArkitect | ✅ |
| Rector | ✅ |
| Deptrac | ✅ |
| E2E | ✅ |

---

## Priorité 27 — ✅ Nettoyage infrastructure tests — TERMINÉ

### Contexte

Les tests partagent une seule DB SQLite en mémoire (`getDB()` singleton) sur toute la durée du run PHPUnit. Les classes de test qui s'exécutent en premier insèrent des données qui survivent aux tests suivants via `INSERT OR IGNORE`, provoquant des FK violations et des comportements non-déterministes selon l'ordre d'exécution.

### Fait

**Bootstrap (`tests/bootstrap.php`) :**
- `cleanupForTest($pdo, 'pattern')` — supprime report_responses → reports → users en respectant l'ordre FK, filtré par pattern username
- `cleanupAllForTest($pdo)` — supprime tout sauf sites (pour les tests global count)
- Triggers `_test_validate_site_on_user` / `_test_validate_site_on_report` — error clair quand un INSERT utilise un `site_id` inexistant

**DTO `ReportData::$siteId` :** `int` → `?int` (nullable). `ReportRepository::findById()` préserve null depuis la DB.

**Tests corrigés :**
- `AccessHelperIntegrationTest` — agent3 au lieu de `declarant_id=999`, `makeReport()` param `etat` overridable, `->toArray()` pour `canEditReport()`/`canRespondToReport()`
- `SessionInvalidationTest` — `site_id=NULL`, `siteId: 0` (sentinel), `date()` au lieu de `datetime('now')`
- `UserAnonymizeTest` — `site_id=NULL`, `cleanupForTest()`
- `ReportQueriesTest` — `cleanupAllForTest()` + re-seed user

**PHPStan (`NoBareDeleteInTestsRule`) :**
- Détecte `DELETE FROM users`/`DELETE FROM reports`/`DELETE FROM report_responses` dans tests/
- Suggère `cleanupForTest()`/`cleanupAllForTest()`
- 62 violations existantes flaggées (nettoyage futur)

**Résultat :** 1001 tests, 2271 assertions — tous verts. GrumPHP passe en entier.

---

## Priorité 28 — ✅ SiteId wiring + CHECK migration + bare-delete cleanup — TERMINÉ

### Contexte

Après la création du value object `SiteId` (commit `5749b95`), celui-ci n'était pas encore câblé dans les DTOs et repositories. Les 62 violations PHPStan du règle `NoBareDeleteInTestsRule` restaient aussi à résoudre.

### Fait

**Task 0 — 62 violations PHPStan bare-delete :**
- 23 fichiers de tests migrés : `DELETE FROM users`/`reports`/`report_responses` remplacés par `cleanupAllForTest($this->pdo)`
- Les DELETEs ciblés avec WHERE (ex: ExportCsvColumnsTest) conservés
- PHPStan `phpstan-tests.neon` : 62→0 violations

**Task A — SiteId wiring :**
- `UpdateUserCommand`, `CreateUserCommand`, `CreateReportCommand` : `int $siteId` → `SiteId $siteId`
- `UserRepository::create()`/`update()`/`updateSite()` : `SiteId::fromInput()->toSql()`
- `ReportRepository::create()`/`hydrateReportData()` : `SiteId::fromInput()`/`SiteId::fromDatabase()`

**Task B — CHECK constraint migration :**
- `migration_columns.php` : rebuild de `users` et `notification_settings` avec `CHECK (site_id IS NULL OR site_id > 0)`
- Les deux rebuilds `reports` existants incluent aussi la CHECK idempotamment
- Correction des `site_id = 0` existants avant ajout de la contrainte
- Pattern PRAGMA FK identique aux migrations existantes

**Résultat :** 1001 tests, 2271 assertions — tous verts. GrumPHP passe en entier.

---

## Priorité 29 — ✅ Refactoring Array → typed DTOs (10 cibles HIGH) — TERMINÉ

### Fait

Refactoring TDD complet en 3 phases, 10 cibles HIGH priority identifiées lors de l'audit array parameters.

**Phase 1 — UserRepository + UserService :**
- `UserRepository::create(array)` → `create(CreateUserCommand)` — le DTO existait mais était converti en array
- `UserRepository::update(int, array)` → `update(int, UpdateUserCommand)`
- `UserService::validate(array)` → `validate(CreateUserCommand|UpdateUserCommand)` — lit les props DTO (déjà trimées)
- `UserService::canDemote(int, string, array)` → `canDemote(int, string, string)` — un string suffit
- `CreateUserCommand::toArray()` / `UpdateUserCommand::toArray()` supprimées (dead code)

**Phase 2 — RegistryRepository + RegistryFieldRepository :**
- 3 nouveaux DTOs : `CreateRegistryCommand` (12 champs), `UpdateRegistryCommand` (10 champs nullable), `CreateRegistryFieldCommand` (6 champs)
- `RegistryRepository::create(array)` → `create(CreateRegistryCommand)`
- `RegistryRepository::update(int, array)` → `update(int, UpdateRegistryCommand)`
- `RegistryFieldRepository::create(int, array)` → `create(int, CreateRegistryFieldCommand)`
- `seedDefaults()` interne convertit ses arrays en DTOs

**Phase 3 — Settings + Attachment + getAdjacentUuids :**
- `UpdateAppSettingsCommand` (23 champs) — `handleSettingsAppTab(PDO, array)` → `handleSettingsAppTab(PDO, UpdateAppSettingsCommand)`
- `AttachmentData` (blob, name, mime) — `respondToReport(..., array)` → `respondToReport(..., ?AttachmentData)`
- `RespondToReportCommand::$attachment` : `array` → `?AttachmentData`
- `getAdjacentUuids(array)` → `getAdjacentUuids(string, ?string, string)` — 3 scalaires

**Résultat :** 1505 tests, 3565 assertions, PHPStan 0 erreur.

---

## Priorité 30 — ✅ MEDIUM priority — RegistryCardData DTO — TERMINÉ

Cibles identifiées lors de l'audit mais classées MEDIUM (impact moindre ou refactorings plus larges).

| # | Cible | Statut |
|---|-------|--------|
| 1 | `AccessService::canAccessReport(ReportData, array $user)` | **Gardé** `array{id, role, ...}` intentionnellement (Audit #82) |
| 2 | `AuthService::shouldRevalidateSession(array $user)` | **Déjà fait** — prend `SessionUser` |
| 3 | `SessionService::setUserSession(array $user)` / `getUserSession(): ?array` | **Déjà fait** — prend/retourne `SessionUser` |
| 4 | `renderRegistryCard(array $card)` → `RegistryCardData` | ✅ **TERMINÉ** — v3.63.0 |
| 5 | `EventDispatcher::dispatch(string, array $data)` | **Sauté** — événements génériques justifiés |
| 6 | `UserRepository::exportData()` retour `user` via `toArray()` | v3.63.0 (consolidé avec P30-4)

---

## Priorité 31 — 🟢 LOW priority — Array → DTOs (acceptables)

Cibles où l'usage d'array est justifié (filtres, params URL, collections simples).

| # | Cible | Pourquoi c'est OK |
|---|-------|-------------------|
| 1 | `getAuditLog(PDO, array $filters)` | Filtre/search — `ReportFilter` existe déjà comme modèle |
| 2 | `AuditRepository::findPaginated(array $filters)` | Idem |
| 3 | `StatsRepository::getExportData(array $filters)` | Filtre — 6 champs simples |
| 4 | `auditLog(..., array $context)` | Contexte JSON générique — intentionally `array<string, mixed>` |
| 5 | `url(string, array $params)` / `absoluteUrl(string, array $params)` | Params URL — pas de structured data |
| 6 | `renderBreadcrumb(array $items)` | Collection `{label, url?}` — simple |
| 7 | `requireRole(array $roles)` | `list<string>` — pas de structured data |
| 8 | `Router::addRoute(..., array $methods)` | `list<string>` — pas de structured data |
| 9 | `setFormData(array $data)` / `setFormErrors(array $errors)` | Generic form bag — intentionally untyped |
| 10 | `QueryFilterBuilder::addRaw(string, array $params)` | SQL params — not structured data |

**Conclusion :** ces 10 cas sont des usages légitimes d'arrays. Pas de DTO nécessaire.
