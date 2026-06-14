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

if (!isValidUuid($uuid)) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();
$report = getReportByUuid($pdo, $uuid);

if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

// Access control: centralized via canAccessReport()
$user = $_SESSION['user'];

if (!canAccessReport($report, $user)) {
    setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
    redirect(url('home'));
}

// Log confidential report access by supervisor/CHSCT
logConfidentialReportAccess($pdo, $report, $user);

// Get response history
$responses = getReportResponses($pdo, $uuid);

$type = $report['type'] ?? 'rsst';
$registryLabel = REGISTRY_LABELS[$type] ?? strtoupper($type);
$registryShortLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);
$etatLabel = ETAT_LABELS[$report['etat']] ?? $report['etat'];

// --- Build PDF with FPDF ---
require_once __DIR__ . '/../src/lib/fpdf/fpdf.php';

/**
 * Convert UTF-8 string to cp1252 for FPDF TrueType font rendering.
 * cp1252 covers all Western European characters including French accents,
 * oe ligature, Euro sign, etc.
 */
function utf8ToCp1252(?string $s): string {
    if ($s === null || $s === '') {
        return '';
    }
    // mb_convert_encoding handles all cp1252 characters including oe, Euro, etc.
    $converted = mb_convert_encoding($s, 'cp1252', 'UTF-8');
    return $converted !== false ? $converted : $s;
}

/**
 * Extended FPDF class with custom header and footer.
 */
class SSTPDF extends FPDF
{
    public string $headerText = '';
    public string $footerOrgName = '';

    /** Public getters for protected FPDF margin properties. */
    public function getLeftMargin(): float  { return $this->lMargin; }
    public function getRightMargin(): float { return $this->rMargin; }

    public function Header(): void
    {
        if ($this->headerText !== '') {
            $this->SetFont('DejaVu', '', 8);
            $this->SetTextColor(102, 102, 102);
            $this->Cell(0, 6, utf8ToCp1252($this->headerText), 0, 1, 'L');
            $this->SetDrawColor(204, 204, 204);
            $this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
            $this->Ln(4);
        }
    }

    public function Footer(): void
    {
        $this->SetY(-18);
        $this->SetFont('DejaVu', '', 7);
        $this->SetDrawColor(204, 204, 204);
        $this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
        $this->Ln(2);
        $this->SetTextColor(153, 153, 153);
        $this->Cell(0, 8, utf8ToCp1252(
            'Page ' . $this->PageNo() . ' / {nb}'
            . ' — Généré le ' . date('d/m/Y H:i')
        ), 0, 0, 'C');
    }
}

// Create PDF
$pdf = new SSTPDF('P', 'mm', 'A4');
$pdf->headerText = getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')
    . ' — Signalement ' . $report['reference'];
$pdf->footerOrgName = getConfig('app_nom_organisation', 'DREETS BFC');
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
$pdf->SetAuthor(getConfig('app_nom_organisation', 'DREETS BFC'), true);

// --- Colors ---
$blueDark = [26, 58, 92];   // #1a3a5c
$colorRsst = [46, 92, 138]; // #2E5C8A
$colorRami = [108, 108, 108]; // #6C6C6C
$colorDgi = [178, 34, 34];  // #B22222
$colorNouveau = [46, 92, 138];
$colorEnCours = [230, 126, 34]; // #E67E22
$colorTraite = [39, 174, 96];  // #27AE60
$colorAbandonne = [149, 165, 166]; // #95A5A6

$registryColors = [
    'rsst' => $colorRsst,
    'rami' => $colorRami,
    'dgi'  => $colorDgi,
];
$etatColors = [
    'nouveau'   => $colorNouveau,
    'en_cours'  => $colorEnCours,
    'traite'    => $colorTraite,
    'abandonne' => $colorAbandonne,
];

// =====================================================================
// LAYOUT HELPER FUNCTIONS
// =====================================================================

/**
 * Draw a colored badge (rounded rectangle with text).
 */
