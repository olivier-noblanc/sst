# GRAPHIFY REPORT — SST DREETS BFC
## Analyse Honnête de la Qualité du Code | Rapport CTO

> ⚠️ **RAPPORT HISTORIQUE OBSOLÈTE** — Daté du 8 juillet 2026, version v3.26.0.
> Le projet est actuellement en **v3.64.0** (août 2026) et a subi une refactorisation DDD complète.
> Ce document décrit une architecture **100% procédurale** qui n'existe plus — la plupart des "god functions"
> listées ici ont été converties en classes OOP (Services, Repository, DTO, Enums).
> **Ne pas utiliser comme référence pour l'état actuel du code.**

**Date** : 8 juillet 2026  
**Version** : v3.26.0  
**Auteur** : Analyse automatique (4 agents parallèles + revue manuelle)

---

## Résumé Exécutif — Le Verdict Brutal

L'application SST est **fonctionnelle, sécurisée et bien testée**, mais présente des **défauts structurels majeurs** en termes de architecture logicielle. Le code est **100% procédural** (zéro classe, zéro OOP), avec des **god functions**, de la **duplication significative**, et une **absence totale d'abstraction**.

| Indicateur | Valeur | Verdict |
|---|---|---|
| **Lignes de code** | 22 003 (PHP) | Projet modéré |
| **Architecture** | 100% procédural | ⚠️ Vieillissant |
| **God functions** | 12 fonctions > 50 lignes | 🔴 Problème |
| **Duplication** | ~15% du code dupliqué | ⚠️ À réfactorer |
| **Tests** | 360 tests / 131 assertions | ✅ Excellente couverture |
| **Sécurité** | 10/12 catégories fortes | ✅ Fort |
| **Dette technique** | Moyenne | ⚠️ À addresser |

**Note CTO** : Ce projet a été construit "pour fonctionner" — et il fonctionne bien. Mais il n'a jamais été conçu pour évoluer proprement. La dette technique s'accumule.

---

## 1. 🔴 Problème #1 : 100% Procédural — Zéro OOP

### Le constat

Le code entier est constitué de **fonctions procédurales globales**. Il n'existe **aucune classe** dans le code de production (hors FPDF/Parsedown vendored).

```
src/auth.php          → 12 fonctions globales
src/session.php       → 15 fonctions globales  
src/user_context.php  → 18 fonctions globales (wrapper de wrappers)
src/helpers/formatting.php → 15 fonctions globales
src/helpers/access.php    → 10 fonctions globales
src/queries/*.php     → 40+ fonctions globales
handlers/*.php        → Scripts procéduraux (pas de fonctions)
pages/*.php           → Scripts mixant logique + HTML
```

**Total : ~130+ fonctions globales dans l'espace de noms global.**

### Pourquoi c'est un problème

1. **Collisions de noms** : avec 130+ fonctions globales, le risque de collision augmente avec chaque ajout
2. **Testabilité** : pas d' injection de dépendance, pas de mocking possible sans include path hack
3. **Lisibilité** : impossible de savoir quel fichier définit quelle fonction sans lire tout le code
4. **Maintenabilité** : refactorer une fonction nécessite de vérifier tous les appelants manuellement
5. **Scalabilité** : l'ajout de fonctionnalités complexes (workflow, multi-tenant) deviendra ingérable

### Comparaison avec une architecture OOP

```php
// ACTUEL : 130+ fonctions globales
function createReport(PDO $pdo, array $data): string { ... }
function getReportByUuid(PDO $pdo, string $uuid): ?array { ... }
function updateReport(PDO $pdo, string $uuid, array $data, int $userId): bool { ... }

// PROPOSÉ : Classe avec responsabilité unique
class ReportService {
    public function __construct(
        private ReportRepository $repo,
        private NotificationService $notifications,
        private AuditLogger $audit
    ) {}
    
    public function create(CreateReportCommand $cmd): Report { ... }
    public function getById(Uuid $id): ?Report { ... }
    public function update(Uuid $id, UpdateReportCommand $cmd): void { ... }
}
```

---

## 2. 🔴 Problème #2 : God Functions

### Top 12 des fonctions trop longues

