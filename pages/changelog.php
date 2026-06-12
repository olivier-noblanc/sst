<?php
/**
 * Changelog Page — Application SST DREETS BFC
 *
 * Displays the CHANGELOG.md content rendered as HTML.
 * URL: index.php?page=changelog
 *
 * NOTE: This page is included by the router with header/sidebar/footer.
 * The router has already started the session, loaded config/database/helpers/queries,
 * and checked authentication. We do NOT need to re-require or re-start session.
 */

$changelogPath = __DIR__ . '/../CHANGELOG.md';
$changelogExists = file_exists($changelogPath);
$htmlContent = '';

if ($changelogExists) {
    $mdContent = file_get_contents($changelogPath);
    require_once __DIR__ . '/../src/lib/Parsedown.php';
    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);  // Strip raw HTML blocks — prevents XSS via Markdown
    $htmlContent = $parsedown->text($mdContent);
}
?>

<div class="page-header">
    <h1>Historique des modifications</h1>
</div>

<?php if (!$changelogExists): ?>
    <div class="alert alert--warning">
        <p>Le fichier CHANGELOG.md est introuvable. Veuillez vérifier que le fichier est présent à la racine de l'application.</p>
    </div>
<?php else: ?>
    <div class="changelog-content">
        <?php echo $htmlContent; ?>
    </div>
<?php endif; ?>
