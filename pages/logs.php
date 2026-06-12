<?php
/**
 * Logs Page — Application SST DREETS BFC
 *
 * Displays the PHP error log in the UI for easy debugging.
 * Access: superviseur only (admin tool)
 */
requireRole(['superviseur']);

$logFile = ini_get('error_log') ?: __DIR__ . '/../../data/php-error.log';

// Handle clear action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_logs') {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        file_put_contents($logFile, '');
        setFlash('success', 'Logs effacés avec succès.');
        redirect(url('logs'));
    }
}

// Read log file
$logContent = '';
$logLines = [];
$logCount = 0;
$maxLines = 500;

if (file_exists($logFile) && is_readable($logFile)) {
    $raw = file_get_contents($logFile);
    if (!empty($raw)) {
        // Split into lines and reverse (newest first)
        $lines = array_filter(explode("\n", trim($raw)), fn($l) => trim($l) !== '');
        $logCount = count($lines);

        // Take only the last N lines
        $lines = array_slice($lines, -$maxLines);
        $lines = array_reverse($lines);

        // Group multi-line entries (stack traces belong to the previous entry)
        $entries = [];
        $currentEntry = '';
        foreach ($lines as $line) {
            // New log entry starts with [dd-Mon-YYYY HH:MM:SS UTC] or [dd-Mon-YYYY HH:MM:SS Europe/Paris]
            if (preg_match('/^\[?\d{2}-\w{3}-\d{4}/', $line)) {
                if ($currentEntry !== '') {
                    $entries[] = $currentEntry;
                }
                $currentEntry = $line;
            } else {
                // Continuation of previous entry (stack trace, etc.)
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
    } elseif (stripos($line, 'SST App:') !== false) {
        $category = 'app';
        $categoryLabel = 'Application';
    }
    $categorized[] = ['text' => $line, 'category' => $category, 'label' => $categoryLabel];
}

$filter = $_GET['filter'] ?? 'all';
$filteredLines = $filter === 'all'
    ? $categorized
    : array_filter($categorized, fn($e) => $e['category'] === $filter);

$logFileSize = file_exists($logFile) ? filesize($logFile) : 0;
?>

<h1 class="page-title">Journal d'erreurs</h1>

<nav class="breadcrumb" aria-label="Fil d'Ariane">
    <a href="<?php echo url('home'); ?>" class="breadcrumb__item">Accueil</a>
    <span class="breadcrumb__separator">/</span>
    <span class="breadcrumb__current">Journal d'erreurs</span>
</nav>

<?php require __DIR__ . '/../templates/alert.php'; ?>

<div class="card">
    <div class="card__title-row">
        <h3 class="card__subtitle">
            <?php echo e(basename($logFile)); ?>
            <span class="text-muted text-small">(<?php echo number_format($logFileSize, 0, ',', ' '); ?> octets — <?php echo $logCount; ?> lignes<?php echo $logCount > $maxLines ? ', ' . $maxLines . ' dernières affichées' : ''; ?>)</span>
        </h3>
        <form method="POST" action="<?php echo url('logs'); ?>" class="form--inline">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
            <input type="hidden" name="action" value="clear_logs">
            <button type="submit" class="btn btn--sm btn--danger" onclick="return confirm('Effacer tous les logs ? Cette action est irreversible.')">Effacer les logs</button>
        </form>
    </div>

    <!-- Filter tabs -->
    <div class="tab-bar">
        <a href="<?php echo url('logs'); ?>" class="tab<?php echo $filter === 'all' ? ' tab--active' : ''; ?>">Tout (<?php echo count($categorized); ?>)</a>
        <a href="<?php echo url('logs', ['filter' => 'fatal']); ?>" class="tab<?php echo $filter === 'fatal' ? ' tab--active' : ''; ?>">Fatal</a>
        <a href="<?php echo url('logs', ['filter' => 'warning']); ?>" class="tab<?php echo $filter === 'warning' ? ' tab--active' : ''; ?>">Warnings</a>
        <a href="<?php echo url('logs', ['filter' => 'db']); ?>" class="tab<?php echo $filter === 'db' ? ' tab--active' : ''; ?>">Base de données</a>
        <a href="<?php echo url('logs', ['filter' => 'mail']); ?>" class="tab<?php echo $filter === 'mail' ? ' tab--active' : ''; ?>">E-mail</a>
        <a href="<?php echo url('logs', ['filter' => 'respond']); ?>" class="tab<?php echo $filter === 'respond' ? ' tab--active' : ''; ?>">Réponses</a>
        <a href="<?php echo url('logs', ['filter' => 'backup']); ?>" class="tab<?php echo $filter === 'backup' ? ' tab--active' : ''; ?>">Sauvegarde</a>
        <a href="<?php echo url('logs', ['filter' => 'migration']); ?>" class="tab<?php echo $filter === 'migration' ? ' tab--active' : ''; ?>">Migration</a>
    </div>

    <?php if (empty($filteredLines)): ?>
        <div class="empty-state">
            <p class="text-muted">Aucune entrée dans le journal<?php echo $filter !== 'all' ? ' pour ce filtre' : ''; ?>.</p>
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
