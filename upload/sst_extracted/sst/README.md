# Application SST — DREETS Bourgogne-Franche-Comté

Plateforme des Registres en Santé et Sécurité au Travail

## Stack Technique

- **Langage**: PHP 8.3 (vanilla, aucun framework)
- **Base de données**: SQLite via PDO
- **Authentification**: IIS Windows Authentication (pas de LDAP — lecture de `$_SERVER['AUTH_USER']` uniquement)
- **Dépendances**: ZÉRO (pas de Composer, pas de npm)
- **Serveur de prod**: IIS avec FastCGI

## Installation sur IIS

1. Copier le dossier `sst-app-fixed/` sur le serveur IIS
2. Configurer un site IIS pointant vers `sst-app-fixed/public/`
3. Activer Windows Authentication sur le site IIS (désactiver Anonymous Authentication)
4. Installer PHP 8.3 NTS via https://windows.php.net/download/
5. Configurer `php.ini` : extensions `sqlite3`, `pdo_sqlite`, `mbstring`, `session.save_path`, `display_errors = On`
6. Dans `src/config.php`, passer `APP_ENV` à `'prod'` (ou via variable d'environnement)
7. Donner les permissions IIS_IUSRS en écriture sur le dossier `data/`
8. Accéder à l'application — la base se crée automatiquement au premier accès
9. Configurer les notifications par email et les paramètres dans l'interface admin

**Voir `DEPLOY.md` pour le guide de déploiement complet.**

## Comptes de test (DEV_MODE)

| Identifiant | Rôle | Site |
|------------|------|------|
| admin.dev | Superviseur | Siège |
| agent.dev | Agent | UR Côte-d'Or |
| chsct.dev | Membre CHSCT | Siège |

Mot de passe pour tous : `test`

## Développement local

```bash
php -S localhost:8080 -t public/ public/router.php
# Ouvrir http://localhost:8080/?page=login
```

## Promotion automatique des superviseurs

Un seul mécanisme pour promouvoir un utilisateur en Superviseur :

- **Liste explicite des logins superviseur** : ajouter les logins Windows séparés par virgules dans **Paramètres → Application → Logins Windows des superviseurs**
  - Exemple : `jean.martin, sophie.dupont`

La promotion s'applique aussi aux utilisateurs existants à leur prochaine connexion.

Un superviseur peut attribuer le rôle Superviseur à un autre utilisateur via la gestion des utilisateurs.

## Structure

```
sst-app-fixed/
├── public/          ← Racine web (point d'entrée IIS)
│   ├── index.php    ← Routeur principal
│   ├── router.php   ← Routeur pour serveur PHP intégré (dev)
│   ├── web.config   ← Configuration IIS
│   └── css/         ← Feuilles de style
├── src/             ← Logique métier
│   ├── config.php   ← Configuration (APP_ENV, DEV_MODE)
│   ├── database.php ← Connexion SQLite + auto-migration
│   ├── auth.php     ← Authentification (AUTH_USER / mock login)
│   ├── session.php  ← Gestion des sessions + CSRF
│   ├── helpers.php  ← Fonctions utilitaires + getConfig()
│   ├── mail.php     ← Notifications email (stub)
│   ├── queries/     ← Requêtes SQL préparées
│   └── middleware/   ← Contrôle d'accès
├── pages/           ← Pages de l'application
│   ├── choose_site.php  ← Choix du site au premier login
│   └── ...
├── handlers/        ← Traitements des formulaires POST
│   ├── choose_site_handler.php  ← Validation choix site
│   └── ...
├── templates/       ← Templates réutilisables (header, sidebar, footer...)
├── data/            ← Base de données SQLite (auto-créée)
├── schema.sql       ← Schéma de la base
├── seed.php         ← Données de test
├── DEPLOY.md        ← Guide de déploiement IIS complet
├── SPEC.md          ← Spécification technique détaillée
└── README.md        ← Ce fichier
```

## Sécurité

- ✅ Requêtes SQL préparées (PDO) — Protection injection SQL
- ✅ htmlspecialchars via e() — Protection XSS
- ✅ Tokens CSRF sur tous les formulaires
- ✅ Contrôle d'accès par rôle (requireRole)
- ✅ Vérification d'appartenance (auteur seul peut modifier/abandonner)
- ✅ Visibilité par site (Agent = son site uniquement)
- ✅ Session sécurisée (HttpOnly, SameSite, Secure en prod)
- ✅ Échappement CSV (protection injection formule Excel)
- ✅ Validation côté serveur de tous les inputs

## 3 Registres

| Registre | Sigle | Couleur | Usage |
|----------|-------|---------|-------|
| Santé et Sécurité au Travail | RSST | Bleu | Signalements généraux |
| Agressions, Menaces et Incivilités | RAMI | Gris | Agressions verbales/physiques |
| Danger Grave et Imminent | DGI | Rouge | Dangers immédiats |

## 3 Rôles

| Rôle | Permissions |
|------|------------|
| Agent | Créer, voir, modifier (ses), abandonner ses signalements |
| Superviseur | Agent + Répondre, Synthèse, Export, Stats, Paramètres, Utilisateurs |
| CHSCT | Vue élargie sur tous les sites (lecture) |
