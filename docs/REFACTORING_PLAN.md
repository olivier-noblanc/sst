# Plan de Refactoring — SST DREETS BFC
## 3 Phases, Incrémental, Zéro Réécriture

> ⚠️ **PLAN OBSOLÈTE** — Daté du 8 juillet 2026, version v3.26.0.
> Le projet est actuellement en **v3.64.0** (août 2026).
> **Toutes les phases 1 et 2 sont réalisées** : Services OOP, Repository, DTOs readonly, EventDispatcher,
> formatting.php refactorisé, EmailRenderer, RegistryCardService, etc.
> Ce document est conservé à titre historique uniquement.

**Règle d'or** : chaque étape est **indépendante et déployable**. On ne casse jamais quelque chose qui fonctionne.

---

## Phase 1 — Quick Wins (1-2 jours)
### Objectif : Éliminer la duplication, respecter le SRP
### Risque : Quasi nul — on déplace du code, on ne change pas le comportement

---

### 1.1 Scinder `formatting.php` en 3 fichiers

**Fichier actuel** : `src/helpers/formatting.php` (269 lignes, 5 responsabilités)

**Avant** :
```
src/helpers/formatting.php
  → e(), formatDateFR(), formatDateTimeFR()     [escaping + formatting]
  → generateReference(), getNextSequence()       [report utilities]
  → getRegistryColor(), get*BadgeClass()         [badge logic]
  → getMimeType(), truncate(), todayISO(), nowTime() [utilities]
  → renderBreadcrumb(), buildWordCloud()          [rendering + NLP]
```

**Après** :
```
src/helpers/escaping.php        → e() + formatDateFR() + formatDateTimeFR()
src/helpers/badges.php          → getRegistryColor() + get*BadgeClass()
src/helpers/utilities.php       → getMimeType() + truncate() + todayISO() + nowTime()
src/helpers/rendering.php       → renderBreadcrumb()
src/helpers/wordcloud.php       → buildWordCloud() + stopWords
```

**Fichier de compatibilité** (bridging) :
```php
// src/helpers/formatting.php — REDIRECT COMPAT (supprimer après migration)
require_once __DIR__ . '/escaping.php';
require_once __DIR__ . '/badges.php';
require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/rendering.php';
require_once __DIR__ . '/wordcloud.php';
// Legacy aliases
function generateReference(string $type, string $year2, int $seq): string {
    return $type . '-' . $year2 . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}
function getNextSequence(PDO $pdo, string $type, int $year): int {
    // reste inchangé — déplacer dans report_queries.php à terme
}
```

**Tests** : Exécuter `phpunit` — tous les 360 tests doivent passer sans modification.

---

### 1.2 Extraire `RegistryCardRenderer` depuis `home.php`

**Problème** : Le HTML des cartes RSST/RAMI/DGI est dupliqué 2 fois dans `home.php` (~50 lignes identiques).

**Nouveau fichier** : `src/helpers/registry_card_renderer.php`