function drawBadge(SSTPDF $pdf, string $text, array $bgColor, ?float $x = null, float $w = 0): void
{
    if ($x !== null) {
        $pdf->SetX($x);
    }
    $pdf->SetFont('DejaVu', 'B', 8);
    $textCp = utf8ToCp1252($text);
    $textW = $pdf->GetStringWidth($textCp) + 6;
    $badgeW = ($w > 0) ? $w : $textW;
    $badgeH = 6;
    $y = $pdf->GetY();

    // Draw badge background
    $pdf->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($badgeW, $badgeH, $textCp, 0, 0, 'C', true);
    $pdf->SetTextColor(34, 34, 34); // Reset to dark text

    // Add a small gap after badge
    $pdf->SetX($pdf->GetX() + 2);
}

/**
 * Draw a field row (label: value).
 */
function drawField(SSTPDF $pdf, string $label, string $value, float $labelW = 55): void
{
    $pdf->SetFont('DejaVu', 'B', 10);
    $pdf->SetTextColor(85, 85, 85);
    $labelCp = utf8ToCp1252($label);
    $pdf->Cell($labelW, 6, $labelCp, 0, 0);

    $pdf->SetFont('DejaVu', '', 10);
    $pdf->SetTextColor(34, 34, 34);
    $valueCp = utf8ToCp1252($value);

    // If value is short, use Cell; if long, use MultiCell
    $availableW = $pdf->GetPageWidth() - $pdf->getRightMargin() - $pdf->GetX();
    if ($pdf->GetStringWidth($valueCp) <= $availableW) {
        $pdf->Cell(0, 6, $valueCp, 0, 1);
    } else {
        $pdf->MultiCell($availableW, 6, $valueCp, 0, 'L');
    }
}

/**
 * Draw a multiline field (label on first line, value on next lines).
 */
function drawMultiField(SSTPDF $pdf, string $label, string $value, float $labelW = 55): void
{
    $pdf->SetFont('DejaVu', 'B', 10);
    $pdf->SetTextColor(85, 85, 85);
    $labelCp = utf8ToCp1252($label);
    $pdf->Cell($labelW, 6, $labelCp, 0, 0);

    $pdf->SetFont('DejaVu', '', 10);
    $pdf->SetTextColor(34, 34, 34);
    $valueCp = utf8ToCp1252($value);

    $availableW = $pdf->GetPageWidth() - $pdf->getRightMargin() - $pdf->GetX();
    $pdf->MultiCell($availableW, 6, $valueCp, 0, 'L');
}

/**
 * Draw a section title (H2).
 */
function drawSectionTitle(SSTPDF $pdf, string $title, array $color): void
{
    $pdf->Ln(6);
    $pdf->SetFont('DejaVu', 'B', 12);
    $pdf->SetTextColor($color[0], $color[1], $color[2]);
    $titleCp = utf8ToCp1252($title);
    $pdf->Cell(0, 7, $titleCp, 0, 1);
    $y = $pdf->GetY();
    $pdf->SetDrawColor(204, 204, 204);
    $pdf->Line($pdf->getLeftMargin(), $y, $pdf->GetPageWidth() - $pdf->getRightMargin(), $y);
    $pdf->Ln(3);
}

/**
 * Draw a horizontal rule.
 */
function drawHR(SSTPDF $pdf): void
{
    $pdf->Ln(4);
    $pdf->SetDrawColor(204, 204, 204);
    $pdf->Line($pdf->getLeftMargin(), $pdf->GetY(), $pdf->GetPageWidth() - $pdf->getRightMargin(), $pdf->GetY());
    $pdf->Ln(4);
}

// =====================================================================
// BUILD THE PDF CONTENT
// =====================================================================

$orgName = getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté');
$labelUnite = getConfig('app_label_unite', 'UR');

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
    'Date de l\'événement'  => formatDateFR($report['date_evenement']),
    'Heure de l\'événement' => $report['heure_evenement'] ?? '—',
    'Lieu'                  => $report['lieu'] ?? '—',
    'Objet'                 => $report['objet'],
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
drawField($pdf, $labelUnite, ($report['site_nom'] ?? '—') . ' (' . ($report['site_code'] ?? '—') . ')');

