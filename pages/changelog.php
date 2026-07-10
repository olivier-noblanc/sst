<?php
/**
 * Changelog Page — Application SST DREETS BFC
 *
 * Displays the CHANGELOG.md content rendered as HTML.
 * URL: index.php?page=changelog
 *
 * Access: superviseur only — the changelog contains internal technical details
 * that are not relevant to agents or CHSCT members.
 *
 * NOTE: This page is included by the router with header/sidebar/footer.
 * The router has already started the session, loaded config/database/helpers/queries,
 * and checked authentication. We do NOT need to re-require or re-start session.
 */

// Restrict changelog access to superviseurs only
requireRole([ROLE_SUPERVISEUR]);

// Try multiple path resolution strategies (same logic as getAppVersion())
$changelogPath = null;
$candidatePaths = [
    __DIR__ . '/../CHANGELOG.md',                                          // pages/../CHANGELOG.md
    dirname(__DIR__) . '/CHANGELOG.md',                                    // project root
];

// IIS: resolve from document root
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $candidatePaths[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') . '/../CHANGELOG.md';
}

// From entry script location
if (!empty($_SERVER['SCRIPT_FILENAME'])) {
    $candidatePaths[] = dirname((string) $_SERVER['SCRIPT_FILENAME'], 2) . '/CHANGELOG.md';
}

foreach ($candidatePaths as $candidate) {
    $real = realpath($candidate);
    if ($real && is_readable($real)) {
        $changelogPath = $real;
        break;
    }
}

$changelogExists = ($changelogPath !== null);
$htmlContent = '';
$parseError = '';

if ($changelogExists) {
    $mdContent = file_get_contents($changelogPath);
    if ($mdContent === false) {
        $parseError = 'Impossible de lire le contenu du fichier CHANGELOG.md (erreur de lecture).';
    } else {
        $parsedownPath = __DIR__ . '/../src/lib/Parsedown.php';
        $parsedownReal = realpath($parsedownPath);
        if ($parsedownReal && is_readable($parsedownReal)) {
            require_once $parsedownReal;
            $parsedown = new Parsedown();
            $parsedown->setSafeMode(true);  // Strip raw HTML blocks — prevents XSS via Markdown
            $htmlContent = $parsedown->text($mdContent);
            if (empty(trim((string) $htmlContent))) {
                $parseError = 'Le fichier CHANGELOG.md a été lu mais le rendu Markdown a produit un contenu vide.';
            }
        } else {
            $parseError = 'La bibliothèque Parsedown est introuvable (src/lib/Parsedown.php). Le rendu Markdown est impossible.';
        }
    }
}
?>

<div class="page-header">
    <h1>Historique des modifications</h1>
</div>

<?php if (!$changelogExists): ?>
    <div class="alert alert--warning">
        <p>Le fichier CHANGELOG.md est introuvable. Veuillez vérifier que le fichier est présent à la racine de l'application.</p>
        <?php if (DEV_MODE): ?>
        <details class="changelog-details">
            <summary>Chemins testés (mode dev)</summary>
            <ul>
                <?php foreach ($candidatePaths as $p): ?>
                <li><code><?php echo (new \App\Services\FormattingService())->e($p); ?></code> — <?php echo is_readable(realpath($p) ?: $p) ? '✓ lisible' : '✗ introuvable/illisible'; ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
    </div>
<?php elseif ($parseError): ?>
    <div class="alert alert--danger">
        <p>Erreur lors du rendu du changelog : <?php echo (new \App\Services\FormattingService())->e($parseError); ?></p>
    </div>
<?php else: ?>
    <div class="changelog-content">
        <?php echo $htmlContent; ?>
    </div>
<?php endif; ?>
