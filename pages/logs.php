<?php
/**
 * Logs Page — Application SST DREETS BFC
 *
 * Two tabs: Erreurs PHP (error log file) + Journal d'audit (database audit_log).
 * Access: superviseur only (admin tool)
 */
requireRole([ROLE_SUPERVISEUR]);

$activeTab = $_GET['tab'] ?? 'audit';

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

// Read log file — tail-like approach: read last N lines from the end
// without ever loading the entire file into memory.
// Even a 500 MB log file uses only ~50 KB of RAM.
$logLines = [];
$logCount = 0;
$maxLines = 5000;
$chunkSize = 8192; // 8 KB read chunks

if (file_exists($logFile) && is_readable($logFile)) {
    $fileSize = filesize($logFile);
    if ($fileSize > 0) {
        $fp = fopen($logFile, 'r');
        $collected = [];       // lines collected from the end
        $buffer = '';          // partial line at chunk boundary
        $position = $fileSize; // current seek position (start of next chunk)

        while ($position > 0 && count($collected) < $maxLines) {
            // How much to read in this chunk
            $readLen = min($chunkSize, $position);
            $position -= $readLen;
            fseek($fp, $position);
            $chunk = fread($fp, $readLen);

            // Prepend chunk to buffer, then split into lines
            $buffer = $chunk . $buffer;
            $lines = explode("\n", $buffer);

            // The first element is a partial line (unless we're at position 0)
            if ($position > 0) {
                $buffer = array_shift($lines); // keep partial for next iteration
            } else {
                $buffer = ''; // we've read from the very start
            }

            // Collect lines from the end (they come in reverse order from our iteration)
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $collected[] = $trimmed;
                    if (count($collected) >= $maxLines) {
                        break;
                    }
                }
            }
        }
        // Handle any remaining partial line
        if ($buffer !== '' && trim($buffer) !== '' && count($collected) < $maxLines) {
            $collected[] = trim($buffer);
        }
        fclose($fp);

        // $collected is in reverse chronological order (newest first)
        $logCount = count($collected);

        // Group multi-line entries (stack traces belong to the previous entry)
        $entries = [];
        $currentEntry = '';
        foreach ($collected as $line) {
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
    if (!empty($_GET['user'])) {
        $auditFilters['username'] = trim($_GET['user']);
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

<?php echo renderBreadcrumb([
    ['url' => url('home'), 'label' => 'Accueil'],
    ['label' => 'Journal'],
]); ?>


<!-- Main tab bar: Audit / Erreurs -->
<div class="tab-bar tab-bar--flush">
    <a href="<?php echo url('logs', ['tab' => 'audit']); ?>" class="tab<?php echo $activeTab === 'audit' ? ' tab--active' : ''; ?>">Journal d'audit</a>
    <a href="<?php echo url('logs', ['tab' => 'errors']); ?>" class="tab<?php echo $activeTab === 'errors' ? ' tab--active' : ''; ?>">Erreurs PHP</a>
</div>

<?php if ($activeTab === 'errors'): ?>
<!-- ============================================================ -->
<!-- Tab: Erreurs PHP                                              -->
<!-- ============================================================ -->
<div class="card card--flush-top">
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
<div class="card card--flush-top">
    <div class="card__title-row">
        <h3 class="card__subtitle">
            Journal d'audit
            <span class="text-muted text-small">(<?php echo number_format($auditTotal, 0, ',', ' '); ?> entrées)</span>
        </h3>
    </div>

    <!-- Filters -->
    <form method="GET" action="<?php echo url('logs'); ?>" class="filter-bar filter-bar--spaced">
        <input type="hidden" name="page" value="logs">
        <input type="hidden" name="tab" value="audit">
        <div class="filter-bar__group">
            <label for="audit-category" class="filter-bar__label">Catégorie</label>
            <select id="audit-category" name="category" class="form-control form-control--auto">
                <option value="">Toutes</option>
                <?php foreach ($auditCategoryLabels as $key => $label): ?>
                    <option value="<?php echo e($key); ?>"<?php echo ($_GET['category'] ?? '') === $key ? ' selected' : ''; ?>><?php echo e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-bar__group">
            <label for="audit-user" class="filter-bar__label">Utilisateur</label>
            <input type="text" id="audit-user" name="user" class="form-control form-control--search" placeholder="Nom d'utilisateur…" value="<?php echo e($_GET['user'] ?? ''); ?>">
        </div>
        <div class="filter-bar__group">
            <label for="audit-q" class="filter-bar__label">Recherche</label>
            <input type="text" id="audit-q" name="q" class="form-control form-control--search" placeholder="Utilisateur, détail…" value="<?php echo e($_GET['q'] ?? ''); ?>">
        </div>
        <div class="filter-bar__group">
            <label for="audit-from" class="filter-bar__label">Du</label>
            <input type="date" id="audit-from" name="date_from" class="form-control form-control--auto" value="<?php echo e($_GET['date_from'] ?? ''); ?>">
        </div>
        <div class="filter-bar__group">
            <label for="audit-to" class="filter-bar__label">Au</label>
            <input type="date" id="audit-to" name="date_to" class="form-control form-control--auto" value="<?php echo e($_GET['date_to'] ?? ''); ?>">
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
                            <td class="text-small text-muted"><?php echo e($entry['created_at']); ?></td>
                            <td>
                                <?php
                                $catKey = $entry['category'];
                                $catLabel = $auditCategoryLabels[$catKey] ?? $catKey;
                                ?>
                                <span class="badge badge--cat-<?php echo e($catKey); ?>"><?php echo e($catLabel); ?></span>
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
                'user'     => $_GET['user'] ?? '',
                'q'        => $_GET['q'] ?? '',
                'date_from'=> $_GET['date_from'] ?? '',
                'date_to'  => $_GET['date_to'] ?? '',
            ], fn($v) => $v !== '');
        ?>
        <div class="pagination pagination--flex">
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
