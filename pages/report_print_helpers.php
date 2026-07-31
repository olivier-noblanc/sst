<?php

use App\Services\FormattingService;
use App\Enum\ReportState;
use App\Enum\ReportType;

/**
 * Report Print Helpers — Application SST DREETS BFC
 *
 * PDF helper functions, SSTPDF class, color definitions,
 * image embedding and response table rendering for report_print.php.
 */
// --- Color definitions ---
$blueDark = [26, 58, 92];       // #1a3a5c
$registryColors = array_combine(
    array_map(fn(ReportType $t) => $t->value, ReportType::cases()),
    array_map(fn(ReportType $t) => $t->pdfColor(), ReportType::cases())
);
$etatColors = array_combine(
    array_map(fn(ReportState $s) => $s->value, ReportState::cases()),
    array_map(fn(ReportState $s) => $s->pdfColor(), ReportState::cases())
);

/** Convert UTF-8 string to cp1252 for FPDF TrueType font rendering. */
function utf8ToCp1252(?string $s): string
{
    if ($s === null || $s === '') {
        return '';
    }
    $converted = mb_convert_encoding($s, 'cp1252', 'UTF-8');
    return $converted;
}

/** Extended FPDF class with custom header and footer. */
class SSTPDF extends FPDF
{
    public function getLeftMargin(): float
    {
        $margin = $this->lMargin;
        return $margin;
    }
    public function getRightMargin(): float
    {
        $margin = $this->rMargin;
        return $margin;
    }
}

/**
 * Draw a colored badge (rounded rectangle with text).
 * @param array{int, int, int} $bgColor
 */
function drawBadge(SSTPDF $pdf, string $text, array $bgColor, ?float $x = null, float $w = 0): void
{
    if ($x !== null) {
        $pdf->SetX($x);
    }
    $pdf->SetFont('DejaVu', 'B', 8);
    $textCp = utf8ToCp1252($text);
    $strWidth = $pdf->GetStringWidth($textCp);
    $badgeW = ($w > 0) ? $w : $strWidth + 6;
    $pdf->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($badgeW, 6, $textCp, 0, 0, 'C', true);
    $pdf->SetTextColor(34, 34, 34);
    $curX = $pdf->GetX();
    $pdf->SetX($curX + 2);
}

/**
 * Compute the label column width to use for a field row: the fixed
 * $labelW normally, or the label's actual rendered width (+2mm margin)
 * when that's wider — Cell() doesn't wrap or clip on its own, a label
 * wider than the column just overflows visually into whatever is drawn
 * right after it. Requires the intended font to already be set on $pdf
 * (label font is bold, see drawField/drawMultiField) since string width
 * depends on it.
 */
function effectiveLabelWidth(SSTPDF $pdf, string $labelCp, float $labelW): float
{
    return max($labelW, $pdf->GetStringWidth($labelCp) + 2);
}

/** Draw a field row (label: value). */
function drawField(SSTPDF $pdf, string $label, string $value, float $labelW = 55): void
{
    $pdf->SetFont('DejaVu', 'B', 10);
    $pdf->SetTextColor(85, 85, 85);
    $labelCp = utf8ToCp1252($label);
    // The fixed label width assumes short, static French labels ("Objet",
    // "Lieu", "Déclarant"...). That breaks for real with the default
    // config: "Transmission aux Membre FS/CSAs" (role label is
    // admin-configurable, see ConfigService::getRoleLabel()) is already
    // wider than 55mm out of the box, well before any unusually long
    // custom label. See effectiveLabelWidth().
    $pdf->Cell(effectiveLabelWidth($pdf, $labelCp, $labelW), 6, $labelCp, 0, 0);
    $pdf->SetFont('DejaVu', '', 10);
    $pdf->SetTextColor(34, 34, 34);
    $valueCp = utf8ToCp1252($value);
    $pageW = $pdf->GetPageWidth();
    $curX = $pdf->GetX();
    $availableW = $pageW - $pdf->getRightMargin() - $curX;
    if ($pdf->GetStringWidth($valueCp) <= $availableW) {
        $pdf->Cell(0, 6, $valueCp, 0, 1);
    } else {
        $pdf->MultiCell($availableW, 6, $valueCp, 0, 'L');
    }
}

