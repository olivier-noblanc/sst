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
- Les captures sont au format **HTML** (DOM rendu complet avec CSS inline), pas PNG.
- Elles sont générées via `render_page.php` (PHP CLI) qui rend chaque page avec les vraies données et templates.
- Voir `docs/screenshots/CAPTURES.md` pour la liste complète et la procédure de régénération.
- Les captures servies au navigateur sont dans `public/screenshots/` (copie de `docs/screenshots/`).

### Structure du dépôt
- `docs/screenshots/` : captures HTML + CAPTURES.md
- `tools/` : scripts CLI manuels (anonymize_old_reports.php, check_delays.php, backup_sst_db.ps1)
- `src/` : logique métier (queries, auth, mail, helpers, database, audit, config, cron)
- `src/cron.php` : lazy cron — tâches de maintenance déclenchées au login (check_delays + anonymize). Pas de cron système.
- `pages/` : pages PHP rendues côté serveur
- `handlers/` : handlers POST (création, édition, réponse, export)
- `templates/` : composants réutilisables (header, footer, form, etc.)
- `nuclear-reset.php` : purge des signalements (CLI uniquement, guard php_sapi_name)

## Générer les captures d'écran

```bash
# 1. Initialiser la base de données avec les données de test
/home/z/my-project/scripts/php-sst.sh /home/z/my-project/scripts/init_sst_db.php

# 2. Capturer chaque page
cd /home/z/my-project/sst-repo
/home/z/my-project/scripts/php-sst.sh render_page.php "home" "agent.dev" "docs/screenshots/cu1-accueil.html"
# ... etc. (voir CAPTURES.md pour la liste complète)

# 3. Copier les captures dans public/screenshots/
cp docs/screenshots/*.html public/screenshots/
```
