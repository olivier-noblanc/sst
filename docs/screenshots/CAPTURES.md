# Captures d'écran — Application SST DREETS BFC

Ce document liste les captures d'écran PNG annotées de l'application réelle.

Les captures sont au format **PNG annoté** (numérotation + flèches + descriptions), pas HTML.
Elles sont intégrées dans la page de Documentation (`help.php`) via des `<img>` (imprimables).

## Captures disponibles

| Fichier | Profil | Page | Section aide | Description |
|---|---|---|---|---|
| `cu1-accueil.png` | agent.dev | `?page=home` | CU1 | Page d'accueil agent avec les 3 cartes de registre |
| `cu1-accueil-superviseur.png` | admin.dev | `?page=home` | CU5 | Page d'accueil superviseur |
| `cu1-accueil-chsct.png` | chsct.dev | `?page=home` | — | Page d'accueil CSA/CHSCT |
| `cu2-creation-rsst.png` | agent.dev | `?page=report_create&type=rsst` | CU1 | Formulaire de création RSST |
| `cu3-creation-rami.png` | agent.dev | `?page=report_create&type=rami` | CU2 | Formulaire RAMI avec « Pour le compte de » et nature_auteur/type_acte |
| `cu4-creation-dgi.png` | agent.dev | `?page=report_create&type=dgi` | CU3 | Formulaire DGI avec bandeau d'avertissement |
| `cu4-repondre-signalement.png` | admin.dev | `?page=report_respond&uuid=...` | CU4 | Formulaire de réponse du superviseur |
| `cu4-modifier-signalement.png` | agent.dev | `?page=report_edit&uuid=...` | CU4 | Formulaire de modification d'un signalement |
| `cu5-liste-signalements-sup.png` | admin.dev | `?page=report_list` | CU5 | Liste des signalements (vue superviseur, avec Abandonner) |
| `consultation-liste-signalements.png` | agent.dev | `?page=report_list` | Cycle de vie | Liste des signalements (vue agent) |
| `consultation-voir-rsst.png` | agent.dev | `?page=report_view&uuid=...` | Cycle de vie + CU4 | Vue détaillée d'un signalement RSST |
| `consultation-voir-rami.png` | agent.dev | `?page=report_view&uuid=...` | Cycle de vie | Vue détaillée d'un signalement RAMI |
| `consultation-voir-dgi.png` | agent.dev | `?page=report_view&uuid=...` | Cycle de vie | Vue détaillée d'un signalement DGI |
| `cu6-statistiques.png` | admin.dev | `?page=statistics` | CU6 | Page des statistiques |
| `cu7-synthese.png` | chsct.dev | `?page=synthesis` | CU6 | Page de synthèse par site et registre |
| `cu8-export.png` | admin.dev | `?page=export` | CU8 | Page d'export des données |
| `cu9-parametres.png` | admin.dev | `?page=settings` | CU7 | Paramètres (Application, SMTP, Notifications) |
| `cu10-utilisateurs.png` | admin.dev | `?page=users` | CU7 | Gestion des utilisateurs |
| `cu11-journaux.png` | admin.dev | `?page=logs` | — | Journaux d'audit |
| `cu12-aide.png` | agent.dev | `?page=help` | — | Page de documentation |
| `cu13-preambule.png` | agent.dev | `?page=preamble` | — | Page Préambule RGPD |
| `cu14-journal-modifs.png` | agent.dev | `?page=changelog` | — | Historique des modifications |
| `cu15-choix-site.png` | agent.dev | `?page=choose_site` | CU7 | Page de choix du site (première connexion) |

## Emplacements

- **Source (dépôt Git)** : `docs/screenshots/` — fichiers HTML bruts + PNG annotés
- **Public (servis par le serveur)** : `public/screenshots/` — copie PNG servie aux navigateurs via `<img>`
- Les fichiers HTML sources sont conservés pour régénération

## Régénération

```bash
# 1. Capturer les HTML en PNG (Playwright)
python3 tools/capture_screenshots.py

# 2. Ajouter les annotations (Pillow)
python3 tools/annotate_screenshots.py

# 3. Copier les PNG dans public/screenshots/ (ou lancer update_sst.ps1)
cp docs/screenshots/*.png public/screenshots/
```

Les annotations sont définies dans `tools/annotate_screenshots.py` (dictionnaire `ANNOTATIONS`).
Chaque entrée contient des tuples `(x_pct, y_pct, description)` en pourcentage de la taille de l'image.
