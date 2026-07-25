<?php
/**
 * Help / Documentation Page — Application SST DREETS BFC
 *
 * Refonte complète de la documentation intégrée.
 * Captures d'écran PNG annotées (flèches + labels numérotés).
 * Imprimables, sans iframe — compatibles CSP frame-ancestors 'none'.
 * Navigation par ancres — contenu toujours visible, rien de pliable.
 */
$pageTitle = 'Documentation';
$userRole = currentUserRole() !== '' ? currentUserRole() : \App\Enum\UserRole::Agent->value;
$labelUnite = new \App\Services\FormattingService()->e(getConfigService()->get('app_label_unite', 'UR'));
$screenshotBase = 'asset.php?f=screenshots';
$isAgent = ($userRole === \App\Enum\UserRole::Agent->value);

// Modular-audit P1.2 — Récupère tous les registres actifs au lieu de hardcoder
// RSST+RAMI+DGI. Les pages d'aide itèrent maintenant dynamiquement.
$enabledRegistries = \App\Repository\RegistryRepository::instance()->findEnabled();
$registryCount = count($enabledRegistries);

// Compatibilité arrière : $ramiEnabled / $dgiEnabled encore utilisés par
// le partial _registres.php (3 cartes hardcodées). Sera supprimé en Phase 2
// quand le partial sera rendu dynamique.
$ramiEnabled = false;
$dgiEnabled = false;
foreach ($enabledRegistries as $reg) {
    $code = (string) $reg['code'];
    if ($code === \App\Enum\ReportType::Rami->value) { $ramiEnabled = true; }
    if ($code === \App\Enum\ReportType::Dgi->value) { $dgiEnabled = true; }
}

$hotlineNumber = getConfigService()->get('app_hotline_number', '');
$hotlineEnabled = !empty($hotlineNumber);

// Screenshot helper — must be defined BEFORE any HTML output
function helpImg(string $name, string $alt, string $base): string
{
    $src = $base . '/' . $name;
    return '<img src="' . $src . '" alt="' . new \App\Services\FormattingService()->e($alt) . '" class="help-img">';
}

/**
 * Generate a visible screenshot block (no collapsible details).
 * Renders an annotated PNG image — always visible, never folded, printable.
 * Converts .html source path to .png automatically.
 */
function helpScreenshot(string $src, string $alt): string
{
    $id = 'ss-' . substr(md5($src), 0, 8);
    $imgSrc = preg_replace('/\.html$/', '.png', $src);
    // Audit #92 — escape $alt and $imgSrc for HTML context. Before this fix,
    // they were interpolated raw in the heredoc → XSS risk if alt/src contain
    // special chars (e.g. quotes, angle brackets).
    $idEsc = e($id);
    $altEsc = e($alt);
    $imgSrcEsc = e((string) $imgSrc);
    return <<<HTML
    <div class="help-screenshot-block" id="{$idEsc}">
        <p class="help-screenshot-label">{$altEsc}</p>
        <div class="help-screenshot-wrapper">
            <img src="{$imgSrcEsc}" alt="{$altEsc}" class="help-screenshot-img" loading="lazy" />
        </div>
    </div>
    HTML;
}
?>

<?php if ($isAgent): ?>
<?php include __DIR__ . '/help/_agent.php'; ?>
<?php else: ?>
<?php include __DIR__ . '/help/_full.php'; ?>
<?php endif; ?>
