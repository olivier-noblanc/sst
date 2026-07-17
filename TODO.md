# TODO — Application SST DREETS BFC

Dernière mise à jour : 2026-07-17

---

## État actuel

| Métrique | Valeur |
|----------|--------|
| Baseline PHPStan | **423 blocs / ~640 erreurs** |
| PHPStan hors baseline | **0** |
| Tests | **857** (1542 assertions) |
| Niveau PHPStan | **10** (max) |

---

## Priorité 1 — Cast int/string (~106 erreurs restantes)

Fichiers avec le plus d'erreurs cast dans le baseline :

| Fichier | Erreurs | Difficulté |
|---------|---------|------------|
| pages/report_list.php | 12 | Facile (extraction $_GET) |
| src/Repository/ReportRepository.php | 11 | Facile (null coalescing) |
| pages/report_view.php | 4 | Facile |
| src/queries/report_queries.php | 6 | Facile |
| src/Services/NotificationService.php | 5 | Facile |
| src/mail_notifications.php | 4 | Facile |
| src/mail_templates.php | 4 | Facile |
| src/error_notify.php | 4 | Facile |
| pages/choose_site.php | 3 | Facile |
| pages/logs.php | 3 | Facile |
| pages/changelog.php | 3 | Facile |
| pages/home.php | 2 | Facile |
| pages/report_create.php | 2 | Facile |
| pages/report_abandon.php | 2 | Facile |
| pages/export.php | 2 | Facile |
| pages/report_print.php | 2 | Facible |
| src/queries/report_response_queries.php | 2 | Facile |
| src/validation.php | 2 | Facile |
| src/user_context.php | 2 | Facile |
| src/Services/ConfigService.php | 2 | Facile |
| templates/report_form.php | 2 | Facile |
| templates/report_form_linked_agents.php | 3 | Facile |
| templates/sidebar.php | 1 | Facile |
| pages/report_print_helpers.php | 1 | Facile |
| pages/report_respond.php | 1 | Facile |
| pages/response_attachment.php | 1 | Facile |
| pages/report_reopen.php | 1 | Facile |
| pages/report_edit.php | 1 | Facile |
| pages/settings/tab_sites.php | 1 | Facile |
| pages/settings/tab_manage_sites.php | 1 | Facile |
| pages/settings.php | 1 | Facile |
| src/cron.php | 1 | Facile |
| src/audit.php | 1 | Facile |
| src/validation_user.php | 1 | Facile |
| src/migration_columns.php | 3 | Facile |
| src/DTO/CreateReportCommand.php | 1 | Facile |

**Pattern de fix** : Extraire les `$_GET`/`$_POST`/`$report['field']` en variables intermédiaires `@var string`/`@var int` avant le cast.

---

## Priorité 2 — argument.type (~158 erreurs)

Les erreurs `argument.type` viennent de variables `mixed` passées à des fonctions typées. Fix : `@var` sur les variables avant l'appel.

Fichiers principaux : `src/Repository/ReportRepository.php`, `pages/report_print.php`, `pages/report_print_helpers.php`, `templates/report_form.php`, `templates/user_form_fields.php`.

---

## Priorité 3 — binaryOp.invalid (~90 erreurs)

Concaténation avec `mixed` : `"texte " . $mixedVar`. Fix : `@var string` sur la variable concaténée.

Fichiers principaux : `pages/report_print.php`, `pages/report_print_helpers.php`, `src/mail_notifications.php`, `src/Repository/ReportRepository.php`.

---

## Priorité 4 — offsetAccess (~91 erreurs)

Accès à des offsets sur `mixed` : `$mixed['key']`. Fix : `is_array()` guard ou `@var array<string, mixed>` sur la variable.

Fichiers principaux : `pages/report_print.php`, `pages/report_print_helpers.php`, `templates/report_form.php`, `src/Repository/ReportRepository.php`.

---

## Priorité 5 — return.type (~49 erreurs)

Méthodes retournant `mixed` au lieu du type déclaré. Fix : `@phpstan-var` sur les résultats PDO.

Fichiers principaux : `src/Repository/ReportRepository.php`, `src/Repository/SiteRepository.php`, `src/Services/ReportService.php`, `src/Services/UserService.php`.

---

## Priorité 6 — variable.undefined (~25 erreurs)

Variables de template non visibles par PHPStan (injectées via `extract()`/`include`). Fix : `@var` en haut du fichier.

Fichiers principaux : `pages/settings/tab_*.php`, `templates/report_form_rami.php`, `templates/report_form.php`.

---

## Priorité 7 — missingType.iterableValue (~12 erreurs)

Annotations `@param`/`@return` manquantes sur les paramètres et retours de type array. Fix : ajouter `@param array<string, mixed>` ou `@return list<...>`.

---

## Priorité 8 — CSS checker intégration

Le script `tools/check_css_classes.php` existe mais n'est pas encore intégré au gate. Intégrer comme étape optionnelle (warning, pas bloquant) dans `update_sst.ps1` et le pre-push hook.

---

## Priorité 9 — Tests e2e

Les 15 specs Playwright existent mais ne sont lancées que si Playwright est installé. Vérifier que les specs passent en local avec Firefox.

---

## Notes techniques

### Pattern de fix cast.int/string

```php
// AVANT (erreur PHPStan)
$id = (int) $_GET['id'];
$name = (string) $report['nom'];

// APRÈS (corrigé)
/** @var string */
$idStr = $_GET['id'] ?? '';
$id = (int) $idStr;

/** @var string */
$name = $report['nom'] ?? '';
```

### Pattern de fix offsetAccess

```php
// AVANT (erreur PHPStan)
$value = $report['field'];

// APRÈS (corrigé)
$value = $report['field'] ?? '';
// ou
if (is_array($report)) {
    $value = $report['field'];
}
```

### Subagents

Les subagents ne fonctionnent pas pour les casts fixes (trop de lectures de fichiers, bloquent au tour 2-3). Les fixes directs sont plus efficaces.