```php
<?php
/**
 * Registry Card Renderer — Extrait depuis home.php
 * Élimine la duplication du HTML des cartes de registre.
 */

/**
 * Render a single registry card.
 *
 * @param array{type: string, title: string, subtitle: string, desc: string, count: int, btnLabel: string, btnUrl: string, listUrl: string} $card
 * @param string $extraClass  Additional CSS class (e.g. 'home-action--large')
 * @return string HTML
 */
function renderRegistryCard(array $card, string $extraClass = ''): string
{
    $cssClass = 'registry-card registry-card--' . e($card['type']);
    if ($extraClass !== '') {
        $cssClass .= ' ' . e($extraClass);
    }
    
    $countLabel = $card['count'] . ' signalement' . ($card['count'] !== 1 ? 's' : '')
                . ' enregistré' . ($card['count'] !== 1 ? 's' : '');
    
    $html = '<div class="' . $cssClass . '">';
    $html .= '<div>';
    $html .= '<div class="registry-card__icon">' . getRegistryIcon($card['type']) . '</div>';
    $html .= '<div class="registry-card__title">' . e($card['title']) . '</div>';
    $html .= '<div class="registry-card__subtitle">' . e($card['subtitle']) . '</div>';
    $html .= '<p class="registry-card__desc">' . e($card['desc']) . '</p>';
    $html .= '</div>';
    $html .= '<div>';
    $html .= '<a href="' . e($card['btnUrl']) . '" class="registry-card__btn">' . e($card['btnLabel']) . '</a>';
    $html .= '<a href="' . e($card['listUrl']) . '" class="registry-card__link">Voir les signalements</a>';
    $html .= '<div class="registry-card__stat">' . $countLabel . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

function getRegistryIcon(string $type): string
{
    return match ($type) {
        'rsst' => '&#x1F4CB;',
        'rami' => '&#x26A0;&#xFE0F;',
        'dgi'  => '&#x1F534;',
        default => '&#x1F4CB;',
    };
}

/**
 * Render the registry cards grid for the home page.
 *
 * @param array<int, array{type: string, title: string, subtitle: string, desc: string, count: int, btnLabel: string, btnUrl: string, listUrl: string}> $cards
 * @param string $layout  'compact' or 'large'
 * @return string HTML
 */
function renderRegistryCards(array $cards, string $layout = 'compact'): string
{
    $gridClass = $layout === 'large' ? 'registry-cards registry-cards--large' : 'registry-cards';
    $extraClass = $layout === 'large' ? 'home-action--large' : '';
    
    $html = '<div class="' . $gridClass . '">';
    foreach ($cards as $card) {
        $html .= renderRegistryCard($card, $extraClass);
    }
    $html .= '</div>';
    
    return $html;
}
```

**home.php réécrit** (avant ~198 lignes → après ~90 lignes) :
```php
<?php
$pageTitle = 'Accueil';
$pdo = getDB();
$user = currentUser();
// ... logique de comptage identique ...

$rsstCards = [];
$rsstCards[] = [
    'type' => 'rsst', 'title' => 'Registre de Santé et de Sécurité au Travail',
    'subtitle' => 'RSST', 'desc' => getConfig('app_rsst_description', '...'),
    'count' => $rsstCount, 'btnLabel' => 'Déposer un signalement',
    'btnUrl' => url('report_create', ['type' => TYPE_RSST]),
    'listUrl' => url('report_list', ['type' => TYPE_RSST]),
];
if ($ramiEnabled) {
    $rsstCards[] = [ /* RAMI card data */ ];
}
if ($dgiEnabled) {
    $rsstCards[] = [ /* DGI card data */ ];
}

// Une seule fonction pour les deux layouts
$layout = ($userRole === ROLE_AGENT) ? 'large' : 'compact';
echo renderRegistryCards($rsstCards, $layout);
```

---

### 1.3 Extraire `QueryFilterBuilder`

**Problème** : Le pattern "construire $where dynamique" est répété dans 4 fonctions.

**Nouveau fichier** : `src/helpers/query_filter_builder.php`

```php
<?php
/**
 * Query Filter Builder — Élimine la duplication des WHERE dynamiques.
 */

class QueryFilterBuilder
{
    private string $where = '1=1';
    private array $params = [];

    public function addFilter(string $column, mixed $value, string $condition = 'AND'): self
    {
        if ($value === null || $value === '' || $value === 0) {
            return $this;
        }
        $paramKey = ':' . str_replace('.', '_', $column) . '_' . count($this->params);
        $this->where .= " $condition $column = $paramKey";
        $this->params[$paramKey] = $value;
        return $this;
    }

    public function addInFilter(string $column, array $values, string $condition = 'AND'): self
    {
        if (empty($values)) {
            return $this;
        }
        $placeholders = [];
        foreach ($values as $i => $value) {
            $key = ':in_' . str_replace('.', '_', $column) . '_' . $i;
            $placeholders[] = $key;
            $this->params[$key] = $value;
        }
        $this->where .= " $condition $column IN (" . implode(', ', $placeholders) . ')';
        return $this;
    }

    public function addCustomCondition(string $sqlFragment, string $condition = 'AND'): self
    {
        $this->where .= " $condition $sqlFragment";
        return $this;
    }

    public function build(): array
    {
        return ['where' => $this->where, 'params' => $this->params];
    }

    public function getWhere(): string
    {
        return $this->where;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}
```

