# TODO — Application SST DREETS BFC

Dernière mise à jour : 2026-07-20

---

## État actuel

| Métrique | Valeur |
|----------|--------|
| PHPStan erreurs | **0** |
| PHPStan strict rules | **installé** (phpstan-strict-rules + disallowed-calls + dead-code-detector) |
| Infection MSI | **51%** (objectif 85%) |
| Tests | **860** (1804 assertions) |
| Niveau PHPStan | **8** |
| Enums consolidés | **4** (ReportState, ReportType, UserRole, VisibilityMode) |
| Pre-commit hook | **hook .git** (PHPStan + PHPUnit) |
| Dead code detector | **shipmonk** (installé via composer) |
| Copy-paste detector | **phpcpd** (1.96% duplication, 13 blocs) |

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

## Priorité 9 — Tests e2e (bloqué, investigation terminée)

**Résultat** : 0/15 specs chargent — tous les tests échouent au chargement du module. Cause : incompatibilité ESM/CJS (`"type": "commonjs"` dans package.json vs syntaxe `import` ESM dans tous les fichiers e2e/). Possible incompatibilité Node.js v24 + Playwright 1.61.0.

**Fix recommandé** : Renommer `e2e/*.spec.js` → `e2e/*.spec.mjs` + `e2e/helpers.js` → `e2e/helpers.mjs`, ou ajouter `e2e/package.json` avec `"type": "module"`.

**Effort** : ~1h (fix config) + validation
**Statut** : Investigation terminée, pas de bug applicatif trouvé (les tests n'atteignent jamais l'app)

---

## Priorité 10 — Nettoyage @var bricolage

~145 annotations @var dans le codebase. Celles ajoutées pour le level 10 sont inutiles au level 8. Passer en revue et ne garder que les @var utiles (templates injectés, résultats PDO, doc de type).

**Effort** : ~2h (travail minutieux)
**Statut** : À faire (subagent annulé, non prioritaire)

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

## Priorité 13 — Infection MSI 51% → 85% (pause)

Le mutation score est à 51%, bien en dessous du seuil de 85%. Identifier les mutants survivants les plus critiques et ajouter des tests pour les tuer.

**Effort** : ~4-8h
**Statut** : En attente — ne pas lancer sans supervision (risque de tests qui tuent des mutants sans vérifier de vrai comportement)

---

## Priorité 14 — Nettoyage queries orphelines (investigation terminée)

Les fichiers `src/queries/report_queries.php`, `src/queries/report_response_queries.php` etc. sont probablement orphelins (migrés vers les Repository classes). Vérifier et supprimer si inutilisés — éliminerait ~60% de la duplication détectée par phpcpd.

**Résultat investigation** : 5 fichiers entièrement orphelins (user_admin, user_gdpr, stats, rami_stats, notification). 6 fichiers partiellement orphelins. `createReport()` et `updateReport()` ont des doublons SQL avec ReportRepository — createReport est identique, updateReport a divergé (pas de transaction dans la version query). `updateReport()` a 0 appelant prod (dormant, pas actif).

**Effort** : ~3-4h (migration tests + suppressions)
**Statut** : Investigation terminée, prêt pour validation. Chantier de refactorisation, pas un nettoyage rapide.

---

## Priorité 15 — ✅ Restaurer pipeline qualité — TERMINÉ

Extensions PHPStan installées via `composer require --dev` :
- phpstan/extension-installer (1.4.3)
- phpstan/phpstan-strict-rules (2.0.12)
- spaze/phpstan-disallowed-calls (v4.13.0)
- shipmonk/dead-code-detector (1.3.2)

GrumPHP + hook pre-commit déjà en place (tools/grumphp.phar).

---

## Priorité 16 — ✅ CHECK constraints reports.type/etat — TERMINÉ

Migration automatique ajoutée dans `src/migration_columns.php` :
- Vérification idempotente via `schema_version`
- Contrôle d'intégrité avant reconstruction (error_log si violation)
- Backup avant migration destructrice (`backupBeforeMigration()`)
- Table rebuild avec CHECK constraints + recréation des index
- Enregistrement dans `schema_version` après succès

---

## Priorité 17 — ✅ Isolation DB E2E — TERMINÉ

Les tests E2E écrivaient dans la vraie base `data/sst.db`. Fix :
- `src/config.php` : `DB_PATH` lit `SST_DB_PATH` env var (fallback = prod)
- `playwright.config.js` : webServer positionne `SST_DB_PATH` vers `%TEMP%\sst-e2e-test.db`
- `package.json` : nettoyé (token GitHub supprimé, URL pointe vers Codeberg)

