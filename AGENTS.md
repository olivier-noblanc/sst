# Instructions pour les agents IA — Projet SST DREETS BFC

## Règles et préférences du projet

### Pas de manuel en Markdown
- **Ne pas créer de manuel utilisateur en .md** (MANUEL_AGENT.md, MANUEL_SUPERVISEUR.md, etc.).
- La documentation utilisateur est intégrée **dans l'application** (page help.php).
- Les fichiers `docs/MANUEL_AGENT.md` et `docs/MANUEL_SUPERVISEUR.md` sont dans `.gitignore`.
- Les **captures d'écran** de l'application réelle sont dans `docs/screenshots/*.png`.

### Fichiers CSS
- Le CSS de l'application est dans **`public/css/style.css`** (fichier statique, inliné via `inlineCss()` dans les templates).
- Il n'y a **pas de `style.php`**. Ne pas créer de fichier PHP pour le CSS.

### Terminologie
- Toujours utiliser **CSA/CHSCT** (et non CHSCT seul) dans tout texte visible par l'utilisateur.
- L'identifiant de rôle dans le code reste `'chsct'` (inchangé).

### Captures d'écran
- Les captures d'écran sont des **PNG de l'application réelle** (pas des maquettes HTML).
- Elles sont générées via `scripts/screenshot_sst.sh` (serveur PHP + agent-browser headless).
- Voir `docs/screenshots/CAPTURES.md` pour la liste complète.

### Structure du dépôt
- `docs/screenshots/` : captures d'écran PNG + CAPTURES.md
- `scripts/` : scripts de génération (screenshots, etc.)
- `tools/` : scripts CLI (anonymize_old_reports.php, check_delays.php, backup_sst_db.ps1)
- `src/` : logique métier (queries, auth, mail, helpers, database, audit, config)
- `pages/` : pages PHP rendues côté serveur
- `handlers/` : handlers POST (création, édition, réponse, export)
- `templates/` : composants réutilisables (header, footer, form, etc.)

## Compiler PHP (environnement de build)

Si PHP n'est pas disponible sur le système, le compiler depuis les sources :

```bash
# Télécharger
curl -sL https://www.php.net/distributions/php-8.3.7.tar.gz -o /tmp/php-src.tar.gz
cd /tmp && tar xzf php-src.tar.gz

# Configurer (SQLite + session + XML, pas besoin de mbstring)
cd php-8.3.7
./configure \
  --prefix=$HOME/.local/php \
  --disable-all \
  --enable-pdo --with-pdo-sqlite --with-sqlite3 \
  --enable-session --enable-filter --enable-ctype \
  --enable-simplexml --enable-xml --with-libxml \
  --enable-fileinfo --enable-phar \
  --disable-cgi --disable-phpdbg --disable-zts

# Compiler et installer
make -j$(nproc) && make install

# Vérifier
$HOME/.local/php/bin/php -v
```

## Générer les captures d'écran

```bash
export PATH="$HOME/.local/php/bin:$PATH"
cd /chemin/vers/sst

# Lancer le serveur PHP (doit rester dans le même shell)
APP_ENV=dev php -S 0.0.0.0:8200 -t public/ public/router.php &

# Lancer le script de captures
bash scripts/screenshot_sst.sh
```

**Note** : le serveur PHP built-in est mono-thread. Utiliser `0.0.0.0` (pas `127.0.0.1`) car le navigateur headless peut ne pas accéder à localhost selon l'environnement.