**Utilisation dans `report_queries.php`** (avant 67 lignes → après 25 lignes) :
```php
function getReportsByRegistry(PDO $pdo, string $type, array $filters, int $userSiteId, bool $seeAllSites, int $page = 1, int $perPage = 20): array
{
    $builder = new QueryFilterBuilder();
    $builder->addFilter('r.type', $type);

    if (!$seeAllSites) {
        $builder->addFilter('r.site_id', $userSiteId);
    }
    $builder->addFilter('r.etat', $filters['etat'] ?? '');
    $builder->addFilter('r.site_id', $filters['site_id'] ?? '', 'AND');
    $builder->addFilter('r.declarant_id', $filters['declarant_id'] ?? '');

    if (!empty($filters['q'])) {
        // FTS5 logic (identique, pas de changement)
    }

    $where = $builder->getWhere();
    $params = $builder->getParams();

    // COUNT + data query (identique)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports r WHERE $where");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $params[':limit'] = $perPage;
    $params[':offset'] = ($page - 1) * $perPage;
    $stmt = $pdo->prepare(reportSelectWithSite() . " WHERE $where ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->execute($params);
    return ['reports' => $stmt->fetchAll(), 'total' => $total];
}
```

---

### 1.4 Extraire `EmailRenderer`

**Problème** : HTML email construit inline dans 5 fonctions.

**Nouveau fichier** : `src/mail/email_renderer.php`

```php
<?php
/**
 * Email Renderer — Template unifié pour les emails HTML.
 */

function renderEmailBody(string $title, string $contentHtml, string $siteName = ''): string
{
    $brandColor = getConfig('app_brand_color', '#1e40af');
    $appName = getConfig('app_nom_organisation', 'SST DREETS BFC');
    $footerText = $siteName !== '' ? " — $siteName" : '';
    
    return '<html><body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width:600px; margin:0 auto; padding:20px;">'
        . '<h2 style="color:' . e($brandColor) . ';">' . e($title) . '</h2>'
        . $contentHtml
        . '<hr style="margin:24px 0; border:none; border-top:1px solid #ddd;">'
        . '<p style="font-size:12px; color:#888;">'
        . "Cet e-mail a été envoyé automatiquement par $appName{$footerText}. Ne pas répondre directement à ce message."
        . '</p>'
        . '</body></html>';
}

function renderEmailField(string $label, string $value): string
{
    return '<p><strong>' . e($label) . ' :</strong> ' . e($value) . '</p>';
}

function renderEmailLink(string $url, string $label): string
{
    return '<p><a href="' . e($url) . '">' . e($label) . '</a></p>';
}

function renderEmailButton(string $url, string $label): string
{
    return '<p style="text-align:center; margin:16px 0;">'
        . '<a href="' . e($url) . '" style="display:inline-block; padding:12px 24px; background:#2563eb; color:#fff; text-decoration:none; border-radius:6px; font-weight:600;">'
        . e($label) . '</a></p>';
}
```

**Utilisation dans `mail_notifications.php`** :
```php
// AVANT (12 lignes de HTML inline)
$body = '<html><body>';
$body .= '<h2>Nouveau signalement enregistré</h2>';
$body .= '<p><strong>Référence :</strong> ' . e($report['reference']) . '</p>';
// ... 8 lignes de plus ...
$body .= '</body></html>';

// APRÈS (5 lignes)
$body = renderEmailBody(
    'Nouveau signalement enregistré',
    renderEmailField('Référence', $report['reference'])
    . renderEmailField('Registre', $registryLabel)
    . renderEmailField('Objet', $report['objet'])
    . renderEmailField('Déclarant', $report['declarant_prenom'] . ' ' . $report['declarant_nom'])
    . renderEmailField('Date de l\'événement', formatDateFR($report['date_evenement']))
    . renderEmailLink(getBaseUrl() . '/' . url('report_view', ['uuid' => $reportUuid]), 'Consulter le signalement')
);
```

