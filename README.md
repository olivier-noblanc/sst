# Application SST — DREETS Bourgogne-Franche-Comté

Plateforme des Registres en Santé et Sécurité au Travail

## Stack Technique

- **Langage** : PHP 8.3 (vanilla, aucun framework)
- **Base de données** : SQLite via PDO
- **Authentification** : IIS Windows Authentication (pas de LDAP — lecture de `$_SERVER['AUTH_USER']`)
- **Dépendances PHP** : FPDF 1.9 (génération PDF, inclus sans Composer), Parsedown (inclus sans Composer)
- **JavaScript** : AUCUN — zéro JS, tout fonctionne en PHP/HTML/CSS pur
- **Serveur de prod** : IIS 10+ avec FastCGI

## Installation sur IIS

1. Cloner le dépôt dans `C:\inetpub\sst\`
2. Configurer un site IIS pointant vers `C:\inetpub\sst\public\`
3. Activer Windows Authentication (désactiver Anonymous Authentication)
4. Installer PHP 8.3 NTS + extensions (`sqlite3`, `pdo_sqlite`, `mbstring`)
5. Donner les permissions IIS_IUSRS en écriture sur `data\`
7. Dans `src/config.php`, passer `APP_ENV` à `'prod'`
8. Accéder à l'application — la base se crée automatiquement

**Voir `DEPLOY.md` pour le guide de déploiement complet.**

## Mise à jour

Sur le serveur, exécuter en tant qu'administrateur :

```cmd
powershell -ExecutionPolicy Bypass -File C:\inetpub\sst\update_sst.ps1
```

Le script effectue : `git pull` → vérification permissions → `iisreset`

## Comptes de test (DEV_MODE)

| Identifiant | Rôle | Site |
|------------|------|------|
| admin.dev | Superviseur | UR21 Côte-d'Or |
| agent.dev | Agent | À choisir au login |
| chsct.dev | Membre CHSCT | UR25 Doubs |

Mot de passe pour tous : `test`

## Développement local

```bash
php -S localhost:8080 -t public/ public/router.php
# Ouvrir http://localhost:8080/?page=login
```

## Visibilité des signalements — 3 modes (configurable par le superviseur)

| Mode | Description |
|------|-------------|
| **Confidentiel** | L'agent ne voit que ses propres signalements. Les autres agents ne voient rien. |
| **Choix de l'agent** | L'agent choisit par signalement (public/confidentiel). Confidentiel par défaut. |
| **Visibilité publique** | Tous les signalements du site sont visibles par tous les agents du site. |

Les superviseurs et membres du CHSCT voient tous les signalements, y compris confidentiels, quel que soit le mode.

## Structure

```
C:\inetpub\sst\
├── public/          ← Racine web (point d'entrée IIS)
│   ├── index.php    ← Routeur principal
│   ├── web.config   ← Configuration IIS
│   └── css/         ← Feuilles de style
├── src/             ← Logique métier
│   ├── config.php   ← Configuration (APP_ENV, modes visibilité)
│   ├── database.php ← Connexion SQLite + auto-migration
│   ├── auth.php     ← Authentification (AUTH_USER / mock login)
│   ├── helpers.php  ← Fonctions utilitaires + getConfig()
│   ├── queries/     ← Requêtes SQL préparées
│   ├── middleware/   ← Contrôle d'accès
│   └── lib/         ← Parsedown.php, fpdf/
├── pages/           ← Pages de l'application
├── handlers/        ← Traitements des formulaires POST
├── templates/       ← Templates réutilisables (header, sidebar, footer...)
├── data/            ← Base SQLite (auto-créée, git-ignorée)
├── schema.sql       ← Schéma de la base
├── update_sst.ps1   ← Script de mise à jour automatisée
├── DEPLOY.md        ← Guide de déploiement IIS complet
├── SPEC.md          ← Spécification technique détaillée
└── README.md        ← Ce fichier
```

## Sécurité

- Requêtes SQL préparées (PDO) — Protection injection SQL
- `htmlspecialchars` via `e()` — Protection XSS
- Tokens CSRF sur tous les formulaires
- Contrôle d'accès par rôle (`requireRole`)
- Vérification d'appartenance (auteur seul peut modifier/abandonner)
- Visibilité par site + 3 modes de confidentialité
- Session sécurisée (HttpOnly, SameSite, Secure en prod)
- Échappement CSV (protection injection formule Excel)
- Validation côté serveur de tous les inputs
- Zéro JavaScript — pas de surface d'attaque XSS côté client

## 3 Registres

| Registre | Sigle | Couleur | Usage |
|----------|-------|---------|-------|
| Santé et Sécurité au Travail | RSST | Bleu | Signalements généraux |
| Agressions, Menaces et Incivilités | RAMI | Gris | Agressions verbales/physiques |
| Danger Grave et Imminent | DGI | Rouge | Dangers immédiats |

## 3 Rôles

| Rôle | Permissions |
|------|------------|
| Agent | Créer, voir (selon mode de visibilité), modifier (ses signalements) |
| Superviseur | Agent + Répondre, Abandonner, Synthèse, Export, Stats, Paramètres, Utilisateurs, Impression |
| CHSCT | Vue élargie sur tous les sites (lecture), Synthèse, Export, Stats |