/** Draw a multiline field (label on first line, value on next lines). */
function drawMultiField(SSTPDF $pdf, string $label, string $value, float $labelW = 55): void
{
    $pdf->SetFont('DejaVu', 'B', 10);
    $pdf->SetTextColor(85, 85, 85);
    $labelCp = utf8ToCp1252($label);
    $pdf->Cell(effectiveLabelWidth($pdf, $labelCp, $labelW), 6, $labelCp, 0, 0);
    $pdf->SetFont('DejaVu', '', 10);
    $pdf->SetTextColor(34, 34, 34);
    $pageW = $pdf->GetPageWidth();
    $curX = $pdf->GetX();
    $availableW = $pageW - $pdf->getRightMargin() - $curX;
    $pdf->MultiCell($availableW, 6, utf8ToCp1252($value), 0, 'L');
}

/**
 * Draw a section title (H2).
 * @param array{int, int, int} $color
 */
function drawSectionTitle(SSTPDF $pdf, string $title, array $color): void
{
    $pdf->Ln(6);
    $pdf->SetFont('DejaVu', 'B', 12);
    $pdf->SetTextColor($color[0], $color[1], $color[2]);
    $pdf->Cell(0, 7, utf8ToCp1252($title), 0, 1);
    $y = $pdf->GetY();
    $pdf->SetDrawColor(204, 204, 204);
    $pageW = $pdf->GetPageWidth();
    $pdf->Line($pdf->getLeftMargin(), $y, $pageW - $pdf->getRightMargin(), $y);
    $pdf->Ln(3);
}

/** Draw a horizontal rule. */
function drawHR(SSTPDF $pdf): void
{
    $pdf->Ln(4);
    $pdf->SetDrawColor(204, 204, 204);
    $pageW = $pdf->GetPageWidth();
    $pdf->Line($pdf->getLeftMargin(), $pdf->GetY(), $pageW - $pdf->getRightMargin(), $pdf->GetY());
    $pdf->Ln(4);
}

/**
 * Embed an image attachment via data:// URI (no temp file).
 *
 * Audit #82 — was `array $report` (loosely `array<string, mixed>`), reading
 * $report['attachment_blob']/['attachment_mime']. report_print.php passed
 * $report->toArray() here — but ReportData::toArray() never includes
 * attachment_blob (the blob is intentionally excluded from findById() for
 * performance and fetched separately via getAttachmentBlob(), specifically
 * for this print page). The blob WAS correctly fetched into $attachmentBlob
 * in report_print.php, and even used to pick the caption text ("image
 * embarquée ci-dessous" vs "jointe au signalement") — but never actually
 * passed to this function, so no image was ever embedded even when the
 * caption said it would be. Explicit typed params instead of a loose array
 * make this kind of "the caller has the value but never routes it in"
 * mismatch impossible to reintroduce silently.
 *
 * @param array{int, int, int} $blueDark
 */
