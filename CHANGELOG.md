# Changelog — Application SST DREETS BFC

Toutes les modifications notables de ce projet sont documentées dans ce fichier.


## [3.7.2] — 2026-06-12

### Audit — Accessibilité, Cache-Control, Server header

- **1** 🔴 **ARIA : élément caché ne doit pas être focusable** — Le checkbox `#sidebar-toggle` avait `aria-hidden="true"` mais restait techniquement focusable au clavier (violation axe-core « ARIA hidden element must not contain focusable elements »). Remplacement par `tabindex="-1"` sans `aria-hidden` : l'attribut `hidden` le cache déjà de l'arbre d'accessibilité, `tabindex="-1"` empêche le focus programme.
- **2** 🔴 **Cache-Control : `no-cache` sans `max-age=0`** — L'audit signalait que `max-age` ne doit pas coexister avec `no-cache` (redondance). Remplacement de `Cache-Control: no-cache, max-age=0` par `Cache-Control: no-cache` dans 7 fichiers (header.php, login.php, choose_site.php, report_print.php, report_attachment.php, export_handler.php, user_edit_handler.php). La directive `no-cache` seule suffit : elle impose la revalidation systématique.
- **3** 🔴 **Header `Server: microsoft-iis/10.0` supprimé via web.config** — IIS ajoute le header Server APRÈS PHP, rendant `header_remove('Server')` inefficace. Ajout d'une règle URL Rewrite sortante dans `web.config` qui remplace la valeur de `RESPONSE_Server` par une chaîne vide. Prérequis : module URL Rewrite installé sur IIS (déjà requis par le projet).
- **4** 🟡 **Favicon Content-Type standardisé** — `image/x-icon` remplacé par `image/vnd.microsoft.icon` (type MIME IANA officiel) dans `asset.php` et `router.php`. Pas de `charset` sur les types binaires.
- **5** 🟡 **Faux positifs documentés** — L'audit signale que le `content-type` des assets CSS et favicon devrait être `text/html` : c'est une erreur de l'outil d'audit (les types `text/css` et `image/vnd.microsoft.icon` sont corrects). L'audit recommande aussi un `max-age ≤ 180` pour les assets versionnés avec `immutable` : c'est un faux positif (les assets versionnés avec cache busting doivent avoir un long cache pour la performance).

---

## [3.7.1] — 2026-06-12

### Correctif — Migration report_responses bloquée

- **1** 🔴 **`report_responses_new` orphelin bloquait la migration** — La migration rendant `report_id` nullable échouait à chaque requête car une table `report_responses_new` résiduelle d'une précédente tentative avortée existait déjà (`CREATE TABLE report_responses_new` → erreur « table already exists »). La migration ne passait jamais, laissant `report_id NOT NULL`, ce qui causait l'erreur `Integrity constraint violation: 19 NOT NULL constraint failed: report_responses.report_id` lors de l'INSERT par `respondToReport()`. Correction : la migration supprime d'abord la table orpheline `_new` si elle existe, puis nettoie aussi en cas d'échec (`DROP TABLE IF EXISTS report_responses_new` dans le catch).

### Fonctionnalité — Notification par e-mail lors d'un changement de rôle

- **2** 🔴 **E-mail de notification automatique lors d'un changement de rôle** — Nouvelle fonction `notifyRoleChange()` dans `src/mail.php`. Lorsqu'un superviseur modifie le rôle d'un utilisateur, un e-mail est envoyé à l'utilisateur pour l'informer du changement (ancien rôle → nouveau rôle) avec une description des permissions associées au nouveau rôle.
- **3** 🟡 **Case à cocher « Avertir l'utilisateur par e-mail »** — Dans la page d'édition d'un utilisateur (`user_edit.php`), une case à cocher apparaît lorsque le rôle sélectionné diffère du rôle actuel ET que l'utilisateur a une adresse e-mail. Elle est cochée par défaut. Si l'utilisateur n'a pas d'e-mail, un message ⚠ est affiché dans le flash de succès.
- **4** 🟢 **CSS `.checkbox-label`** — Nouvelle classe CSS pour le style des labels de checkbox (flex, gap, cursor pointer).

---

## [3.7.0] — 2026-06-12

### Fonctionnalité — Notifications e-mail automatiques en cas d'erreur critique

