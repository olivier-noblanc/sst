# Captures d'écran — Application SST DREETS BFC

Ce document liste les captures d'écran à réaliser pour illustrer la page d'aide in-app (`pages/help.php`). Chaque capture correspond à un emplacement `<img>` avec la classe `help-screenshot` dans le code PHP.

## Maquettes HTML interactives

Des **maquettes HTML** sont disponibles dans ce dossier pour prévisualiser l'interface sans lancer l'application. Ouvrez-les directement dans un navigateur :

| Fichier HTML | Cas d'usage | Description |
|---|---|---|
| `cu1-accueil.html` | CU1 | Page d'accueil avec les 3 cartes de registre (RSST, RAMI, DGI) |
| `cu1-formulaire-rsst.html` | CU1 | Formulaire de création d'un signalement RSST rempli |
| `cu2-formulaire-rami.html` | CU2 | Formulaire RAMI avec « Pour le compte de » coché et champs nature_auteur/type_acte |
| `cu3-formulaire-dgi.html` | CU3 | Formulaire DGI avec avertissement de procédure prioritaire |
| `cu4-repondre.html` | CU4 | Formulaire de réponse du superviseur avec changement de statut |
| `cu5-abandonner.html` | CU5 | Formulaire d'abandon d'un signalement avec champ motif |
| `cu6-synthese.html` | CU6 | Page de synthèse avec tableau par site et registre |
| `cu7-utilisateurs.html` | CU7 | Gestion des utilisateurs avec rôles et sites d'affectation |
| `cu8-pdf.html` | CU8 | Aperçu de la fiche de signalement générée en PDF |

Ces maquettes utilisent les mêmes couleurs et la même identité visuelle que l'application réelle (DREETS BFC). Elles sont autonomes (CSS embarqué) et ne nécessitent aucun serveur.

## Instructions pour les captures PNG

1. Ouvrez le fichier HTML correspondant dans un navigateur (Chrome/Edge recommandé).
2. Ajustez la fenêtre à la largeur souhaitée (1280 px recommandé).
3. Réalisez la capture en plein écran ou de la zone pertinente.
4. Enregistrez le fichier PNG dans ce dossier (`docs/screenshots/`) avec le nom exact indiqué ci-dessous.
5. Les images seront servies depuis la racine du site — le chemin `src` dans `<img>` est `docs/screenshots/[nom].png`.

## Liste des captures PNG à réaliser

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
