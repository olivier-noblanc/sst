# CORRECTIONS.md — Audit Application SST DREETS BFC (Volet 2)

Date : 13 juin 2026  
Référence : Audit du 11 juin 2026 — Volet 2 (Conformité SST / RGPD / Couverture fonctionnelle / Gouvernance)

## Constat général

Les 7 points (9 à 15) identifiés par l'audit étaient **tous non résolus** dans le code au moment de la vérification. Chacun a été corrigé selon les instructions de l'audit.

---

## Point 9 — Références légales incorrectes dans pages/preamble.php

**Statut : 🔧 corrigé (partiellement — nécessite revue humaine pour RAMI)**

**Fichier modifié :** `pages/preamble.php`

**Corrections appliquées :**

| Registre | Avant | Après |
|----------|-------|-------|
| RSST | « Articles R4123-1 et suivants du Code du travail » (référence inexistante) | « Décret n° 82-453 du 28 mai 1982, article 3-2 (modifié par le décret n° 2011-774 du 28 juin 2011) » + mention de la consultation par l'ensemble des agents |
| RAMI | « Article 1 du décret n° 2010-696 du 24 juin 2010 » (décret inexistant) | « Cadre juridique à confirmer » avec commentaire HTML `<!-- TODO revue juridique/RH -->` explicite et piste citée (art. 6 quater A, loi 83-634) |
| DGI | « Articles L4131-1 et suivants du Code du travail » (incomplet) | Même référence conservée + ajout de l'article D4132-1 pour le formalisme du registre spécial |

**Confirmation explicite : aucune nouvelle référence légale n'a été inventée.**  
Le registre RAMI est laissé en attente de revue juridique/RH avec un `<!-- TODO -->` visible dans le code source et un affichage « Cadre juridique à confirmer » pour les agents.

**⚠️ Nécessite revue humaine :** Le service juridique/RH doit identifier le texte applicable au registre RAMI et valider les références RSST et DGI.

---

## Point 10 — Visibilité globale unique vs par-registre

**Statut : 🔧 corrigé**

**Fichiers modifiés :**
- `src/helpers.php` : `getReportVisibilityMode()` et `getReportVisibility()` acceptent désormais un paramètre `?string $type` pour lire la clé spécifique au registre (`app_report_visibility_rsst`, `_rami`, `_dgi`), avec fallback sur la clé globale `app_report_visibility`.
- `src/database.php` : ajout de 3 nouvelles clés `config_app` dans `migrateConfigKeys()` : `app_report_visibility_rsst` (défaut `public`), `app_report_visibility_rami` (défaut vide = fallback global), `app_report_visibility_dgi` (défaut vide = fallback global).
- `schema.sql` : ajout des 3 clés dans les INSERT par défaut.
- `pages/settings.php` : 3 sélecteurs de visibilité (un par registre) avec mention de la base légale à côté de chaque sélecteur. Avertissement réglementaire affiché si le RSST n'est pas en mode « public » (conformément au décret 82-453 art. 3-2).
- `handlers/settings_handler.php` : sauvegarde des 3 clés par registre.

**Comportement par défaut :**
- RSST : `public` (conforme décret 82-453 art. 3-2)
- RAMI et DGI : vide (= utilise la clé globale `app_report_visibility`, comportement inchangé pour les installations existantes)

**Avertissement réglementaire SPEC.md (L969) :** Implémenté. Affiché dans settings.php quand la visibilité RSST est restreinte.

---

## Point 11 — canAccessReport() spécifiée/absente, logique triplicée

**Statut : 🔧 corrigé**

**Fichiers modifiés :**
- `src/helpers.php` : ajout de la fonction `canAccessReport(array $report, array $user): bool` centralisant la logique de contrôle d'accès. Prend en compte le rôle, le site, le mode de visibilité (par registre) et la confidentialité du signalement.
- `pages/report_view.php` : bloc de contrôle d'accès (27 lignes) remplacé par `if (!canAccessReport($report, $user))`.
- `pages/report_print.php` : bloc identique (18 lignes) remplacé de la même façon.
- `pages/report_attachment.php` : bloc identique (14 lignes) remplacé par `if (!canAccessReport($report, $user))`, conservation du HTTP 403 au lieu du redirect.

**Comportement préservé :** Seule la règle d'accès est centralisée. La gestion de l'échec (redirect+flash vs HTTP 403) reste spécifique à chaque page, comme demandé.

---

## Point 12 — Conservation / anonymisation

**Statut : 🔧 corrigé (mécanisme implémenté, politique à valider par le DPO)**

**Fichiers créés :**
- `tools/anonymize_old_reports.php` : script CLI avec mode `--dry-run`, confirmation interactive, logging dans `audit_log` (catégorie `gdpr`, action `anonymize`).

