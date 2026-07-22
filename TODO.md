# TODO — Application SST DREETS BFC

Dernière mise à jour : 2026-07-22

---

## État actuel

| Métrique | Valeur |
|----------|--------|
| PHPStan erreurs | **0** (173 fichiers analysés) |
| PHPStan strict rules | **installé** (phpstan-strict-rules + disallowed-calls + dead-code-detector) |
| Infection MSI | **51%** (objectif 85%, en pause — voir Priorité 13) |
| Tests | **896** (1874 assertions) |
| Niveau PHPStan | **8** |
| Enums consolidés | **4** (ReportState, ReportType, UserRole, VisibilityMode) |
| CI | **GitHub Actions** (`.github/workflows/ci.yml` : lint + PHPStan + PHPUnit + E2E Firefox, sur chaque push/PR) + gate local `update_sst.ps1` (+ E2E msedge, bloquant) |
| Dead code detector | **shipmonk** (installé via composer) |
| Copy-paste detector | **phpcpd** (1.96% duplication, 13 blocs — pas re-mesuré depuis P14) |

### ⚠️ Pipeline qualité — État réel

| Composant | Config dans neon | Installé dans vendor/ |
|-----------|------------------|----------------------|
| GrumPHP | Non (pas de grumphp.yml) | PHAR dans tools/ |
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

## Priorité 13 — Infection MSI — 🟡 EN COURS (avancé ce soir, avec supervision)

