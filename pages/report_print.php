<?php

/**
 * Report Print Page — Application SST DREETS BFC
 *
 * Generates a PDF of a single report using FPDF v1.9.
 * No JavaScript, no window.print() — pure server-side PDF generation.
 * No Composer, no mPDF — zero dependency, everything in memory.
 * URL: index.php?page=report_print&uuid={report_uuid}
 *
 * NOTE: This page is included by the router BEFORE header/sidebar.
 * The router has already started the session, loaded config/database/helpers/queries,
 * and checked authentication. We do NOT need to re-require or re-start session.
 */

$uuid = $_GET['uuid'] ?? '';
$report = fetchReportOrRedirect($uuid);

// Access control: centralized via canAccessReport()
$user = (new \App\Services\SessionService())->getUserSession();

if (!(new \App\Services\AccessService())->canAccessReport($report, $user)) {
    (new \App\Services\SessionService())->setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
    (new \App\Services\HttpService())->redirect((new \App\Services\HttpService())->url('home'));
}

// Log confidential report access by supervisor/CHSCT
$pdo = getContainer()->get(\PDO::class);
(new \App\Services\AccessService())->logConfidentialReportAccess($pdo, $report, $user);

// Fetch attachment blob separately (not loaded by findById for performance)
$attachmentData = \App\Repository\ReportRepository::instance()->getAttachmentBlob((string) $uuid);
$report['attachment_blob'] = $attachmentData['attachment_blob'] ?? null;

// Get response history
$responses = \App\Repository\ReportRepository::instance()->getResponses($uuid);

$type = $report['type'] ?? 'rsst';
$registryLabel = REGISTRY_LABELS[$type] ?? strtoupper($type);
$registryShortLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper((string) $type);
$etatLabel = ETAT_LABELS[$report['etat']] ?? $report['etat'];

// --- Build PDF with FPDF ---
require_once __DIR__ . '/../src/lib/fpdf/fpdf.php';
require_once __DIR__ . '/report_print_helpers.php';

// Create PDF
$pdf = new SSTPDF('P', 'mm', 'A4');
$pdf->headerText = \App\Services\ConfigService::getInstance()->get('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')
    . ' — Signalement ' . $report['reference'];
$pdf->footerOrgName = \App\Services\ConfigService::getInstance()->get('app_nom_organisation', 'DREETS BFC');
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 22);
$pdf->SetMargins(15, 22, 15);

// Add DejaVu Sans font (Unicode TrueType)
$fontDir = __DIR__ . '/../src/lib/fpdf/font/';
$pdf->AddFont('DejaVu', '', 'DejaVuSans.json', $fontDir);
$pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.json', $fontDir);

$pdf->AddPage();

// Set PDF metadata (pass UTF-8, FPDF handles conversion internally)
$pdf->SetTitle('Signalement ' . $report['reference'], true);
$pdf->SetAuthor(\App\Services\ConfigService::getInstance()->get('app_nom_organisation', 'DREETS BFC'), true);

// =====================================================================
// BUILD THE PDF CONTENT
// =====================================================================

$orgName = \App\Services\ConfigService::getInstance()->get('app_nom_complet', 'DREETS Bourgogne-Franche-Comté');
$labelUnite = \App\Services\ConfigService::getInstance()->get('app_label_unite', 'UR');

// --- Title ---
$pdf->SetFont('DejaVu', 'B', 16);
$pdf->SetTextColor($blueDark[0], $blueDark[1], $blueDark[2]);
$pdf->Cell(0, 10, utf8ToCp1252('Signalement — ' . $report['reference']), 0, 1);
$pdf->Ln(2);

// --- Badges ---
$regColor = $registryColors[$type] ?? $colorRsst;
drawBadge($pdf, $registryShortLabel, $regColor);

$etatColor = $etatColors[$report['etat']] ?? $colorNouveau;
drawBadge($pdf, $etatLabel, $etatColor);