---

## Phase 2 — Structure (3-5 jours)
### Objectif : Séparer logique métier / UI / données
### Risque : Faible — on ajoute des couches, on ne supprime pas les anciennes

---

### 2.1 Créer des DTOs (Data Transfer Objects)

**Problème** : Les arrays `['type' => ..., 'objet' => ..., ...]` passés partout ne sont pas typés.

**Nouveau fichier** : `src/DTO/CreateReportCommand.php`

```php
<?php
/**
 * CreateReportCommand — DTO pour la création d'un signalement.
 * Remplace l'array $data non typé passé à createReport().
 */

class CreateReportCommand
{
    public function __construct(
        public readonly string $type,
        public readonly string $objet,
        public readonly string $description,
        public readonly string $dateEvenement,
        public readonly ?string $heureEvenement,
        public readonly ?string $lieu,
        public readonly int $declarantId,
        public readonly string $declarantNom,
        public readonly string $declarantPrenom,
        public readonly int $siteId,
        public readonly ?string $siteText,
        public readonly ?string $pole,
        public readonly ?string $serviceAffectation,
        public readonly ?string $telephoneMobile,
        public readonly int $isConfidential,
        public readonly int $consentSyndicat,
        // RAMI
        public readonly ?string $natureAuteur,
        public readonly ?string $typeActe,
        public readonly ?string $pourCompteNom,
        public readonly ?string $pourComptePrenom,
        // Attachment
        public readonly ?string $attachmentBlob,
        public readonly ?string $attachmentName,
        public readonly ?string $attachmentMime,
    ) {}

    /**
     * Créer depuis les données POST (normalisation centralisée).
     */
    public static function fromPost(array $post, array $user): self
    {
        return new self(
            type: $post['type'],
            objet: trim($post['objet'] ?? ''),
            description: trim($post['description'] ?? ''),
            dateEvenement: trim($post['date_evenement'] ?? ''),
            heureEvenement: $post['heure_evenement'] ?? null,
            lieu: trim($post['lieu'] ?? ''),
            declarantId: (int) $user['id'],
            declarantNom: $user['nom'],
            declarantPrenom: $user['prenom'],
            siteId: (int) ($post['site_id'] ?? 0),
            siteText: trim($post['site_text'] ?? ''),
            pole: trim($post['pole'] ?? ''),
            serviceAffectation: trim($post['service_affectation'] ?? ''),
            telephoneMobile: trim($post['telephone_mobile'] ?? ''),
            isConfidential: isset($post['is_confidential']) ? 1 : 0,
            consentSyndicat: isset($post['consent_syndicat']) ? 1 : 0,
            natureAuteur: trim($post['nature_auteur'] ?? ''),
            typeActe: trim($post['type_acte'] ?? ''),
            pourCompteNom: trim($post['pour_compte_nom'] ?? ''),
            pourComptePrenom: trim($post['pour_compte_prenom'] ?? ''),
            attachmentBlob: null, // rempli par validateReportAttachment()
            attachmentName: null,
            attachmentMime: null,
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
```

**Autres DTOs à créer** :
- `src/DTO/ReportFilter.php` — Remplace l'array `$filters` dans les queries
- `src/DTO/UpdateReportCommand.php` — Pour l'édition
- `src/DTO/RespondToReportCommand.php` — Pour les réponses

---

### 2.2 Créer `ReportService` (Couche Métier)

**Problème** : La logique métier est mélangée dans les handlers (scripts procéduraux).

**Nouveau fichier** : `src/Service/ReportService.php`

