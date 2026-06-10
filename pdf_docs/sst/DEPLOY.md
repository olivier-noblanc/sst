# Guide de Déploiement — Production IIS

## Prérequis

- Windows Server 2016+ avec IIS 10+
- PHP 8.3 installé (Non-Thread-Safe recommandé pour IIS/FastCGI)
- **PAS BESOIN** du module URL Rewrite (l'app utilise un routage par query string)
- Active Directory DREETS BFC accessible (pour l'authentification Windows)
- Module IIS **Windows Authentication** installé

## Flux d'authentification

L'application a **deux modes d'authentification** complètement différents :

### Production (`APP_ENV=prod`)
```
Navigateur → IIS (Windows Auth automatique) → PHP reçoit $_SERVER['AUTH_USER']
```
1. IIS authentifie l'utilisateur via Windows Authentication **AVANT** que PHP ne s'exécute
2. `$_SERVER['AUTH_USER']` est **TOUJOURS** rempli (format : `DREETS-BFC\jean.martin`)
3. PHP lit `AUTH_USER`, cherche l'utilisateur en base, le crée si nécessaire (auto-provisioning)
4. **Règle de promotion automatique** : si le login commence par le préfixe configuré (par défaut `adm.`), l'utilisateur est automatiquement promu Superviseur. Exemple : `adm.olivier.noblanc` → Superviseur
5. **AUCUN formulaire de login** — la page de login n'est pas accessible en production
6. Le "déconnexion" vide la session PHP mais IIS re-authentifie automatiquement au prochain accès

### Développement (`APP_ENV=dev`)
```
Navigateur → Formulaire mock login → PHP crée la session
```
1. Pas d'IIS, pas de Windows Auth
2. Formulaire de login mock avec comptes de test (`admin.dev`, `agent.dev`, etc.)
3. Le mot de passe n'est pas vérifié (mode dev uniquement)
4. Tout login inconnu crée automatiquement un compte agent

### Configuration du mode
```php
// Dans src/config.php :
define('APP_ENV', getenv('APP_ENV') ?: 'dev');  // 'dev' par défaut
// ou
define('APP_ENV', 'prod');  // pour IIS en production
```

## Étapes de déploiement

### 1. Installer PHP sur IIS

1. Télécharger PHP 8.3 NTS depuis https://windows.php.net/download/
2. Décompresser dans `C:\php\`
3. Configurer `C:\php\php.ini` :
   ```ini
   extension=sqlite3
   extension=pdo_sqlite
   extension=mbstring
   session.save_path = "C:\inetpub\sessions"
   upload_tmp_dir = "C:\inetpub\uploads"
   date.timezone = Europe/Paris
   
   ; IMPORTANT : les erreurs PHP sont TOUJOURS affichées
   ; (configuré aussi dans config.php, mais mettre ici aussi en sécurité)
   display_errors = On
   display_startup_errors = On
   error_reporting = E_ALL
   log_errors = On
   error_log = "C:\inetpub\logs\php-errors.log"
   ```
4. Créer les dossiers :
   ```
   mkdir C:\inetpub\sessions
   mkdir C:\inetpub\uploads
   mkdir C:\inetpub\logs
   ```
5. Donner les permissions IIS_IUSRS sur ces dossiers

### 2. Configurer FastCGI dans IIS

1. Ouvrir IIS Manager
2. Sélectionner le serveur → Handler Mappings
3. Ajouter un Module Mapping :
   - Request path: `*.php`
   - Module: `FastCgiModule`
   - Executable: `C:\php\php-cgi.exe`
   - Name: `PHP83_via_FastCGI`

### 3. Déployer l'application

1. Copier le contenu du dossier `sst-app-fixed/` dans `C:\inetpub\wwwroot\sst\`
2. La structure doit être :
   ```
   C:\inetpub\wwwroot\sst\
   ├── public\           ← RACINE DU SITE IIS
   │   ├── index.php
   │   ├── web.config    ← Configuration IIS
   │   └── css\
   ├── src\              ← Inaccessible depuis le web (hiddenSegments)
   ├── handlers\
   ├── pages\
   ├── templates\
   ├── queries\
   ├── middleware\
   ├── data\             ← Base SQLite (accès en ÉCRITURE requis)
   └── schema.sql
   ```
3. Créer un site IIS pointant vers `C:\inetpub\wwwroot\sst\public\`
4. Configurer le binding (port 80 ou 443)
5. `index.php` est déjà défini comme document par défaut dans web.config

### 4. Permissions IIS

Donner les permissions **IIS_IUSRS** (lecture + écriture) sur :
- `data\` — base SQLite, logs (ÉCRITURE obligatoire)
- `C:\inetpub\sessions\` — sessions PHP

Donner les permissions **IIS_IUSRS** (lecture seule) sur :
- `public\`, `src\`, `handlers\`, `pages\`, `templates\`

### 5. Activer l'authentification Windows

1. S'assurer que le module Windows Authentication est installé :
   ```
   dism /online /enable-feature /featurename:IIS-WindowsAuthentication
   ```
2. Dans IIS Manager → Sélectionner le site → Authentication :
   - **Désactiver** : Anonymous Authentication
   - **Activer** : Windows Authentication
3. Dans Advanced Settings de Windows Authentication :
   - Activer : Extended Protection
   - Providers : Negotiate, NTLM

### 6. web.config

Le fichier `public/web.config` est déjà configuré avec :
- **Document par défaut** : `index.php` (avec `<clear />` pour éviter les doublons avec la config IIS parente)
- **Sécurité** : blocage d'accès aux fichiers `.sql`, `.db`, `.sqlite`, `.log`, `.env`, `.bak`
- **Hidden segments** : dossiers `data/`, `src/`, `handlers/`, `pages/`, `templates/`, `queries/`, `middleware/` inaccessibles
- **En-têtes de sécurité** : X-Content-Type-Options, X-Frame-Options, X-XSS-Protection
- **Erreurs détaillées** : `errorMode="Detailed"` pour voir les erreurs IIS

**Note** : PAS besoin du module URL Rewrite. L'application utilise un routage par query string (`?page=xxx`).

### 7. Initialiser la base de données

```cmd
cd C:\inetpub\wwwroot\sst
php src\database.php
```

Ou simplement accéder à l'application dans le navigateur — la base se crée automatiquement au premier accès.

### 8. Configurer l'application

Dans `src/config.php` :
```php
// Pour la production IIS :
define('APP_ENV', 'prod');
// DEV_MODE sera automatiquement false
```

Les paramètres SMTP et organisation sont configurables via l'interface admin :
- Menu **Paramètres → Notifications** : emails de notification
- Menu **Paramètres → SMTP** : configuration serveur mail
- Menu **Paramètres → Application** : nom org, label unités, préfixe admin, visibilité agents

### 9. Configurer les administrateurs

Les premiers utilisateurs sont auto-provisionnés avec le rôle `agent`.
Pour promouvoir des utilisateurs en `superviseur` automatiquement, il existe **deux mécanismes** :

#### Mécanisme 1 : Préfixe de login (recommandé)
Par défaut, tout login Windows commençant par `adm.` est automatiquement promu Superviseur.
- Exemple : `adm.olivier.noblanc` → Superviseur
- Ce préfixe est configurable dans **Paramètres → Paramètres de l'application → Préfixe de login administrateur**
- Laisser le champ vide pour désactiver cette règle
- La promotion s'applique aussi aux utilisateurs existants à leur prochaine connexion

#### Mécanisme 2 : Liste explicite de logins
1. Aller dans **Paramètres → Paramètres de l'application**
2. Ajouter les logins Windows séparés par des virgules dans le champ "Logins Windows des administrateurs" :
   ```
   jean.martin, sophie.dupont
   ```
3. Ces utilisateurs seront automatiquement promus au rôle `superviseur` lors de leur connexion

Alternativement, un administrateur peut modifier le rôle manuellement via la page **Gestion des utilisateurs**.

### 10. SMTP pour les notifications

**Option A** — Via l'interface admin (recommandé) :
1. Se connecter en tant que Superviseur
2. Aller dans Paramètres → configurer SMTP

**Option B** — Via `php.ini` :
```ini
[mail function]
SMTP = smtp.dreets.gouv.fr
smtp_port = 25
sendmail_from = noreply@dreets.gouv.fr
```

### 11. Vérification du déploiement

1. Accéder à l'URL du site dans un navigateur
2. Si page blanche ou erreur → vérifier :
   - `display_errors = On` dans `php.ini` ET dans `config.php`
   - Permissions sur `data/` (IIS_IUSRS écriture)
   - Module FastCGI configuré correctement
   - Extensions PHP : `sqlite3`, `pdo_sqlite`, `mbstring`
3. Vérifier les logs :
   - `C:\inetpub\logs\php-errors.log`
   - `data\php-error.log` (log de l'application)
   - IIS logs : `C:\inetpub\logs\LogFiles\`

### 12. Sécurité supplémentaire

- Configurer **HTTPS** avec certificat (obligatoire pour cookie_secure)
- Restreindre l'accès IP au réseau DREETS si nécessaire
- Activer le logging IIS
- Sauvegarder régulièrement `data\sst.db`
- En production stable, on peut passer `errorMode="DetailedLocalOnly"` dans web.config
  (les erreurs PHP restent visibles grâce à `display_errors=On` dans config.php)

## Dépannage IIS

### Erreur "collection dupliquée add avec value index.php"
IIS a déjà `index.php` dans la liste des documents par défaut au niveau serveur.
Le `web.config` utilise `<clear />` avant `<add value="index.php" />` pour éviter ce conflit.
Si le problème persiste, vérifiez qu'il n'y a pas d'autre web.config parent qui définit aussi index.php.

### Page blanche sans erreur
1. Vérifier `display_errors = On` dans `C:\php\php.ini`
2. Vérifier que `error_reporting = E_ALL` dans `php.ini`
3. Redémarrer IIS : `iisreset`
4. Vérifier les permissions sur `data/`
5. Ajouter un fichier `test.php` avec `<?php phpinfo();` pour vérifier que PHP fonctionne

### Erreur 500 Internal Server Error
1. Vérifier le handler FastCGI (pointe vers `php-cgi.exe`)
2. Vérifier les permissions IIS_IUSRS
3. Regarder `C:\inetpub\logs\php-errors.log`

### Erreur 404 sur index.php
1. Vérifier le document par défaut dans IIS → Default Document → ajouter `index.php`
2. Vérifier que le site pointe bien vers `public\`

### AUTH_USER vide / "Erreur de configuration : AUTH_USER non disponible"
1. Vérifier que Windows Authentication est **activée**
2. Vérifier que Anonymous Authentication est **désactivée**
3. Vérifier les Providers (Negotiate avant NTLM)
4. Si le message "AUTH_USER non disponible" s'affiche, c'est que IIS n'envoie pas l'identité Windows à PHP — vérifier la configuration Windows Auth dans IIS Manager

### Page de login inaccessible en production
C'est **NORMAL**. En production, IIS authentifie automatiquement via Windows Auth.
La page de login mock (avec admin.dev, agent.dev) n'existe qu'en mode développement.
Si vous voyez le formulaire de login en prod, c'est que `APP_ENV` est encore à `dev` dans `config.php`.