| # | Fonction | Fichier | Lignes | Params | Branches | Responsabilités |
|---|---|---|---|---|---|---|
| 1 | `getReportsByRegistry()` | report_queries.php:135 | **67** | 7 | 8+ | Construit WHERE dynamique + FTS5 + pagination |
| 2 | `createReport()` | report_queries.php:50 | **62** | 2 | 3 | INSERT + FTS5 + transaction + error handling |
| 3 | `notifyNewReport()` | mail_notifications.php:19 | **40** | 4 | 4 | Query DB + build HTML + send loop + DGI special |
| 4 | `getExportData()` | stats_queries.php:54 | **60** | 2 | 6 | Construit WHERE dynamique avec 6 filtres |
| 5 | `buildWordCloud()` | formatting.php:236 | **56** | 3 | 5 | Query DB + NLP + HTML generation |
| 6 | `getStatsBySite()` | stats_queries.php:177 | **39** | 3 | 3 | Construit WHERE dynamique + GROUP BY |
| 7 | `getStatisticsIndicateurs()` | stats_queries.php:125 | **42** | 3 | 3 | Aggregations dynamiques |
| 8 | `report_create_handler.php` | handler | **199** | — | 15+ | Script complet inline |
| 9 | `home.php` | page | **198** | — | 12+ | Logique + HTML mélangés |
| 10 | `report_list.php` | page | **213** | — | 10+ | Logique + HTML mélangés |
| 11 | `sendAgentInviteEmails()` | mail_notifications.php:219 | **27** | 3 | 4 | Loop + token + email build |
| 12 | `checkAndPromoteUser()` | auth.php:217 | **22** | 3 | 3 | Query + update + log |

### Analyse détaillée des pires cas

#### `getReportsByRegistry()` — Le God Query Builder

```php
// 67 lignes, 7 paramètres, 8+ branches conditionnelles
function getReportsByRegistry(
    PDO $pdo, string $type, array $filters, 
    int $userSiteId, bool $seeAllSites, 
    int $page = 1, int $perPage = 20
): array {
    // 8 if/elseif pour construire $where dynamiquement
    // FTS5 avec fallback LIKE
    // COUNT séparé + data query
    // Pagination LIMIT/OFFSET
}
```

**Problème** : Cette fonction fait 3 choses distinctes :
1. Construire la requête WHERE (filtres)
2. Exécuter le COUNT
3. Exécuter la requête data avec pagination

**Réfactoring proposé** : Extraire un `ReportFilterBuilder` et un `PaginatedQuery`.

#### `report_create_handler.php` — Le Script Monolithique

```php
// 199 lignes de script procédural
validatePostRequest(url('home'));           // L10
$type = $_POST['type'] ?? '';              // L13
// ... 20 lignes de trim/cast sur $_POST ...
$errors = validateReportFields(...);       // L55
$attachment = validateReportAttachment($errors); // L58
// ... validation site, RAMI, emails ...
$reportData = [...];                       // L119-159 (40 lignes de mapping)
createReport($pdo, $reportData);           // L163
auditLog(...);                             // L169
notifyNewReport(...);                      // L174
// ... encore 20 lignes ...
```

**Problème** : Un seul fichier contient :
- Validation des entrées
- Normalisation des données
- Mapping vers le schéma DB
- Appel au service de création
- Audit logging
- Envoi de notifications
- Gestion des erreurs
- Redirection

**Réfactoring proposé** : Extraire un `ReportCreateAction` ou `ReportController::create()`.

---

## 3. ⚠️ Problème #3 : Duplication de Code

### Duplication #1 : Cartes de registre (home.php)

Le HTML des cartes RSST/RAMI/DGI est dupliqué **2 fois** dans `home.php` :
- Lignes 53-65 (vue agent — `registry-cards--large`)
- Lignes 134-181 (vue superviseur — `registry-cards`)

**~50 lignes de HTML quasi identique** avec seulement des variations de classe CSS et de texte.

```php
// Copié-collé #1 (agent)
<div class="registry-card registry-card--rsst home-action--large">
    <div class="registry-card__icon">&#x1F4CB;</div>
    <div class="registry-card__title">Registre de Santé et de Sécurité au Travail</div>
    ...
</div>

// Copié-collé #2 (superviseur)  
<div class="registry-card registry-card--rsst">
    <div class="registry-card__icon">&#x1F4CB;</div>
    <div class="registry-card__title">Registre de Santé et de Sécurité au Travail</div>
    ...
</div>
```

### Duplication #2 : Construction de WHERE dynamique

Le pattern "construire $where avec des if/elseif" est répété dans **4 fichiers** :

| Fichier | Fonction | Lignes de WHERE dynamique |
|---|---|---|
| `report_queries.php` | `getReportsByRegistry()` | 30 lignes |
| `stats_queries.php` | `getExportData()` | 35 lignes |
| `stats_queries.php` | `getStatsBySite()` | 15 lignes |
| `stats_queries.php` | `getStatisticsIndicateurs()` | 12 lignes |