**Fichiers modifiés :**
- `src/database.php` : ajout de la clé `app_retention_years` (défaut `0` = désactivé) dans `migrateConfigKeys()`.
- `schema.sql` : ajout de `app_retention_years` dans les INSERT par défaut.
- `DEPLOY.md` : section « Conservation et anonymisation des signalements (RGPD) » ajoutée avec instructions de planification et avertissement DPO.

**Comportement :**
- `0` (défaut) : désactivé, conservation illimitée.
- `N > 0` : les signalements `traité`/`abandonné` dont `date_evenement` est antérieure à N années sont anonymisés (nom/prénom → « Anonymisé », pour_compte_nom/prenom → NULL).
- Le contenu descriptif (`description`, `reponse`) est conservé (seules les données nominatives sont anonymisées).

**⚠️ Nécessite revue humaine :** La durée de conservation doit être validée par le DPO avant activation.

---

## Point 13 — Journal d'accès aux signalements confidentiels

**Statut : 🔧 corrigé**

**Fichiers modifiés :**
- `src/helpers.php` : ajout de la fonction `logConfidentialReportAccess(PDO $pdo, array $report, array $user): void` qui ne loggue que lorsqu'un superviseur/CHSCT consulte un signalement `is_confidential=1` dont il n'est pas le déclarant.
- `src/database.php` : ajout de la table `report_access_log` (report_uuid, user_id, role, accessed_at) dans `migrateSchema()` + index.
- `schema.sql` : ajout de la table `report_access_log` avec index.
- `pages/report_view.php` : appel à `logConfidentialReportAccess()` après le contrôle d'accès.
- `pages/report_print.php` : idem.
- `pages/report_attachment.php` : idem.

**Filtre :** Ne loggue PAS les agents consultant leurs propres signalements, ni les consultations de signalements non confidentiels.

---

## Point 14 — Tests sur la logique d'autorisation

**Statut : 🔧 corrigé**

**Fichiers créés :**
- `tools/tests/test_can_access_report.php` : script autonome (pas de Composer, pas de DB) couvrant la matrice rôle × visibilité × site × confidentialité × déclarant = 72 cas + 7 cas limites = 79 tests au total.

**Fichiers modifiés :**
- `README.md` : section « Tests » ajoutée avec la commande d'exécution.

**Cas couverts :**
- 3 rôles (agent, superviseur, chsct) × 3 visibilités (confidential, agent_choice, public) × 2 sites (même, différent) × 2 confidentialités (0, 1) × 2 déclarants (soi, autre)
- Cas limites : superviseur/CHSCT sur autre site, agent en mode public sur autre site, agent en mode agent_choice sur signalement non confidentiel d'un autre, etc.

**Exécution :** `php tools/tests/test_can_access_report.php` — code de sortie 0 si succès, 1 si échec.

---

## Point 15 — Sauvegarde de data/sst.db

**Statut : 🔧 corrigé**

**Fichiers créés :**
- `tools/backup_sst_db.ps1` : script PowerShell (même style que `update_sst.ps1`) effectuant :
  1. Checkpoint WAL via PHP/SQLite (`PRAGMA wal_checkpoint(FULL)`)
  2. Copie horodatée de `data\sst.db` vers `data\backups\sst_db_YYYYMMDD_HHmmss.db`
  3. Nettoyage automatique des sauvegardes de plus de 30 jours

**Fichiers modifiés :**
- `DEPLOY.md` : note sur la planification de la sauvegarde via tâche planifiée Windows (`schtasks /create`), avec commande complète.

---

## Récapitulatif

| # | Axe | Statut | Revue humaine requise ? |
|---|-----|--------|------------------------|
| 9 | SST | 🔧 corrigé | ⚠️ Oui — valider réf. RSST/DGI, identifier base RAMI |
| 10 | SST | 🔧 corrigé | Non |
| 11 | Fonctionnel | 🔧 corrigé | Non |
| 12 | RGPD | 🔧 corrigé | ⚠️ Oui — valider durée de conservation avec DPO |
| 13 | RGPD | 🔧 corrigé | Non |
| 14 | Gouvernance | 🔧 corrigé | Non |
| 15 | Gouvernance | 🔧 corrigé | Non |

**Aucune référence légale ou réglementaire n'a été inventée dans le cadre de ces corrections.** Les deux points nécessitant une validation humaine (référence RAMI, durée de conservation) sont explicitement marqués `<!-- TODO revue juridique/RH -->` dans le code et signalés dans l'interface.

---

## v4 — 2026-06-14

### Correction A — Références légales RAMI (pages/preamble.php)

**Statut : ✅ corrigé**

**Fichiers modifiés :** `pages/preamble.php`

**Corrections appliquées :**

