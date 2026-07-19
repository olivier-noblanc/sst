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
$userRole = currentUserRole() !== '' ? currentUserRole() : 'agent';
$labelUnite = new \App\Services\FormattingService()->e(\App\Services\ConfigService::getInstance()->get('app_label_unite', 'UR'));
$screenshotBase = 'asset.php?f=screenshots';
$isAgent = ($userRole === 'agent');
$ramiEnabled = \App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_RAMI);
$dgiEnabled = \App\Services\ConfigService::getInstance()->isRegistryEnabled(TYPE_DGI);
$registryCount = 1 + ($ramiEnabled ? 1 : 0) + ($dgiEnabled ? 1 : 0);
$hotlineNumber = \App\Services\ConfigService::getInstance()->get('app_hotline_number', '');
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
    return <<<HTML
    <div class="help-screenshot-block" id="{$id}">
        <p class="help-screenshot-label">{$alt}</p>
        <div class="help-screenshot-wrapper">
            <img src="{$imgSrc}" alt="{$alt}" class="help-screenshot-img" loading="lazy" />
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
