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

<style>
.changelog-content {
    background: #fff;
    border: 1px solid var(--grey-200, #e5e7eb);
    border-radius: 8px;
    padding: 24px 32px;
    max-width: 900px;
    line-height: 1.7;
}

.changelog-content h1 {
    font-size: 1.5rem;
    color: var(--color-primary, #1a3a5c);
    border-bottom: 2px solid var(--color-primary, #1a3a5c);
    padding-bottom: 8px;
    margin-top: 0;
}

.changelog-content h2 {
    font-size: 1.2rem;
    color: var(--color-primary, #1a3a5c);
    border-bottom: 1px solid var(--grey-200, #e5e7eb);
    padding-bottom: 6px;
    margin-top: 28px;
}

.changelog-content h3 {
    font-size: 1.05rem;
    color: var(--color-primary-dark, #2d6da3);
    margin-top: 18px;
}

.changelog-content ul {
    margin: 8px 0;
    padding-left: 24px;
}

.changelog-content li {
    margin: 4px 0;
}

.changelog-content strong {
    color: var(--color-primary, #1a3a5c);
}

.changelog-content code {
    background: var(--grey-100, #f3f4f6);
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 0.9em;
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
}

.changelog-content hr {
    border: none;
    border-top: 1px solid var(--grey-200, #e5e7eb);
    margin: 24px 0;
}

.changelog-content p {
    margin: 8px 0;
}
</style>
