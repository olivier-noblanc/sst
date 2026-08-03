<?php
/**
 * Logs Page — Application SST DREETS BFC
 *
 * Two tabs: Erreurs PHP (error log file) + Journal d'audit (database audit_log).
 * Access: superviseur only (admin tool)
 */
/** @var string $csrfToken */
requireRole([\App\Enum\UserRole::Superviseur->value]);

// Service instances (created once for the page)
$fmt = new \App\Services\FormattingService();
$http = new \App\Services\HttpService();
$session = new \App\Services\SessionService();

$activeTab = $_GET['tab'] ?? 'audit';

// ============================================================
// Tab 1: PHP Error Log
// ============================================================
$logFile = ini_get('error_log') !== false && ini_get('error_log') !== '' ? ini_get('error_log') : __DIR__ . '/../../data/php-error.log';
$maxLines = 5000;

// Handle clear action
// Bug #84 — Before this fix, CSRF validation failure was silent (no flash,
// no redirect). The user would click "Effacer" and nothing would happen
// with no explanation. Now we flash an error on failure.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_logs') {
    if ($session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        file_put_contents($logFile, '');
        $session->setFlash('success', 'Journal d\'erreurs effacé avec succès.');
    } else {
        $session->setFlash('error', 'Erreur de sécurité. Le journal n\'a pas été effacé.');
    }
    $http->redirect($http->url('logs', ['tab' => 'errors']));
}

require_once __DIR__ . '/logs/_error_log_reader.php';
$errorFilter = $_GET['filter'] ?? 'all';
$logResult = readErrorLog($logFile, $maxLines, $errorFilter);
$categorized = $logResult['categorized'];
$logCount = $logResult['logCount'];
$logFileSize = $logResult['logFileSize'];
$filteredLines = $logResult['filteredLines'];

// ============================================================
// Tab 2: Audit Log (from database)
// ============================================================
$auditEntries = [];
$auditTotal = 0;
/** @var string */
$pageStr = $_GET['p'] ?? '1';
$auditPage = max(1, (int) $pageStr);
$auditPerPage = 50;

if ($activeTab === 'audit') {
    $pdo = getContainer()->get(\PDO::class);

    $auditFilters = [];
    if (!empty($_GET['category'])) {
        $auditFilters['category'] = $_GET['category'];
    }
    if (!empty($_GET['user'])) {
        /** @var string */
        $userFilterStr = $_GET['user'];
        $auditFilters['username'] = trim($userFilterStr);
    }
    if (!empty($_GET['q'])) {
        /** @var string */
        $qFilterStr = $_GET['q'];
        $auditFilters['q'] = trim($qFilterStr);
    }
    if (!empty($_GET['date_from'])) {
        $auditFilters['date_from'] = $_GET['date_from'] . ' 00:00:00';
    }
    if (!empty($_GET['date_to'])) {
        $auditFilters['date_to'] = $_GET['date_to'] . ' 23:59:59';
    }

    $result = getAuditLog($pdo, $auditFilters, $auditPage, $auditPerPage);
    $auditEntries = $result['entries'];
    $auditTotal = $result['total'];
}

// Category labels for audit log display
$auditCategoryLabels = [
    'auth' => 'Authentification', 'report' => 'Signalement',
    'user' => 'Utilisateur', 'site' => 'Site',
    'config' => 'Configuration', 'export' => 'Export',
    'backup' => 'Sauvegarde', 'gdpr' => 'RGPD',
];

$auditActionLabels = [
    'login' => 'Connexion', 'logout' => 'Déconnexion',
    'login_failed' => 'Échec de connexion',
    'impersonate_start' => 'Début d\'incarnation',
    'impersonate_stop' => 'Fin d\'incarnation',
    'create' => 'Création', 'edit' => 'Modification',
    'delete' => 'Suppression', 'reactivate' => 'Réactivation',
    'role_change' => 'Changement de rôle', 'abandon' => 'Abandon',
    'respond' => 'Réponse', 'attachment_upload' => 'Ajout de pièce jointe',
    'update' => 'Mise à jour', 'csv_export' => 'Export CSV',
    'auto_backup' => 'Sauvegarde automatique',
    'pre_migration_backup' => 'Sauvegarde pré-migration',
    'data_export' => 'Export de données', 'anonymize' => 'Anonymisation',
];
?>

<h1 class="page-title">Journal</h1>

<?php echo $fmt->renderBreadcrumb([
    ['url' => $http->url('home'), 'label' => 'Accueil'],
    ['label' => 'Journal'],
]); ?>

