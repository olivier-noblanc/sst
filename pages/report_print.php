<?php

use App\Services\SessionService;
use App\Services\AccessService;
use App\Services\HttpService;
use App\Repository\ReportRepository;
use App\Services\ConfigService;
use App\Services\FormattingService;

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

/** @var string */
$uuid = $_GET['uuid'] ?? '';
$report = fetchReportOrRedirect($uuid);

$reportReference = $report['reference'] ?? '';
$reportType = $report['type'] ?? 'rsst';
$reportEtat = $report['etat'] ?? '';

// Access control: centralized via canAccessReport()
$user = SessionService::getInstance()->getUserSession();

if ($user === null || !new AccessService()->canAccessReport($report, $user)) {
    SessionService::getInstance()->setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
    new HttpService()->redirect(new HttpService()->url('home'));
}

// Log confidential report access by supervisor/CHSCT
$pdo = getContainer()->get(PDO::class);
assert($user !== null);
new AccessService()->logConfidentialReportAccess($pdo, $report, $user);

// Fetch attachment blob separately (not loaded by findById for performance)
$attachmentData = ReportRepository::instance()->getAttachmentBlob($uuid);
$report['attachment_blob'] = $attachmentData['attachment_blob'] ?? null;

// Get response history
/** @var list<array<string, mixed>> $responses */
$responses = ReportRepository::instance()->getResponses($uuid);

$type = $reportType;
$registryLabel = REGISTRY_LABELS[$type] ?? strtoupper((string) $type);
$registryShortLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper((string) $type);
$etatLabel = ETAT_LABELS[$reportEtat] ?? $reportEtat;

// --- Build PDF with FPDF ---
require_once __DIR__ . '/../src/lib/fpdf/fpdf.php';
require_once __DIR__ . '/report_print_helpers.php';

/** @var array{int, int, int} $blueDark */
/** @var array{int, int, int} $colorRsst */
/** @var array{int, int, int} $colorNouveau */
/** @var array<string, array{int, int, int}> $registryColors */
/** @var array<string, array{int, int, int}> $etatColors */

// Create PDF
$pdf = new SSTPDF('P', 'mm', 'A4');
$pdf->headerText = ConfigService::getInstance()->get('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')
    . ' — Signalement ' . $reportReference;
$pdf->footerOrgName = ConfigService::getInstance()->get('app_nom_organisation', 'DREETS BFC');
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 22);
$pdf->SetMargins(15, 22, 15);

// Add DejaVu Sans font (Unicode TrueType)
$fontDir = __DIR__ . '/../src/lib/fpdf/font/';
$pdf->AddFont('DejaVu', '', 'DejaVuSans.json', $fontDir);
$pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.json', $fontDir);

$pdf->AddPage();

// Set PDF metadata (pass UTF-8, FPDF handles conversion internally)
$pdf->SetTitle('Signalement ' . $reportReference, true);
$pdf->SetAuthor(ConfigService::getInstance()->get('app_nom_organisation', 'DREETS BFC'), true);

// =====================================================================
// BUILD THE PDF CONTENT
// =====================================================================

$orgName = ConfigService::getInstance()->get('app_nom_complet', 'DREETS Bourgogne-Franche-Comté');
$labelUnite = ConfigService::getInstance()->get('app_label_unite', 'UR');

// --- Title ---
$pdf->SetFont('DejaVu', 'B', 16);
$pdf->SetTextColor($blueDark[0], $blueDark[1], $blueDark[2]);
$pdf->Cell(0, 10, utf8ToCp1252('Signalement — ' . $reportReference), 0, 1);
$pdf->Ln(2);

// --- Badges ---
$regColor = $registryColors[$type] ?? $colorRsst;
drawBadge($pdf, $registryShortLabel, $regColor);

$etatColor = $etatColors[$reportEtat] ?? $colorNouveau;
drawBadge($pdf, $etatLabel, $etatColor);

