# Captures d'écran — Application SST DREETS BFC

Ce document liste les captures d'écran de l'application réelle, générées automatiquement par le script `scripts/screenshot_sst.sh` (utilise le serveur PHP + agent-browser headless).

## Captures disponibles

| Fichier | Profil | Page | Description |
|---|---|---|---|
| `cu1-accueil.png` | agent.dev | `/` | Page d'accueil avec les 3 cartes de registre (RSST, RAMI, DGI) |
| `cu1-formulaire-rsst.png` | agent.dev | `?page=report_create&type=rsst` | Formulaire de création RSST rempli |
| `cu2-formulaire-rami.png` | agent.dev | `?page=report_create&type=rami` | Formulaire RAMI avec « Pour le compte de » coché et champs nature_auteur/type_acte |
| `cu3-formulaire-dgi.png` | agent.dev | `?page=report_create&type=dgi` | Formulaire DGI avec indication de procédure prioritaire |
| `cu4-repondre.png` | admin.dev | `?page=report_list&type=rsst` | Liste des signalements RSST (vue superviseur) |
| `cu5-abandonner.png` | admin.dev | `?page=report_list&type=rami` | Liste des signalements RAMI (vue superviseur) |
| `cu6-synthese.png` | admin.dev | `?page=synthesis` | Page de synthèse avec tableau par site et registre |
| `cu6-statistiques.png` | admin.dev | `?page=statistics` | Page de statistiques avec KPI et tableau |
| `cu7-utilisateurs.png` | admin.dev | `?page=users` | Gestion des utilisateurs avec rôles et sites |
| `page-preambule.png` | agent.dev | `?page=preamble` | Page Préambule avec cadre juridique et mention RGPD |
| `page-aide.png` | agent.dev | `?page=help` | Page Documentation avec profils et droits |

## Régénération

Pour régénérer les captures d'écran :

```bash
# 1. Compiler PHP (si pas déjà fait)
# Voir AGENTS.md pour les instructions

# 2. Lancer le serveur PHP
export PATH="$HOME/.local/php/bin:$PATH"
cd /chemin/vers/sst
APP_ENV=dev php -S 0.0.0.0:8200 -t public/ public/router.php &

# 3. Lancer le script de captures
bash scripts/screenshot_sst.sh
```

Les captures sont des PNG retina (device_scale_factor=2, viewport 1280×900).