<!-- Main tab bar: Audit / Erreurs -->
<div class="tab-bar tab-bar--flush">
    <a href="<?php echo $http->url('logs', ['tab' => 'audit']); ?>" class="tab<?php echo $activeTab === 'audit' ? ' tab--active' : ''; ?>">Journal d'audit</a>
    <a href="<?php echo $http->url('logs', ['tab' => 'errors']); ?>" class="tab<?php echo $activeTab === 'errors' ? ' tab--active' : ''; ?>">Erreurs PHP</a>
</div>

<?php if ($activeTab === 'errors'): ?>
<!-- Tab: Erreurs PHP -->
<div class="card card--flush-top">
    <div class="card__title-row">
        <h2 class="card__subtitle">
            <?php echo $fmt->e(basename($logFile)); ?>
            <span class="text-muted text-small">(<?php echo number_format($logFileSize, 0, ',', ' '); ?> octets — <?php echo $logCount; ?> lignes<?php echo $logCount > $maxLines ? ', ' . $maxLines . ' dernières affichées' : ''; ?>)</span>
        </h2>
        <form method="POST" action="<?php echo $http->url('logs', ['tab' => 'errors']); ?>" class="form--inline">
            <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">
            <input type="hidden" name="action" value="clear_logs">
            <button type="submit" class="btn btn--sm btn--danger" onclick="return confirm('Effacer tous les logs ? Cette action est irreversible.')">Effacer les logs</button>
        </form>
    </div>

    <!-- Sub-filter tabs -->
    <div class="tab-bar">
        <a href="<?php echo $http->url('logs', ['tab' => 'errors']); ?>" class="tab<?php echo $errorFilter === 'all' ? ' tab--active' : ''; ?>">Tout (<?php echo count($categorized); ?>)</a>
        <a href="<?php echo $http->url('logs', ['tab' => 'errors', 'filter' => 'fatal']); ?>" class="tab<?php echo $errorFilter === 'fatal' ? ' tab--active' : ''; ?>">Fatal</a>
        <a href="<?php echo $http->url('logs', ['tab' => 'errors', 'filter' => 'warning']); ?>" class="tab<?php echo $errorFilter === 'warning' ? ' tab--active' : ''; ?>">Warnings</a>
        <a href="<?php echo $http->url('logs', ['tab' => 'errors', 'filter' => 'db']); ?>" class="tab<?php echo $errorFilter === 'db' ? ' tab--active' : ''; ?>">Base de données</a>
        <a href="<?php echo $http->url('logs', ['tab' => 'errors', 'filter' => 'mail']); ?>" class="tab<?php echo $errorFilter === 'mail' ? ' tab--active' : ''; ?>">E-mail</a>
        <a href="<?php echo $http->url('logs', ['tab' => 'errors', 'filter' => 'respond']); ?>" class="tab<?php echo $errorFilter === 'respond' ? ' tab--active' : ''; ?>">Réponses</a>
        <a href="<?php echo $http->url('logs', ['tab' => 'errors', 'filter' => 'backup']); ?>" class="tab<?php echo $errorFilter === 'backup' ? ' tab--active' : ''; ?>">Sauvegarde</a>
        <a href="<?php echo $http->url('logs', ['tab' => 'errors', 'filter' => 'migration']); ?>" class="tab<?php echo $errorFilter === 'migration' ? ' tab--active' : ''; ?>">Migration</a>
    </div>

    <?php if (empty($filteredLines)): ?>
        <div class="empty-state">
            <p class="text-muted">Aucune entrée dans le journal<?php echo $errorFilter !== 'all' ? ' pour ce filtre' : ''; ?>.</p>
        </div>
    <?php else: ?>
        <div class="log-viewer">
            <?php foreach ($filteredLines as $i => $entry): ?>
                <div class="log-entry log-entry--<?php echo $fmt->e($entry['category']); ?>">
                    <span class="log-entry__badge badge badge--<?php echo $fmt->e($entry['category']); ?>"><?php echo $fmt->e($entry['label']); ?></span>
                    <pre class="log-entry__text"><?php echo $fmt->e($entry['text']); ?></pre>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- Tab: Journal d'audit -->