if ($type === 'rami' && !empty($report['pour_compte_nom'])) {
    drawField($pdf, 'Déclaré pour le compte de',
        ($report['pour_compte_prenom'] ?? '') . ' ' . $report['pour_compte_nom']);
}

drawField($pdf, 'Date de création', formatDateTimeFR($report['created_at']));

if (!empty($report['attachment_name'])) {
    $isImage = !empty($report['attachment_mime']) && in_array($report['attachment_mime'], ['image/jpeg', 'image/png', 'image/gif']);
    if ($isImage && !empty($report['attachment_blob'])) {
        drawField($pdf, 'Pièce jointe', $report['attachment_name'] . ' (image embarquée ci-dessous)');
    } else {
        drawField($pdf, 'Pièce jointe', $report['attachment_name'] . ' (jointe au signalement)');
    }
}

// --- Embed image attachment in PDF ---
if (!empty($report['attachment_blob']) && !empty($report['attachment_mime'])
    && in_array($report['attachment_mime'], ['image/jpeg', 'image/png', 'image/gif'])) {
    try {
        // Build a data:// URI from the BLOB — no temp file needed.
        // FPDF's Image() accepts any PHP stream wrapper because it uses
        // fopen() + fread() internally. data:// is built into PHP.
        $typeStr = match($report['attachment_mime']) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            default      => 'jpg',
        };
        $dataUri = 'data://' . $report['attachment_mime'] . ';base64,' . base64_encode($report['attachment_blob']);

        // Get image dimensions to calculate proportional size
        $imageInfo = @getimagesize($dataUri);
        $pageWidth = $pdf->GetPageWidth() - $pdf->getLeftMargin() - $pdf->getRightMargin();
        $maxImageHeight = 120; // mm — max height on page

        if ($imageInfo !== false) {
            $imgWidthPx = $imageInfo[0];
            $imgHeightPx = $imageInfo[1];
            // Calculate proportional dimensions (pixels → mm, fit to page width)
            $ratio = $imgHeightPx / $imgWidthPx;
            $displayWidth = min($pageWidth, 180); // max 180mm wide
            $displayHeight = $displayWidth * $ratio;

            // If image is too tall, scale down by height
            if ($displayHeight > $maxImageHeight) {
                $displayHeight = $maxImageHeight;
                $displayWidth = $displayHeight / $ratio;
            }
        } else {
            // Fallback if getimagesize fails
            $displayWidth = min($pageWidth, 180);
            $displayHeight = 80;
        }

        // Check if image fits on current page, otherwise add new page
        $currentY = $pdf->GetY();
        $bottomMargin = 22; // footer space
        if ($currentY + $displayHeight + 6 > $pdf->GetPageHeight() - $bottomMargin) {
            $pdf->AddPage();
        }

        // Section title for image
        drawSectionTitle($pdf, 'Image jointe', $blueDark);

        // Draw light gray background rectangle behind image
        $x = $pdf->getLeftMargin();
        $y = $pdf->GetY();
        $pdf->SetFillColor(248, 248, 248);
        $pdf->Rect($x, $y, $displayWidth + 4, $displayHeight + 4, 'F');

        // Embed image in PDF via data:// stream (no temp file on disk)
        $pdf->Image($dataUri, $x + 2, $y + 2, $displayWidth, $displayHeight, $typeStr);
        $pdf->SetY($y + $displayHeight + 8);
    } catch (\Throwable $e) {
        // If image embedding fails, just skip it — don't break PDF generation
        // The filename is already shown above
    }
}

// État badge (special rendering)
$pdf->Ln(1);
$pdf->SetFont('DejaVu', 'B', 10);
$pdf->SetTextColor(85, 85, 85);
$pdf->Cell(55, 6, utf8ToCp1252('État'), 0, 0);
drawBadge($pdf, $etatLabel, $etatColor);
$pdf->Ln(8);

