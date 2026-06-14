# Captures d'écran — Application SST DREETS BFC

Ce document liste les captures d'écran à réaliser pour illustrer la page d'aide in-app (`pages/help.php`). Chaque capture correspond à un emplacement `<img>` avec la classe `help-screenshot` dans le code PHP.

## Instructions

1. Connectez-vous avec le profil indiqué.
2. Naviguez vers l'URL indiquée.
3. Réalisez la capture en plein écran ou de la zone pertinente.
4. Enregistrez le fichier PNG dans ce dossier (`docs/screenshots/`) avec le nom exact indiqué.
5. Les images seront servies depuis la racine du site — le chemin `src` dans `<img>` est `docs/screenshots/[nom].png`.

## Liste des captures

### CU1 — Agent signale RSST
| Fichier | Profil | URL | Description |
|---|---|---|---|
| `cu1-accueil.png` | agent.dev | `/` | Page d'accueil montrant les 3 cartes de registre |
| `cu1-formulaire-rsst.png` | agent.dev | `/?page=report_create&type=rsst` | Formulaire de création RSST rempli |
| `cu1-confirmation.png` | agent.dev | `/?page=report_view&uuid=...` | Signalement créé avec référence et statut Nouveau |

### CU2 — Signalement RAMI pour un tiers
| Fichier | Profil | URL | Description |
|---|---|---|---|
| `cu2-formulaire-rami.png` | agent.dev | `/?page=report_create&type=rami` | Formulaire RAMI avec « Pour le compte de » coché et champs nature_auteur/type_acte visibles |
| `cu2-pour-compte-de.png` | agent.dev | `/?page=report_create&type=rami` | Détail du champ « Pour le compte de » complété avec prénom et nom |

### CU3 — Signalement DGI
| Fichier | Profil | URL | Description |
|---|---|---|---|
| `cu3-formulaire-dgi.png` | agent.dev | `/?page=report_create&type=dgi` | Formulaire DGI avec indication de procédure prioritaire |

### CU4 — Superviseur traite un signalement
| Fichier | Profil | URL | Description |
|---|---|---|---|
| `cu4-liste-signalements.png` | admin.dev | `/?page=report_list&type=rsst` | Liste des signalements RSST avec badges d'état |
| `cu4-repondre.png` | admin.dev | `/?page=report_respond&uuid=...` | Formulaire de réponse avec changement de statut |
| `cu4-traite.png` | admin.dev | `/?page=report_view&uuid=...` | Signalement traité avec réponse du superviseur |

### CU5 — Superviseur abandonne
| Fichier | Profil | URL | Description |
|---|---|---|---|
| `cu5-abandonner.png` | admin.dev | `/?page=report_abandon&uuid=...` | Formulaire d'abandon avec champ motif |

### CU6 — Membre CSA/CHSCT consulte
| Fichier | Profil | URL | Description |
|---|---|---|---|
| `cu6-synthese.png` | chsct.dev | `/?page=synthesis` | Page de synthèse avec tableau par site et registre |
| `cu6-statistiques.png` | chsct.dev | `/?page=statistics` | Page de statistiques avec KPI et tableau |

### CU7 — Superviseur gère utilisateurs
| Fichier | Profil | URL | Description |
|---|---|---|---|
| `cu7-utilisateurs.png` | admin.dev | `/?page=users` | Liste des utilisateurs avec rôles et sites |
| `cu7-parametres.png` | admin.dev | `/?page=settings&tab=app` | Paramètres de l'application |

### CU8 — Impression PDF
| Fichier | Profil | URL | Description |
|---|---|---|---|
| `cu8-pdf.png` | admin.dev | `/?page=report_print&uuid=...` | Aperçu de la fiche PDF générée |

### Pages générales
| Fichier | Profil | URL | Description |
|---|---|---|---|
| `page-preambule.png` | agent.dev | `/?page=preamble` | Page Préambule avec cadre juridique et mention RGPD |
| `page-aide.png` | agent.dev | `/?page=help` | Page Documentation avec profils et droits |
| `page-choisir-site.png` | agent.dev (nouveau) | `/?page=choose_site` | Page de choix du site à la première connexion |

---

**Temps estimé** : 30 minutes pour un opérateur familier avec l'application.
