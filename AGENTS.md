# Instructions pour les agents IA — Projet SST DREETS BFC

## Règles et préférences du projet

### Pas de manuel en Markdown
- **Ne pas créer de manuel utilisateur en .md** (MANUEL_AGENT.md, MANUEL_SUPERVISEUR.md, etc.).
- La documentation utilisateur doit être intégrée **dans l'application** (page help.php, maquettes HTML interactives dans `docs/screenshots/`).
- Les fichiers `docs/MANUEL_AGENT.md` et `docs/MANUEL_SUPERVISEUR.md` sont dans `.gitignore` et ne doivent pas exister dans le dépôt.
- Les maquettes HTML (`docs/screenshots/*.html`) servent de support visuel — elles sont autonomes, ouvertes directement dans un navigateur.

### Fichiers CSS
- Le CSS de l'application est dans **`public/css/style.css`** (fichier statique, inliné via `inlineCss()` dans les templates).
- Il n'y a **pas de `style.php`**. Ne pas créer de fichier PHP pour le CSS.

### Terminologie
- Toujours utiliser **CSA/CHSCT** (et non CHSCT seul) dans tout texte visible par l'utilisateur.
- L'identifiant de rôle dans le code reste `'chsct'` (inchangé).

### Structure du dépôt
- `docs/screenshots/` : maquettes HTML interactives + fichier CAPTURES.md
- `docs/screenshots/*.html` : maquettes autonomes (CSS embarqué), sans dépendance serveur
- `tools/` : scripts CLI (anonymize_old_reports.php, check_delays.php, backup_sst_db.ps1)
- `src/` : logique métier (queries, auth, mail, helpers, database, audit, config)
- `pages/` : pages PHP rendues côté serveur
- `handlers/` : handlers POST (création, édition, réponse, export)
- `templates/` : composants réutilisables (header, footer, form, etc.)