- Le commentaire HTML `<!-- TODO revue juridique/RH -->` a été **supprimé** totalement.
- La phrase `<strong>Cadre juridique à confirmer</strong>` et le texte « Le texte d'application est en cours d'identification » ont été **remplacés** par les références vérifiées sur Légifrance :
  - **Article L135-6 du CGFP** (loi n° 2019-828 du 6 août 2019)
  - **Articles R135-1 à R135-10 du CGFP** (décret n° 2024-1038 du 6 novembre 2024, en vigueur depuis le 1er février 2025)
  - Liens Légifrance vers les articles L135-6 et R135-1 à R135-10
- **RSST** : ajout du lien Légifrance vers le Décret 82-453 art. 3-2
- **DGI** : ajout des liens Légifrance vers les articles L4131-1 et D4132-1 du Code du travail

**Confirmation :** aucun TODO, aucune phrase « à confirmer » ne subsiste dans le HTML rendu côté agent. Les URL Légifrance sont présentes et correctes.

---

### Correction B — Anti-pattern de test canAccessReport (src/helpers.php + test)

**Statut : ✅ corrigé**

**Fichiers modifiés :**
- `src/helpers.php` : ajout d'un 3ème paramètre optionnel `?string $forcedVisibility = null` à `canAccessReport()`. Quand fourni, il contourne l'appel à `getReportVisibilityMode()` (et donc la DB), permettant aux tests d'injecter le mode de visibilité.
- `tools/tests/test_can_access_report.php` : réécriture complète. La fonction locale `testCanAccessReport()` (copie de la logique) a été **supprimée**. Le test appelle désormais **directement** `canAccessReport($report, $user, $visibilityMode)` importée depuis `src/helpers.php`. La matrice couvre les mêmes 79 cas (72 de la matrice + 7 cas limites).

**Confirmation :** le test appelle bien `canAccessReport()` depuis `helpers.php`, pas une copie locale. Les 3 call sites existants (`report_view.php`, `report_print.php`, `report_attachment.php`) passent exactement 2 arguments — le comportement de production est inchangé.

---

### Correction C — Protection anti-fixation de session (handlers/login_handler.php)

**Statut : ✅ corrigé**

**Fichiers modifiés :**
- `handlers/login_handler.php` : remplacement du commentaire `// session_regenerate_id(false) // Disabled for dev server;` par l'appel `safeSessionRegenerate();`
- `public/index.php` : ajout de `require_once __DIR__ . '/../src/session_patch.php';` dans le bootstrap (le fichier existait mais n'était jamais chargé)

**Comportement :** `safeSessionRegenerate()` appelle `session_regenerate_id(!DEV_MODE)` — en production, l'ancienne session est détruite (protection contre la fixation) ; en développement, le drapeau `false` évite le crash du serveur intégré PHP.

---

### Correction D — Header Content-Security-Policy (public/web.config)

**Statut : ✅ corrigé**

**Fichiers modifiés :** `public/web.config`

**Corrections appliquées :**
- Ajout du header `Content-Security-Policy` dans `<httpProtocol><customHeaders>` :
  `default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'`
- `unsafe-inline` requis pour les styles et scripts inline existants (pas de refactoring de scope)
- `img-src 'self' data: blob:` couvre les data URIs (favicons/logo inline, v3.8.0)
- `frame-ancestors 'none'` remplace fonctionnellement X-Frame-Options: DENY (les deux coexistent)
- `form-action 'self'` empêche l'exfiltration de formulaires vers un domaine externe
- Le header X-Frame-Options est conservé s'il existe (double protection, pas de conflit)
- XML valide (balises correctement fermées)

---

### Correction E — Chiffrement de smtp_pass en base

**Statut : ✅ corrigé**

**Fichiers modifiés :**
- `src/helpers.php` : ajout des fonctions `encryptConfigValue()` et `decryptConfigValue()` (AES-256-CBC, clé via `SST_SECRET_KEY`, format `enc:` + base64(iv + ciphertext))
- `src/mail.php` : lecture du mot de passe SMTP via `decryptConfigValue(getConfig('smtp_pass', ''))`
- `handlers/settings_handler.php` : écriture via `encryptConfigValue($smtpPass)` lors de la sauvegarde
- `src/database.php` : ajout de `migrateEncryptSmtpPass($pdo)` appelée dans le pipeline de migration — chiffre automatiquement les valeurs en clair existantes au premier démarrage
- `DEPLOY.md` : section « Variable d'environnement SST_SECRET_KEY (requis) » ajoutée après la section SMTP

**Confirmation :** la migration auto chiffre les valeurs en clair existantes dès le premier démarrage (si `SST_SECRET_KEY` est configurée). Les fonctions `getConfig()` et `updateConfig()` ne sont pas modifiées — le chiffrement est géré par les deux fonctions dédiées.
