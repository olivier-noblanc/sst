# Statut final des findings — audit initial

**Date :** 2026-09-14
**Référence :** HEAD `6528397` (lot livré `9a1723b`, CI verte `34835467593`)
**Nature :** consolidation documentaire du statut final des findings, sur décision Oracle
**Scope :** documentation uniquement — **aucun** code PHP ni `public/web.config` modifié

---

## 1. Objet

Un rapport d'audit initial a listé plusieurs findings. Une revue indépendante (Oracle)
a statué sur le traitement de chacun. Le présent document **consigne le statut final**
de ces findings dans un chemin suivi par git, conformément à la règle « Rapports
d'audit » de `AGENTS.md` (jamais uniquement dans `worklog.md`/`download/`).

Il ne rouvre aucune tâche et ne substitue pas aux canaux habituels de vérification
(tests, PHPStan, CI).

**État de l'arbre :** au moment de la rédaction, le working tree contient des
modifications **non commitées** traitant deux findings (`src/database.php` et les tests
associés ; `tests/unit/CliEntryPointsIncludeTest.php`). Elles sont décrites telles
qu'observées ; elles n'ont **pas** été produites ni modifiées par ce lot documentaire.

## 2. Statut de synthèse

| Finding | Statut final | Suite donnée |
|---|---|---|
| `promote.php` (et `seed.php`) | **Bug confirmé** | Correctif en working tree non commité (requires orphelins supprimés) + garde-fou `CliEntryPointsIncludeTest` ; aucun code modifié par ce lot documentaire |
| `busy_timeout` (concurrence SQLite) | **Hypothèse traitée par test/correctif** (Oracle) | `PRAGMA busy_timeout = 5000` sur `getDB()` + test subprocess dédié (working tree non commité) |
| URL Rewrite / `web.config` | **Dette documentaire — faux finding fonctionnel** | Documentation corrigée (`DEPLOY.md`, `AGENTS.md`) |
| Visibilité | **Non reproduite — code inchangé** | Aucune modification |
| Autres points du rapport initial | **Docs obsolètes** | Requalifiés obsolètes, sans ré-assertion ni preuve inventée |

## 3. Détail par finding

### 3.1 `promote.php` — bug confirmé

Le script `promote.php` charge une dépendance qui n'existe plus :

- `promote.php:41` : `require_once __DIR__ . '/src/queries/user_queries.php';`
- Le répertoire `src/queries/` a été **supprimé** par le commit `446348f`
  (« fix(ci) : créer /tmp/php_sessions + supprimer queries procédurales », 2026-07-24).
- `promote.php` n'a pas été mis à jour lors de cette suppression ; sa dernière
  modification remonte à `c1ffef6` (2026-06-16), **antérieure** à la suppression.

**Conséquence :** toute exécution de `promote.php` échoue au chargement
(`require_once` sur un fichier absent → erreur fatale), avant même la logique de
promotion. Le finding est donc **confirmé comme bug réel**.

**Motif identique dans `seed.php` :** les mêmes `require_once` vers
`src/queries/report_queries.php`, `src/queries/user_queries.php` et
`src/queries/site_queries.php` y subsistent également (fichiers absents).

**Correctif (working tree, non commité) :** les `require_once` orphelins ont été retirés
— `promote.php` (`src/queries/user_queries.php`) et `seed.php`
(`report_queries.php`, `user_queries.php`, `site_queries.php`, ainsi que
`seed/_notifications.php`, fragment lui aussi absent). Vérification : tous les includes
`__DIR__` de `promote.php`, `seed.php` et `seed/_*.php` résolvent désormais vers un
fichier existant.

**Garde-fou posé (working tree, non commité) :** `tests/unit/CliEntryPointsIncludeTest.php`
vérifie que chaque `require`/`include` écrit avec `__DIR__` dans `promote.php`, `seed.php`
et les fragments `seed/_*.php` résout vers un fichier existant — signal RED avant
correction, GREEN après.

**Portée :** la correction du code n'entre pas dans le scope de ce lot documentaire
(contrainte : aucun code PHP modifié) et n'est pas commitée. La dette documentaire associée
— arborescences citant encore `src/queries/` — a été corrigée dans `DEPLOY.md` et `AGENTS.md`.

### 3.2 `busy_timeout` — hypothèse traitée par test/correctif

Finding d'origine : **hypothèse** portant sur la concurrence d'écriture SQLite
(`SQLITE_BUSY`) sur la connexion applicative de `getDB()`. Statut retenu par l'Oracle :
l'hypothèse a été **traitée par un test et un correctif**, et **non** retenue comme bug
en soi.

**Correctif (working tree, non commité) :** `src/database.php:55` configure la connexion
`getDB()` avec `PRAGMA busy_timeout = 5000;`, aux côtés de `foreign_keys = ON` et
`journal_mode = WAL`.