if (!empty($report['is_confidential'])) {
    drawBadge($pdf, 'Confidentiel', [107, 114, 128]);
}
$pdf->Ln(8);

// --- Fields ---
$reportDateEvenement = $report['date_evenement'] ?? '';
$reportHeureEvenement = $report['heure_evenement'] ?? '—';
$reportLieu = $report['lieu'] ?? '—';
$reportObjet = $report['objet'] ?? '';
$reportConsentSyndicat = $report['consent_syndicat'] ?? 0;

$fields = [
    'Référence'             => $reportReference,
    'Registre'              => $registryLabel,
    'Date de l\'événement'  => new FormattingService()->formatDateFR($reportDateEvenement),
    'Heure du dépôt'        => $reportHeureEvenement,
    'Lieu'                  => $reportLieu,
    'Objet'                 => $reportObjet,
    'Transmission aux ' . ConfigService::getInstance()->getRoleLabel('chsct') . 's' => $reportConsentSyndicat !== 0 ? 'Acceptée' : 'Refusée',
];

foreach ($fields as $label => $value) {
    drawField($pdf, $label, $value);
}

// Description (multiline)
$reportDescription = $report['description'] ?? '';
$pdf->Ln(1);
drawMultiField($pdf, 'Description', $reportDescription);

// Remaining fields
$reportDeclarantPrenom = $report['declarant_prenom'] ?? '';
$reportDeclarantNom = $report['declarant_nom'] ?? '';
$pdf->Ln(1);
drawField($pdf, 'Déclarant', $reportDeclarantPrenom . ' ' . $reportDeclarantNom);
if (!ConfigService::getInstance()->isNoSiteMode()) {
    $reportSiteNom = $report['site_nom'] ?? '—';
    $reportSiteCode = $report['site_code'] ?? '—';
    drawField($pdf, $labelUnite, $reportSiteNom . ' (' . $reportSiteCode . ')');
}
if (!empty($report['site_text'])) {
    drawField($pdf, 'Site', $report['site_text']);
}

if ($type === 'rami' && !empty($report['pour_compte_nom'])) {
    $reportPourComptePrenom = $report['pour_compte_prenom'] ?? '';
    $reportPourCompteNom = $report['pour_compte_nom'] ?? '';
    drawField(
        $pdf,
        'Déclaré pour le compte de',
        $reportPourComptePrenom . ' ' . $reportPourCompteNom
    );
}

$reportCreatedAt = $report['created_at'] ?? '';
drawField($pdf, 'Date de création', new FormattingService()->formatDateTimeFR($reportCreatedAt));

if (!empty($report['attachment_name'])) {
    $reportAttachmentName = $report['attachment_name'] ?? '';
    $reportAttachmentMime = $report['attachment_mime'] ?? '';
    $isImage = $reportAttachmentMime !== '' && in_array($reportAttachmentMime, ['image/jpeg', 'image/png', 'image/gif'], true);
    if ($isImage && !empty($report['attachment_blob'])) {
        drawField($pdf, 'Pièce jointe', $reportAttachmentName . ' (image embarquée ci-dessous)');
    } else {
        drawField($pdf, 'Pièce jointe', $reportAttachmentName . ' (jointe au signalement)');
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
    'Document généré le ' . new FormattingService()->formatDateFR(date('Y-m-d'))
    . ' — ' . ConfigService::getInstance()->get('app_nom_organisation', 'DREETS BFC')
), 0, 1, 'C');

// Output PDF inline (displayed in browser, not forced download)
// 'I' = Content-Disposition: inline — le navigateur ouvre le PDF dans un onglet
// L'utilisateur peut ensuite l'imprimer ou le télécharger depuis le visualiseur PDF.

// Disable gzip output buffer for binary PDF output
while (ob_get_level() > 0) {
    ob_end_clean();
}

new HttpService()->removeUnwantedHeaders();
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');

$filename = 'signalement-' . $reportReference . '.pdf';
$pdf->Output('I', $filename, true);
exit;
