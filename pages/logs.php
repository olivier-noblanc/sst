<?php
/**
 * Logs Page — Application SST DREETS BFC
 *
 * Two tabs: Erreurs PHP (error log file) + Journal d'audit (database audit_log).
 * Access: superviseur only (admin tool)
 */
requireRole(['superviseur']);

$activeTab = $_GET['tab'] ?? 'errors';

// ============================================================
// Tab 1: PHP Error Log
// ============================================================
$logFile = ini_get('error_log') ?: __DIR__ . '/../../data/php-error.log';

// Handle clear action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_logs') {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        file_put_contents($logFile, '');
        setFlash('success', 'Journal d\'erreurs effacé avec succès.');
        redirect(url('logs', ['tab' => 'errors']));
    }
}

// Read log file
$logLines = [];
$logCount = 0;
$maxLines = 500;

if (file_exists($logFile) && is_readable($logFile)) {
    $raw = file_get_contents($logFile);
    if (!empty($raw)) {
        $lines = array_filter(explode("\n", trim($raw)), fn($l) => trim($l) !== '');
        $logCount = count($lines);
        $lines = array_slice($lines, -$maxLines);
        $lines = array_reverse($lines);

        // Group multi-line entries (stack traces belong to the previous entry)
        $entries = [];
        $currentEntry = '';
        foreach ($lines as $line) {
            if (preg_match('/^\[?\d{2}-\w{3}-\d{4}/', $line)) {
                if ($currentEntry !== '') {
                    $entries[] = $currentEntry;
                }
                $currentEntry = $line;
            } else {
                $currentEntry .= "\n" . $line;
            }
        }
        if ($currentEntry !== '') {
            $entries[] = $currentEntry;
        }
        $logLines = $entries;
    }
}

// Categorize entries
$categorized = [];
foreach ($logLines as $line) {
    $category = 'info';
    $categoryLabel = 'Info';
    if (stripos($line, 'Fatal error') !== false || stripos($line, 'critical') !== false) {
        $category = 'fatal';
        $categoryLabel = 'Fatal';
    } elseif (stripos($line, 'Warning') !== false || stripos($line, 'warning') !== false) {
        $category = 'warning';
        $categoryLabel = 'Warning';
    } elseif (stripos($line, '[SST-DB]') !== false) {
        $category = 'db';
        $categoryLabel = 'Base de données';
    } elseif (stripos($line, '[SST-MAIL]') !== false) {
        $category = 'mail';
        $categoryLabel = 'E-mail';
    } elseif (stripos($line, '[SST-BACKUP]') !== false) {
        $category = 'backup';
        $categoryLabel = 'Sauvegarde';
    } elseif (stripos($line, '[SST-MIGRATION]') !== false) {
        $category = 'migration';
        $categoryLabel = 'Migration';
    } elseif (stripos($line, '[SST-AUDIT]') !== false) {
        $category = 'audit';
        $categoryLabel = 'Audit';
    } elseif (stripos($line, '[SST-RESPOND]') !== false) {
        $category = 'respond';
        $categoryLabel = 'Réponse';
    } elseif (stripos($line, '[SST-ERROR-MAIL]') !== false) {
        $category = 'mail';
        $categoryLabel = 'E-mail';
    } elseif (stripos($line, 'SST App:') !== false) {
        $category = 'app';
        $categoryLabel = 'Application';
    }
    $categorized[] = ['text' => $line, 'category' => $category, 'label' => $categoryLabel];
}

$errorFilter = $_GET['filter'] ?? 'all';
$filteredLines = $errorFilter === 'all'
    ? $categorized
    : array_filter($categorized, fn($e) => $e['category'] === $errorFilter);

$logFileSize = file_exists($logFile) ? filesize($logFile) : 0;

// ============================================================
// Tab 2: Audit Log (from database)
// ============================================================
$auditEntries = [];
$auditTotal = 0;
$auditPage = max(1, (int) ($_GET['p'] ?? 1));
$auditPerPage = 50;