if (!empty($report['is_confidential'])) {
    drawBadge($pdf, 'Confidentiel', [107, 114, 128]);
}
$pdf->Ln(8);

// --- Fields ---
$fields = [
    'Référence'             => $report['reference'],
    'Registre'              => $registryLabel,
    'Date de l\'événement'  => (new \App\Services\FormattingService())->formatDateFR($report['date_evenement']),
    'Heure du dépôt'        => $report['heure_evenement'] ?? '—',
    'Lieu'                  => $report['lieu'] ?? '—',
    'Objet'                 => $report['objet'],
    'Transmission aux ' . \App\Services\ConfigService::getInstance()->getRoleLabel('chsct') . 's' => ($report['consent_syndicat'] ?? 0) ? 'Acceptée' : 'Refusée',
];

foreach ($fields as $label => $value) {
    drawField($pdf, $label, $value);
}

// Description (multiline)
$pdf->Ln(1);
drawMultiField($pdf, 'Description', $report['description']);

// Remaining fields
$pdf->Ln(1);
drawField($pdf, 'Déclarant', $report['declarant_prenom'] . ' ' . $report['declarant_nom']);
if (!\App\Services\ConfigService::getInstance()->isNoSiteMode()) {
    drawField($pdf, $labelUnite, ($report['site_nom'] ?? '—') . ' (' . ($report['site_code'] ?? '—') . ')');
}

if ($type === 'rami' && !empty($report['pour_compte_nom'])) {
    drawField(
        $pdf,
        'Déclaré pour le compte de',
        ($report['pour_compte_prenom'] ?? '') . ' ' . $report['pour_compte_nom']
    );
}

drawField($pdf, 'Date de création', (new \App\Services\FormattingService())->formatDateTimeFR($report['created_at']));

if (!empty($report['attachment_name'])) {
    $isImage = !empty($report['attachment_mime']) && in_array($report['attachment_mime'], ['image/jpeg', 'image/png', 'image/gif']);
    if ($isImage && !empty($report['attachment_blob'])) {
        drawField($pdf, 'Pièce jointe', $report['attachment_name'] . ' (image embarquée ci-dessous)');
    } else {
        drawField($pdf, 'Pièce jointe', $report['attachment_name'] . ' (jointe au signalement)');
    }
}

// --- Embed image attachment in PDF ---
drawEmbeddedImage($pdf, $report, $blueDark);

// État badge (special rendering)
$pdf->Ln(1);
$pdf->SetFont('DejaVu', 'B', 10);
$pdf->SetTextColor(85, 85, 85);
$pdf->Cell(55, 6, utf8ToCp1252('État'), 0, 0);
drawBadge($pdf, $etatLabel, $etatColor);
$pdf->Ln(8);

// --- Response history table ---
drawResponseTable($pdf, $responses, $blueDark);

// --- Footer info ---
drawHR($pdf);
$pdf->SetFont('DejaVu', '', 8);
$pdf->SetTextColor(153, 153, 153);
$pdf->Cell(0, 5, utf8ToCp1252(
    'Document généré le ' . (new \App\Services\FormattingService())->formatDateFR(date('Y-m-d'))
    . ' — ' . \App\Services\ConfigService::getInstance()->get('app_nom_organisation', 'DREETS BFC')
), 0, 1, 'C');

// Output PDF inline (displayed in browser, not forced download)
// 'I' = Content-Disposition: inline — le navigateur ouvre le PDF dans un onglet
// L'utilisateur peut ensuite l'imprimer ou le télécharger depuis le visualiseur PDF.

// Disable gzip output buffer for binary PDF output
while (ob_get_level() > 0) {
    ob_end_clean();
}

(new \App\Services\HttpService())->removeUnwantedHeaders();
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');

$filename = 'signalement-' . $report['reference'] . '.pdf';
$pdf->Output('I', $filename, true);
exit;
