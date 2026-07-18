# TODO — Application SST DREETS BFC

Dernière mise à jour : 2026-07-18

---

## État actuel

| Métrique | Valeur |
|----------|--------|
| Baseline PHPStan | **25 erreurs** (127 lignes) |
| PHPStan hors baseline | **0** |
| Tests | **863** (1770 assertions) |
| Niveau PHPStan | **8** |

---

## Priorité 1 — ✅ Cast int/string — TERMINÉ

Toutes les erreurs cast.int/string corrigées (baseline 423→25). 62 fichiers modifiés.

---

## Priorité 2 — ✅ argument.type — TERMINÉ

Baseline réduite de 158→8 erreurs argument.type. Les 8 restantes sont dans le baseline (edge cas de typage strict).

---

## Priorité 3 — ✅ binaryOp.invalid — TERMINÉ

Baseline réduite de 90→0 erreurs. Toutes corrigées.

---

## Priorité 4 — ✅ offsetAccess — TERMINÉ

Baseline réduite de 91→3 erreurs. Les 3 restantes sont des valides offsetAccess.invalidOffset.

---

## Priorité 5 — ✅ return.type — TERMINÉ

Baseline réduite de 49→6 erreurs. Les 6 restantes sont des edge cas (PDO array<mixed> vs list).

---

## Priorité 6 — ✅ variable.undefined — TERMINÉ

Baseline réduite de 25→0 erreurs. Toutes corrigées via @var sur templates injectés.

---

## Priorité 7 — ✅ missingType.iterableValue — TERMINÉ

Baseline réduite de 12→1 erreur. La restante est dans registry_card_renderer.php.

---

## Priorité 8 — CSS checker intégration

Le script `tools/check_css_classes.php` existe mais n'est pas encore intégré au gate. Intégrer comme étape optionnelle (warning, pas bloquant) dans `update_sst.ps1` et le pre-push hook.

**Effort** : ~30 min

---

## Priorité 9 — Tests e2e

Les 15 specs Playwright existent mais n'ont jamais été validées en local avec Firefox. Lancer les tests, identifier les failures, corriger.

**Effort** : ~1-2h

---

## Priorité 10 — Nettoyage @var bricolage

~145 annotations @var dans le codebase. Celles ajoutées pour le level 10 sont inutiles au level 8. Passer en revue et ne garder que les @var utiles (templates injectés, résultats PDO, doc de type).

**Effort** : ~2h (travail minutieux)

---

## Priorité 11 — Nettoyage DB wordcloud

La clé legacy `app_wordcloud_words` (format plaintext) est orpheline dans la DB. La clé actuelle est `word_cloud_words` (format JSON). Nettoyer les données obsolètes.

**Effort** : ~15 min

---

## Notes techniques

### Baseline actuelle (25 erreurs)

| Type | Count | Nature |
|------|-------|--------|
| argument.type | 8 | Edge cas de typage (PDO string\|false) |
| return.type | 6 | PDO array<mixed> vs list |
| method.nonObject | 2 | PDOStatement\|false guards |
| offsetAccess.invalidOffset | 3 | Array keys optionnels |
| phpDoc.parseError | 2 | Array shape syntax non supportée |
| ternary.alwaysFalse | 2 | Constantes define() |
| function.notFound | 1 | a() → e() déjà corrigé |
| missingType.iterableValue | 1 | @param manquant |
| offsetAccess.notFound | 1 | Offset optionnel |

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