```php
<?php
/**
 * ReportService — Couche métier pour les signalements.
 * Extrait la logique depuis les handlers.
 */

class ReportService
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * Créer un signalement.
     * Logique métier : validation, création, audit, notifications.
     */
    public function create(CreateReportCommand $cmd): array
    {
        // 1. Validation métier (pas juste format — règles métier)
        $this->validateForCreation($cmd);

        // 2. Normalisation visibility
        $cmd = $this->enforceVisibility($cmd);

        // 3. Création en DB
        $uuid = createReport($this->pdo, $cmd->toArray());

        // 4. Audit
        $report = getReportByUuid($this->pdo, $uuid);
        auditLog($this->pdo, 'report', 'create',
            'Signalement créé : ' . $report['reference'],
            (int) $report['id'], 'report',
            ['reference' => $report['reference'], 'type' => $cmd->type, 'site_id' => $cmd->siteId]
        );

        // 5. Notifications (non-bloquantes)
        $this->sendCreationNotifications($uuid, $cmd);

        return $report;
    }

    /**
     * Répondre à un signalement.
     */
    public function respond(string $uuid, RespondToReportCommand $cmd): array
    {
        $report = getReportByUuid($this->pdo, $uuid);
        if (!$report) {
            throw new \RuntimeException('Signalement introuvable.');
        }
        if (!canRespondToReport($report, currentUserRole())) {
            throw new \RuntimeException('Vous ne pouvez pas répondre à ce signalement.');
        }

        $result = respondToReport($this->pdo, $uuid, currentUserId(), $cmd->reponse, $cmd->nouvelEtat, $cmd->attachment);

        auditLog($this->pdo, 'report', 'respond',
            'Réponse ajoutée au signalement ' . $report['reference'],
            (int) $report['id'], 'report'
        );

        notifyReportResponse($this->pdo, $uuid, currentUserId());

        return $result;
    }

    /**
     * Éditer un signalement.
     */
    public function update(string $uuid, UpdateReportCommand $cmd): bool
    {
        $report = getReportByUuid($this->pdo, $uuid);
        if (!$report) {
            throw new \RuntimeException('Signalement introuvable.');
        }
        if (!canEditReport($report, currentUserId())) {
            throw new \RuntimeException('Vous ne pouvez pas modifier ce signalement.');
        }

        $result = updateReport($this->pdo, $uuid, $cmd->toArray(), currentUserId());

        auditLog($this->pdo, 'report', 'update',
            'Signalement modifié : ' . $report['reference'],
            (int) $report['id'], 'report'
        );

        return $result;
    }

    private function validateForCreation(CreateReportCommand $cmd): void
    {
        $errors = validateReportFields($cmd->dateEvenement, $cmd->objet, $cmd->description, $cmd->lieu, $cmd->heureEvenement);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Données invalides: ' . implode(', ', $errors));
        }
    }

    private function enforceVisibility(CreateReportCommand $cmd): CreateReportCommand
    {
        $visibility = getReportVisibilityMode($cmd->type);
        if ($visibility === 'public') {
            return new CreateReportCommand(...array_merge($cmd->toArray(), ['isConfidential' => 0]));
        }
        return $cmd;
    }

    private function sendCreationNotifications(string $uuid, CreateReportCommand $cmd): void
    {
        try {
            notifyNewReport($this->pdo, $uuid, $cmd->type, $cmd->siteId);
            if ($cmd->type === TYPE_RAMI && !empty($cmd->pourCompteNom)) {
                notifyPourCompte($this->pdo, $uuid);
            }
        } catch (\Exception $e) {
            error_log('[SST-MAIL] Notification error: ' . $e->getMessage());
        }
    }
}
```

---

### 2.3 Créer `ReportRepository` (Couche Données)

**Problème** : Les fonctions de query sont des fonctions globales, non testables isolément.

**Nouveau fichier** : `src/Repository/ReportRepository.php`

