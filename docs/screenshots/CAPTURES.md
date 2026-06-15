# Captures d'écran — Application SST DREETS BFC

Ce document liste les captures d'écran PNG annotées de l'application réelle.

Les captures sont au format **PNG annoté** (numérotation + flèches + descriptions), pas HTML.
Elles sont intégrées dans la page de Documentation (`help.php`) via des `<img>` (imprimables).

## Captures disponibles

| Fichier | Profil | Page | Description |
|---|---|---|---|
| `cu1-accueil.png` | agent.dev | `?page=home` | Page d'accueil agent avec les 3 cartes de registre |
| `cu1-accueil-superviseur.png` | admin.dev | `?page=home` | Page d'accueil superviseur |
| `cu1-accueil-chsct.png` | chsct.dev | `?page=home` | Page d'accueil CSA/CHSCT |
| `cu2-creation-rsst.png` | agent.dev | `?page=report_create&type=rsst` | Formulaire de création RSST |
| `cu3-creation-rami.png` | agent.dev | `?page=report_create&type=rami` | Formulaire RAMI avec « Pour le compte de » et nature_auteur/type_acte |
| `cu4-creation-dgi.png` | agent.dev | `?page=report_create&type=dgi` | Formulaire DGI avec bandeau d'avertissement |
| `cu5-liste-signalements.png` | agent.dev | `?page=report_list` | Liste des signalements (vue agent) |
| `cu5-liste-signalements-sup.png` | admin.dev | `?page=report_list` | Liste des signalements (vue superviseur) |
| `cu5-voir-signalement.png` | agent.dev | `?page=report_view&uuid=...` | Vue détaillée d'un signalement RSST |
| `cu5-voir-rami.png` | agent.dev | `?page=report_view&uuid=...` | Vue détaillée d'un signalement RAMI |
| `cu5-voir-dgi.png` | agent.dev | `?page=report_view&uuid=...` | Vue détaillée d'un signalement DGI |
| `cu5-modifier-signalement.png` | agent.dev | `?page=report_edit&uuid=...` | Formulaire de modification d'un signalement |
| `cu5-repondre-signalement.png` | admin.dev | `?page=report_respond&uuid=...` | Formulaire de réponse du superviseur |
| `cu6-statistiques.png` | admin.dev | `?page=statistics` | Page des statistiques |
| `cu7-synthese.png` | chsct.dev | `?page=synthesis` | Page de synthèse par site et registre |
| `cu8-export.png` | admin.dev | `?page=export` | Page d'export des données |
| `cu9-parametres.png` | admin.dev | `?page=settings` | Paramètres (Application, SMTP, Notifications) |
| `cu10-utilisateurs.png` | admin.dev | `?page=users` | Gestion des utilisateurs |
| `cu11-journaux.png` | admin.dev | `?page=logs` | Journaux d'audit |
| `cu12-aide.png` | agent.dev | `?page=help` | Page de documentation |
| `cu13-preambule.png` | agent.dev | `?page=preamble` | Page Préambule RGPD |
| `cu14-journal-modifs.png` | agent.dev | `?page=changelog` | Historique des modifications |
| `cu15-choix-site.png` | agent.dev | `?page=choose_site` | Page de choix du site (première connexion) |

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