- **1** 🔴 **Gestionnaire d'erreurs personnalisé** — Nouveau module `src/error_handler.php` qui intercepte toutes les erreurs PHP et envoie automatiquement un e-mail à l'administrateur technique configuré lorsque des erreurs critiques surviennent (Fatal error, Parse error, Core error, Compile error, Recoverable error). Les notices, warnings et deprecated ne déclenchent pas d'e-mail pour éviter le bruit.
- **2** 🔴 **Clé de configuration `app_admin_email`** — Nouvelle clé dans la table `config_app`, configurable via l'onglet « Paramètres de l'application ». L'adresse e-mail de l'administrateur technique reçoit les alertes automatiques. Laissez vide pour désactiver les notifications.
- **3** 🟡 **Anti-spam : limitation d'envoi** — Une même erreur ne déclenche qu'un seul e-mail toutes les 5 minutes (throttle basé sur un hash de l'erreur). Le fichier `data/error-throttle.json` stocke les horodatages des derniers envois. Les entrées de plus d'une heure sont automatiquement nettoyées.
- **4** 🟡 **E-mail détaillé avec contexte** — Chaque notification contient : le type d'erreur, le message, le fichier et la ligne, l'URL de la requête, l'adresse IP, la date/heure, et un lien vers le journal d'erreurs dans l'interface.
- **5** 🟡 **Champ « E-mail administrateur technique » dans les paramètres** — Nouveau champ dans l'onglet « Paramètres de l'application » de la page Paramètres, avec validation de l'adresse e-mail. Un texte d'aide explique le fonctionnement du throttle et renvoie vers la page Journal d'erreurs.
- **6** 🟢 **Journal d'erreurs : catégorie `[SST-ERROR-MAIL]`** — Les entrées de log liées aux notifications d'erreurs sont désormais catégorisées sous « E-mail » dans le journal d'erreurs (page Logs), au même titre que `[SST-MAIL]`.
- **7** 🟡 **Shutdown handler pour erreurs fatales** — En plus du `set_error_handler()`, un `register_shutdown_function()` attrape les erreurs fatales (E_ERROR, E_PARSE, etc.) qui bypassent le error handler standard.

---

## [3.6.0] — 2026-06-12

### Sécurité — Tout passe par l'authentification Windows

- **1** 🔴 **Accès anonyme supprimé pour `asset.php`** — Le bloc `<location path="asset.php">` qui activait l'authentification anonyme pour ce script a été supprimé du `web.config`. Désormais, **toutes les requêtes** — y compris les assets statiques (CSS, images, fonts) — nécessitent une authentification Windows. Aucune ressource de l'application n'est accessible sans authentification préalable.
- **2** 🔴 **Accès direct aux assets statiques bloqué par IIS** — Les répertoires `css/`, `img/`, `fonts/` et `js/` sont ajoutés aux `<hiddenSegments>` du `web.config`. Toute requête HTTP directe vers ces répertoires (ex: `/css/style.css`) renvoie une erreur 404 par IIS. Seul `asset.php` peut lire ces fichiers via le système de fichiers PHP (`readfile()`), qui n'est pas affecté par les hiddenSegments IIS. Cela garantit qu'aucun asset ne peut être servi sans passer par PHP et donc sans authentification Windows.
- **3** 🟡 **`asset.php` — Documentation mise à jour** — Les commentaires du script documentent désormais le fait que l'authentification Windows est requise en production, et que l'accès direct aux répertoires d'assets est bloqué par le `web.config`.
- **4** 🟡 **`web.config` — Commentaires clarifiés** — Les commentaires expliquent que TOUT passe par Windows Auth, qu'aucune exception d'accès anonyme n'existe, et pourquoi les hiddenSegments sont utilisés pour forcer le passage par `asset.php`.

---

## [3.5.0] — 2026-06-12

### Serveur d'assets PHP — Contrôle total des headers HTTP

- **1** 🔴 **`asset.php` — Serveur d'assets statiques en PHP** — Nouveau fichier `public/asset.php` qui sert TOUS les assets statiques (CSS, images, fonts, icônes) via PHP au lieu d'IIS. Cela donne un contrôle total sur les headers HTTP : `Content-Type` avec charset, `X-Content-Type-Options: nosniff`, `Cache-Control` avec `immutable` pour les assets versionnés, `ETag` pour les 304, `Last-Modified`, suppression de `X-Powered-By`/`Server`/`Expires`/`Pragma`. Sécurité : whitelist d'extensions, whitelist de répertoires, prévention de directory traversal.
- **2** 🔴 **`assetUrl()` route via `asset.php`** — La fonction `assetUrl('css/style.css')` génère désormais `asset.php?f=css/style.css&v=3.5.0` au lieu de `css/style.css?v=3.5.0`. Tous les assets passent par le serveur PHP.
- **3** 🔴 **Cache-Control `immutable`** — Les assets versionnés (`?v=`) reçoivent `Cache-Control: public, max-age=..., immutable`. Le flag `immutable` indique au navigateur que le contenu ne changera jamais pendant la durée du cache, éliminant les revalidations inutiles.
- **4** 🟡 **Support ETag + 304 Not Modified** — `asset.php` génère un ETag basé sur `filemtime` + `filesize` + `crc32` du chemin. Si le client envoie `If-None-Match` ou `If-Modified-Since`, le serveur répond `304 Not Modified` sans renvoyer le contenu.
- **5** 🟡 **Favicons servis via `asset.php`** — Les favicons (`favicon.png`, `favicon.ico`) dans `header.php` passent désormais par `assetUrl()` au lieu d'URLs directes.
- **6** 🟡 **`web.config` : accès anonyme pour `asset.php`** — Ajout d'une `<location path="asset.php">` permettant l'authentification anonyme uniquement pour ce script. Les assets n'ont pas besoin d'authentification Windows, éliminant la surcharge NTLM/Kerberos sur chaque requête CSS/image/font.
- **7** 🟡 **CSP mise à jour** — Suppression de `script-src 'self'` (plus de JS du tout). La CSP est désormais `default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'`.

### Correctif — Réponse superviseur toujours en erreur

- **8** 🔴 **`respondToReport()` retourne `'true'` (string) mais le handler comparait avec `true` (booléen)** — La comparaison stricte `$result === true` échouait systématiquement car la fonction retourne la chaîne `'true'`, pas le booléen. Le superviseur ne pouvait jamais enregistrer de réponse — l'erreur « Erreur lors de l'enregistrement de la réponse » s'affichait à chaque tentative. Correction : `$result === 'true'` (comparaison de chaînes).
- **9** 🟡 **Logging amélioré en cas d'échec** — Ajout de `error_log()` avec le contexte complet (result, user_id, report_uuid, nouvel_etat) pour faciliter le diagnostic si l'erreur se reproduit.

### Fonctionnalité — Journal d'erreurs dans l'interface

- **10** 🟢 **Page « Journal d'erreurs » dans le sidebar** — Nouvelle page `logs` accessible uniquement aux superviseurs, affichant le contenu du fichier `data/php-error.log` directement dans l'interface. Fini la nécessité d'accéder au serveur pour lire les logs. Les entrées sont affichées les plus récentes en premier, avec un affichage terminal sombre (Catppuccin) et une coloration par catégorie.
- **11** 🟢 **Filtrage par catégorie** — Onglets de filtre : Tout, Fatal, Warnings, Base de données, E-mail, Réponses, Sauvegarde, Migration. Chaque catégorie a sa couleur de badge et sa bordure latérale.
- **12** 🟢 **Bouton « Effacer les logs »** — Permet de vider le fichier de log en un clic (protégé par CSRF token et confirmation).
- **13** 🟡 **Sidebar : entrée « Journal »** — Nouvel item dans le menu sidebar (icône 📜), visible uniquement pour les superviseurs, entre « Paramètres » et le footer.

---

## [3.4.0] — 2026-06-12

### Audit — Sécurité HTTP, cache busting, zéro JavaScript

- **1** 🔴 **Cache busting sur les assets statiques** — La fonction `assetUrl()` ne faisait qu'ajouter le chemin brut sans paramètre de version. Ajout de `?v=APP_VERSION` pour forcer le navigateur à recharger les ressources CSS/JS/images après chaque déploiement. Cela résout le signal « Resource should use cache busting but URL does not match configured patterns ».
- **2** 🔴 **Suppression du header `Server` par `header_remove()`** — Tous les fichiers utilisaient `header('Server: ')` qui est inefficace sur IIS (le serveur réinsère sa valeur). Remplacement systématique par `header_remove('Server')` dans `index.php`, `header.php`, `login.php`, `choose_site.php`, `report_print.php`, `report_attachment.php`, `export_handler.php`, `user_edit_handler.php` et `router.php`. Le header `Server` ne doit contenir que le nom du serveur, sans version.
- **3** 🔴 **Suppression des headers dépréciés `Expires` et `Pragma`** — Ces headers sont obsolètes et remplacés par `Cache-Control`. Ajout de `header_remove('Expires')` et `header_remove('Pragma')` dans tous les points d'entrée PHP pour nettoyer les headers HTTP.
- **4** 🔴 **`X-Content-Type-Options: nosniff` appliqué à TOUS les assets statiques** — Le `router.php` ne l'ajoutait que pour les assets texte (css, js, json, svg). Désormais ajouté sur TOUS les assets statiques (images, fonts incluses) pour empêcher le MIME sniffing.
- **5** 🟡 **Cache-Control intelligent dans `router.php`** — Les assets non versionnés (sans `?v=`) ont désormais un `max-age=180` (3 minutes) au lieu d'une longue durée, pour éviter les problèmes de cache périmé. Les assets versionnés conservent les longues durées (7 jours CSS/JS, 30 jours images, 1 an fonts).
- **6** 🔴 **Suppression complète du JavaScript** — Conformément à la contrainte de durabilité 10 ans (zéro JS) :
  - **Sidebar** : Remplacement du JS de toggle mobile par un checkbox CSS-only (`#sidebar-toggle`). Le label hamburger dans le header coche/décoche la checkbox cachée. CSS `:checked ~ .sidebar` ouvre le panneau. L'overlay est aussi un label pour la checkbox (cliquer ferme).
  - **Bouton « retour en haut »** : Remplacement du JS `scroll`/`click` par un simple lien `<a href="#top">` qui utilise l'ancre `#top` placée en début de `<main>`. Plus besoin de JS pour la visibilité ni le défilement.
- **7** 🟡 **Sécurité CSP : `frame-ancestors 'none'` remplace `X-Frame-Options`** — Suppression implicite de tout header `X-Frame-Options` (aucun n'était émis, mais le commentaire est clarifié). CSP `frame-ancestors 'none'` est le mécanisme moderne, avec un support plus large et des vérifications plus strictes.
- **8** 🟡 **Header menu button → `<label>`** — Le bouton hamburger était un `<button>` qui nécessitait JS. Transformé en `<label for="sidebar-toggle">` pour fonctionner avec le checkbox hack CSS-only. Ajout de `tabindex="0"` pour l'accessibilité clavier.

## [3.3.0] — 2026-06-12

### Audit — Conformité 10/10 (compatibilité, performance, sécurité, bonnes pratiques)

- **1** 🔴 **`-webkit-user-select` ajouté pour Safari** — Les propriétés `user-select: none` dans `style.css` n'avaient pas le préfixe vendor `-webkit-user-select`, rendant la sélection impossible à désactiver sur Safari 3+. Ajouté sur `.breadcrumb__separator` et `th`.
- **2** 🔴 **`Content-Type` charset uniformisé en minuscules** — Les headers `charset=UTF-8` (majuscule) de `export_handler.php` et `user_edit_handler.php` normalisés en `charset=utf-8` pour respecter la RFC 2616 (section 3.4 : les valeurs de paramètre sont case-insensitive mais la convention est minuscule).
- **3** 🔴 **`Cache-Control` nettoyé** — Les directives `no-store` et `must-revalidate` étaient signalées comme non recommandées par l'audit. Remplacement uniforme par `no-cache, max-age=0` sur toutes les pages dynamiques (header.php, login.php, choose_site.php, report_print.php, report_attachment.php, export_handler.php, user_edit_handler.php). La directive `no-cache` demande au navigateur de revalider avant d'utiliser le cache, ce qui est le comportement souhaité sans les effets de bord de `no-store`.
- **4** 🟡 **CSP : ajout de `script-src 'self'`** — La Content-Security-Policy ne déclarait pas `script-src`, héritant de `default-src 'self'`. Ajout explicite de `script-src 'self'` pour documenter l'intention et éviter l'interprétation bloquant les scripts inline éventuels. Maintenu `style-src 'self' 'unsafe-inline'` pour les classes utilitaires CSS.
- **5** 🟡 **`X-Content-Type-Options: nosniff` ajouté sur toutes les réponses** — Les réponses binaires (report_attachment.php, report_print.php) et les téléchargements (export_handler.php, user_edit_handler.php) n'avaient pas ce header. Ajouté systématiquement.
- **6** 🔴 **`X-Powered-By` supprimé + `Server` nettoyé** — `header_remove('X-Powered-By')` ajouté dans router.php (manquant). `header('Server: ')` ajouté dans index.php, router.php, header.php, login.php, choose_site.php, report_attachment.php, report_print.php, export_handler.php, user_edit_handler.php pour masquer la version du serveur.
- **7** 🔴 **Headers dépréciés supprimés de FPDF** — `Pragma: public` et `must-revalidate` retirés du header FPDF (`fpdf.php`). Le PDF utilise désormais `Cache-Control: private, max-age=0`.
- **8** 🟢 **71 styles inline migrés vers CSS externe** — Tous les attributs `style="..."` (71 occurrences dans 14 fichiers PHP) ont été remplacés par 27 nouvelles classes CSS et 7 classes existantes réutilisées. Plus aucun style inline ne subsiste dans les templates, conformément aux bonnes pratiques de séparation contenu/présentation.

---

## [3.2.1] — 2026-06-12

### Infrastructure — Suppression .htaccess + web.config minimal

- **1** 🔴 **`.htaccess` supprimé** — L'application est déployée sur IIS, le `.htaccess` est inutile et source de confusion. Tout est géré par PHP.
- **2** 🟡 **`web.config` réduit au strict minimum** — Suppression de `customHeaders` (CSP, nosniff, etc. → gérés par PHP), suppression de `clientCache` (géré par PHP), suppression de `httpErrors`. Ne reste que : document par défaut, protection fichiers/dossiers sensibles, MIME types manquants (.woff, .woff2, .svg), authentification Windows.

---

## [3.2.0] — 2026-06-12

### Correctif — CSS non chargé + headers HTTP conformes

- **1** 🔴 **CSS servi avec `application/octet-stream` au lieu de `text/css`** — Le `.htaccess` appliquait `Header always set` sur TOUTES les réponses (CSS inclus), ce qui ajoutait CSP, X-Frame-Options, Cache-Control no-cache sur les assets statiques. Le navigateur bloquait le CSS. Correction : les security headers sont désormais envoyés uniquement par PHP (header.php), le `.htaccess` ne gère plus que le cache statique et les MIME types.
- **2** 🔴 **`Cache-Control` manquant sur les assets statiques** — Les fichiers CSS/JS/images n'avaient pas de `Cache-Control` propre. Le `.htaccess`, `router.php` et `web.config` servent désormais les assets statiques avec les bons headers : CSS/JS 7 jours, images 30 jours, fonts 1 an.
- **3** 🟡 **`Content-Type: text/css; charset=utf-8`** — Le CSS est désormais servi avec le charset explicite. Le `router.php` ajoute `charset=utf-8` à tous les types texte (CSS, JS, JSON, SVG).
- **4** 🟡 **Headers dépréciés supprimés** — `Pragma` (requête uniquement, pas réponse), `Expires` (remplacé par `Cache-Control`), `X-Frame-Options` (remplacé par CSP `frame-ancestors 'none'`), `X-XSS-Protection` (remplacé par CSP) supprimés de `header.php`, `login.php`, `choose_site.php`, `report_print.php`, `report_attachment.php`, `export_handler.php`, `user_edit_handler.php`.
- **5** 🟡 **`X-Powered-By` supprimé** — `header_remove('X-Powered-By')` ajouté dans `index.php`, `header.php`, `login.php` et `choose_site.php` pour ne pas divulguer la version PHP.
- **6** 🟡 **`web.config` IIS corrigé** — `staticContent` réécrit avec MIME types explicites et Cache-Control par type d'asset. `customHeaders` réduit aux seuls headers utiles (CSP, nosniff, Referrer-Policy, Permissions-Policy). `X-Frame-Options` et `X-XSS-Protection` retirés (redondants avec CSP).
- **7** 🟡 **`router.php` réécrit** — Les fichiers statiques sont servis AVANT l'output buffering gzip. Chaque type MIME a son charset. Seuls les assets texte ont `X-Content-Type-Options: nosniff`.

---

## [3.1.0] — 2026-06-12

### Accessibilité — WCAG 2.1 (10/10)

- **1** 🔴 **`<div class="main">` → `<main>`** — Le conteneur principal est désormais un landmark sémantique `<main id="main-content" role="main">`, conforme WCAG 2.1. Le skip-link pointe sur un véritable landmark.
- **2** 🔴 **Login sans landmark ni skip-link** — Ajout de `<main role="main">` et d'un skip-link « Aller au formulaire de connexion » sur la page de connexion (standalone, sans header.php).
- **3** 🟡 **`aria-describedby` sur tous les `.form-hint`** — Chaque hint de formulaire est désormais lié à son champ via `aria-describedby` : report_form (lieu, objet, description, attachment), login (password), users (username), user_edit (username), settings (emails), export (dates), report_respond (réponse).
- **4** 🟡 **`aria-invalid` + `aria-describedby` + `id` sur form-error** — Tous les messages d'erreur de formulaire ont un `id` unique et sont liés au champ via `aria-describedby` + `aria-invalid="true"` : users.php (6 champs), user_edit.php (6 champs), site_edit.php (3 champs), report_respond.php (2 champs).
- **5** 🟡 **`aria-label` sur les 14 tables** — Toutes les tables de données ont un `aria-label` descriptif : report_list, users, report_card (×2), report_respond (×2), report_abandon, user_view, synthesis, statistics, settings, help (×3).
- **6** 🟡 **`aria-controls` + `aria-expanded` sur checkbox pour-compte** — Le checkbox « Signaler pour le compte d'un autre agent » déclare désormais `aria-controls="pour_compte_fields"` et `aria-expanded` dynamique via JS.
- **7** 🟡 **Emojis dans `<h1>` avec `aria-hidden`** — Les emojis décoratifs dans les titres help.php et choose_site.php sont enveloppés dans `<span aria-hidden="true">` pour ne pas perturber les lecteurs d'écran.
- **8** 🟡 **Focus trap sidebar mobile** — Quand la sidebar est ouverte sur mobile, le focus clavier est piégé à l'intérieur (Tab/Shift+Tab wrap). Le premier item reçoit le focus à l'ouverture.
- **9** 🟡 **`autocomplete` sur le formulaire de login** — Ajout de `autocomplete="username"` et `autocomplete="current-password"` pour les gestionnaires de mots de passe et l'autofill navigateur.

### UX — Facilité de prise en main

- **10** 🟡 **Compteur de caractères sur la description** — Ajout d'un compteur en temps réel `X/20 000` avec `aria-live="polite"` et couleur d'avertissement au-dessus de 19 000 caractères.
- **11** 🟡 **CTA sur les états vides** — Quand aucune donnée n'est trouvée, les listes affichent un bouton d'action : « + Inscrire un signalement » (report_list) et « + Inscrire un utilisateur » (users).
- **12** 🟢 **Bouton « Retour en haut »** — Apparaît après 400px de scroll, smooth scroll vers le haut, accessible avec `aria-label`, responsive sur mobile.

### Responsive — Petits écrans

- **13** 🟡 **Breakpoint 480px** — Nouveau media query pour les petits téléphones : font-size réduite, header compact, username tronqué avec ellipsis, cards et tables adaptées, back-to-top button redimensionné.

### Sécurité — Headers manquants

- **14** 🟡 **choose_site.php sans headers** — Ajout des headers Cache-Control (no-store, no-cache, must-revalidate, max-age=0), Pragma, Expires et des security headers (X-Frame-Options, X-Content-Type-Options, CSP, etc.) sur la page choose_site.php qui sort avant le layout.

---

## [3.0.1] — 2026-06-12

### Correctif — Réponse superviseur impossible sur signalement en cours

- **F28** 🔴 **`report_id NOT NULL` bloquait l'INSERT dans `report_responses`** — la migration ayant ajouté `report_uuid` n'avait pas assoupli la contrainte `NOT NULL` sur l'ancienne colonne `report_id`. L'INSERT du code actuel ne fournit que `report_uuid`, pas `report_id` → violation de contrainte silencieuse → la transaction était rollbackée → message d'erreur trompeur « Le signalement a peut-être déjà été traité ». Correction : migration automatique qui recrée `report_responses` avec `report_id` nullable (SQLite ne supporte pas ALTER COLUMN). Le `CREATE TABLE IF NOT EXISTS` de la migration est aussi mis à jour pour utiliser `report_uuid` au lieu de `report_id`.
- **F29** 🟠 **`respondToReport()` retourne un code au lieu de `bool`** — la fonction retourne désormais `'true'` (succès), `'concurrent'` (modifié par un autre superviseur entre-temps) ou `'error'` (erreur base de données). Le handler affiche un message spécifique à chaque cas au lieu du générique « peut-être déjà été traité ». L'UPDATE réussi mais l'INSERT qui échouait n'est plus masqué par un message ambigu.

---

## [3.0.0] — 2026-06-12

### Sécurité — Headers HTTP

- **1** 🔴 Headers de sécurité ajoutés dans `header.php` — `X-Frame-Options: DENY` (anti-clickjacking), `X-Content-Type-Options: nosniff` (anti-MIME-sniffing), `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy` (same-origin uniquement). Défense en profondeur contre l'escalade XSS et les injections de contenu.

### Conformité — Journal d'audit

- **2** 🔴 **Table `audit_log` + fonctions** — traçabilité de toutes les actions significatives : connexion, création/édition/abandon/réponse de signalements, gestion des utilisateurs (CRUD, changement de rôle), modifications de sites, changements de paramètres, exports CSV, actions RGPD. Huit catégories (`auth`, `report`, `user`, `site`, `config`, `export`, `backup`, `gdpr`). Les entrées incluent utilisateur, horodatage, IP et contexte JSON. Fonctionne en mode non-bloquant — un échec de log ne casse jamais l'application.

### Conformité — RGPD

- **10** 🟠 **Droit d'accès et d'effacement** — deux actions RGPD ajoutées dans le profil utilisateur (`user_view.php`) : export JSON des données personnelles (droit d'accès art. 15) et anonymisation irréversible (droit d'effacement art. 17). L'anonymisation remplace noms et email par des placeholders, désactive le compte, mais conserve les signalements pour le registre. Les deux actions sont tracées dans l'audit log.

### Recherche — FTS5

- **11** 🟠 **Index plein texte FTS5** — table virtuelle `reports_fts` indexant `objet` et `description` pour une recherche rapide et pertinente. La migration crée l'index et peuple les données existantes. La recherche dans `getReportsByRegistry()` utilise FTS5 en priorité avec fallback LIKE si FTS5 n'est pas disponible. L'index est mis à jour à chaque création/édition de signalement.

### Accessibilité

- **8a** 🟡 **Skip link** — lien "Aller au contenu principal" visible uniquement au focus clavier (CSS pur, pas de JS). Permet de sauter la sidebar.
- **8b** 🟡 **`aria-hidden="true"`** sur tous les emoji du sidebar — les lecteurs d'écran ne lisent plus "clipboard", "warning sign" etc.
- **8c** 🟡 **`aria-describedby` + `aria-invalid`** sur les champs en erreur du formulaire de signalement — les erreurs sont maintenant programmatiquement associées à leur champ (6 champs : date, objet, description, pièce jointe, site, pour le compte de).
- **8d** 🟡 **`<fieldset>` + `<legend>`** sur les radio buttons de visibilité dans les paramètres — regroupement sémantique pour les lecteurs d'écran.

### Technique

- **13** 🟢 **`truncate()` corrigé** — `strlen()`/`substr()` remplacés par `mb_strlen()`/`mb_substr()` avec encodage UTF-8 explicite. Les coupures ne se font plus au milieu d'un caractère accentué (é, ê, ç).

---

## [2.9.0] — 2026-06-12

### Fiabilité — SQLite

- **S1/S2** 🔴 Transactions ajoutées sur `respondToReport()` et `createReport()` — l'UPDATE de `reports` + INSERT dans `report_responses` (et séquence + INSERT) sont désormais atomiques. Un crash entre les deux requêtes ne peut plus laisser la base incohérente.
- **S3** 🔴 **Stratégie de backup autonome** — `src/backup.php` : sauvegarde automatique via `VACUUM INTO` (SQL pure, pas de script externe, compatible IIS/Windows). Le backup ne se déclenche que si la base a changé depuis le dernier snapshot (comparaison filemtime + taille après checkpoint WAL). Zéro gaspillage de stockage si rien n'a bougé.
- **S4** 🟡 Rotation des backups — les 10 plus récents sont conservés dans `data/backups/`, les plus anciens sont supprimés automatiquement. Protection HTTP via `.htaccess` + `web.config`.
- **S5** 🟡 Backup pré-migration — avant chaque modification de schéma, un snapshot est créé. Permet de restaurer la base si une migration échoue.
- **S6** 🟡 **Table `schema_version`** — versionnage des migrations. Chaque migration appliquée est enregistrée avec un numéro de version et un horodatage. Les bases existantes reçoivent le baseline v1 automatiquement.
- **S7** 🟡 `data/backups/` ajouté au `.gitignore` — les snapshots ne polluent pas le dépôt.

### Export CSV

- **E1** 🔴 **`fputcsv()`** remplace la construction manuelle — les champs contenant des `;`, des guillemets ou des retours à la ligne sont correctement encadrés. Plus de CSV cassé.
- **E2** 🔴 **Historique multi-réponses exporté** — colonne `Historique réponses` avec toutes les réponses du signalement au format `[Date] Répondant (État) : Réponse`. Colonne `Nb réponses` pour le compteur.
- **E3** 🟠 Colonnes ajoutées : `Nom UR`, `Date création`, `Déclaré pour le compte de`, `Heure événement`, `Lieu`.
- **E4** 🟡 Description et réponse conservent leurs retours à la ligne (encapsulation `"` par `fputcsv`).

---

## [2.8.2] — 2026-06-12

### Correctif

- **F04** ↩️ `display_errors` rétabli à toujours activé — le paramétrage précédent (désactivation en prod) avait été appliqué sans accord. Les erreurs PHP sont de nouveau affichées en production comme en dev.
- **F07** 🟠 Affichage multi-réponses corrigé — un signalement peut recevoir plusieurs réponses du superviseur. La carte "Réponse" (unique, dernier seulement) et l'"Historique des réponses" étaient redondantes et masquaient ce fait. Remplacées par une seule section "Réponses (N)" listant toutes les réponses, dans le card HTML (`report_card.php`) et le PDF (`report_print.php`).

---

## [2.8.1] — 2026-06-12

### Sécurité — Corrections de l'audit fonctionnel

Corrections prioritaires issues de l'audit fonctionnel complet (31 constats) :

- **F01** 🔴 `phpinfo.php` supprimé du dépôt — exposition complète de la config serveur en production
- **F02/F03** 🟡 Protection du dernier superviseur actif — impossible de rétrograder ou désactiver le dernier superviseur (`user_edit_handler.php` + `user_delete_handler.php`). Empêche le verrouillage admin de l'application.
- **F04** 🟡 `display_errors` désactivé en production — les erreurs PHP (requêtes SQL, chemins, variables) n'apparaissent plus aux utilisateurs. Reste activé en mode dev.
- **F09** 🟠 Superviseur peut créer des signalements pour n'importe quel site — le handler bloquait les superviseurs comme les agents (`site_id !== user.site_id`). Corrigé avec `canSeeAllSites()`.
- **F20** 🟠 Utilisateur désactivé : `findOrCreateUser()` cherche désormais les utilisateurs inactifs aussi — un utilisateur désactivé ne provoque plus de violation UNIQUE sur le username à la reconnexion via IIS.

### Technique — Nettoyage et corrections

- **F13/F14** Routes orphelines `user_create`, `user_delete`, `user_reactivate` retirées de `$validPages` — ces POST-only n'ont pas de page GET
- **F17** Requête `getAllSites()` inutile retirée de `report_edit.php` (dropdown site masqué en mode édition)
- **F18** Champs mot de passe supprimés du formulaire et du handler `user_edit` — l'auth est IIS, pas de mot de passe dans le schéma
- **F19** Validation de l'existence du `site_id` ajoutée dans `user_create_handler.php`
- **F24** Branche morte `elseif ($isEdit && !empty($report['is_confidential']))` retirée de `report_form.php` — les 3 modes de visibilité couvrent tous les cas
- **F25** Accents français corrigés dans `report_abandon.php` (événement, Êtes-vous sûr, irréversible, abandonné)

---

## [2.8.0] — 2026-06-11

### Sécurité — Audit XSS complet

Passage en revue systématique de toutes les sorties HTML. L'application utilisait déjà `e()` (alias `htmlspecialchars` avec `ENT_QUOTES` + `UTF-8`) de manière quasi systématique. Les quelques lacunes restantes sont corrigées :

- `pages/changelog.php` : Parsedown n'avait pas `setSafeMode(true)` — le Markdown pouvait contenir du HTML brut (ex: `<img onerror=...>`). Safe Mode activé : les blocs HTML sont désormais échappés.
- `src/helpers.php` : `formatDateFR()` et `formatDateTimeFR()` retournaient la chaîne brute en fallback si le parsing échouait — désormais encodées via `e()` dans le chemin fallback (défense en profondeur).
- `templates/report_card.php` : 4 appels `formatDateFR`/`formatDateTimeFR` sans `e()` — corrigés.
- `pages/report_abandon.php` : `formatDateFR` sans `e()` — corrigé.
- `pages/report_list.php` : `formatDateFR` sans `e()` — corrigé.
- `pages/user_edit.php` : `value="<?php echo $userId ?>"` sans `e()` — corrigé (entier casté, mais principe).
- `pages/site_edit.php` : `value="<?php echo $siteId ?>"` sans `e()` — corrigé.
- `pages/settings.php` : IDs de site dans les attributs HTML sans `e()` — corrigés.

### Sécurité — CSRF déjà complet

Vérification que tous les handlers POST valident le token CSRF et que tous les formulaires l'envoient. **Déjà en place** : les 14 handlers vérifient `validateCsrfToken()` avec `hash_equals()`, et les 21 formulaires incluent le champ `csrf_token`. Aucune correction nécessaire.

---

## [2.7.3] — 2026-06-11

### Technique — Case confidentiel invisible pour les superviseurs

Les superviseurs ne voyaient pas la case « Signalement confidentiel » dans le formulaire de création/modification de signalement, même en mode « Au choix de l'agent ». Aucun `input hidden` n'était injecté non plus, donc le champ `is_confidential` n'était tout simplement pas envoyé. Cause : `getReportVisibility()` renvoie `'all'` pour les non-agents (lecture), et les fonctions `reportVisibilityIs*()` comparaient avec cette valeur — aucun match.

- `src/helpers.php` : extraction de `getReportVisibilityMode()` (role-agnostic, lit la config brute) utilisée par les fonctions `reportVisibilityIs*()`. `getReportVisibility()` conserve son comportement pour la lecture/filtrage (retourne `'all'` pour les non-agents).

### Technique — Alignement des checkboxes dans le formulaire

Les checkboxes « Signalement confidentiel » et « Signaler pour le compte d'un autre agent » étaient affichées au-dessus de leur libellé au lieu d'être alignées à gauche du texte. Cause : le CSS global applique `width: 100%` à tous les `<input>` et `display: block` aux `<label>` dans `.form-group`.

- `templates/report_form.php` : style inline sur les `<label>` (`display:flex; align-items:center; gap:8px`) et les `<input type="checkbox">` (`width:auto; margin:0`)

---

## [2.7.2] — 2026-06-11

### Fonctionnalité — Colonne Visibilité dans la liste des signalements

La liste des signalements (`report_list`) affiche désormais une colonne **Visibilité** indiquant si le signalement est 🔒 Confidentiel ou Public, avec des badges colorés (gris pour confidentiel, vert pour public). Cohérent avec le badge affiché dans la vue détaillée.

- `pages/report_list.php` : ajout de la colonne « Visibilité » avec badge Confidentiel/Public

### Technique — Corrections de bugs

- `pages/settings.php` : les radios de visibilité n'étaient pas cochés sur le réglage en cours — `getReportVisibility()` renvoie `'all'` pour les superviseurs, qui ne correspond à aucune des 3 valeurs possibles. Remplacé par `getConfig('app_report_visibility', 'agent_choice')` qui lit directement la valeur en base.
- `DEPLOY.md` : la « Méthode 2 : Auto-promotion bootstrap » était impossible — un agent n'a pas accès aux Paramètres (`requireRole(['superviseur'])`). Réécriture de la section 9 : la Méthode 1 est désormais le script CLI `promote.php`, la Méthode 2 est la promotion par un superviseur existant, et la variable d'environnement est en méthode alternative.

### Technique — Affichage de la version PHP

- `templates/footer.php` : ajout de la version PHP (`PHP_VERSION`) dans le footer, après la version de l'application

---

## [2.7.1] — 2026-06-11

### Technique — Correction de l'erreur « finfo not found »

L'upload de pièces jointes provoquait une erreur fatale `Class "finfo" not found` lorsque l'extension PHP `fileinfo` n'était pas activée sur le serveur. Désormais, un message clair s'affiche à l'utilisateur demandant d'activer l'extension, sans contournement.

- `src/helpers.php` : ajout de la fonction `getMimeType()` qui exige l'extension `fileinfo` — si absente, lève une `RuntimeException` avec le message : « L'extension PHP "fileinfo" est requise pour le téléchargement de pièces jointes. Veuillez l'activer dans php.ini : extension=fileinfo, puis redémarrer le serveur web. »
- `handlers/report_create_handler.php` : remplacement de `new finfo()` par `getMimeType()` avec `try/catch` pour afficher le message d'erreur dans le formulaire
- `handlers/report_edit_handler.php` : même remplacement
- `DEPLOY.md` : `fileinfo` ajouté aux extensions PHP **requises** (était absent), section de dépannage ajoutée
- `DEPLOY.md` : `extension=fileinfo` ajouté dans l'exemple `php.ini`
- `DEPLOY.md` : checklist de vérification mise à jour avec `fileinfo`

---

## [2.7.0] — 2026-06-11

### Fonctionnalité — Images embarquées dans les PDF

Les pièces jointes de type image (JPG, PNG, GIF) sont désormais intégrées directement dans le PDF généré par FPDF, au lieu d'afficher uniquement le nom du fichier. Le PDF est ainsi autonome et contient toutes les informations visuelles du signalement.

- `pages/report_print.php` : après la section « Pièce jointe », si l'attachment est une image, le BLOB est écrit dans un fichier temporaire (`tempnam()`), intégré via `$pdf->Image()` avec des dimensions proportionnelles (max 180 mm de large, max 120 mm de haut, fond gris clair), puis le temp est supprimé immédiatement. Si l'intégration échoue, le PDF est quand même généré (le nom du fichier reste affiché). Les PDF en pièce jointe ne sont pas embarqués.
- `pages/report_attachment.php` : ajout du paramètre `inline=1` — les images sont servies avec `Content-Disposition: inline` pour affichage dans le navigateur (aperçu dans la page). Sans ce paramètre, le téléchargement forcé est conservé.
- `templates/report_card.php` : les images sont affichées en aperçu inline (`<img>` avec `max-height:400px`) au-dessus du bouton de téléchargement. Cliquer sur l'image lance le téléchargement. Les PDF restent en lien de téléchargement simple.

### Technique — Mise à jour de la spécification

- `SPEC.md` : `MAX_DESCRIPTION_LENGTH` corrigé de 5000 à 20000 (la valeur réelle dans config.php était déjà 20000, la spec était en retard). Ajout des constantes `MAX_ATTACHMENT_SIZE` et `ALLOWED_ATTACHMENT_MIMES` dans le tableau des constantes. Ajout des colonnes `attachment_blob`, `attachment_name`, `attachment_mime` dans le schéma de la table `reports`. Ajout de la route `report_attachment` dans la table des routes. Ajout du champ `attachment` dans le tableau des champs du formulaire. Section PDF mise à jour avec la description de l'embarquement d'images.

---

## [2.6.1] — 2026-06-11

### Sécurité — Correction critique : génération UUID invalide

La fonction `generateUuid()` utilisait `| 0x8` au lieu de `(& 0x3F | 0x80)` pour les bits de variante UUID v4. Cela produisait des UUID invalides dans environ 25 % des cas (4e groupe commençant par c, d, e ou f au lieu de 8, 9, a, b uniquement). La fonction `isValidUuid()` les rejetait, et `getReportByUuid()` retournait `null` → message « Signalement introuvable » au clic sur « Voir » depuis la liste.

- `src/queries/report_queries.php` : `generateUuid()` corrigé — `& 0x3F | 0x80` au lieu de `| 0x8`
- `src/queries/report_queries.php` : `isValidUuid()` assoupli — accepte tout UUID bien formé (8-4-4-4-12 hex) pour la rétrocompatibilité avec les UUID existants mal formatés en base
- `src/database.php` : migration automatique qui corrige les UUID existants avec des bits de variante invalides (c→8, d→9, e→a, f→b) dans `reports` et `report_responses`
- `src/database.php` : backfill des UUID NULL même si la colonne existe déjà (migration partielle possible)
- `seed.php` : même correction sur la génération UUID

### Fonctionnalité — Promotion superviseur immédiate

La vérification de `app_superviseur_usernames` ne s'appliquait qu'au moment du login. Si l'utilisateur était déjà en session, modifier ce paramètre n'avait aucun effet jusqu'à la déconnexion/reconnexion. Désormais, la vérification s'exécute à chaque chargement de page : un agent dont le login figure dans la liste est promu superviseur immédiatement, sans déconnexion.

- `public/index.php` : ajout du bloc « SUPERVISEUR PROMOTION CHECK » avant le rendu de chaque page
- `pages/settings.php` : libellé mis à jour — « immédiatement (dès la prochaine page consultée) » au lieu de « lors de leur connexion via IIS »

### Technique — Détection automatique de l'environnement

L'ancien système `define('APP_ENV', getenv('APP_ENV') ?: 'prod')` ne fonctionnait pas sur les serveurs non-IIS (Space-Z, Docker, Apache) : la variable d'environnement n'était pas définie, l'app restait en mode dev avec le formulaire de login, et l'utilisateur voyait « Mode développement » même en configurant `prod`. Le nouveau système détecte automatiquement : si `AUTH_USER` est disponible (IIS) → prod, sinon → dev.

- `src/config.php` : détection en 3 niveaux — `APP_ENV_FORCE` (constante PHP) > `getenv('APP_ENV')` > auto-détection via `$_SERVER['AUTH_USER']`
- `pages/login.php` : badge « Mode sans IIS — authentification par identifiant » au lieu de « Mode développement » quand `AUTH_USER` n'est pas disponible ; ajout d'une aide pour devenir superviseur via les paramètres
- `README.md` : section installation mise à jour
- `DEPLOY.md` : section configuration mise à jour

---

## [2.6.0] — 2026-06-11

### Sécurité — Migration des PK reports vers UUID

Les identifiants primaires de la table `reports` passent d'entiers auto-incrémentés (`id`) à des **UUID v4** (`uuid`). Cela empêche l'énumération d'URL : un agent ne peut plus deviner l'existence d'autres signalements en incrémentant l'ID dans l'URL.

- `schema.sql` : `reports.uuid TEXT PRIMARY KEY` remplace `reports.id INTEGER PRIMARY KEY`. La colonne `id` est entièrement supprimée.
- `src/queries/report_queries.php` : toutes les requêtes utilisent `uuid` au lieu de `id`. Ajout de `generateUuid()`, `isValidUuid()`, `getReportByUuid()`. Les fonctions `updateReport()`, `abandonReport()`, `respondToReport()` prennent désormais un UUID en paramètre.
- `report_responses.report_uuid` : clé étrangère vers `reports(uuid)` au lieu de `reports(id)`.
- Toutes les URLs de signalements utilisent `?uuid=...` au lieu de `?id=...` : `report_view`, `report_edit`, `report_abandon`, `report_respond`, `report_print`.
- `templates/sidebar.php` : lookup du type de registre via `uuid` au lieu de `id`.
- Validation UUID systématique dans chaque page/handler (`strlen($uuid) !== 36` ou `isValidUuid()`).

### Sécurité — Contrôle d'autorisation dans report_print

- `pages/report_print.php` : ajout du contrôle d'accès identique à `report_view.php`. Un agent ne peut plus imprimer un signalement auquel il n'a pas accès (site différent, confidentiel d'un autre agent, etc.). Auparavant, seul le format PDF était protégé par la non-devinabilité de l'ID entier.

### Sécurité — Restriction du dropdown site pour les agents

- `pages/report_create.php` : les agents ne voient que leur propre site dans le dropdown, les superviseurs/CHSCT voient tous les sites. Auparavant, `$canSelectSite` était calculé mais jamais utilisé dans le template, ce qui affichait tous les sites à tous les utilisateurs.

### Technique — Corrections de syntaxe PHP

- `pages/report_list.php` : 3 appels `url()` avec parenthèses en trop (`]))` au lieu de `]`). Fatal error PHP.
- `templates/report_form.php` : 1 appel `url()` avec parenthèse en trop. Fatal error PHP.
- `pages/report_print.php` : `SSTPDF::Header()` et `SSTPDF::Footer()` déclarées `public` au lieu de `protected` (FPDF les déclare publiques, la classe fille ne peut pas restreindre la visibilité).

### Technique — Nettoyage du dépôt

- `pdf_docs/` retiré du dépôt git et ajouté au `.gitignore` (80 fichiers, 14 128 lignes supprimées).

---

## [2.5.0] — 2026-06-11

### Technique — Migration mPDF → FPDF

Remplacement de **mPDF 8.2** (nécessite Composer, écrit des fichiers temporaires sur disque) par **FPDF 1.9** (zéro dépendance, zéro I/O disque, tout en mémoire). Ce changement élimine la dépendance Composer et garantit la pérennité du code (FPDF : 24 ans de stabilité d'API, 0 rupture de compatibilité depuis 2001).

- `pages/report_print.php` : réécriture complète avec l'API FPDF (Cell, MultiCell, Rect, Line) au lieu de HTML/CSS via mPDF. Même rendu visuel : badges colorés, tableau d'historique, en-tête/pied de page, boîte de réponse avec bordure verte.
- `src/lib/fpdf/` : FPDF v1.9 inclus (fpdf.php + font/). Aucune dépendance Composer.
- `src/lib/fpdf/font/` : polices DejaVu Sans (Unicode TrueType, cp1252) pour le support des caractères français accentués.
- `composer.json` : dépendance `mpdf/mpdf` supprimée. Fichier vidé (`require: {}`).
- `public/index.php` : l'autoloader Composer n'est plus requis. Chargé conditionnellement si présent (rétro-compatible).
- `test_fpdf.php` : script de test autonome pour valider le rendu PDF (accents, badges, tableau, multiligne).

### Technique — Simplification du déploiement

- **Extensions PHP réduites** : seules `sqlite3`, `pdo_sqlite`, `mbstring` sont nécessaires. Les extensions `gd`, `xml`, `curl`, `zip` ne sont plus requises (elles étaient pour mPDF).
- **Plus besoin de Composer** : FPDF est inclus directement dans le projet. `composer install` n'est plus nécessaire au déploiement.
- **Plus de dossier temporaire** : FPDF génère le PDF entièrement en mémoire. Pas besoin de `sys_get_temp_dir()` ou de RAM disk.
- `DEPLOY.md` : mis à jour — suppression des sections Composer, extensions réduites, nouvelle structure sans `vendor/`.
- `README.md` : mis à jour — stack technique, installation simplifiée, structure sans `vendor/`.
- `update_sst.ps1` : script simplifié — étape Composer supprimée, seuls `git pull` + `iisreset` restent.

---

## [2.4.1] — 2026-06-11

### Technique — Suppression totale du JavaScript

Plus aucune ligne de JavaScript dans l'application. Toutes les confirmations utilisent désormais un mécanisme PHP inline (rechargement de page avec paramètre URL de confirmation) au lieu de `<dialog>` HTML5 + `onclick` + `<script>`.

- `pages/user_edit.php` : confirmation suppression → `?confirm_delete=1` au lieu de `<dialog>` + `onclick`
- `pages/settings.php` : confirmation suppression site → `?confirm_delete_site={id}` au lieu de `<dialog>` + `onclick`
- `templates/report_card.php` : confirmation abandon → `?confirm_abandon=1` au lieu de `<dialog>` + `onclick`
- `pages/report_abandon.php` : confirmation inline PHP (l'ancien `require confirm_dialog.php` causait un fatal error)
- `templates/confirm_dialog.php` : supprimé (plus utilisé)

### Technique — Corrections de bugs critiques (audit statique)

- **C1 — Fatal error `confirm_dialog.php`** : `report_abandon.php` référençait le template supprimé → confirmation PHP inline
- **C2 — Pagination crash PHP 8** : la variable `$currentPage` du routeur (nom de page) écrasait celle de la pagination (numéro) → `$currentPageName` pour le routeur, `$currentPage = $pageNum` avant inclusion de la pagination
- **W4 — Session stale** : après modification d'un utilisateur, la session n'était pas complètement mise à jour (manquaient `site_code`, `site_nom`) → re-lecture complète depuis la DB avec JOIN
- **W1/W2 — Handlers orphelins** : `site_edit` et `user_reactivate` n'étaient pas routés → ajoutés au routing + boutons dans l'UI
- **I4 — CSV export** : colonne `Confidentiel` (Oui/Non) ajoutée à l'export
- **I3 — `phpinfo.php`** : supprimé (risque de sécurité)

### Technique — Déploiement et infrastructure

- `report_print.php` : FPDF génère le PDF en mémoire (pas de fichiers temporaires)
- `DEPLOY.md` : chemin corrigé `C:\inetpub\sst\` (était `C:\inetpub\wwwroot\sst\`)
- `DEPLOY.md` : section proxy Git Kerberos (`http.proxyAuthMethod negotiate`)
- `DEPLOY.md` : Composer derrière le proxy (variables d'environnement HTTP_PROXY/HTTPS_PROXY)
- `web.config` racine : URL Rewrite supprimé (inutile, routage par query string)
- `update_sst.ps1` : script PowerShell de déploiement automatisé (git pull + permissions + iisreset)

### Technique — Avertissement décochage confidentiel

- `templates/report_form.php` : warning CSS `:has()` affiché quand l'agent décoche « Signalement confidentiel » en mode « Choix de l'agent ». Pas de JavaScript, pur CSS.

---

## [2.4.0] — 2026-06-11

### Fonctionnalités — Système de visibilité des signalements en 3 modes

Passage d'un système à 2 modes (confidentiel / public) à un système à **3 modes** configurable par le superviseur dans Paramètres → Application :

- **Mode « Confidentiel »** (le plus restrictif) : l'agent ne voit que ses propres signalements. Les autres agents ne voient rien, pas même le titre. Les superviseurs et membres du CHSCT voient tout.
- **Mode « Choix de l'agent »** (confidentiel par défaut) : l'agent choisit la visibilité de chaque signalement lors de la création (public ou confidentiel). Par défaut, le signalement est confidentiel. L'agent voit les signalements publics de son site ainsi que ses propres signalements (même confidentiels).
- **Mode « Visibilité publique »** : tous les signalements du site sont visibles par tous les agents du site.

### Technique — Changements

- `src/config.php` : ajout de `REPORT_VISIBILITY_MODES` (constante), version 2.4.0
- `src/helpers.php` : `getReportVisibility()` remplace `getAgentVisibility()` (3 valeurs : `confidential`, `agent_choice`, `public`). Nouvelles fonctions `reportVisibilityIsConfidential()`, `reportVisibilityIsAgentChoice()`, `reportVisibilityIsPublic()`. Anciennes fonctions conservées comme alias dépréciés.
- `schema.sql` : nouvelle clé `app_report_visibility` (défaut `agent_choice`), clé `app_agent_visibility` marquée obsolète
- `handlers/settings_handler.php` : validation des 3 valeurs pour `app_report_visibility`, synchronisation avec les anciennes clés
- `pages/settings.php` : 3 radios au lieu de 2 pour la visibilité des signalements
- `pages/report_list.php` : filtre `own_only` pour le mode confidentiel strict, filtre `confidential_filter` pour le mode choix agent
- `src/queries/report_queries.php` : clause `own_only` (declarant_id = userId) pour le mode confidentiel
- `templates/report_form.php` : toggle confidentiel uniquement en mode « Choix de l'agent », badge + hidden input dans les autres modes
- `handlers/report_create_handler.php` : force `is_confidential` selon le mode (1 en confidentiel, 0 en public, choix en agent_choice)
- `handlers/report_edit_handler.php` : même logique que la création
- `pages/report_view.php` : contrôle d'accès pour les 3 modes (bloque même le titre en mode confidentiel)
- `pages/report_print.php` : contrôle d'accès pour les 3 modes
- `pages/home.php` : compteurs adaptés au mode (own only / public+own / all)
- `pages/preamble.php` : wording mis à jour pour les 3 modes
- `pages/help.php` : tableau de visibilité par mode (3 lignes) au lieu de par rôle

### Nettoyage du dépôt Git

- Restructuration du dépôt : le projet (`pdf_docs/sst/`) déplacé à la racine du repo
- Suppression de `download/` (script audit non lié), `upload/` (doublon du projet), `.env` (sécurité)
- Suppression de `data/sst.db` du suivi git
- `.gitignore` racine fusionné avec les règles du projet (vendor, data, .env, IDE, OS, pdf, zip)

---

## [2.3.0] — 2026-06-11

### Fonctionnalités — Confidentialité des signalements par défaut, choix de l'agent

- **Mode « Confidentiel par défaut »** : les signalements sont confidentiels par défaut. L'agent peut choisir de rendre son signalement public lors de la création ou de la modification en décochant la case « Signalement confidentiel ». En mode confidentiel, un agent voit les signalements publics de son site + ses propres signalements (même confidentiels).
- **Mode « Visibilité publique »** : tous les signalements du site sont visibles par tous les agents du site. Conforme au principe de transparence des registres SST. La case confidentiel n'est pas affichée dans ce mode.
- **Badge « 🔒 Confidentiel »** : affiché sur la vue détaillée et le PDF d'un signalement confidentiel.
- **Paramétrage admin** : le superviseur choisit le mode de visibilité dans Paramètres → Application. L'ancien réglage site/own est remplacé par confidentiel/public.
- **Migration automatique** : les bases existantes sont migrées automatiquement — colonne `is_confidential` ajoutée, ancien mode `site` → `public`, ancien mode `own` → `confidential`, les signalements existants conservent leur visibilité précédente.
- **Superviseurs et CHSCT** : voient tous les signalements y compris confidentiels, quel que soit le mode.

### Technique — Fichiers modifiés

- `schema.sql` : colonne `is_confidential` (INTEGER NOT NULL DEFAULT 1) dans `reports`, config par défaut `confidential`
- `src/database.php` : migration auto — ALTER TABLE + UPDATE + index pour `is_confidential`, migration des valeurs de config
- `src/helpers.php` : `getAgentVisibility()` renvoie `confidential`/`public` au lieu de `site`/`own`, ajout de `agentVisibilityIsConfidential()` et `agentVisibilityIsPublic()`, `canSeeAllSites()` simplifié
- `src/queries/report_queries.php` : `createReport()` avec `is_confidential`, `updateReport()` avec `is_confidential`, `getReportsByRegistry()` avec filtre `confidential_filter`, `countActiveReports()` avec paramètres `$userId` et `$confidentialMode`
- `templates/report_form.php` : case à cocher « Signalement confidentiel » (cochée par défaut) en mode confidentiel, badge en mode public
- `templates/report_card.php` : badge « 🔒 Confidentiel »
- `handlers/report_create_handler.php` : sauvegarde de `is_confidential`
- `handlers/report_edit_handler.php` : sauvegarde de `is_confidential` lors de la modification
- `pages/settings.php` : radios confidentiel/public au lieu de site/own, info au lieu d'avertissement
- `handlers/settings_handler.php` : validation `confidential`/`public`
- `pages/report_view.php` : contrôle d'accès avec `is_confidential`
- `pages/report_print.php` : contrôle d'accès + badge confidentiel dans le PDF
- `pages/report_list.php` : filtres `confidential_filter` / `force_site_id` selon le mode
- `pages/home.php` : compteurs avec filtre confidentiel
- `pages/preamble.php` : wording « confidentiel par défaut, l'agent peut le rendre public »
- `pages/help.php` : tableau de visibilité par rôle et par mode
- `src/config.php` : version 2.3.0

---

## [2.2.0] — 2026-06-10

### Technique — Suppression du JavaScript personnalisé (zéro JS côté métier)

Objectif : éliminer tout JavaScript personnalisé de l'application. Les seuls `onclick` restants sont des appels natifs `showModal()` HTML5 pour ouvrir des `<dialog>` — aucun framework, aucune logique métier en JS.

- **synthesis.php** : les filtres année/site utilisaient `onchange="window.location.href=..."` → remplacés par un `<form method="GET">` avec bouton « Filtrer »
- **statistics.php** : le filtre année utilisait `onchange="window.location.href=..."` → remplacé par un `<form method="GET">` avec bouton « Filtrer »
- **export.php** : les checkboxes « Tous » utilisaient `onchange="...disabled=..."` → supprimés. Le handler côté serveur ignore déjà les selects quand la checkbox est cochée.
- **choose_site.php** : le `<script>` qui toggle le warning + `confirm()` → supprimé. Le warning est toujours visible, le select a `required` HTML5.
- **report_form.php** : le `<script>` qui toggle `pour_compte_fields` → remplacé par CSS `:has()` : `.form-grid:has(#pour_compte:checked) #pour_compte_fields { display: block; }`
- **settings.php — Tag input** : le système de tags avec `addTag()`, `syncHidden()`, `onclick`, `onkeydown` → remplacé par des `<textarea>` simples (une adresse e-mail par ligne). Le handler parse les lignes côté serveur.
- **settings.php — SMTP test** : le `fetch()` + `alert()` → remplacé par un formulaire POST classique. Le handler `smtp_test_handler.php` redirige avec flash message au lieu de retourner du JSON.
- **settings.php — Visibilité agent** : le `<script>` qui toggle le warning radio → remplacé par CSS `:has()` : `#visibility-radios:not(:has(input[value="site"]:checked)):not(:has(input[value="own"]:checked)) .agent-visibility-warning { display: none; }`
- **settings.php — Confirm suppressions** : les `onclick="return confirm(...)"` → remplacés par `<dialog>` HTML5 natif avec `showModal()`
- **user_edit.php** : le `onsubmit="return confirm(...)"` → remplacé par `<dialog>` HTML5 natif
- **report_card.php** + **confirm_dialog.php** : le `onclick="...style.display='block'"` → remplacé par `<dialog>` HTML5 natif

### Technique — Fichiers modifiés

- `pages/synthesis.php` : `<form method="GET">` + bouton Filtrer
- `pages/statistics.php` : `<form method="GET">` + bouton Filtrer
- `pages/export.php` : suppression des `onchange`, retrait des `disabled`
- `pages/choose_site.php` : suppression du `<script>`, warning toujours visible, `required` HTML5
- `pages/settings.php` : textarea au lieu de tags, formulaire POST pour SMTP test, CSS `:has()` pour warning, `<dialog>` pour confirmations
- `pages/user_edit.php` : `<dialog>` au lieu de `onsubmit confirm()`
- `templates/report_card.php` : `<dialog>` au lieu de `div` masqué
- `templates/confirm_dialog.php` : contenu `<dialog>` avec `formmethod="dialog"` natif
- `templates/report_form.php` : CSS `:has()` au lieu de `<script>`
- `handlers/settings_handler.php` : parse textarea (une adresse/ligne) au lieu de tableaux
- `handlers/smtp_test_handler.php` : redirect + flash au lieu de JSON

---

## [2.1.0] — 2026-06-10

### Fonctionnalités — Changelog consultable dans l'UI

- **Page Changelog** : le numéro de version dans le footer est désormais un lien cliquable vers `?page=changelog`, qui affiche le contenu du fichier `CHANGELOG.md` rendu en HTML.
- **Parsedown** : ajout du parseur Markdown `Parsedown.php` dans `src/lib/` (fichier unique, sans Composer) pour le rendu du changelog.
- **Pas d'export PDF** : le changelog est en lecture seule, aucun bouton d'export.

### Fonctionnalités — Génération PDF des fiches de signalement

- **Impression PDF native** : `report_print.php` génère désormais un PDF côté serveur via FPDF au lieu d'une vue HTML + `window.print()`. Plus de JavaScript pour l'impression.
- **Bouton « Télécharger en PDF »** : remplace l'ancien bouton « Imprimer la fiche » dans la vue détaillée d'un signalement.
- **PDF professionnel** : en-tête (organisation + référence), pied de page (pagination + date de génération), badges colorés pour le registre et l'état, tableau d'historique des réponses.
- **FPDF 1.9** : bibliothèque incluse directement, sans Composer. Zéro dépendance, zéro fichier temporaire.

### Technique — Dépendances PHP

- **composer.json** : fichier vidé (`require: {}`). Plus de dépendance mPDF.
- **Autoloader Composer** : `vendor/autoload.php` chargé conditionnellement dans `public/index.php` (rétro-compatible si vendor/ existe).
- **FPDF inclus** : `src/lib/fpdf/fpdf.php` + polices dans `src/lib/fpdf/font/`.

### Technique — Fichiers modifiés

- `pages/changelog.php` : nouvelle page — parse le CHANGELOG.md via Parsedown
- `pages/report_print.php` : réécrit — génération PDF FPDF au lieu de HTML + `window.print()`
- `pages/help.php` : CU8 mis à jour — « Télécharger en PDF » au lieu de « vue imprimable via le navigateur »
- `templates/footer.php` : version cliquable → lien vers `?page=changelog`
- `templates/report_card.php` : bouton « Imprimer la fiche » → « Télécharger en PDF »
- `public/index.php` : route `changelog`, titre page, autoload Composer
- `public/css/style.css` : styles `.footer-version` (lien cliquable dans le footer)
- `public/web.config` : hidden segment `vendor`
- `src/lib/Parsedown.php` : parseur Markdown (fichier unique)
- `composer.json` : `require: {}` (dépendance mPDF supprimée)
- `.gitignore` : exclusion de `vendor/`, `data/*.db`, IDE, OS
- `DEPLOY.md` : documentation simplifiée — plus de Composer, extensions réduites, structure sans `vendor/`

---

## [2.0.0] — 2026-06-10

### Breaking Changes — Refonte du système de rôles

- **Rôle Manager supprimé** : le rôle `manager` n'existe plus dans l'application. Il a été retiré de tous les fichiers : config.php, helpers.php, sidebar.php, handlers, pages, seed.php, promote.php, database.php, schema.sql, style.css, help.php. Les fonctionnalités de consultation élargie (tous les sites, synthèse, export, stats) sont déjà couvertes par le rôle CHSCT.
- **Système d'auto-promotion par préfixe supprimé** : le mécanisme `app_admin_prefix` (par défaut `adm.`) qui promouvait automatiquement les logins commençant par ce préfixe est supprimé. Ce système était source de confusion et de faille de sécurité potentielle.
- **Clé de config renommée** : `app_admin_usernames` → `app_superviseur_usernames` — le nom reflète désormais clairement son usage : liste de logins Windows séparés par virgules qui seront automatiquement promus Superviseur. Utile pour une première installation.

### Attribution du rôle Superviseur (nouveau système)

Deux méthodes pour obtenir le rôle Superviseur :
1. **Par un autre superviseur** via la gestion des utilisateurs dans l'interface
2. **Via la liste de config** `app_superviseur_usernames` (Paramètres → Application) — les utilisateurs de cette liste sont auto-promus à leur connexion via IIS

### Sécurité — Corrections de confidentialité

- **Visibilité agent par défaut = son site** : le défaut de `app_agent_visibility` passe de `'all'` à `'site'`. Par défaut, un agent ne voit que les signalements de son site.
- **Option 'all' supprimée** : l'option « Tous les signalements » n'est plus proposée dans les paramètres de visibilité agent. Seules les options « Son site » (par défaut) et « Ses propres signalements » sont disponibles.
- **Contrôle d'accès renforcé** : `canAccessReport()` dans helpers.php vérifie systématiquement que l'utilisateur a le droit d'accéder au signalement (déclarant, superviseur ou CHSCT).
- **Abandon de signalement** : réservé au superviseur uniquement (conforme à la documentation de référence).

### Documentation

- **help.php réécrit** : conforme à la documentation PDF de référence. 3 rôles uniquement (Agent, Superviseur, CHSCT). Section confidentialité ajoutée.
- **SPEC.md réécrit** : suppression des références LDAP, des fonctions obsolètes, du rôle Manager. Documentation du système de rôles à 3 profils.
- **README.md mis à jour** : reflète le nouveau système de rôles et d'attribution.
- **CHANGELOG.md mis à jour** : ce fichier.

### Technique

- `src/auth.php` : suppression des fonctions `determineProvisionRole()` (prefix + list) et `checkAndPromoteUser()` (prefix) — remplacées par un mécanisme simplifié basé uniquement sur la liste `app_superviseur_usernames`
- `src/config.php` : `ROLE_LABELS` ne contient plus que agent, superviseur, chsct. `APP_VERSION` → 2.0.0
- `src/helpers.php` : `getRoleBadgeClass()` sans manager, `canSeeAllSites()` sans manager, `getAgentVisibility()` défaut 'site'
- `schema.sql` : rôle commenté `'agent'|'superviseur'|'chsct'`, clé `app_superviseur_usernames` au lieu de `app_admin_prefix`/`app_admin_usernames`
- `src/database.php` : seed sans manager.dev, config keys mises à jour
- Tous les handlers et pages : retraits des références au rôle manager
- `public/css/style.css` : retrait de `--role-manager` et `.badge--manager`
- `promote.php` : rôles valides = agent, superviseur, chsct

---

## [1.1.0] — 2026-06-10

### Sécurité — Corrections de confidentialité

- **Vulnérabilité critique corrigée** : le défaut de `app_agent_visibility` passe de `'all'` à `'site'`. Par défaut, un agent ne voit plus que les signalements de son site.
- **Contrôle d'accès renforcé** : ajout de `canAccessReport()` dans helpers.php.
- **Rôle Manager corrigé** : le manager ne peut plus répondre aux signalements.
- **Abandon de signalement corrigé** : l'abandon est désormais réservé au superviseur.
- **Option 'all' supprimée des paramètres**.

### Fonctionnalités métier ajoutées

- **Réactivation d'utilisateur** : bouton « Réactiver » dans la liste des utilisateurs.
- **Modification de site** : bouton « Modifier » dans l'onglet « Gestion des sites ».

### Code mort supprimé

- `updateUserRole()`, `updateUserSite()`, `agentSeesOnlyOwn()` supprimées.

---

## [1.0.0] — 2025-06-05

### Première version

- Application SST DREETS BFC complète.
- 3 profils utilisateurs : Agent, Superviseur, CHSCT.
- 3 registres : RSST, RAMI, DGI.
- Authentification IIS Windows (prod) / mock login (dev).
- Notifications par e-mail, configuration SMTP.
- Statistiques, synthèse, export CSV.
- Gestion des utilisateurs et des sites.