```php
<?php
/**
 * ReportRepository — Couche d'accès aux données pour les signalements.
 * Encapsule les requêtes SQL.
 */

class ReportRepository
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function findById(string $uuid): ?array
    {
        return getReportByUuid($this->pdo, $uuid);
    }

    public function findPaginated(ReportFilter $filter, int $page = 1, int $perPage = 20): array
    {
        return getReportsByRegistry(
            $this->pdo,
            $filter->type,
            $filter->toArray(),
            $filter->userSiteId,
            $filter->seeAllSites,
            $page,
            $perPage
        );
    }

    public function findBySite(int $siteId): array
    {
        return getReportsBySite($this->pdo, $siteId);
    }

    public function create(CreateReportCommand $cmd): string
    {
        return createReport($this->pdo, $cmd->toArray());
    }

    public function update(string $uuid, array $data, int $userId): bool
    {
        return updateReport($this->pdo, $uuid, $data, $userId);
    }

    public function countByState(string $type, int $siteId = 0, bool $seeAllSites = true): array
    {
        return countReportsByState($this->pdo, $type, $siteId, $seeAllSites);
    }

    public function getStatistics(string $year = '', int $siteId = 0): array
    {
        return getStatisticsIndicateurs($this->pdo, $year, $siteId);
    }

    public function getExportData(array $filters = []): array
    {
        return getExportData($this->pdo, $filters);
    }
}
```

---

### 2.4 Réécrire les Handlers en Controllers Légers

**Avant** (`handlers/report_create_handler.php` — 199 lignes procédurales) :

```php
<?php
// Script procédural : fait TOUT
validatePostRequest(url('home'));
$type = $_POST['type'] ?? '';
// ... 190 lignes de validation, mapping, DB, audit, notifications ...
```

**Après** (`handlers/report_create_handler.php` — 30 lignes) :

```php
<?php
/**
 * Report Create Handler — Thin controller delegating to ReportService.
 */
validatePostRequest(url('home'));

try {
    $cmd = CreateReportCommand::fromPost($_POST, currentUser());
    $service = new ReportService(getDB());
    $report = $service->create($cmd);

    setFlash('success', 'Signalement enregistré avec la référence ' . e($report['reference']));
    $_SESSION['report_created'] = true;
    redirect(url('report_view', ['uuid' => $report['uuid']]));

} catch (\InvalidArgumentException $e) {
    setFormErrors(['general' => $e->getMessage()]);
    setFormData($_POST);
    redirect(url('report_create', ['type' => $_POST['type'] ?? '']));
} catch (\Exception $e) {
    error_log('[SST-DB] report_create failed: ' . $e->getMessage());
    setFlash('error', 'Erreur lors de l\'enregistrement : ' . e($e->getMessage()));
    setFormData($_POST);
    redirect(url('report_create', ['type' => $_POST['type'] ?? '']));
}
```

**Impact** : Le handler passe de 199 lignes à 30 lignes. Toute la logique métier est dans `ReportService`.

---

## Phase 3 — Architecture (2-3 semaines)
### Objectif : Infrastructure pour l'évolutivité
### Risque : Moyen — nécessite des tests avant chaque changement

---

### 3.1 Container DI Léger

**Nouveau fichier** : `src/Container/Container.php`

```php
<?php
/**
 * Simple DI Container — Pas de framework, juste un registry de factories.
 */

class Container
{
    private array $factories = [];
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (!isset($this->instances[$id])) {
            if (!isset($this->factories[$id])) {
                throw new \RuntimeException("Service '$id' not registered.");
            }
            $this->instances[$id] = ($this->factories[$id])($this);
        }
        return $this->instances[$id];
    }
}

// bootstrap.php
$container = new Container();
$container->set(PDO::class, fn() => getDB());
$container->set(ReportService::class, fn($c) => new ReportService($c->get(PDO::class)));
$container->set(ReportRepository::class, fn($c) => new ReportRepository($c->get(PDO::class)));
```

---

### 3.2 Event System (Création → Notifications)

**Nouveau fichier** : `src/Event/EventDispatcher.php`