**Test associé (working tree, non commité) :**
`tests/unit/DatabaseBusyTimeoutTest.php`, adossé au subprocess runner
`tests/database_busy_timeout_runner.php`. Le bootstrap de test redéfinissant `getDB()`,
le runner démarre un process PHP réel qui charge l'autoloader de production, appelle le
vrai `getDB()` sur une base fichier jetable et renvoie les PRAGMA effectifs ; le test
assert que `busy_timeout` vaut exactement 5000 ms (avec contrôles de bord `journal_mode`
et `foreign_keys` pour prouver qu'on teste bien le code de production).

**Découverte consignée par le test :** la prémisse de l'audit supposait le défaut SQLite
à `0 ms`. En réalité, `PDO_SQLITE` positionne `PDO::ATTR_TIMEOUT` à 60 s (mappé sur
`sqlite3_busy_timeout()`), donc la valeur *effective* avant correctif est `60000 ms`, pas
`0`. Le PRAGMA explicite **abaisse** cette attente bornée à 5 s : c'est un changement de
comportement délibéré (réduction de la latence pire-cas), signalé pour validation, et non
la réparation d'un timeout absent.

**Contexte concurrence déjà en place :** verrou de prise atomique + reprise après échec
couverts par `tests/unit/CronServiceRetryTest.php` ; sérialisation `workers: 1` en CI
(`playwright.config.js`, commit `55b9f45`) pour la course sur la base SQLite partagée en
E2E. `nuclear-reset.php:55` conserve son propre `PRAGMA busy_timeout = 30000` (script CLI
isolé).

### 3.3 URL Rewrite / `web.config` — dette documentaire, pas un défaut fonctionnel

Le finding supposait une dépendance de l'application au module URL Rewrite. Vérification :

- L'application **n'utilise pas** URL Rewrite : le routage passe intégralement par la
  query string (`?page=xxx`).
- `public/web.config` ne contient qu'**une** utilisation de `<rewrite>` : la règle
  **outbound** « Remove Server Header » (`public/web.config`, section `rewrite`).
  Cette règle est **facultative** et **inopérante sans le module** ; elle n'affecte ni le
  routage ni les fonctionnalités.
- Aucune règle entrante (`inboundRules`) : l'application ne requiert donc pas le module.

**Conclusion :** faux finding fonctionnel — il s'agit d'une dette documentaire, désormais
corrigée (`DEPLOY.md` : `web.config` optionnel, règle Server header facultative/inopérante ;
`AGENTS.md` : CSP effectif émis par PHP). Aucun module à installer.

### 3.4 Visibilité — non reproduite, code inchangé

Finding d'origine sur la **visibilité des signalements** : **non reproduit** dans le
périmètre courant. Aucune modification de code n'a été apportée.

Couverture existante (non rejouée dans ce lot documentaire) : `tests/unit/VisibilityModeTest.php`,
`tests/unit/AccessHelperVisibilityTest.php`, `tests/unit/LinkedAgentVisibilityTest.php` ;
`TODO.md` classe les corrections de visibilité comme validées par les tests et la CI.

### 3.5 Autres points du rapport initial — docs obsolètes

Les points du rapport initial non rattachables à une preuve vérifiable dans l'arbre
courant (commit `6528397`) sont **classés comme documentation obsolète**. Le rapport
initial lui-même n'est pas archivé dans un chemin versionné du dépôt, ce qui empêche
toute ré-assertion item par item.

Ils ne sont ni re-listés comme tâches ouvertes, ni re-déclarés comme bugs : conformément
à la contrainte « sans inventer de preuves », seul ce qui est vérifiable est consigné.
`TODO.md` §« Historique requalifié — à ne pas rouvrir » porte déjà la requalification des
constats historiques (métriques intermédiaires, échecs E2E anciens, lots d'audit CTO).

## 4. Validations

Ce lot ne modifie que de la documentation. Vérifications effectuées :

- Présence/absence des fichiers cités (`src/queries/` absent ; `promote.php`, `seed.php`
  présents ; fichiers `src/queries/*` requis absents).
- Historique git des artefacts (`446348f`, `c1ffef6`, `55b9f45`).
- Lecture de l'état courant de `src/database.php` (`PRAGMA busy_timeout = 5000` en
  place) et des tests associés (`DatabaseBusyTimeoutTest`, runner, `CliEntryPointsIncludeTest`).
- Cohérence de l'énoncé CSP avec les sources PHP (`templates/header.php`,
  `pages/login.php`, `pages/choose_site.php`) et `public/web.config`.
- Résolution des includes `__DIR__` de `promote.php`, `seed.php` et `seed/_*.php`
  (contrôle par script) : tous pointent vers un fichier existant après le correctif.
- Diff documentaire limité à `DEPLOY.md`, `AGENTS.md` et le présent fichier (les
  modifications code/tests observées dans le working tree sont hors de ce lot).

Aucun test PHPUnit/PHPStan n'a été **rejoué** pour ce lot (périmètre documentaire) : les
statuts s'appuient sur la CI rapportée (`34835467593`), l'historique git et la lecture
directe de l'arbre. Le correctif des includes CLI place `CliEntryPointsIncludeTest` en
position GREEN attendue (signal RED avant correction).

## 5. Limites explicites

- La décision Oracle est relayée ici ; son détail n'est pas re-vérifié indépendamment.
- Les traitements `busy_timeout`, `promote.php`/`seed.php` et le garde-fou des points
  d'entrée CLI sont présents en **working tree non commité** ; ils peuvent évoluer après
  la rédaction.
- Le rapport d'audit **initial** n'étant pas versionné, ses items non recoupables sont
  qualifiés obsolètes sans ré-assertion.

## 6. Conclusion

Findings confirmés : `promote.php` et `seed.php` (includes `src/queries/` manquants — bug
réel, correctif en working tree non commité + garde-fou). Finding écarté comme fonctionnel :
URL Rewrite (dette documentaire, corrigée). Hypothèse traitée : `busy_timeout` (correctif
`PRAGMA busy_timeout = 5000` + test subprocess, en working tree non commité). Non reproduit :
visibilité (code inchangé). Le reste du rapport initial est classé documentation obsolète.

**Validation owner :** orchestrateur.