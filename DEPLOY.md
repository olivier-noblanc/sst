# Guide de Déploiement — Production IIS

## Prérequis

- Windows Server 2016+ avec IIS 10+
- PHP 8.3 installé (Non-Thread-Safe recommandé pour IIS/FastCGI)
- **Composer** (gestionnaire de dépendances PHP) — voir section 3
- **PAS BESOIN** du module URL Rewrite (l'app utilise un routage par query string)
- Active Directory DREETS BFC accessible (pour l'authentification Windows)
- Module IIS **Windows Authentication** installé

### Extensions PHP requises

```
sqlite3, pdo_sqlite, mbstring, gd, xml, curl, zip
```

> L'extension `gd` est nécessaire pour la génération de PDF (mPDF). Vérifier avec `php -m | findstr gd`.

## Flux d'authentification

L'application a **deux modes d'authentification** complètement différents :

### Production (`APP_ENV=prod`)
```
Navigateur → IIS (Windows Auth automatique) → PHP reçoit $_SERVER['AUTH_USER']
```
1. IIS authentifie l'utilisateur via Windows Authentication **AVANT** que PHP ne s'exécute
2. `$_SERVER['AUTH_USER']` est **TOUJOURS** rempli (format : `DREETS-BFC\jean.martin`)
3. PHP lit `AUTH_USER`, cherche l'utilisateur en base, le crée si nécessaire (auto-provisioning)
4. **Règle de promotion automatique** : si le login figure dans la liste `app_superviseur_usernames` configurée dans `src/config.php`, l'utilisateur est automatiquement promu Superviseur lors de sa première connexion
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
   extension=gd
   extension=xml
   extension=curl
   extension=zip
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

1. Copier le contenu du projet dans `C:\inetpub\sst\`
2. **Installer les dépendances PHP avec Composer** :
   ```cmd
   cd C:\inetpub\sst
   composer install --no-dev --optimize-autoloader
   ```
   > **Si Composer n'est pas installé**, voir https://getcomposer.org/download/ — télécharger `Composer-Setup.exe` ou utiliser :
   ```cmd
   php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
   php composer-setup.php
   php -r "unlink('composer-setup.php');"
   ```
   > L'exécutable `composer.phar` peut être placé dans `C:\php\` (à côté de `php.exe`).

3. La structure doit être :
   ```
   C:\inetpub\sst\
   ├── public\           ← RACINE DU SITE IIS
   │   ├── index.php
   │   ├── web.config    ← Configuration IIS
   │   └── css\
   ├── src\              ← Inaccessible depuis le web (hiddenSegments)
   │   └── lib\          ← Parsedown.php (inclus sans Composer)
   ├── vendor\           ← Dépendances Composer (mPDF, etc.)
   │   ├── autoload.php
   │   └── mpdf\
   ├── handlers\
   ├── pages\
   ├── templates\
   ├── queries\
   ├── middleware\
   ├── data\             ← Base SQLite (accès en ÉCRITURE requis)
   ├── composer.json     ← Dépendances PHP
   └── schema.sql
   ```
4. Créer un site IIS pointant vers `C:\inetpub\sst\public\`
5. Configurer le binding (port 80 ou 443)
6. `index.php` est déjà défini comme document par défaut dans web.config

> **Note** : le dossier `vendor/` NE DOIT PAS être accessible depuis le web. Le fichier `web.config` inclut déjà `vendor/` dans les hidden segments.

### 4. Permissions IIS

Donner les permissions **IIS_IUSRS** (lecture + écriture) sur :
- `data\` — base SQLite, logs (ÉCRITURE obligatoire)
- `C:\inetpub\sessions\` — sessions PHP

Donner les permissions **IIS_IUSRS** (lecture seule) sur :
- `public\`, `src\`, `handlers\`, `pages\`, `templates\`, `vendor\`

> **Note** : mPDF utilise le dossier temp système de PHP (`sys_get_temp_dir()`) pour son cache.
> Ce dossier correspond à `upload_tmp_dir` dans `php.ini` (par défaut `C:\inetpub\uploads`).
> Il doit être accessible en écriture par IIS_IUSRS — ce qui est normalement déjà le cas.

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
- **Hidden segments** : dossiers `data/`, `src/`, `handlers/`, `pages/`, `templates/`, `queries/`, `middleware/`, `vendor/` inaccessibles
- **En-têtes de sécurité** : X-Content-Type-Options, X-Frame-Options, X-XSS-Protection
- **Erreurs détaillées** : `errorMode="Detailed"` pour voir les erreurs IIS

**Note** : PAS besoin du module URL Rewrite. L'application utilise un routage par query string (`?page=xxx`).

### 7. Initialiser la base de données

```cmd
cd C:\inetpub\sst
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
- Menu **Paramètres → Application** : nom org, label unités, visibilité agents

### 9. Configurer les superviseurs

Les premiers utilisateurs sont auto-provisionnés avec le rôle `agent`.
Pour promouvoir des utilisateurs en `superviseur`, il existe **deux méthodes** :

#### Méthode 1 : Liste de bootstrap dans config.php (recommandé pour la première installation)
Dans `src/config.php`, renseigner la liste des logins Windows à promouvoir automatiquement :
```php
$app_superviseur_usernames = ['jean.martin', 'sophie.dupont'];
```
Ces utilisateurs seront automatiquement promus au rôle `superviseur` lors de leur connexion via IIS.
Cette méthode est utile pour la **première installation** quand aucun superviseur n'existe encore en base.

#### Méthode 2 : Par un autre superviseur via l'interface
1. Se connecter en tant que Superviseur
2. Aller dans **Utilisateurs**
3. Modifier le rôle de l'utilisateur souhaité

Un superviseur peut attribuer le rôle superviseur à d'autres utilisateurs.

> **Rôle Admin** : le rôle `admin` suit le même pattern — liste de bootstrap `app_admin_usernames` dans config.php + attribution par un autre admin via l'interface. Le rôle admin dispose de droits supplémentaires (gestion des paramètres, suppression de signalements).

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
   - Extensions PHP : `sqlite3`, `pdo_sqlite`, `mbstring`, `gd`, `xml`, `curl`, `zip`
   - Dossier `vendor/` présent (Composer installé) — sinon exécuter `composer install`
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

### 13. Configuration Git derrière un proxy Kerberos

Le réseau DREETS utilise un proxy sortant avec authentification **Kerberos (Negotiate) uniquement** — NTLM est refusé. Git utilise `libcurl` en interne, donc la même méthode d'authentification que `curl` fonctionne.

#### Trouver l'adresse du proxy

```cmd
netsh winhttp show proxy
```

Ou vérifier la configuration curl existante :
```cmd
curl --verbose https://github.com 2>&1 | findstr "Proxy"
```

#### Configurer Git pour le proxy Kerberos

Git utilise `libcurl` : il suffit de lui dire d'utiliser `negotiate` comme méthode d'authentification proxy, exactement comme `curl --proxy-auth negotiate` :

```cmd
:: Adresse du proxy (adapter avec l'adresse réelle trouvée ci-dessus)
git config --global http.proxy http://PROXY_DREETS:PORT
git config --global https.proxy http://PROXY_DREETS:PORT

:: Méthode d'authentification : Negotiate (Kerberos)
git config --global http.proxyAuthMethod negotiate
```

> **Important** : `http.proxyAuthMethod negotiate` dit à libcurl d'utiliser `CURLAUTH_NEGOTIATE` (SPNEGO/Kerberos).
> C'est équivalent à `curl --proxy-auth negotiate`. Le ticket Kerberos est fourni par la session Windows.

#### Vérification

```cmd
git ls-remote https://github.com/olivier-noblanc/sst.git
```

Si la commande affiche les refs du dépôt, la configuration est correcte.

#### Composer derrière le proxy

Composer utilise aussi `libcurl`. Configurer les variables d'environnement PHP :

```cmd
:: Dans C:\php\php.ini, ajouter :
curl.cainfo = C:\php\cacert.pem

:: Télécharger les certificats CA :
cd C:\php
curl --proxy-auth negotiate -o cacert.pem https://curl.se/ca/cacert.pem
```

Pour Composer avec le proxy :
```cmd
set HTTP_PROXY=http://PROXY_DREETS:PORT
set HTTPS_PROXY=http://PROXY_DREETS:PORT
composer install --no-dev --optimize-autoloader
```

#### Dépannage proxy

- **`Failed to connect to github.com port 443`** : le proxy n'est pas configuré dans Git → exécuter les commandes `git config --global http.proxy` et `http.proxyAuthMethod negotiate`
- **`The requested URL returned error: 407`** : le proxy refuse l'authentification → vérifier que `proxyAuthMethod` est à `negotiate` (pas `ntlm`)
- **`gnutls_handshake() failed`** : problème SSL → vérifier `curl.cainfo` dans `php.ini`

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

### Erreur "Class 'Mpdf\Mpdf' not found"
1. Vérifier que `composer install` a été exécuté dans le dossier de l'application
2. Vérifier que le dossier `vendor/mpdf/` existe
3. Vérifier que `vendor/autoload.php` existe et est lisible par IIS_IUSRS
4. Relancer `composer install --no-dev --optimize-autoloader`

### Erreur mPDF "Unable to find font"
1. Vérifier que l'extension `gd` est activée dans `php.ini` : `extension=gd`
2. Vérifier avec `php -m | findstr gd`
3. Relancer IIS après modification de `php.ini` : `iisreset`

### Mise à jour des dépendances
Pour mettre à jour les dépendances PHP après une mise à jour du code :
```cmd
cd C:\inetpub\sst
composer update --no-dev --optimize-autoloader
```
