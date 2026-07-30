# Instructions pour les agents IA — Projet SST DREETS BFC

## Règles et préférences du projet

### Pas de manuel en Markdown
- **Ne pas créer de manuel utilisateur en .md** (MANUEL_AGENT.md, MANUEL_SUPERVISEUR.md, etc.).
- La documentation utilisateur est intégrée **dans l'application** (page help.php).
- Les fichiers `docs/MANUEL_AGENT.md` et `docs/MANUEL_SUPERVISEUR.md` sont dans `.gitignore`.

### Fichiers CSS
- Le CSS de l'application est dans **`public/css/style.css`** (fichier statique, inliné via `inlineCss()` dans les templates).
- Il n'y a **pas de `style.php`**. Ne pas créer de fichier PHP pour le CSS.
- **Pas de styles inline** dans le PHP — le CSP (`web.config`) interdit `style-src 'unsafe-inline'`. Tous les styles doivent aller dans `public/css/style.css` avec des classes CSS.

### Terminologie
- Toujours utiliser **CSA/CHSCT** (et non CHSCT seul) dans tout texte visible par l'utilisateur.
- L'identifiant de rôle dans le code reste `'chsct'` (inchangé).
- Exception : les références légales exactes du Code du travail (ex : « CSE/CHSCT » dans D4132-1) sont inchangées.

### Enums — Toujours utiliser les enums, jamais de magic strings
- **JAMAIS** écrire de magic strings métier dans le code source (hors tests/seed/tools).
- Utiliser les enums : `VisibilityMode::Confidential->value`, `ReportType::Rsst->value`, `ReportState::Nouveau->value`, etc.
- Les comparaisons `=== 'confidential'` sont interdites → `=== VisibilityMode::Confidential->value`
- Les switch/case `case 'nouveau':` sont interdits → `case ReportState::Nouveau->value:`
- Les clés de tableau `$arr['rsst']` → `$arr[ReportType::Rsst->value]` quand c'est une clé métier (pas une colonne SQL)
- **Exceptions** : noms de colonnes SQL (`$row['nouveau']`), form values HTML (`value="rsst"`), seed data, tests
- PHPStan vérifie ça via la règle custom `NoMagicStringRule` (`src/PHPStan/NoMagicStringRule.php`)
- Rector peut auto-migrer les `===`/`!==` et `switch/case` via `ReplaceMagicStringWithEnumRector`
- **Nouveaux développements** : introduire les enums dès le départ, ne jamais créer de constantes string pour des valeurs métier
- **DTO typés** : préférer `public readonly VisibilityMode $visibility` à `public readonly string $visibility` quand le DTO porte une valeur d'enum — impossible de passer une string, le bug est éliminé à la compilation