Baseline réelle mesurée ce soir (pcov compilé pour l'occasion) : **2318 mutants, MSI 48,5%**.

**Fait ce soir** :
- `infection.json` : exclusion de `src/lib` (Parsedown, FPDF — librairies tierces, déjà exclues de PHPStan). Gain honnête sans écrire un test : nouveau total 1957 mutants, **MSI recalculé ~57,4%**.
- `ReportRepository::findPaginated()` : couverture réelle de la pagination ajoutée (offset, page par défaut) — le test précédent ne dépassait jamais la page 1. Vérifié en mutant le code à la main : le mutant sur `($page - 1) * $perPage` est bien tué ; le mutant sur la valeur par défaut `$page = 1 → 0` reste un mutant **équivalent** (SQLite traite un OFFSET négatif comme 0 — indétectable par nature, pas un trou de couverture).
- `StatsRepository::getSynthesis()` : **vrai bug trouvé et corrigé**. `COUNT(*)` comptait la ligne fantôme produite par le `LEFT JOIN` quand aucun signalement ne correspond — un site sans signalement sur l'année affichait `total: 1` au lieu de `0`. Corrigé (`COUNT(r.uuid)`). Sans impact visible actuel (le seul appelant, `pages/synthesis.php`, filtrait déjà cette ligne par accident) mais un vrai bug de contrat sur la méthode.

**Reste réellement à faire** : ~530 mutants à tuer pour atteindre 85%, sur `Repository/StatsRepository.php` (le reste), les enums `ReportState`/`ReportType`, `ConfigService`, `FormattingService`, les DTO `CreateReportCommand`/`UpdateReportCommand`, `SQLiteSessionHandler`. Beaucoup ressemblent au motif déjà écarté ce soir (plomberie `instance()`/DI, sans valeur à tester) — le ratio "mutant creusé → vrai bug trouvé" observé ce soir est d'environ 1 sur 2 quand on filtre le bruit, mais rien ne garantit qu'il tienne sur le reste.

**Effort restant** : plusieurs heures, à reprendre avec du temps dédié plutôt qu'en fin de session — pour continuer à trouver de vrais trous plutôt que de gonfler le chiffre.

---

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

## Priorité 18 — Supprimer le concept "Sites" (Unités Régionales) du projet (non prioritaire — plan détaillé, non exécuté)

**Objectif** : Supprimer entièrement la notion de site/UR du projet. La table `sites`, les FK `site_id` dans `reports`/`users`/`notification_settings`, le sélecteur de site au login, le filtrage par site — tout disparaît.

### ⚠️ Décision produit bloquante (à trancher avant tout début d'exécution)

En vérifiant `AccessService::canAccessReport()` (le portail d'accès central à un signalement), la ségrégation par site n'est **pas** un simple filtre cosmétique : pour le rôle `agent`, c'est la première porte, avant même le mode de visibilité :

```php
$reportSiteId = (int) ($report['site_id'] ?? 0);
$userSiteId = (int) ($user['site_id'] ?? 0);
if ($reportSiteId !== $userSiteId) {
    return false;   // accès refusé, sans même regarder la confidentialité
}
```

Supprimer `site_id` sans rien y substituer signifie qu'**un agent verrait potentiellement tous les signalements de toutes les anciennes UR**, y compris ceux d'agents d'autres UR — un changement de comportement de confidentialité, pas juste un nettoyage technique, sur une application qui gère des signalements réels d'incidents santé/sécurité au travail. Le TODO d'origine notait déjà : *« Sans sites, la visibilité devient simplement 'par utilisateur' ou 'globale' »* — mais **laquelle des deux** est une décision produit, pas technique, et elle change qui peut lire quoi. Je ne l'ai pas tranchée moi-même : c'est le seul point de tout ce chantier où j'ai choisi de m'arrêter plutôt que de choisir à ta place, parce que la conséquence touche la confidentialité de données réelles, pas juste la structure du code.

### Inventaire vérifié (grep exhaustif, plus large que l'estimation initiale)

**47 fichiers de code applicatif** (hors tests) référencent `site_id`/`SiteRepository`/`choose_site`/`site_code`/`site_nom`/`seeAllSites`/`isNoSiteMode`/`app_label_unite` :

| Couche | Fichiers | Détail |
|--------|----------|--------|
| **DB** | `schema.sql` | Table `sites` ; `reports.site_id` **NOT NULL** (obligatoire, pas nullable) ; `users.site_id` nullable ; `notification_settings.site_id` nullable ; 3 `FOREIGN KEY`, 4 index dédiés |
| **Repository (5)** | `SiteRepository`, `ReportRepository`, `UserRepository`, `StatsRepository`, `NotificationRepository` | Filtres, jointures `LEFT JOIN sites`, `findBySite`, agrégats par site |
| **Services (6)** | `AccessService` (⚠️ ci-dessus), `ConfigService`, `UserService`, `AuthService`, `NotificationService` | `canSeeAllSites()`, `isNoSiteMode()`, filtres site dans la logique métier |
| **DTO (4)** | `ReportFilter`, `CreateReportCommand`, `CreateUserCommand`, `UpdateUserCommand` | Champs `siteId`/`forceSiteId`/`seeAllSites` |
| **Queries** | `site_queries.php` (11 fonctions), + refs dans `report_queries.php`, `report_count_queries.php`, `user_queries.php` | |
| **Handlers (7)** | `settings_handler_sites.php`, `site_edit_handler.php`, `choose_site_handler.php`, `report_create_handler.php`, `report_abandon_handler.php`, `export_handler.php`, `settings_handler_app.php` | |
| **Pages (13)** | `choose_site.php`, `site_edit.php`, `settings.php` + `tab_app.php`/`tab_manage_sites.php`, `report_list.php`, `report_create.php`, `report_print.php`, `report_respond.php`, `statistics.php`, `synthesis.php`, `users.php`, `user_edit.php`, `user_view.php`, `export.php`, `home.php`, `guide.php`, `help.php` | |
| **Templates (3)** | `report_card.php`, `report_form.php`, `user_form_fields.php` | |
| **Auth/routing** | `src/Middleware/bootstrap.php`, `src/Router/routes.php`, `public/index.php` | Redirect post-login vers `choose_site` |
| **Migrations** | `migration_columns.php`, `migration_indexes.php`, `migration_tables.php`, `database.php` | |
| **Autres** | `src/cron.php`, `src/mail_notifications.php`, `src/mail_templates.php`, `src/validation_user.php`, `src/helpers/config.php`, `src/user_context.php` | |

**28 fichiers de test** seedent ou assertent sur `site_id`/`SiteRepository`/`createSite()`.

### Sous-chantiers (ordre recommandé, une fois la décision ci-dessus tranchée)

1. **Trancher la politique de visibilité de remplacement** pour le rôle `agent` (cf. ci-dessus) — préalable non-technique à tout le reste.
2. **Schema DB** — migration destructive : `sites`, FK `site_id` (×3), colonnes, index. Backup automatique déjà en place (`backupBeforeMigration()`) mais à vérifier explicitement avant.
3. **Repository/DTO** — supprimer `SiteRepository`, champs `siteId`/`seeAllSites`/`forceSiteId`.
4. **Queries** — supprimer `site_queries.php` + les wrappers dans les autres fichiers `queries/`.
5. **Services** — implémenter la politique tranchée à l'étape 1 dans `AccessService::canAccessReport()` ; nettoyer `ConfigService::isNoSiteMode()`/`app_label_unite`.
6. **Handlers** — supprimer les 3 handlers dédiés site ; nettoyer les 4 autres qui passent `site_id` en paramètre annexe.
7. **Pages/Templates** — supprimer les 3 pages dédiées ; nettoyer les 10 pages et 3 templates qui affichent/filtrent par site.
8. **Auth/routing** — supprimer le redirect `choose_site` post-login.
9. **Tests** — migrer les 28 fichiers (retirer les seeds `createSite()`, adapter les assertions qui dépendaient du filtrage par site).
10. **Vérification finale** — `phpstan analyse` + `phpunit --no-coverage` + relecture manuelle du comportement de visibilité par rôle.

### Risques

- **Confidentialité** : cf. décision bloquante ci-dessus — c'est le risque principal, pas la mécanique de suppression.
- **Données existantes** : migration destructive, données de site perdues (backup obligatoire, déjà scripté).
- **Notifications** : `notification_settings.site_id` — les notifications par site disparaissent, à clarifier si un mode "par site" doit survivre autrement.
- **Export** : les exports filtraient par site — à simplifier ou remplacer.

**Effort estimé (révisé après inventaire)** : 10-15h (le périmètre réel — 47 fichiers applicatifs + 28 de test — est plus large que l'estimation initiale de 8-12h).
**Statut** : Plan détaillé livré (ce que le TODO demandait comme préalable). **Non exécuté** dans cette session — la décision de politique de visibilité ci-dessus n'est pas une question technique que je peux trancher à ta place sans risquer un vrai changement de confidentialité sur des données réelles.

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