function drawEmbeddedImage(SSTPDF $pdf, ?string $attachmentMime, ?string $attachmentBlob, array $blueDark): void
{
    $attachmentMime ??= '';
    if (empty($attachmentBlob) || $attachmentMime === ''
        || !in_array($attachmentMime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
        return;
    }
    try {
        $typeStr = match ($attachmentMime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', default => 'gif',
        };
        $dataUri = 'data://' . $attachmentMime . ';base64,' . base64_encode($attachmentBlob);
        $imageInfo = @getimagesize($dataUri);
        $pageW = $pdf->GetPageWidth();
        $pageWidth = $pageW - $pdf->getLeftMargin() - $pdf->getRightMargin();
        $maxImgH = 120.0;
        if ($imageInfo !== false) {
            $ratio = (float) $imageInfo[1] / (float) $imageInfo[0];
            $displayWidth = min($pageWidth, 180.0);
            $displayHeight = $displayWidth * $ratio;
            if ($displayHeight > $maxImgH) {
                $displayHeight = $maxImgH;
                $displayWidth = $displayHeight / $ratio;
            }
        } else {
            $displayWidth = min($pageWidth, 180.0);
            $displayHeight = 80.0;
        }
        $curY = $pdf->GetY();
        $pageH = $pdf->GetPageHeight();
        if ($curY + $displayHeight + 6 > $pageH - 22) {
            $pdf->AddPage();
        }
        drawSectionTitle($pdf, 'Image jointe', $blueDark);
        $x = $pdf->getLeftMargin();
        $y = $pdf->GetY();
        $pdf->SetFillColor(248, 248, 248);
        $pdf->Rect($x, $y, $displayWidth + 4, $displayHeight + 4, 'F');
        $pdf->Image($dataUri, $x + 2, $y + 2, $displayWidth, $displayHeight, $typeStr);
        $pdf->SetY($y + $displayHeight + 8);
    } catch (Throwable) {
        // @silent-ok: if image embedding fails, skip — don't break PDF generation
        // over one attachment thumbnail.
    }
}

/**
 * Draw response history table with auto page-break and repeated headers.
 * @param list<array<string, string|int|null>> $responses
 * @param array{int, int, int} $blueDark
 */
function drawResponseTable(SSTPDF $pdf, array $responses, array $blueDark): void
{
    if (empty($responses)) {
        return;
    }
    drawHR($pdf);
    drawSectionTitle($pdf, 'Réponses (' . count($responses) . ')', $blueDark);
    $colWidths = [30, 35, 25, 70]; // Date, Répondant, État, Réponse
    $headers = ['Date', 'Répondant', 'Nouvel état', 'Réponse'];
    $headerH = 7;
    $rowH = 7;
    $drawHeader = function () use ($pdf, $colWidths, $headers, $headerH): void {
        $pdf->SetFont('DejaVu', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetDrawColor(221, 221, 221);
        $pdf->SetTextColor(34, 34, 34);
        foreach ($headers as $i => $header) {
            $pdf->Cell($colWidths[$i], $headerH, utf8ToCp1252($header), 1, 0, 'L', true);
        }
        $pdf->Ln();
    };
    $drawHeader();
    $pdf->SetFont('DejaVu', '', 8);
    foreach ($responses as $resp) {
        $createdResp = $resp['created_at'] ?? '';
        $prenomResp = $resp['prenom'] ?? '';
        $nomResp = $resp['nom'] ?? '';
        $nouvelEtat = $resp['nouvel_etat'] ?? '';
        $reponseResp = $resp['reponse'] ?? '';
        $etatResp = $nouvelEtat !== ''
            ? (ETAT_LABELS[$nouvelEtat] ?? $nouvelEtat) : '—';
        $row = [
            new FormattingService()->formatDateTimeFR($createdResp),
            $prenomResp . ' ' . $nomResp,
            $etatResp,
            $reponseResp,
        ];
        // Calculate row height based on response column (column 3)
        $responseText = utf8ToCp1252((string) $row[3]);
        $responseColW = $colWidths[3] - 2;
        $maxLines = 1;
        $testLine = '';
        foreach (explode(' ', $responseText) as $word) {
            $testLine .= ($testLine !== '' ? ' ' : '') . $word;
            $testWidth = $pdf->GetStringWidth($testLine);
            if ($testWidth > $responseColW) {
                $maxLines++;
                $testLine = $word;
            }
        }
        $currentRowH = max($maxLines * 5 + 2, $rowH);
        $curY = $pdf->GetY();
        $pageH = $pdf->GetPageHeight();
        if ($curY + $currentRowH > $pageH - 25) {
            $pdf->AddPage();
            $drawHeader();
            $pdf->SetFont('DejaVu', '', 8);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->SetFillColor(255, 255, 255);
        for ($i = 0; $i < 4; $i++) {
            $pdf->Rect($x + array_sum(array_slice($colWidths, 0, $i)), $y, $colWidths[$i], $currentRowH, 'DF');
        }
        for ($i = 0; $i < 4; $i++) {
            $cellX = $x + array_sum(array_slice($colWidths, 0, $i));
            $pdf->SetXY($cellX + 1, $y + 1);
            $cellText = utf8ToCp1252((string) $row[$i]);
            if ($i === 3) {
                $pdf->MultiCell($colWidths[$i] - 2, 5, $cellText, 0, 'L');
            } else {
                $pdf->Cell($colWidths[$i] - 2, 5, $cellText, 0, 0, 'L');
            }
        }
        $pdf->SetY($y + $currentRowH);
    }
}