// --- Response history table ---
if (!empty($responses)) {
    drawHR($pdf);
    drawSectionTitle($pdf, 'Réponses (' . count($responses) . ')', $blueDark);

    $colWidths = [30, 35, 25, 70]; // Date, Répondant, État, Réponse
    $headers = ['Date', 'Répondant', 'Nouvel état', 'Réponse'];
    $headerH = 7;
    $rowH = 7;

    // Table header
    $pdf->SetFont('DejaVu', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetDrawColor(221, 221, 221);
    $pdf->SetTextColor(34, 34, 34);
    foreach ($headers as $i => $header) {
        $pdf->Cell($colWidths[$i], $headerH, utf8ToCp1252($header), 1, 0, 'L', true);
    }
    $pdf->Ln();

    // Table rows
    $pdf->SetFont('DejaVu', '', 8);
    foreach ($responses as $resp) {
        $etatResp = !empty($resp['nouvel_etat'])
            ? (ETAT_LABELS[$resp['nouvel_etat']] ?? $resp['nouvel_etat'])
            : '—';

        $row = [
            formatDateTimeFR($resp['created_at']),
            ($resp['prenom'] ?? '') . ' ' . ($resp['nom'] ?? ''),
            $etatResp,
            $resp['reponse'] ?? '',
        ];

        // Calculate row height based on the response column (column 3)
        $responseText = utf8ToCp1252($row[3]);
        $responseColW = $colWidths[3] - 2;
        $maxLines = 1;
        $words = explode(' ', $responseText);
        $testLine = '';
        foreach ($words as $word) {
            $testLine .= ($testLine ? ' ' : '') . $word;
            if ($pdf->GetStringWidth($testLine) > $responseColW) {
                $maxLines++;
                $testLine = $word;
            }
        }
        $currentRowH = max($maxLines * 5 + 2, $rowH);

        // Check if we need a new page
        if ($pdf->GetY() + $currentRowH > $pdf->GetPageHeight() - 25) {
            $pdf->AddPage();
            // Re-draw header on new page
            $pdf->SetFont('DejaVu', 'B', 9);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetDrawColor(221, 221, 221);
            foreach ($headers as $i => $header) {
                $pdf->Cell($colWidths[$i], $headerH, utf8ToCp1252($header), 1, 0, 'L', true);
            }
            $pdf->Ln();
            $pdf->SetFont('DejaVu', '', 8);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Draw cell backgrounds with borders
        $pdf->SetFillColor(255, 255, 255);
        for ($i = 0; $i < 4; $i++) {
            $pdf->Rect($x + array_sum(array_slice($colWidths, 0, $i)), $y, $colWidths[$i], $currentRowH, 'DF');
        }

        // Fill cell text
        for ($i = 0; $i < 4; $i++) {
            $cellX = $x + array_sum(array_slice($colWidths, 0, $i));
            $pdf->SetXY($cellX + 1, $y + 1);
            $cellText = utf8ToCp1252($row[$i]);
            if ($i === 3) {
                // Response column: use MultiCell
                $pdf->MultiCell($colWidths[$i] - 2, 5, $cellText, 0, 'L');
            } else {
                $pdf->Cell($colWidths[$i] - 2, 5, $cellText, 0, 0, 'L');
            }
        }

        $pdf->SetY($y + $currentRowH);
    }
}

// --- Footer info ---
drawHR($pdf);
$pdf->SetFont('DejaVu', '', 8);
$pdf->SetTextColor(153, 153, 153);
$pdf->Cell(0, 5, utf8ToCp1252(
    'Document généré le ' . formatDateFR(date('Y-m-d'))
    . ' — ' . getConfig('app_nom_organisation', 'DREETS BFC')
), 0, 1, 'C');

// Output PDF inline (displayed in browser, not forced download)
// 'I' = Content-Disposition: inline — le navigateur ouvre le PDF dans un onglet
// L'utilisateur peut ensuite l'imprimer ou le télécharger depuis le visualiseur PDF.

// Disable gzip output buffer for binary PDF output
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Cache-Control: no-cache for this dynamically generated PDF
header_remove('X-Powered-By');
header_remove('Server');
header_remove('Expires');
header_remove('Pragma');
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');

$filename = 'signalement-' . $report['reference'] . '.pdf';
$pdf->Output('I', $filename, true);
exit;
