# Guide de Déploiement — Production IIS

## Prérequis

- Windows Server 2016+ avec IIS 10+
- PHP 8.3 installé (Non-Thread-Safe recommandé pour IIS/FastCGI)
- **PAS BESOIN** de Composer (FPDF est inclus directement)
- **PAS BESOIN** du module URL Rewrite (l'app utilise un routage par query string)
- Active Directory DREETS BFC accessible (pour l'authentification Windows)
- Module IIS **Windows Authentication** installé

### Extensions PHP requises

```
sqlite3, pdo_sqlite, mbstring, fileinfo
```

> - `mbstring` : nécessaire pour la conversion UTF-8 → cp1252 dans les PDF (FPDF). Vérifier avec `php -m | findstr mbstring`.
> - `fileinfo` : nécessaire pour la vérification du type MIME des pièces jointes lors de l'upload. Sans cette extension, l'ajout de pièce jointe affichera un message d'erreur. Vérifier avec `php -m | findstr fileinfo`.

## Flux d'authentification

L'application a **deux modes d'authentification** complètement différents :

### Production (`APP_ENV=prod`)
```
Navigateur → IIS (Windows Auth automatique) → PHP reçoit $_SERVER['AUTH_USER']
```
1. IIS authentifie l'utilisateur via Windows Authentication **AVANT** que PHP ne s'exécute
2. `$_SERVER['AUTH_USER']` est **TOUJOURS** rempli (format : `DREETS-BFC\jean.martin`)
3. PHP lit `AUTH_USER`, cherche l'utilisateur en base, le crée si nécessaire (auto-provisioning)
4. **Règle de promotion automatique (bootstrap)** : si le login figure dans la liste `app_superviseur_usernames` (configurée via l'interface **Paramètres → Application** et stockée en base de données), l'utilisateur est automatiquement promu Superviseur lors de sa connexion. Cette liste peut aussi être définie via la variable d'environnement `APP_SUPERVISEUR_USERNAMES` (voir §9).
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

L'application détecte automatiquement l'environnement :

- **Si `$_SERVER['AUTH_USER']` existe** (serveur IIS avec Windows Authentication) → mode **prod** automatique
- **Sinon** (Apache, Caddy, Docker, développement local) → mode **dev** avec formulaire de login

Pour forcer un mode spécifique, décommenter et modifier dans `src/config.php` :
```php
// Forcer le mode prod (même sans IIS) :
define('APP_ENV_FORCE', 'prod');

// Ou utiliser une variable d'environnement :
// SetEnv APP_ENV prod  (dans Apache .htaccess ou vhost)
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
   extension=fileinfo
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
2. La structure doit être :
   ```
   C:\inetpub\sst\
   ├── public\           ← RACINE DU SITE IIS
   │   ├── index.php
   │   ├── web.config    ← Configuration IIS
   │   └── css\
   ├── src\              ← Inaccessible depuis le web (hiddenSegments)
   │   └── lib\          ← Parsedown.php, fpdf/ (FPDF 1.9 + polices)
   ├── handlers\
   ├── pages\
   ├── templates\
   ├── queries\
   ├── middleware\
   ├── data\             ← Base SQLite (accès en ÉCRITURE requis)
   └── schema.sql
   ```
3. Créer un site IIS pointant vers `C:\inetpub\sst\public\`
5. Configurer le binding (port 80 ou 443)
6. `index.php` est déjà défini comme document par défaut dans web.config

> **Note** : PAS besoin de dossier `vendor/` — FPDF est inclus directement dans `src/lib/fpdf/`.

### 4. Permissions IIS

Donner les permissions **IIS_IUSRS** (lecture + écriture) sur :
- `data\` — base SQLite, logs (ÉCRITURE obligatoire)
- `C:\inetpub\sessions\` — sessions PHP

Donner les permissions **IIS_IUSRS** (lecture seule) sur :
- `public\`, `src\`, `handlers\`, `pages\`, `templates\`

> **Note** : FPDF génère les PDF entièrement en mémoire, sans écriture de fichiers temporaires sur disque.
> Contrairement à mPDF, il n'y a pas besoin de dossier temporaire accessible en écriture.

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
// Pour forcer la production (décommenter si nécessaire) :
// define('APP_ENV_FORCE', 'prod');
// Par défaut, l'application détecte automatiquement :
// - AUTH_USER présent (IIS) → prod
// - AUTH_USER absent → dev
```

Les paramètres SMTP et organisation sont configurables via l'interface admin :
- Menu **Paramètres → Notifications** : emails de notification
- Menu **Paramètres → SMTP** : configuration serveur mail
- Menu **Paramètres → Application** : nom org, label unités, visibilité agents

### 9. Configurer les superviseurs

Les premiers utilisateurs sont auto-provisionnés avec le rôle `agent`.
Pour promouvoir des utilisateurs en `superviseur`, il existe **deux méthodes** :

#### Méthode 1 : Promotion par un superviseur existant (méthode normale)

> **Usage** : quand au moins un superviseur existe déjà.

1. Se connecter en tant que Superviseur
2. Aller dans **Utilisateurs**
3. Modifier le rôle de l'utilisateur souhaité

Un superviseur peut attribuer le rôle superviseur à d'autres utilisateurs. C'est la méthode **recommandée en fonctionnement normal**.

#### Méthode 2 : Auto-promotion bootstrap (première installation uniquement)

> **Usage** : première installation, quand aucun superviseur n'existe encore.

Cette méthode permet à un agent de se promouvoir lui-même en superviseur. C'est intentionnel et nécessaire pour le démarrage initial : sans superviseur, personne ne pourrait en créer un via l'interface.

**Comment ça fonctionne :**

1. Se connecter avec le premier compte créé (rôle agent)
2. Aller dans **Paramètres → Application**
3. Renseigner le champ **Logins Windows des superviseurs** avec les logins séparés par des virgules (ex: `jean.martin, sophie.dupont`)
4. La promotion est **immédiate** : au rechargement de la page, l'utilisateur est superviseur

**Où est stockée la liste ?**

La liste `app_superviseur_usernames` est stockée **en base de données** (table `settings`), **pas** dans un fichier PHP.
Cela signifie que **`git pull` n'écrase jamais cette configuration**.

**⚠️ Recommandation de sécurité** : après la promotion initiale, **vider le champ** dans Paramètres → Application
(puisque les superviseurs existants peuvent en promouvoir d'autres via l'interface). Cela évite qu'un agent
non-autorisé ne soit promu si son login est ajouté par erreur à la liste.

#### Backup : variable d'environnement `APP_SUPERVISEUR_USERNAMES`

Si la base de données ne contient pas de liste (par exemple après une réinstallation), l'application utilise la variable d'environnement `APP_SUPERVISEUR_USERNAMES` comme **source de secours**.

| Priorité | Source | Survit aux `git pull` ? | Modifiable sans redémarrage ? |
|----------|--------|--------------------------|-------------------------------|
| 1 (principale) | Base de données (Paramètres → Application) | ✅ Oui | ✅ Oui, via l'interface |
| 2 (backup) | Variable d'environnement `APP_SUPERVISEUR_USERNAMES` | ✅ Oui | ❌ Nécessite un redémarrage IIS |

**Procédure complète pour IIS (FastCGI) :**

1. Ouvrir le Gestionnaire IIS → Sélectionner le site SST
2. Double-cliquer sur **FastCGI Settings** (paramètres FastCGI)
3. Sélectionner l'application `C:\php\php-cgi.exe`
4. Cliquer sur **Edit...** (Modifier)
5. Développer la section **Environment Variables** (Variables d'environnement)
6. Cliquer sur **Add...** (Ajouter) :
   - Name : `APP_SUPERVISEUR_USERNAMES`
   - Value : `jean.martin,sophie.dupont` (logins séparés par des virgules, sans espaces)
7. Cliquer sur **OK** pour fermer chaque boîte de dialogue
8. Redémarrer IIS : `iisreset`
9. L'utilisateur se connecte → il est automatiquement promu superviseur
10. Une fois la promotion effective, aller dans **Paramètres → Application** et renseigner le champ **Logins Windows des superviseurs** dans l'interface → la DB devient la source principale et l'env var n'est plus lue

> **Alternative via web.config** (moins recommandé car le fichier est dans le dépôt git) :
> ```xml
> <!-- Dans C:\inetpub\sst\public\web.config → configuration → system.webServer → fastCgi -->
> <application fullPath="C:\php\php-cgi.exe">
>   <environmentVariables>
>     <environmentVariable name="APP_SUPERVISEUR_USERNAMES" value="jean.martin,sophie.dupont" />
>   </environmentVariables>
> </application>
> ```

**Définition dans Apache :**

```apache
# Dans le VirtualHost ou .htaccess
SetEnv APP_SUPERVISEUR_USERNAMES "jean.martin,sophie.dupont"
```

**Définition en Docker :**

```yaml
environment:
  - APP_SUPERVISEUR_USERNAMES=jean.martin,sophie.dupont
```

> **Note** : la variable d'environnement n'est utilisée que si la base de données ne contient pas de liste. Dès que le champ « Logins Windows des superviseurs » est rempli dans Paramètres → Application, la DB prend le relais et l'env var est ignorée.

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
   - Extensions PHP : `sqlite3`, `pdo_sqlite`, `mbstring`, `fileinfo` (recommandée)
   - Dossier `src/lib/fpdf/` présent (FPDF inclus)
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

### Erreur "finfo not found" / message "extension fileinfo est requise"
1. L'extension `fileinfo` n'est pas activée dans `php.ini`
2. Décommenter ou ajouter `extension=fileinfo` dans `C:\php\php.ini`
3. Redémarrer IIS : `iisreset`
4. Vérifier avec `php -m | findstr fileinfo`

### Erreur "Class 'FPDF' not found"
1. Vérifier que le fichier `src/lib/fpdf/fpdf.php` existe
2. Vérifier que les fichiers de police `src/lib/fpdf/font/DejaVuSans.json` et `DejaVuSans.z` existent
3. Vérifier les permissions IIS_IUSRS sur `src/lib/fpdf/`

### Erreur "Undefined font: DejaVu"
1. Vérifier que les fichiers `DejaVuSans.json` et `DejaVuSans-Bold.json` sont dans `src/lib/fpdf/font/`
2. Vérifier que les fichiers `.z` correspondants existent
3. Vérifier les permissions de lecture sur le dossier `font/`

### Mise à jour
Pour mettre à jour l'application, utiliser le script PowerShell fourni :
```cmd
powershell -ExecutionPolicy Bypass -File C:\inetpub\sst\update_sst.ps1
```

Ou manuellement (force la synchronisation avec le remote, écrase les conflits locaux) :
```cmd
cd C:\inetpub\sst
git fetch origin main
git reset --hard origin/main
git clean -fd
iisreset
```

> **Important** : `git reset --hard` écrase les fichiers locaux par la version du remote.
> Cela évite les conflits. La base SQLite (`data\sst.db`) est préservée car elle est dans `.gitignore`.
> **Ne jamais utiliser `git pull`** sur le serveur de production — il peut créer des conflits de fusion
> qui bloquent la mise à jour.