<div class="card card--flush-top">
    <div class="card__title-row">
        <h2 class="card__subtitle">
            Journal d'audit
            <span class="text-muted text-small">(<?php echo number_format($auditTotal, 0, ',', ' '); ?> entrées)</span>
        </h2>
    </div>

    <!-- Filters -->
    <form method="GET" action="<?php echo $http->url('logs'); ?>" class="filter-bar filter-bar--spaced">
        <input type="hidden" name="page" value="logs">
        <input type="hidden" name="tab" value="audit">
        <div class="filter-bar__group">
            <label for="audit-category" class="filter-bar__label">Catégorie</label>
            <select id="audit-category" name="category" class="form-control form-control--auto">
                <option value="">Toutes</option>
                <?php foreach ($auditCategoryLabels as $key => $label): ?>
                    <option value="<?php echo $fmt->e($key); ?>"<?php echo ($_GET['category'] ?? '') === $key ? ' selected' : ''; ?>><?php echo $fmt->e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-bar__group">
            <label for="audit-user" class="filter-bar__label">Utilisateur</label>
            <input type="text" id="audit-user" name="user" class="form-control form-control--search" placeholder="Nom d'utilisateur…" value="<?php echo $fmt->e($_GET['user'] ?? ''); ?>">
        </div>
        <div class="filter-bar__group">
            <label for="audit-q" class="filter-bar__label">Recherche</label>
            <input type="text" id="audit-q" name="q" class="form-control form-control--search" placeholder="Utilisateur, détail…" value="<?php echo $fmt->e($_GET['q'] ?? ''); ?>">
        </div>
        <div class="filter-bar__group">
            <label for="audit-from" class="filter-bar__label">Du</label>
            <input type="date" id="audit-from" name="date_from" class="form-control form-control--auto" value="<?php echo $fmt->e($_GET['date_from'] ?? ''); ?>">
        </div>
        <div class="filter-bar__group">
            <label for="audit-to" class="filter-bar__label">Au</label>
            <input type="date" id="audit-to" name="date_to" class="form-control form-control--auto" value="<?php echo $fmt->e($_GET['date_to'] ?? ''); ?>">
        </div>
        <div class="filter-bar__group">
            <button type="submit" class="btn btn--sm btn--primary">Filtrer</button>
            <a href="<?php echo $http->url('logs', ['tab' => 'audit']); ?>" class="btn btn--sm btn--outline">Réinitialiser</a>
        </div>
    </form>

    <?php if (empty($auditEntries)): ?>
        <div class="empty-state">
            <p class="text-muted">Aucune entrée dans le journal d'audit<?php echo !empty($_GET['category']) || !empty($_GET['q']) ? ' pour ces filtres' : ''; ?>.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table table--compact" aria-label="Journal d'audit">
                <thead>
                    <tr>
                        <th class="th--date">Date</th>
                        <th class="th--category">Catégorie</th>
                        <th class="th--user">Utilisateur</th>
                        <th class="th--action">Action</th>
                        <th>Détail</th>
                        <th class="th--ip">Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditEntries as $entry): ?>
                        <tr>
                            <td class="text-small text-muted"><?php echo $fmt->e($entry['created_at']); ?></td>
                            <td>
                                <?php $catKey = (string) $entry['category'];
                        $catLabel = $auditCategoryLabels[$catKey] ?? $catKey; ?>
                                <span class="badge badge--cat-<?php echo $fmt->e($catKey); ?>"><?php echo $fmt->e($catLabel); ?></span>
                            </td>
                            <td class="text-small"><?php echo $fmt->e($entry['username']); ?></td>
                            <td>
                                <span class="text-small"><?php echo $fmt->e($auditActionLabels[(string) $entry['action']] ?? (string) $entry['action']); ?></span>
                            </td>
                            <td class="text-small"><?php echo $fmt->e($entry['details']); ?></td>
                            <td class="text-small text-muted"><?php echo $fmt->e($entry['ip_address']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php
        $totalPages = (int) ceil($auditTotal / $auditPerPage);
        if ($totalPages > 1):
            $paginationParams = array_filter([
                'tab'       => 'audit',
                'category'  => $_GET['category'] ?? '',
                'user'      => $_GET['user'] ?? '',
                'q'         => $_GET['q'] ?? '',
                'date_from' => $_GET['date_from'] ?? '',
                'date_to'   => $_GET['date_to'] ?? '',
            ], fn($v) => $v !== '');
            ?>
        <div class="pagination pagination--flex">
            <?php if ($auditPage > 1): ?>
                <a href="<?php echo $http->url('logs', array_merge($paginationParams, ['p' => $auditPage - 1])); ?>" class="btn btn--sm btn--outline">&larr; Précédent</a>
            <?php endif; ?>

            <span class="text-small text-muted">
                Page <?php echo $auditPage; ?> / <?php echo $totalPages; ?>
                &mdash; <?php echo number_format($auditTotal, 0, ',', ' '); ?> entrées
            </span>

            <?php if ($auditPage < $totalPages): ?>
                <a href="<?php echo $http->url('logs', array_merge($paginationParams, ['p' => $auditPage + 1])); ?>" class="btn btn--sm btn--outline">Suivant &rarr;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>
