<?php
/**
 * Test script for FPDF PDF generation — Application SST DREETS BFC
 *
 * Run this on your PHP server to verify the FPDF integration works.
 * Usage: php test_fpdf.php
 *
 * This script creates a test PDF with French accented characters
 * to validate that everything renders correctly.
 */

// --- Check prerequisites ---
$fpdfPath = __DIR__ . '/src/lib/fpdf/fpdf.php';
$fontDir = __DIR__ . '/src/lib/fpdf/font/';

echo "=== FPDF Integration Test ===\n\n";

// 1. Check FPDF file
if (file_exists($fpdfPath)) {
    echo "[OK] FPDF found: $fpdfPath\n";
} else {
    echo "[FAIL] FPDF not found: $fpdfPath\n";
    exit(1);
}

// 2. Check font files
$requiredFonts = ['DejaVuSans.json', 'DejaVuSans.z', 'DejaVuSans-Bold.json', 'DejaVuSans-Bold.z'];
$allFontsOk = true;
foreach ($requiredFonts as $font) {
    $path = $fontDir . $font;
    if (file_exists($path)) {
        $size = filesize($path);
        echo "[OK] Font file: $font ($size bytes)\n";
    } else {
        echo "[FAIL] Font file missing: $font\n";
        $allFontsOk = false;
    }
}

if (!$allFontsOk) {
    echo "\nSome font files are missing. Run makefont to generate them.\n";
    exit(1);
}

// 3. Load FPDF
require_once $fpdfPath;

// 4. UTF-8 to cp1252 conversion function
function utf8ToCp1252(?string $s): string {
    if ($s === null || $s === '') return '';
    $converted = mb_convert_encoding($s, 'cp1252', 'UTF-8');
    return $converted !== false ? $converted : $s;
}

// 5. Test class
class TestPDF extends FPDF {
    protected function Header(): void {
        $this->SetFont('DejaVu', '', 8);
        $this->SetTextColor(102, 102, 102);
        $this->Cell(0, 6, 'Application SST - DREETS BFC - Test PDF', 0, 1, 'L');
        $this->SetDrawColor(204, 204, 204);
        $this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
        $this->Ln(4);
    }

    protected function Footer(): void {
        $this->SetY(-18);
        $this->SetFont('DejaVu', '', 7);
        $this->SetDrawColor(204, 204, 204);
        $this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
        $this->Ln(2);
        $this->SetTextColor(153, 153, 153);
        $this->Cell(0, 8, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
    }
}

// 6. Create test PDF
try {
    $pdf = new TestPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 22);
    $pdf->SetMargins(15, 22, 15);

    // Add Unicode TrueType font
    $pdf->AddFont('DejaVu', '', 'DejaVuSans.json', $fontDir);
    $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.json', $fontDir);

    $pdf->AddPage();

    // Title
    $pdf->SetFont('DejaVu', 'B', 16);
    $pdf->SetTextColor(26, 58, 92);
    $pdf->Cell(0, 10, utf8ToCp1252('Test PDF — Caractères français'), 0, 1);
    $pdf->Ln(4);

    // Test French accents
    $pdf->SetFont('DejaVu', '', 11);
    $pdf->SetTextColor(34, 34, 34);

    $testStrings = [
        'Accents aigus : é É',
        'Accents graves : è È à À ù Ù',
        'Accents circonflexes : ê Ê î Î ô Ô û Û',
        'Trémas : ë Ë ï Ï ü Ü ÿ Ÿ',
        'Cédille : ç Ç',
        'Ligatures : œ Œ æ Æ',
        'Symboles : € °',
        'Guillemets : « »',
        'Phrase complète : L\'élève a rédigé un résumé sur la forêt.',
        'Majuscules accentuées : À É È Ù Ç Œ',
    ];

    foreach ($testStrings as $str) {
        $pdf->Cell(0, 7, utf8ToCp1252($str), 0, 1);
    }