**Pattern répété :**
```php
if (!empty($filters['type'])) {
    $sql .= ' AND r.type = :type';
    $params[':type'] = $filters['type'];
}
if (!empty($filters['site_id'])) {
    $sql .= ' AND r.site_id = :site_id';
    $params[':site_id'] = $filters['site_id'];
}
// ... 6x de suite ...
```

### Duplication #3 : Emails HTML inline

Chaque fonction de notification construit son propre HTML email **inline** :

| Fonction | Lignes de HTML | Pattern |
|---|---|---|
| `notifyNewReport()` | 12 lignes | `$body .= '<p>...'` |
| `notifyReportResponse()` | 10 lignes | `$body .= '<p>...'` |
| `notifyPourCompte()` | 10 lignes | `$body .= '<p>...'` |
| `notifyRoleChange()` | 20 lignes | `$body .= '<p>...'` |
| `sendAgentInviteEmails()` | 10 lignes | `$body .= '<p>...'` |

**~62 lignes de HTML email dupliqué** au lieu d'utiliser un template unifié.

### Duplication #4 : Vérifications de visibility

Le pattern de vérification de visibilité est répété dans **3 pages** :

```php
//home.php
$agentVisibility = getReportVisibility();
if ($agentVisibility === 'confidential') { ... }
elseif ($agentVisibility === 'agent_choice') { ... }
else { ... }

// report_list.php  
$agentVisibility = getReportVisibility();
if ($agentVisibility === 'confidential') { ... }
elseif ($agentVisibility === 'agent_choice') { ... }
else { ... }
```

---

## 4. ⚠️ Problème #4 : Couche Métier Inexistante

### Le constat

Il n'y a **aucune séparation entre la logique métier et les handlers/pages**. Les scripts dans `handlers/` font tout : validation, mapping, DB, audit, notifications, redirection.

```
handlers/report_create_handler.php
  → Validation des entrées (devrait être dans un validator)
  → Mapping des données (devrait être dans un DTO/command)
  → Appel DB (devrait être dans un repository)
  → Audit log (devrait être dans un event dispatcher)
  → Notifications (devrait être dans un event listener)
  → Redirection (devrait être dans un controller)
```

### Impact

- **Testabilité** : impossible de tester la logique métier sans simuler $_POST, $_GET, session, et headers
- **Réutilisabilité** : la création de signalement depuis une API future nécessitera de dupliquer tout le handler
- **Maintenabilité** : modifier le workflow de création touche 1 fichier monolithique de 199 lignes

---

## 5. ⚠️ Problème #5 : Fichiers "God File"

### Top 5 des fichiers les plus gros

| Fichier | Lignes | Contenu |
|---|---|---|
| `templates/report_form.php` | 280 | Formulaire HTML + logique conditionnelle |
| `src/migration_columns.php` | 269 | Migrations SQL |
| `src/helpers/formatting.php` | 269 | Escaping + dates + badges + word cloud + breadcrumb |
| `pages/settings/tab_app.php` | 256 | Settings UI + logique |
| `src/mail_notifications.php` | 251 | 5 fonctions de notification + HTML inline |

### `formatting.php` — Un fichier qui fait tout

```php
function e()              → HTML escaping
function formatDateFR()   → Date formatting
function formatDateTimeFR() → DateTime formatting
function generateReference() → Report reference generation
function getNextSequence()   → DB sequence (quoi ?)
function getRegistryColor()  → CSS variable
function getEtatBadgeClass() → CSS class
function getRegistryBadgeClass() → CSS class
function getRoleBadgeClass() → CSS class
function getMimeType()    → File MIME detection
function truncate()       → String truncation
function todayISO()       → Date utility
function nowTime()        → Time utility
function renderBreadcrumb() → HTML rendering
function buildWordCloud() → DB query + NLP + HTML
```

**Ce fichier contient 5 responsabilités distinctes** : escaping, formatting, badge logic, file utilities, et word cloud generation. Il devrait être en 3-4 fichiers.

---

## 6. 📊 Analyse de la Complexité

### Cyclomatic Complexity Estimée

| Fonction | Complexité | Seuil recommandé |
|---|---|---|
| `getReportsByRegistry()` | **12** | < 10 |
| `report_create_handler.php` (script) | **18** | < 10 |
| `home.php` (script) | **15** | < 10 |
| `report_list.php` (script) | **12** | < 10 |
| `canAccessReport()` | **8** | < 10 |
| `getExportData()` | **9** | < 10 |
| `buildWordCloud()` | **7** | < 10 |
| `notifyNewReport()` | **6** | < 10 |

### Nombre de Paramètres

