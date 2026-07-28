<?php
/**
 * Report Print Helpers Tests — Application SST DREETS BFC
 *
 * Regression test for a real PDF layout bug: drawField()/drawMultiField()
 * used a fixed label column width (55mm) that FPDF's Cell() doesn't
 * enforce — a label wider than that just overflows into the value column
 * instead of being wrapped or clipped. This happened out of the box with
 * the default CHSCT role label ("Transmission aux Membre FS/CSAs" — the
 * role label is admin-configurable, see ConfigService::getRoleLabel()),
 * not just with an unusually long custom one.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/lib/fpdf/fpdf.php';
require_once __DIR__ . '/../../pages/report_print_helpers.php';

class ReportPrintHelpersTest extends TestCase
{
    private function newPdf(): SSTPDF
    {
        $pdf = new SSTPDF();
        $pdf->AddPage();
        $fontDir = __DIR__ . '/../../src/lib/fpdf/font/';
        $pdf->AddFont('DejaVu', '', 'DejaVuSans.json', $fontDir);
        $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.json', $fontDir);
        $pdf->SetMargins(15, 15, 15);
        return $pdf;
    }

    // Calls the actual production function used by both drawField() and
    // drawMultiField() (extracted specifically so this is testable without
    // having to reverse-engineer FPDF's drawing/cursor state).
    public function testEffectiveLabelWidthExpandsForLongLabel(): void
    {
        $pdf = $this->newPdf();
        $pdf->SetFont('DejaVu', 'B', 10);

        // The real-world case that triggered this bug: the default CHSCT
        // role label ("Membre FS/CSA") already makes this label wider than
        // the old fixed 55mm column at 10pt bold DejaVu.
        $label = 'Transmission aux Membre FS/CSAs';
        $labelCp = utf8ToCp1252($label);
        $renderedWidth = $pdf->GetStringWidth($labelCp);

        $this->assertGreaterThan(
            55,
            $renderedWidth,
            'This test assumes the label is wider than the old fixed 55mm column — if this fails, the label got shorter and the regression scenario no longer applies.'
        );

        $result = effectiveLabelWidth($pdf, $labelCp, 55);

        $this->assertGreaterThanOrEqual(
            $renderedWidth,
            $result,
            'The column width returned must cover the full rendered label — otherwise Cell() draws the label past the column boundary and the value drawn right after it overlaps the label text.'
        );
    }

    public function testEffectiveLabelWidthKeepsFixedWidthForShortLabels(): void
    {
        // Regression guard the other way: a short, static label ("Objet",
        // "Lieu"...) must still use the normal 55mm column, not shrink —
        // otherwise every field's value would stop lining up in a
        // straight column, which is the whole point of a fixed label
        // width in the first place.
        $pdf = $this->newPdf();
        $pdf->SetFont('DejaVu', 'B', 10);
        $labelCp = utf8ToCp1252('Objet');
        $this->assertLessThan(55, $pdf->GetStringWidth($labelCp));

        $this->assertEquals(55.0, effectiveLabelWidth($pdf, $labelCp, 55));
    }

    public function testDrawFieldWithLongLabelDoesNotThrow(): void
    {
        // Full integration smoke test through the actual function used by
        // report_print.php, with the exact real-world label.
        $pdf = $this->newPdf();
        drawField($pdf, 'Transmission aux Membre FS/CSAs', 'Acceptée');
        $output = $pdf->Output('S');
        $this->assertIsString($output);
        $this->assertStringStartsWith('%PDF', $output);
    }

    /**
     * Regression test — Audit #82. report_print.php passed $report->toArray()
     * to drawEmbeddedImage(), but ReportData::toArray() never includes
     * attachment_blob (excluded from findById() on purpose, fetched
     * separately via getAttachmentBlob() for this exact page) — so no image
     * was ever embedded, even though the caption right above it said
     * "image embarquée ci-dessous". Fixed by passing the mime/blob as
     * explicit typed params instead of a loose array. These tests exercise
     * drawEmbeddedImage() directly with a real (tiny) PNG so a future
     * regression where the blob silently doesn't reach this function would
     * show up as "Image jointe" missing from the PDF stream, not just as
     * "didn't throw".
     */
    public function testDrawEmbeddedImageDrawsSectionWhenBlobPresent(): void
    {
        $pdf = $this->newPdf();
        // 1x1 transparent PNG, well-known minimal fixture.
        $pngBlob = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        drawEmbeddedImage($pdf, 'image/png', $pngBlob, [0, 51, 102]);
        $output = $pdf->Output('S');
        $this->assertIsString($output);
        // The section title drawn right before the image — proof the image
        // branch actually ran, not just that nothing threw.
        $this->assertStringContainsString('Image jointe', $output);
    }

    public function testDrawEmbeddedImageDoesNothingWhenBlobIsNull(): void
    {
        $pdf = $this->newPdf();
        drawEmbeddedImage($pdf, 'image/png', null, [0, 51, 102]);
        $output = $pdf->Output('S');
        $this->assertStringNotContainsString('Image jointe', $output);
    }

    public function testDrawEmbeddedImageDoesNothingForNonImageMime(): void
    {
        $pdf = $this->newPdf();
        drawEmbeddedImage($pdf, 'application/pdf', 'not-actually-an-image', [0, 51, 102]);
        $output = $pdf->Output('S');
        $this->assertStringNotContainsString('Image jointe', $output);
    }
}