    // Test badges
    $pdf->Ln(8);
    $pdf->SetFont('DejaVu', 'B', 12);
    $pdf->SetTextColor(26, 58, 92);
    $pdf->Cell(0, 8, utf8ToCp1252('Test des badges'), 0, 1);
    $pdf->Ln(2);

    $badges = [
        ['RSST', [46, 92, 138]],
        ['RAMI', [108, 108, 108]],
        ['DGI', [178, 34, 34]],
        ['Nouveau', [46, 92, 138]],
        ['En cours', [230, 126, 34]],
        ['Traité', [39, 174, 96]],
        ['Abandonné', [149, 165, 166]],
        ['Confidentiel', [107, 114, 128]],
    ];

    foreach ($badges as $badge) {
        $pdf->SetFont('DejaVu', 'B', 8);
        $pdf->SetFillColor($badge[1][0], $badge[1][1], $badge[1][2]);
        $pdf->SetTextColor(255, 255, 255);
        $textW = $pdf->GetStringWidth(utf8ToCp1252($badge[0])) + 6;
        $pdf->Cell($textW, 6, utf8ToCp1252($badge[0]), 0, 0, 'C', true);
        $pdf->SetTextColor(34, 34, 34);
        $pdf->SetX($pdf->GetX() + 4);
    }
    $pdf->Ln(12);

    // Test multiline
    $pdf->SetFont('DejaVu', 'B', 12);
    $pdf->SetTextColor(26, 58, 92);
    $pdf->Cell(0, 8, utf8ToCp1252('Test multiligne'), 0, 1);
    $pdf->Ln(2);

    $pdf->SetFont('DejaVu', '', 10);
    $longText = 'Ceci est un texte long pour tester le retour à la ligne automatique '
        . 'dans FPDF. Il contient des accents français (é, è, à, ù) et des caractères '
        . 'spéciaux comme l\'Euro (€), la cédille (ç) et les ligatures (œ, æ). '
        . 'Le texte doit s\'afficher correctement sur plusieurs lignes sans troncature '
        . 'ni erreur d\'encodage.';
    $pdf->MultiCell(0, 6, utf8ToCp1252($longText), 0, 'L');

    // Test table
    $pdf->Ln(8);
    $pdf->SetFont('DejaVu', 'B', 12);
    $pdf->SetTextColor(26, 58, 92);
    $pdf->Cell(0, 8, utf8ToCp1252('Test tableau'), 0, 1);
    $pdf->Ln(2);

    $colWidths = [40, 40, 80];
    $headers = ['Date', 'Répondant', 'Réponse'];

    $pdf->SetFont('DejaVu', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetDrawColor(221, 221, 221);
    foreach ($headers as $i => $header) {
        $pdf->Cell($colWidths[$i], 7, utf8ToCp1252($header), 1, 0, 'L', true);
    }
    $pdf->Ln();

    $pdf->SetFont('DejaVu', '', 9);
    $rows = [
        ['25/01/2025 à 14:30', 'Dupont Marie', 'Problème résolu. Mesure corrective mise en place.'],
        ['26/01/2025 à 09:15', 'Martin Jean', 'En cours d\'analyse. Réunion prévue la semaine prochaine.'],
    ];
    foreach ($rows as $row) {
        foreach ($row as $i => $cell) {
            $pdf->Cell($colWidths[$i], 7, utf8ToCp1252($cell), 1, 0, 'L');
        }
        $pdf->Ln();
    }

    // Output
    $outputPath = __DIR__ . '/download/test_fpdf.pdf';
    $dir = dirname($outputPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $pdf->Output('F', $outputPath);

    echo "\n[OK] Test PDF generated successfully: $outputPath\n";
    echo "[OK] File size: " . filesize($outputPath) . " bytes\n";
    echo "\nOpen the PDF and verify that all French accented characters render correctly.\n";

} catch (Exception $e) {
    echo "[FAIL] Error generating PDF: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