| Fonction | Params | Seuil recommandé |
|---|---|---|
| `getReportsByRegistry()` | **7** | < 4 |
| `getExportData()` | **2** (mais $filters est un array non typé) | — |
| `sendMail()` | **4** | < 4 |
| `createReport()` | **2** ($data est un array non typé) | — |
| `notifyNewReport()` | **4** | < 4 |

---

## 7. 🔍 Ce qui est bien fait (malgré tout)

### Sécurité — Impeccable

| Aspect | Implémentation | Note |
|---|---|---|
| SQL Injection | PDO prepared statements partout | 10/10 |
| XSS | `e()` centralisée | 10/10 |
| CSRF | Tokens one-time | 10/10 |
| Auth | Windows Auth + session hardening | 9/10 |
| Audit | Piste complète avec IP | 10/10 |

### Tests — Excellente couverture

- **360 tests unitaires** couvrant auth, queries, validation, CSRF, crypto, formatting
- **11 specs E2E** Playwright
- **PHPStan Level 6** + PSR-12

### Conformité Légale — Complète

- RGPD avec anonymisation
- Code du travail (articles L4131-1, L4131-3, D4132-1)
- Piste d'audit pour chaque transition d'état

---

## 8. 📈 Score Corrigé

```
┌─────────────────────────────────────────────────────────┐
│                  GRAPHIFY SCORE CORRIGÉ                  │
│                                                         │
│   Sécurité          ████████████████████░  9.5/10      │
│   Tests             ████████████████████░  9/10        │
│   Conformité        ████████████████████░  9.5/10      │
│   Performance       ████████████████████░  9/10        │
│   Architecture      ██████████░░░░░░░░░░░  5/10        │
│   Maintenabilité    ████████████░░░░░░░░░  6/10        │
│   Abstraction       ████████░░░░░░░░░░░░░  4/10        │
│   DRY (duplication) ██████████░░░░░░░░░░░  5/10        │
│                                                         │
│   SCORE GLOBAL      ████████████████░░░░░  7.1/10      │
│                                                         │
│   VERDICT: PROJET FONCTIONNEL MAIS ARCHITECTURALEMENT   │
│   FAIBLE — dette technique à addresser avant scaling    │
└─────────────────────────────────────────────────────────┘
```

---

## 9. 🚀 Plan de Refactoring Recommandé

### Phase 1 — Quick Wins (1-2 jours)

| Action | Effort | Impact |
|---|---|---|
| Extraire `RegistryCardRenderer` depuis home.php | 2h | Élimine duplication HTML |
| Extraire `QueryFilterBuilder` depuis report_queries.php | 4h | Élimine duplication WHERE |
| Scorer `formatting.php` en 3 fichiers | 2h | Respecte SRP |
| Extraire `EmailRenderer` depuis mail_notifications.php | 3h | Template unifié |

### Phase 2 — Structure (3-5 jours)

| Action | Effort | Impact |
|---|---|---|
| Créer des DTOs pour les entrées ($data arrays → typed objects) | 4h | Type safety |
| Créer `ReportService` (logique métier depuis handlers) | 8h | Testabilité |
| Créer `ReportRepository` (depuis report_queries.php) | 4h | Couche d'abstraction |
| Créer `ReportController` (depuis handlers + pages) | 8h | Séparation UI/métier |

### Phase 3 — Architecture (2-3 semaines)

| Action | Effort | Impact |
|---|---|---|
| Introduire un container DI léger | 4h | Découplage |
| Créer un Event System (création → notifications) | 8h | Extensibilité |
| Ajouter un Router basé sur des attributs PHP 8 | 4h | Routes explicites |
| Migrer les pages vers des templates isolés | 16h | Séparation complète |

---

## 10. 💡 Recommandation CTO Finale

**Ne pas tout casser.** Le code fonctionne, est sécurisé, et est bien testé. Le refactoring doit être **incrémental** :

1. **Commencer par les quick wins** (Phase 1) — impact immédiate, risque zéro
2. **Ajouter des tests** avant chaque refactoring (déjà 360 tests, c'est un atout)
3. **Ne pas migrer vers un framework** — le choix procédural était délibéré pour la simplicité de déploiement gouvernemental
4. **Documenter les patterns** existants pour que les futurs développeurs comprennent la logique
5. **Prioriser la couche métier** (Phase 2) — c'est le vrai blocage pour l'évolutivité

**En résumé** : le projet a une **excellente fondation** (sécurité, tests, conformité) mais une **architecture vieillissante**. Le refactoring est faisable sans réécriture, mais nécessite de la discipline.

---

*Généré par Graphify — Analyse honnête du codebase SST DREETS BFC*  
*4 agents parallèles + revue manuelle • 8 juillet 2026*
