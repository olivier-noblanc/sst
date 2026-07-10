# Instructions pour les agents IA — Projet SST DREETS BFC

## Règles et préférences du projet

### Pas de manuel en Markdown
- **Ne pas créer de manuel utilisateur en .md** (MANUEL_AGENT.md, MANUEL_SUPERVISEUR.md, etc.).
- La documentation utilisateur est intégrée **dans l'application** (page help.php).
- Les fichiers `docs/MANUEL_AGENT.md` et `docs/MANUEL_SUPERVISEUR.md` sont dans `.gitignore`.

### Fichiers CSS
- Le CSS de l'application est dans **`public/css/style.css`** (fichier statique, inliné via `inlineCss()` dans les templates).
- Il n'y a **pas de `style.php`**. Ne pas créer de fichier PHP pour le CSS.

### Terminologie
- Toujours utiliser **CSA/CHSCT** (et non CHSCT seul) dans tout texte visible par l'utilisateur.
- L'identifiant de rôle dans le code reste `'chsct'` (inchangé).
- Exception : les références légales exactes du Code du travail (ex : « CSE/CHSCT » dans D4132-1) sont inchangées.

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

### Structure du dépôt
- `docs/screenshots/` : captures HTML source + PNG annotés + CAPTURES.md
- `tools/` : scripts CLI manuels (capture_screenshots.py, annotate_screenshots.py, anonymize_old_reports.php, check_delays.php, backup_sst_db.ps1)
- `src/` : logique métier (queries, auth, mail, helpers, database, audit, config, cron)
- `src/helpers/` : modules utilitaires (access.php, formatting.php, http.php, config.php, crypto.php, assets.php)
- `src/cron.php` : lazy cron — tâches de maintenance déclenchées au login (check_delays + anonymize). Pas de cron système.
- `pages/` : pages PHP rendues côté serveur
- `handlers/` : handlers POST (création, édition, réponse, export)
- `templates/` : composants réutilisables (header, footer, form, user_form_fields, breadcrumb, etc.)
- `tests/` : tests unitaires PHPUnit (54 tests, 131 assertions)
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
