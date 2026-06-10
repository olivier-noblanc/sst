<?php
/**
 * Changelog Page — Application SST DREETS BFC
 *
 * Displays the CHANGELOG.md content rendered as HTML.
 * Offers a PDF export button using mPDF.
 * URL: index.php?page=changelog
 *
 * NOTE: This page is included by the router BEFORE footer.
 * The router has already started the session, loaded config/database/helpers/queries,
 * and checked authentication. We do NOT need to re-require or re-start session.
 */

// --- PDF Export handler (before any HTML output) ---
if (isset($_GET['pdf']) && $_GET['pdf'] === '1') {
    $changelogPath = __DIR__ . '/../CHANGELOG.md';

    if (!file_exists($changelogPath)) {
        setFlash('error', 'Fichier CHANGELOG.md introuvable.');
        redirect(url('changelog'));
    }

    $mdContent = file_get_contents($changelogPath);

    // Parse Markdown to HTML
    require_once __DIR__ . '/../src/lib/Parsedown.php';
    $parsedown = new Parsedown();
    $htmlBody = $parsedown->text($mdContent);

    // Generate PDF with mPDF
    require_once __DIR__ . '/../vendor/autoload.php';

    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_left'   => 15,
        'margin_right'  => 15,
        'margin_top'    => 20,
        'margin_bottom' => 20,
        'default_font'  => 'dejavusans',
    ]);

    $mpdf->SetTitle('Historique des modifications — Application SST v' . APP_VERSION);
    $mpdf->SetAuthor(getConfig('app_nom_organisation', 'DREETS BFC'));

    // Header
    $mpdf->SetHTMLHeader('
        <div style="font-size:9px;color:#666;border-bottom:1px solid #ccc;padding-bottom:4px;">
            Application SST v' . e(APP_VERSION) . ' — Historique des modifications
        </div>
    ');

    // Footer with page number
    $mpdf->SetHTMLFooter('
        <div style="font-size:8px;color:#999;border-top:1px solid #ccc;padding-top:4px;text-align:center;">
            Page {PAGENO} / {nb} — Généré le ' . date('d/m/Y H:i') . '
        </div>
    ');

    // CSS for the PDF content
    $pdfCss = '
        body { font-family: dejavusans, sans-serif; font-size: 11pt; color: #222; }
        h1 { font-size: 18pt; color: #1a3a5c; border-bottom: 2px solid #1a3a5c; padding-bottom: 6px; margin-top: 0; }
        h2 { font-size: 14pt; color: #1a3a5c; margin-top: 20px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        h3 { font-size: 12pt; color: #2d6da3; margin-top: 14px; }
        p { line-height: 1.5; margin: 6px 0; }
        ul { margin: 6px 0; padding-left: 20px; }
        li { margin: 3px 0; line-height: 1.4; }
        strong { color: #1a3a5c; }
        code { background: #f5f5f5; padding: 1px 4px; border-radius: 3px; font-size: 10pt; }
        hr { border: none; border-top: 1px solid #ccc; margin: 20px 0; }
    ';

    $mpdf->WriteHTML('<style>' . $pdfCss . '</style>' . $htmlBody);

    $filename = 'changelog-sst-v' . APP_VERSION . '.pdf';
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
    exit;
}

// --- Normal HTML display ---
$changelogPath = __DIR__ . '/../CHANGELOG.md';
$changelogExists = file_exists($changelogPath);
$htmlContent = '';

if ($changelogExists) {
    $mdContent = file_get_contents($changelogPath);
    require_once __DIR__ . '/../src/lib/Parsedown.php';
    $parsedown = new Parsedown();
    $htmlContent = $parsedown->text($mdContent);
}
?>

<div class="page-header">
    <h1>Historique des modifications</h1>
    <div class="page-header__actions">
        <?php if ($changelogExists): ?>
        <a href="?page=changelog&pdf=1" class="btn btn--primary" title="Télécharger le changelog en PDF">
            <span class="icon">📥</span> Exporter en PDF
        </a>
        <?php endif; ?>
    </div>
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
    color: var(--primary, #1a3a5c);
    border-bottom: 2px solid var(--primary, #1a3a5c);
    padding-bottom: 8px;
    margin-top: 0;
}

.changelog-content h2 {
    font-size: 1.2rem;
    color: var(--primary, #1a3a5c);
    border-bottom: 1px solid var(--grey-200, #e5e7eb);
    padding-bottom: 6px;
    margin-top: 28px;
}

.changelog-content h3 {
    font-size: 1.05rem;
    color: var(--primary-dark, #2d6da3);
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
    color: var(--primary, #1a3a5c);
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

@media print {
    .page-header__actions { display: none; }
    .changelog-content { border: none; padding: 0; }
}
</style>
