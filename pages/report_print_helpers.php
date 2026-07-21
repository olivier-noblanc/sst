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
    return $converted !== false ? $converted : $s;
}

/** Extended FPDF class with custom header and footer. */
class SSTPDF extends FPDF
{
    public string $headerText = '';
    public string $footerOrgName = '';

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

    #[Override]
    public function Header(): void
    {
        if ($this->headerText !== '') {
            $this->SetFont('DejaVu', '', 8);
            $this->SetTextColor(102, 102, 102);
            $this->Cell(0, 6, utf8ToCp1252($this->headerText), 0, 1, 'L');
            $this->SetDrawColor(204, 204, 204);
            $w = $this->w;
            $r = $this->rMargin;
            $this->Line($this->lMargin, $this->GetY(), $w - $r, $this->GetY());
            $this->Ln(4);
        }
    }

    #[Override]
    public function Footer(): void
    {
        $this->SetY(-18);
        $this->SetFont('DejaVu', '', 7);
        $this->SetDrawColor(204, 204, 204);
        $w = $this->w;
        $r = $this->rMargin;
        $this->Line($this->lMargin, $this->GetY(), $w - $r, $this->GetY());
        $this->Ln(2);
        $this->SetTextColor(153, 153, 153);
        $pageNo = $this->PageNo();
        $this->Cell(0, 8, utf8ToCp1252(
            'Page ' . (string) $pageNo . ' / {nb} — Généré le ' . date('d/m/Y H:i')
        ), 0, 0, 'C');
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

/** Draw a field row (label: value). */
function drawField(SSTPDF $pdf, string $label, string $value, float $labelW = 55): void
{
    $pdf->SetFont('DejaVu', 'B', 10);
    $pdf->SetTextColor(85, 85, 85);
    $pdf->Cell($labelW, 6, utf8ToCp1252($label), 0, 0);
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
    $pdf->Cell($labelW, 6, utf8ToCp1252($label), 0, 0);
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
 * @param array<string, mixed> $report
 * @param array{int, int, int} $blueDark
 */
function drawEmbeddedImage(SSTPDF $pdf, array $report, array $blueDark): void
{
    $attachmentMime = $report['attachment_mime'] ?? '';
    if (empty($report['attachment_blob']) || $attachmentMime === ''
        || !in_array($attachmentMime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
        return;
    }
    try {
        $typeStr = match ($attachmentMime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', default => 'gif',
        };
        $attachmentBlob = $report['attachment_blob'];
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
        // If image embedding fails, skip — don't break PDF generation
    }
}

/**
 * Draw response history table with auto page-break and repeated headers.
 * @param list<array<string, mixed>> $responses
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
