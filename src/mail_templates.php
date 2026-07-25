<?php

use App\Services\ConfigService;
use App\Services\FormattingService;

/**
 * Mail Template Functions — Application SST DREETS BFC
 *
 * Specialized email body builders.
 * The main email wrapper is renderEmailBody() in src/mail/email_renderer.php.
 */
/**
 * Build the HTML email body for delayed report alerts.
 *
 * Shared between src/cron.php (lazy cron) and tools/check_delays.php (CLI).
 * Before this function, the same ~20 lines of inline-styled HTML table
 * were duplicated between the two files.
 *
 * @param array{site_code: string, site_nom: string, reports: array<int, mixed>} $siteData
 * @param int    $alertDelayDays Configured delay in days
 * @return string  Complete HTML email body
 */
function buildDelayAlertEmail(array $siteData, int $alertDelayDays): string
{
    $appName = ConfigService::getInstance()->get('app_nom_organisation', 'DREETS BFC');

    $body = '<html><body>';
    $body .= '<h2>Alerte de délai de traitement</h2>';
    $body .= "<p>Les signalements suivants sont restés à l'état <strong>Nouveau</strong> pendant plus de <strong>{$alertDelayDays} jour(s)</strong> sur le site <strong>" . htmlspecialchars($siteData['site_code'] . ' — ' . $siteData['site_nom']) . '</strong> :</p>';
    $body .= "<table style='border-collapse:collapse; font-family:sans-serif; font-size:14px; margin:16px 0; width:100%;'>";
    $body .= "<tr style='background:#f5f5f5;'><th style='padding:8px; border:1px solid #ddd; text-align:left;'>Réf.</th><th style='padding:8px; border:1px solid #ddd; text-align:left;'>Registre</th><th style='padding:8px; border:1px solid #ddd; text-align:left;'>Objet</th><th style='padding:8px; border:1px solid #ddd; text-align:left;'>Déclarant</th><th style='padding:8px; border:1px solid #ddd; text-align:left;'>Créé le</th></tr>";

    foreach ($siteData['reports'] as $report) {
        $reportType = $report['type'] ?? '';
        $registryLabel = getRegistryShortLabel($reportType);
        $reference = $report['reference'] ?? '';
        $objet = $report['objet'] ?? '';
        $createdAt = $report['created_at'] ?? '';
        $body .= '<tr>';
        $body .= "<td style='padding:6px 8px; border:1px solid #ddd;'>" . htmlspecialchars((string) $reference) . '</td>';
        $body .= "<td style='padding:6px 8px; border:1px solid #ddd;'>" . htmlspecialchars($registryLabel) . '</td>';
        $body .= "<td style='padding:6px 8px; border:1px solid #ddd;'>" . htmlspecialchars((string) $objet) . '</td>';
        $body .= "<td style='padding:6px 8px; border:1px solid #ddd;'>" . htmlspecialchars(($report['declarant_prenom'] ?? '') . ' ' . ($report['declarant_nom'] ?? '')) . '</td>';
        $body .= "<td style='padding:6px 8px; border:1px solid #ddd;'>" . htmlspecialchars(new FormattingService()->formatDateTimeFR($createdAt)) . '</td>';
        $body .= '</tr>';
    }

    $body .= '</table>';
    $body .= '<p>Merci de traiter ces signalements dans les meilleurs délais.</p>';
    $body .= "<hr style='margin:16px 0; border:none; border-top:1px solid #ddd;'>";
    $body .= "<p style='font-size:12px; color:#888;'>Cet e-mail a été envoyé automatiquement par l'application $appName. Ne pas répondre directement à ce message.</p>";
    $body .= '</body></html>';

    return $body;
}