```php
<?php
/**
 * Simple Event Dispatcher — Découple action métier / notifications.
 */

class EventDispatcher
{
    private array $listeners = [];

    public function addListener(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function dispatch(string $event, array $data): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($data);
        }
    }
}

// bootstrap.php
$events = new EventDispatcher();
$events->listeners['report.created'] = [
    fn($data) => notifyNewReport($data['pdo'], $data['uuid'], $data['type'], $data['siteId']),
];
$events->listeners['report.responded'] = [
    fn($data) => notifyReportResponse($data['pdo'], $data['uuid'], $data['userId']),
];
```

**Utilisation dans ReportService** :
```php
public function create(CreateReportCommand $cmd): array
{
    // ... validation + création ...
    $uuid = createReport($this->pdo, $cmd->toArray());
    
    // Dispatch au lieu d'appel direct
    $this->events->dispatch('report.created', [
        'pdo' => $this->pdo, 'uuid' => $uuid,
        'type' => $cmd->type, 'siteId' => $cmd->siteId,
    ]);
    
    return $report;
}
```

---

### 3.3 Router Basé sur Attributs PHP 8

**Nouveau fichier** : `src/Router/Attribute/Route.php`

```php
<?php
namespace App\Router\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public string $path,
        public string $name,
        public array $methods = ['GET'],
        public ?string $middleware = null,
    ) {}
}
```

**Utilisation** :
```php
class ReportController
{
    #[Route(path: '/reports', name: 'report_list', methods: ['GET'])]
    public function list(): void { /* ... */ }

    #[Route(path: '/reports/create', name: 'report_create', methods: ['GET', 'POST'])]
    public function create(): void { /* ... */ }

    #[Route(path: '/reports/{uuid}', name: 'report_view', methods: ['GET'])]
    public function view(string $uuid): void { /* ... */ }
}
```

---

## 📋 Résumé des Fichiers à Créer/Modifier

### Phase 1 (Quick Wins)

| Action | Fichiers | Effort |
|---|---|---|
| Scinder formatting.php | Créer: `escaping.php`, `badges.php`, `utilities.php`, `rendering.php`, `wordcloud.php` | 2h |
| RegistryCardRenderer | Créer: `src/helpers/registry_card_renderer.php` | 2h |
| QueryFilterBuilder | Créer: `src/helpers/query_filter_builder.php` | 3h |
| EmailRenderer | Créer: `src/mail/email_renderer.php` | 2h |
| Modifier home.php | Utiliser `renderRegistryCards()` | 1h |
| Modifier report_queries.php | Utiliser `QueryFilterBuilder` | 2h |
| Modifier mail_notifications.php | Utiliser `EmailRenderer` | 2h |

### Phase 2 (Structure)

| Action | Fichiers | Effort |
|---|---|---|
| DTOs | Créer: `src/DTO/CreateReportCommand.php`, `ReportFilter.php`, `UpdateReportCommand.php` | 3h |
| ReportService | Créer: `src/Service/ReportService.php` | 6h |
| ReportRepository | Créer: `src/Repository/ReportRepository.php` | 3h |
| Réécrire handlers | Modifier les 15 handlers pour déléguer à ReportService | 8h |
| Tests | Ajouter des tests pour ReportService | 4h |

### Phase 3 (Architecture)

| Action | Fichiers | Effort |
|---|---|---|
| DI Container | Créer: `src/Container/Container.php` + bootstrap | 3h |
| Event System | Créer: `src/Event/EventDispatcher.php` | 4h |
| Router | Créer: `src/Router/` + migrer les routes | 8h |
| Templates isolés | Séparer logique / rendu dans les pages | 12h |
| Tests d'intégration | Valider le tout | 6h |

---

## ⚠️ Règles de Refactoring

1. **Toujours exécuter les tests** avant et après chaque changement
2. **Un commit par étape** — pouvoir revenir en arrière
3. **Ne jamais supprimer l'ancien code** avant d'avoir vérifié que le nouveau fonctionne
4. **Migrer progressivement** — un handler à la fois, pas tous d'un coup
5. **Garder le bridging** (require_once legacy) pendant la phase de transition
6. **Documenter** chaque décision d'architecture dans les commentaires

---

*Plan de refactoring — SST DREETS BFC — 8 juillet 2026*
