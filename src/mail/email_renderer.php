<?php


/**
 * Email Renderer — Template unifié pour les emails HTML.
 *
 * renderEmailBody() est le wrapper unique pour tous les emails.
 * Les fragments (champs, boutons, liens) sont dans les fonctions ci-dessous.
 */

function renderEmailBody(string $title, string $contentHtml, string $siteName = ''): string
{
    $brandColor = getConfigService()->get('app_brand_color', '#1e40af');
    $appName = getConfigService()->get('app_nom_organisation', 'SST DREETS BFC');
    $footerText = $siteName !== '' ? " — $siteName" : '';

    return '<html><body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width:600px; margin:0 auto; padding:20px;">'
        . '<h2 style="color:' . e($brandColor) . ';">' . e($title) . '</h2>'
        . $contentHtml
        . '<hr style="margin:24px 0; border:none; border-top:1px solid #ddd;">'
        . '<p style="font-size:12px; color:#888;">'
        . "Cet e-mail a été envoyé automatiquement par $appName{$footerText}. Ne pas répondre directement à ce message."
        . '</p>'
        . '</body></html>';
}

function renderEmailField(string $label, string $value): string
{
    return '<p><strong>' . e($label) . ' :</strong> ' . e($value) . '</p>';
}

function renderEmailButton(string $url, string $label): string
{
    return '<p style="text-align:center; margin:16px 0;">'
        . '<a href="' . e($url) . '" style="display:inline-block; padding:12px 24px; background:#2563eb; color:#fff; text-decoration:none; border-radius:6px; font-weight:600;">'
        . e($label) . '</a></p>';
}

function renderEmailLink(string $url, string $label): string
{
    return '<p><a href="' . e($url) . '">' . e($label) . '</a></p>';
}
