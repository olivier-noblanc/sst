# TODO — Application SST DREETS BFC

Dernière mise à jour : 2026-07-19

---

## État actuel

| Métrique | Valeur |
|----------|--------|
| PHPStan erreurs | **0** |
| PHPStan strict rules | **activé** (disallowedEmpty désactivé) |
| Infection MSI | **51%** (objectif 85%) |
| Tests | **923** (1884 assertions) |
| Niveau PHPStan | **8** |
| Enums consolidés | **4** (ReportState, ReportType, UserRole, VisibilityMode) |
| Pre-commit hook | **GrumPHP** (phpstan + phpunit + phpcsfixer) |
| Dead code detector | **shipmonk** (installé, désactivé en attente nettoyage) |
| Copy-paste detector | **phpcpd** (1.96% duplication, 13 blocs) |

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
- PHPStan 548→0 erreurs (strict-rules + disallowed-calls + dead-code-detector)
- Infection configuré (minMsi=85, minCoveredMsi=90)
- GrumPHP pre-commit (phpstan + phpunit + phpcsfixer en parallèle)
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

## Priorité 12 — Activer dead-code-detector

shipmonk/dead-code-detector est installé mais désactivé (deadMethods=false). 52 dead methods identifiés par le dernier run. Nettoyer ou supprimer ces méthodes, puis activer la détection.

**Effort** : ~2-4h

---

## Priorité 13 — Infection MSI 51% → 85%

Le mutation score est à 51%, bien en dessous du seuil de 85%. Identifier les mutants survivants les plus critiques et ajouter des tests pour les tuer.

**Effort** : ~4-8h

---

## Priorité 14 — Nettoyage queries orphelines

Les fichiers `src/queries/report_queries.php`, `src/queries/report_response_queries.php` etc. sont probablement orphelins (migrés vers les Repository classes). Vérifier et supprimer si inutilisés — éliminerait ~60% de la duplication détectée par phpcpd.

**Effort** : ~1h

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
rtk vendor/bin/grumphp run

# Ré-enregistrer le hook
rtk vendor/bin/grumphp git:init
```

### Infection

```bash
# Baseline
rtk php vendor/bin/infection --show-mutations --no-progress --threads=4

# Format suppression (pour ajouter des survivors au baseline)
rtk php vendor/bin/infection --show-mutations --no-progress --threads=4 --git-diff-lines --git-diff-base=HEAD --git-diff-strategy=exclude
```