if ($activeTab === 'audit') {
    $pdo = getDB();

    $auditFilters = [];
    if (!empty($_GET['category'])) {
        $auditFilters['category'] = $_GET['category'];
    }
    if (!empty($_GET['q'])) {
        $auditFilters['q'] = trim($_GET['q']);
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
    'auth'   => 'Authentification',
    'report' => 'Signalement',
    'user'   => 'Utilisateur',
    'site'   => 'Site',
    'config' => 'Configuration',
    'export' => 'Export',
    'backup' => 'Sauvegarde',
    'gdpr'   => 'RGPD',
];

$auditActionLabels = [
    'login'              => 'Connexion',
    'logout'             => 'Déconnexion',
    'login_failed'       => 'Échec de connexion',
    'impersonate_start'  => 'Début d\'incarnation',
    'impersonate_stop'   => 'Fin d\'incarnation',
    'create'             => 'Création',
    'edit'               => 'Modification',
    'delete'             => 'Suppression',
    'reactivate'         => 'Réactivation',
    'role_change'        => 'Changement de rôle',
    'abandon'            => 'Abandon',
    'respond'            => 'Réponse',
    'attachment_upload'  => 'Ajout de pièce jointe',
    'update'             => 'Mise à jour',
    'csv_export'         => 'Export CSV',
    'auto_backup'        => 'Sauvegarde automatique',
    'pre_migration_backup' => 'Sauvegarde pré-migration',
    'data_export'        => 'Export de données',
    'anonymize'          => 'Anonymisation',
];
?>

<h1 class="page-title">Journal</h1>

<nav class="breadcrumb" aria-label="Fil d'Ariane">
    <a href="<?php echo url('home'); ?>" class="breadcrumb__item">Accueil</a>
    <span class="breadcrumb__separator">/</span>
    <span class="breadcrumb__current">Journal</span>
</nav>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<!-- Main tab bar: Erreurs / Audit -->
<div class="tab-bar" style="margin-bottom: 0; border-bottom: none;">
    <a href="<?php echo url('logs', ['tab' => 'errors']); ?>" class="tab<?php echo $activeTab === 'errors' ? ' tab--active' : ''; ?>">Erreurs PHP</a>
    <a href="<?php echo url('logs', ['tab' => 'audit']); ?>" class="tab<?php echo $activeTab === 'audit' ? ' tab--active' : ''; ?>">Journal d'audit</a>
</div>

<?php if ($activeTab === 'errors'): ?>
<!-- ============================================================ -->
<!-- Tab: Erreurs PHP                                              -->
<!-- ============================================================ -->
<div class="card" style="border-top-left-radius: 0;">
    <div class="card__title-row">
        <h3 class="card__subtitle">
            <?php echo e(basename($logFile)); ?>
            <span class="text-muted text-small">(<?php echo number_format($logFileSize, 0, ',', ' '); ?> octets — <?php echo $logCount; ?> lignes<?php echo $logCount > $maxLines ? ', ' . $maxLines . ' dernières affichées' : ''; ?>)</span>
        </h3>
        <form method="POST" action="<?php echo url('logs', ['tab' => 'errors']); ?>" class="form--inline">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <input type="hidden" name="action" value="clear_logs">
            <button type="submit" class="btn btn--sm btn--danger" onclick="return confirm('Effacer tous les logs ? Cette action est irreversible.')">Effacer les logs</button>
        </form>
    </div>

    <!-- Sub-filter tabs -->
    <div class="tab-bar">
        <a href="<?php echo url('logs', ['tab' => 'errors']); ?>" class="tab<?php echo $errorFilter === 'all' ? ' tab--active' : ''; ?>">Tout (<?php echo count($categorized); ?>)</a>
        <a href="<?php echo url('logs', ['tab' => 'errors', 'filter' => 'fatal']); ?>" class="tab<?php echo $errorFilter === 'fatal' ? ' tab--active' : ''; ?>">Fatal</a>
        <a href="<?php echo url('logs', ['tab' => 'errors', 'filter' => 'warning']); ?>" class="tab<?php echo $errorFilter === 'warning' ? ' tab--active' : ''; ?>">Warnings</a>
        <a href="<?php echo url('logs', ['tab' => 'errors', 'filter' => 'db']); ?>" class="tab<?php echo $errorFilter === 'db' ? ' tab--active' : ''; ?>">Base de données</a>
        <a href="<?php echo url('logs', ['tab' => 'errors', 'filter' => 'mail']); ?>" class="tab<?php echo $errorFilter === 'mail' ? ' tab--active' : ''; ?>">E-mail</a>
        <a href="<?php echo url('logs', ['tab' => 'errors', 'filter' => 'respond']); ?>" class="tab<?php echo $errorFilter === 'respond' ? ' tab--active' : ''; ?>">Réponses</a>
        <a href="<?php echo url('logs', ['tab' => 'errors', 'filter' => 'backup']); ?>" class="tab<?php echo $errorFilter === 'backup' ? ' tab--active' : ''; ?>">Sauvegarde</a>
        <a href="<?php echo url('logs', ['tab' => 'errors', 'filter' => 'migration']); ?>" class="tab<?php echo $errorFilter === 'migration' ? ' tab--active' : ''; ?>">Migration</a>
    </div>

    <?php if (empty($filteredLines)): ?>
        <div class="empty-state">
            <p class="text-muted">Aucune entrée dans le journal<?php echo $errorFilter !== 'all' ? ' pour ce filtre' : ''; ?>.</p>
        </div>
    <?php else: ?>
        <div class="log-viewer">
            <?php foreach ($filteredLines as $i => $entry): ?>
                <div class="log-entry log-entry--<?php echo e($entry['category']); ?>">
                    <span class="log-entry__badge badge badge--<?php echo e($entry['category']); ?>"><?php echo e($entry['label']); ?></span>
                    <pre class="log-entry__text"><?php echo e($entry['text']); ?></pre>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ============================================================ -->
<!-- Tab: Journal d'audit                                          -->
<!-- ============================================================ -->
<div class="card" style="border-top-left-radius: 0;">
    <div class="card__title-row">
        <h3 class="card__subtitle">
            Journal d'audit
            <span class="text-muted text-small">(<?php echo number_format($auditTotal, 0, ',', ' '); ?> entrées)</span>
        </h3>
    </div>

    <!-- Filters -->
    <form method="GET" action="<?php echo url('logs'); ?>" class="filter-bar" style="margin-bottom: 16px;">
        <input type="hidden" name="page" value="logs">
        <input type="hidden" name="tab" value="audit">
        <div class="filter-bar__group">
            <label for="audit-category" class="filter-bar__label">Catégorie</label>
            <select id="audit-category" name="category" class="form-control" style="width: auto;">
                <option value="">Toutes</option>
                <?php foreach ($auditCategoryLabels as $key => $label): ?>
                    <option value="<?php echo e($key); ?>"<?php echo ($_GET['category'] ?? '') === $key ? ' selected' : ''; ?>><?php echo e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-bar__group">
            <label for="audit-q" class="filter-bar__label">Recherche</label>
            <input type="text" id="audit-q" name="q" class="form-control" placeholder="Utilisateur, détail…" value="<?php echo e($_GET['q'] ?? ''); ?>" style="width: 200px;">
        </div>
        <div class="filter-bar__group">
            <label for="audit-from" class="filter-bar__label">Du</label>
            <input type="date" id="audit-from" name="date_from" class="form-control" value="<?php echo e($_GET['date_from'] ?? ''); ?>" style="width: auto;">
        </div>
        <div class="filter-bar__group">
            <label for="audit-to" class="filter-bar__label">Au</label>
            <input type="date" id="audit-to" name="date_to" class="form-control" value="<?php echo e($_GET['date_to'] ?? ''); ?>" style="width: auto;">
        </div>
        <div class="filter-bar__group">
            <button type="submit" class="btn btn--sm btn--primary">Filtrer</button>
            <a href="<?php echo url('logs', ['tab' => 'audit']); ?>" class="btn btn--sm btn--outline">Réinitialiser</a>
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
                        <th style="width: 140px;">Date</th>
                        <th style="width: 110px;">Catégorie</th>
                        <th style="width: 100px;">Utilisateur</th>
                        <th style="width: 120px;">Action</th>
                        <th>Détail</th>
                        <th style="width: 100px;">Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditEntries as $entry): ?>
                        <tr>
                            <td class="text-small text-muted"><?php echo e($entry['created_at']); ?></td>
                            <td>
                                <?php
                                $catKey = $entry['category'];
                                $catLabel = $auditCategoryLabels[$catKey] ?? $catKey;
                                $catColors = [
                                    'auth'   => '#2E5C8A',
                                    'report' => '#27AE60',
                                    'user'   => '#8E44AD',
                                    'site'   => '#E67E22',
                                    'config' => '#0056A3',
                                    'export' => '#6C6C6C',
                                    'backup' => '#95A5A6',
                                    'gdpr'   => '#B22222',
                                ];
                                $catColor = $catColors[$catKey] ?? '#6C6C6C';
                                ?>
                                <span class="badge" style="background: <?php echo $catColor; ?>; color: white; font-size: 11px;"><?php echo e($catLabel); ?></span>
                            </td>
                            <td class="text-small"><?php echo e($entry['username']); ?></td>
                            <td>
                                <span class="text-small"><?php echo e($auditActionLabels[$entry['action']] ?? $entry['action']); ?></span>
                            </td>
                            <td class="text-small"><?php echo e($entry['details']); ?></td>
                            <td class="text-small text-muted"><?php echo e($entry['ip_address']); ?></td>
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
                'tab'      => 'audit',
                'category' => $_GET['category'] ?? '',
                'q'        => $_GET['q'] ?? '',
                'date_from'=> $_GET['date_from'] ?? '',
                'date_to'  => $_GET['date_to'] ?? '',
            ], fn($v) => $v !== '');
        ?>
        <div class="pagination" style="display: flex; align-items: center; gap: 8px; margin-top: 16px;">
            <?php if ($auditPage > 1): ?>
                <a href="<?php echo url('logs', array_merge($paginationParams, ['p' => $auditPage - 1])); ?>" class="btn btn--sm btn--outline">&larr; Précédent</a>
            <?php endif; ?>

            <span class="text-small text-muted">
                Page <?php echo $auditPage; ?> / <?php echo $totalPages; ?>
                &mdash; <?php echo number_format($auditTotal, 0, ',', ' '); ?> entrées
            </span>

            <?php if ($auditPage < $totalPages): ?>
                <a href="<?php echo url('logs', array_merge($paginationParams, ['p' => $auditPage + 1])); ?>" class="btn btn--sm btn--outline">Suivant &rarr;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>