Migration automatique ajoutée dans `src/migration_columns.php` :
- Vérification idempotente via `schema_version`
- Contrôle d'intégrité avant reconstruction (error_log si violation)
- Backup avant migration destructrice (`backupBeforeMigration()`)
- Table rebuild avec CHECK constraints + recréation des index
- Enregistrement dans `schema_version` après succès

---

## Priorité 18 — Supprimer le concept "Sites" (Unités Régionales) du projet (non prioritaire)

**Objectif** : Supprimer entièrement la notion de site/UR du projet. La table `sites`, les FK `site_id` dans `reports` et `users`, le sélecteur de site au login, le filtrage par site — tout disparaît. L'application devient mono-site (ou les sites ne sont plus gérés par l'app).

### Impact estimé (grep préliminaire)

| Couche | Fichiers concernés | Détail |
|--------|-------------------|--------|
| **DB** | `schema.sql`, `src/database.php`, `src/migration_columns.php` | Table `sites`, FK `site_id` dans `reports`/`users`/`notification_settings` |
| **Repository** | `SiteRepository.php`, `ReportRepository.php`, `UserRepository.php` | `findByCode()`, `findById()`, filtres par `site_id`, jointures `LEFT JOIN sites` |
| **Queries** | `site_queries.php` (11 fonctions), `report_queries.php`, `user_queries.php` | Toutes les fonctions site + wrappers |
| **Services** | `AccessService.php`, `ConfigService.php`, `UserService.php` | `canSeeAllSites()`, `isNoSiteMode()`, `getReportVisibility()` avec filtres site |
| **DTO** | `ReportFilter.php`, `CreateUserCommand.php`, `CreateReportCommand.php` | Champs `siteId`, `forceSiteId`, `seeAllSites` |
| **Handlers** | `settings_handler_sites.php`, `site_edit_handler.php`, `choose_site_handler.php`, `report_create_handler.php`, `user_create_handler.php`, `user_edit_handler.php` | CRUD sites + sélecteur site |
| **Pages** | `choose_site.php`, `site_edit.php`, `tab_manage_sites.php`, `settings.php`, `report_list.php`, `users.php` | UI sites |
| **Templates** | `report_card.php`, `report_form.php`, `user_form_fields.php` | Affichage site |
| **Enums** | Aucun (sites n'est pas un enum) | — |
| **Tests** | `SiteQueriesTest.php`, `RepositoryInvariantTest.php`, `ValidationUserTest.php`, etc. | Seed de sites dans setUp |

### Sous-chantiers (ordre recommandé)

1. **Schema DB** — Supprimer table `sites`, FK `site_id`, colonnes `site_text`, `site_code`, `site_nom`. Migration destructive.
2. **Repository/DTO** — Supprimer `SiteRepository`, champs `siteId`/`seeAllSites`/`forceSiteId` des DTOs et filtres.
3. **Queries** — Supprimer `site_queries.php` entièrement + wrappers dans `report_queries.php`/`user_queries.php`.
4. **Handlers** — Supprimer `settings_handler_sites.php`, `site_edit_handler.php`, `choose_site_handler.php`. Nettoyer les handlers qui passent `site_id`.
5. **Pages** — Supprimer `choose_site.php`, `site_edit.php`, `tab_manage_sites.php`. Nettoyer `settings.php`, `report_list.php`, `users.php`.
6. **Access/Auth** — Simplifier `AccessService` (plus de filtres site), `ConfigService` (supprimer `isNoSiteMode`, `app_label_unite`), login (plus de redirect choose_site).
7. **Templates** — Supprimer affichage site dans cards, forms, user forms.
8. **Tests** — Migrer tous les seeds/ assertions qui utilisent `site_id`.

### Risques

- **Données** : La migration destructive supprime les sites existants. Backup obligatoire.
- **Filtrage** : Le mode `agent_choice` et `confidential` utilisaient `site_id` pour filtrer les signalements. Sans sites, la visibilité devient simplement "par utilisateur" ou "globale".
- **Notifications** : `notification_settings` a un FK `site_id` — les notifications par site disparaissent.
- **Export** : Les exports filtraient par site — à simplifier.

**Effort** : ~8-12h (chantier de refactorisation majeur, pas un nettoyage)
**Statut** : À faire — nécessite un plan détaillé avant de commencer

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