### Mode sans site — convention `site_id` : 0 en entrée, NULL en vérité DB
- **En base** : `sites` est optionnelle pour un `user`/`report` — `site_id` peut être `NULL` (mode sans site). C'est la vérité, jamais `0` en colonne.
- **En entrée** (formulaires, `UpdateUserCommand`, `CreateReportCommand`, etc.) : ces DTOs déclarent `int $siteId` (non nullable, car un select HTML ne soumet jamais `null`) — `0` y sert de sentinel pour « aucun site sélectionné ».
- **Au repository** : c'est lui qui fait le pont, en convertissant explicitement `0`/vide → `NULL` juste avant l'écriture SQL (voir `UserRepository::update()`, `!empty($data['site_id']) ? $data['site_id'] : null`). Ne jamais écrire `0` littéralement en colonne `site_id`.
- **En lecture** (DTOs hydratés depuis la DB, ex. `ReportData`) : `?int $siteId` nullable, reflète la vraie valeur — ne jamais coercer un `NULL` lu en `0` (`(int) ($row['site_id'] ?? 0)` est le bug classique ici, écrase l'information réelle).
- **Dans les tests** : insérer `site_id = NULL` en SQL brut si le test n'a pas besoin d'un site précis — pas la peine de seeder une ligne `sites` juste pour satisfaire une FK. Réserver `INSERT INTO sites (...)` aux tests qui exercent vraiment une logique liée au site (filtrage par site, visibilité, etc.).

### Patterns DDD — Architecture et couche métier

**Règle : la logique métier ne vit que dans `src/Services/` ou `src/Repository/`**, jamais dans `pages/` ni `handlers/`.

**Pattern VisibilityPolicy** (quand la logique de visibilité se complexifie) :
```php
final class VisibilityPolicy
{
    public function canSee(User $user, Report $report): bool
    {
        return match ($this->visibility) {
            VisibilityMode::Confidential => $report->declarantId === $user->id || $report->isLinkedAgent($user->id),
            VisibilityMode::AgentChoice  => !$report->isConfidential || $report->declarantId === $user->id || $report->isLinkedAgent($user->id),
            VisibilityMode::Public       => true,
        };
    }
}
```
→ Évite la duplication SQL/PHP de la logique de visibilité (un seul point de vérité).

**Règle PHPStan** : SQL interdit hors `src/Repository/` — voir `NoSqlOutsideRepositoryRule` (`src/PHPStan/NoSqlOutsideRepositoryRule.php`).

**Outils d'architecture** :
- **Deptrac** (`deptrac.yaml`) — enforce les dépendances entre layers (Enum, DTO, Repository, Service, Helpers)
- **NoMagicStringRule** — interdit les magic strings métier
- **Rector** — auto-migration des patterns legacy

### Règle Rector — refactoring conséquent
- **Étudier la possibilité d'utiliser Rector** pour les refactoring touchant **50+ fichiers** ou **200+ occurrences** (migrations de fonctions, remplacements de constantes, changements de signatures).
- Si un rule Rector existant couvre le pattern, l'utiliser plutôt que le remplacement manuel.
- Créer un Rector rule dédié si aucun rule existant ne couvre le pattern.
- Exemple : la migration `getConfig()` → `ConfigService::getInstance()->get()` aurait dû être faite via Rector, pas manuellement.

### Captures d'écran
- Les captures sont au format **PNG annoté** (numérotation + flèches + descriptions).
- Elles sont générées en deux étapes : `capture_screenshots.py` (HTML→PNG via Playwright) puis `annotate_screenshots.py` (ajout des callouts via Pillow + détection de positions via Playwright).
- Les fichiers HTML source sont dans `docs/screenshots/` (DOM rendu avec CSS inline).
- Les PNG annotés finaux sont dans `public/screenshots/` (servis aux navigateurs) et copiés dans `docs/screenshots/`.
- Voir `docs/screenshots/CAPTURES.md` pour la liste complète et la procédure de régénération.

### Skills disponibles
- Avant de répondre, invoquer le skill `using-superpowers` (`.claude/skills/using-superpowers/SKILL.md`) pour découvrir et charger les skills pertinents à la tâche en cours.

### Outils PHP (PHAR)
- Les outils PHP (PHPUnit, PHPStan, PHP-CS-Fixer, Rector, PHPArkitect, Infection) sont installés en **PHAR dans les shims scoop** (`~\scoop\shims\`).
- **Toujours vérifier** si un PHAR existe dans les shims scoop avant de tenter un `composer require` ou un téléchargement.
- Les binaires sont directement dans le PATH grâce aux shims : `phpunit`, `phpstan`, `php-cs-fixer`, `rector`, `phparkitect`, `infection`.
- `composer.json` ne contient que les dépendances runtime (pas les outils dev en PHAR).
- **phive** est disponible dans le PATH pour installer des PHAR : `phive install <tool>`.

### Git — Interdctions
- **JAMAIS** modifier `git config --global` — c'est un environnement partagé.
- Toute modification git doit se faire au niveau projet : `git config --local` ou variables d'environnement.

### Rapports d'audit — jamais dans `worklog.md`/`download/`
`worklog.md` et `download/` sont gitignorés (« artefacts d'agent internes »), et donc perdus dès la fin de la session d'un agent local. Le 26/07, un audit a identifié 98 bugs ; seuls ceux repris individuellement dans `TODO.md` ont survécu — le détail des 76 restants (Batch 8/9, Medium/Low) a disparu avec la session, sans qu'aucun commit ne le conserve. Règle : tout rapport d'audit, liste de bugs, ou tout autre document destiné à être réutilisé au-delà de la session en cours va dans un chemin suivi par git (ex. `docs/audits/`), jamais uniquement dans `worklog.md` ou `download/`. Ces deux chemins restent réservés au scratch space véritablement jetable (exports temporaires, résultats d'outils intermédiaires).

### Testing — Obligatoire avant chaque push
- **TOUJOURS** lancer les tests (`rtk phpunit --no-coverage`) avant un `git push`.
- **TOUJOURS** vérifier que PHPStan passe (`rtk phpstan analyse --memory-limit=1G`) avant de push.
- **Ne jamais pusher sans test préalable.**

### TDD — Test-Driven Development obligatoire
- **Toujours écrire les tests AVANT l'implémentation** (Red → Green → Refactor).
- Ne jamais écrire l'implémentation en premier puis les tests après — c'est du test-after, pas du TDD.
- Étape 1 : écrire le ou les tests qui décrivent le comportement attendu (ils échouent).
- Étape 2 : implémenter le code minimal pour faire passer les tests.
- Étape 3 : refactoriser si nécessaire, en vérifiant que les tests passent toujours.
- Les tests sont le **spec**, pas un filet de sécurité rajouté après coup.

### Erreurs — Crash hard, jamais silencieux
- **Ne JAMAIS catcher silencieusement les erreurs, critiques ou non** (migrations DB, contraintes FK, intégrité données, handlers POST, tout le reste).
- Préférer un `RuntimeException` (ou laisser l'exception d'origine remonter) qui crash l'app plutôt qu'un `try/catch` qui log et continue.
- Un crash silencieux est **impossible à détecter en prod** — le bug passe inaperçu et les données se dégradent.
- Aucune exception à cette règle, y compris pour les handlers POST.

### Structure du dépôt
- `docs/screenshots/` : captures HTML source + PNG annotés + CAPTURES.md
- `tools/` : scripts CLI manuels (capture_screenshots.py, annotate_screenshots.py, anonymize_old_reports.php, check_delays.php, backup_sst_db.ps1)
- `src/` : logique métier (queries, auth, mail, helpers, database, audit, config, cron)
- `src/helpers/` : modules utilitaires (access.php, formatting.php, http.php, config.php, crypto.php, assets.php)
- `src/cron.php` : lazy cron — tâches de maintenance déclenchées au login (check_delays + anonymize). Pas de cron système.
- `pages/` : pages PHP rendues côté serveur
- `handlers/` : handlers POST (création, édition, réponse, export)
- `templates/` : composants réutilisables (header, footer, form, user_form_fields, breadcrumb, etc.)
- `tests/` : tests unitaires PHPUnit (896 tests, 1874 assertions)
- `nuclear-reset.php` : purge des signalements (CLI uniquement, guard php_sapi_name)

## Générer les captures d'écran

```bash
# 1. Capturer les HTML en PNG (Playwright, 1280px de large)
cd /path/to/sst
python3 tools/capture_screenshots.py

# 2. Ajouter les annotations (callouts numérotés avec flèches)
#    Les positions sont détectées automatiquement via les sélecteurs CSS dans Playwright
python3 tools/annotate_screenshots.py

# Les PNG annotés sont dans public/screenshots/ et docs/screenshots/
```

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
