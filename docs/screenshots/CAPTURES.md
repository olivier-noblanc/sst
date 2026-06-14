# Captures d'écran — Application SST DREETS BFC

Ce document liste les captures d'écran HTML de l'application réelle, générées par le script `render_page.php` qui utilise PHP CLI pour rendre chaque page avec les vraies données et les vrais templates.

Les captures sont au format **HTML** (DOM rendu complet avec CSS inline), pas PNG. Elles sont intégrées dans la page de Documentation (`help.php`) via des `<iframe>` toujours visibles (aucun élément pliable).

## Captures disponibles

| Fichier | Profil | Page | Description |
|---|---|---|---|
| `cu1-accueil.html` | agent.dev | `?page=home` | Page d'accueil agent avec les 3 cartes de registre |
| `cu1-accueil-superviseur.html` | admin.dev | `?page=home` | Page d'accueil superviseur |
| `cu1-accueil-chsct.html` | chsct.dev | `?page=home` | Page d'accueil CSA/CHSCT |
| `cu2-creation-rsst.html` | agent.dev | `?page=report_create&type=rsst` | Formulaire de création RSST |
| `cu3-creation-rami.html` | agent.dev | `?page=report_create&type=rami` | Formulaire RAMI avec « Pour le compte de » et nature_auteur/type_acte |
| `cu4-creation-dgi.html` | agent.dev | `?page=report_create&type=dgi` | Formulaire DGI avec bandeau d'avertissement |
| `cu5-liste-signalements.html` | agent.dev | `?page=report_list` | Liste des signalements (vue agent) |
| `cu5-liste-signalements-sup.html` | admin.dev | `?page=report_list` | Liste des signalements (vue superviseur) |
| `cu5-voir-signalement.html` | agent.dev | `?page=report_view&uuid=...` | Vue détaillée d'un signalement RSST |
| `cu5-voir-rami.html` | agent.dev | `?page=report_view&uuid=...` | Vue détaillée d'un signalement RAMI |
| `cu5-voir-dgi.html` | agent.dev | `?page=report_view&uuid=...` | Vue détaillée d'un signalement DGI |
| `cu5-modifier-signalement.html` | agent.dev | `?page=report_edit&uuid=...` | Formulaire de modification d'un signalement |
| `cu5-repondre-signalement.html` | admin.dev | `?page=report_respond&uuid=...` | Formulaire de réponse du superviseur |
| `cu6-statistiques.html` | admin.dev | `?page=statistics` | Page des statistiques |
| `cu7-synthese.html` | chsct.dev | `?page=synthesis` | Page de synthèse par site et registre |
| `cu8-export.html` | admin.dev | `?page=export` | Page d'export des données |
| `cu9-parametres.html` | admin.dev | `?page=settings` | Paramètres (Application, SMTP, Notifications) |
| `cu10-utilisateurs.html` | admin.dev | `?page=users` | Gestion des utilisateurs |
| `cu11-journaux.html` | admin.dev | `?page=logs` | Journaux d'audit |
| `cu12-aide.html` | agent.dev | `?page=help` | Page de documentation |
| `cu13-preambule.html` | agent.dev | `?page=preamble` | Page Préambule RGPD |
| `cu14-journal-modifs.html` | agent.dev | `?page=changelog` | Historique des modifications |
| `cu15-choix-site.html` | agent.dev | `?page=choose_site` | Page de choix du site (première connexion) |

## Emplacements

- **Source (dépôt Git)** : `docs/screenshots/` — fichiers HTML bruts capturés
- **Public (servis par le serveur PHP)** : `public/screenshots/` — copie servie aux navigateurs via `<iframe>`

## Régénération

Pour régénérer les captures HTML :

```bash
# 1. S'assurer que PHP CLI est disponible avec les extensions nécessaires
PHP_CMD="/home/z/my-project/scripts/php-sst.sh"

# 2. Initialiser la base de données avec les données de test
cd /home/z/my-project/sst-repo
$PHP_CMD /home/z/my-project/scripts/init_sst_db.php

# 3. Capturer chaque page avec render_page.php
$PHP_CMD render_page.php "home" "agent.dev" "docs/screenshots/cu1-accueil.html"
$PHP_CMD render_page.php "report_create&type=rsst" "agent.dev" "docs/screenshots/cu2-creation-rsst.html"
# ... etc.

# 4. Copier les fichiers dans public/screenshots/
cp docs/screenshots/*.html public/screenshots/
```

Les captures contiennent le DOM HTML complet rendu par le serveur PHP, avec la CSS inline et les données réelles de test.
