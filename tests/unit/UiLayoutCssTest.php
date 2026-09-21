<?php

/**
 * UI layout CSS — targeted regression tests.
 *
 * Two contracts are pinned here, both expressed purely in public/css/style.css
 * (the CSS is the source of truth — no JS, no inline style):
 *
 *   1. The report-card transmission help is a CSS-only tooltip: role="tooltip"
 *      + aria-describedby in the markup (see PageRenderingTest), absolutely
 *      positioned here so it sits out of the flex flow and never stretches the
 *      action row (Réouvrir / Transmettre stay aligned). It is revealed on
 *      hover and keyboard focus, and stays in the accessibility tree.
 *
 *   2. The report_list « Filtrer » button reuses the `.align-self-end` utility
 *      already used by statistics/synthesis, so it lines up with the bottom of
 *      the labelled controls instead of floating mid-row.
 */

use PHPUnit\Framework\TestCase;

class UiLayoutCssTest extends TestCase
{
    private static string $css = '';

    public static function setUpBeforeClass(): void
    {
        $path = __DIR__ . '/../../public/css/style.css';
        $css = file_get_contents($path);
        self::$css = is_string($css) ? $css : '';
    }

    /**
     * Extract a top-level rule body by exact, line-anchored selector. Anchoring
     * avoids matching a `.tooltip` that only appears as the tail of a compound
     * reveal selector (`.report-transmit:focus-within .tooltip`), which would
     * otherwise return the reveal body instead of the base rule.
     */
    private function ruleBody(string $selector): string
    {
        $pattern = '/^' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m';
        if (preg_match($pattern, self::$css, $matches) !== 1) {
            return '';
        }
        return (string) $matches[1];
    }

    public function testTooltipBaseIsAbsolutelyPositionedOutOfFlow(): void
    {
        $body = $this->ruleBody('.tooltip');

        $this->assertNotSame('', $body, 'La règle .tooltip doit exister.');
        $this->assertStringContainsString(
            'position: absolute',
            $body,
            'Le tooltip doit sortir du flux flex (position: absolute).'
        );
        $this->assertStringContainsString(
            'clip: rect(0, 0, 0, 0)',
            $body,
            'Le tooltip doit rester dans l\'arbre d\'accessibilité (masqué visuellement, pas display:none).'
        );
        $this->assertStringNotContainsString(
            'display: none',
            $body,
            'Le tooltip ne doit pas être retiré de l\'arbre d\'accessibilité.'
        );
    }

    public function testTooltipRevealsOnHoverAndKeyboardFocus(): void
    {
        $this->assertStringContainsString(
            '.report-transmit:hover .tooltip',
            self::$css,
            'Le tooltip de transmission doit se révéler au survol.'
        );
        $this->assertStringContainsString(
            '.report-transmit:focus-within .tooltip',
            self::$css,
            'Le tooltip de transmission doit se révéler au focus clavier.'
        );
        $this->assertStringContainsString(
            '.consent-consigne:hover .tooltip',
            self::$css,
            'Le tooltip de la case de consentement ne doit pas régresser.'
        );
        $this->assertStringContainsString(
            '.consent-consigne:focus-within .tooltip',
            self::$css,
            'Le tooltip de la case de consentement ne doit pas régresser.'
        );
    }

    public function testReportTransmitIsAPositionedAnchorWithoutInFlowHelp(): void
    {
        $body = $this->ruleBody('.report-transmit');

        $this->assertNotSame('', $body, 'La règle .report-transmit doit exister.');
        $this->assertStringContainsString(
            'position: relative',
            $body,
            'Le bloc de transmission doit ancrer le tooltip positionné en absolute.'
        );
        $this->assertStringNotContainsString(
            'report-transmit__help',
            self::$css,
            'L\'ancienne aide en flux (.report-transmit__help) doit avoir disparu.'
        );
    }

    public function testFilterButtonAlignmentUtilityExists(): void
    {
        $body = $this->ruleBody('.align-self-end');

        $this->assertNotSame('', $body, 'L\'utilitaire .align-self-end doit exister (réutilisé par report_list).');
        $this->assertStringContainsString(
            'align-self: flex-end',
            $body,
            'L\'utilitaire doit aligner le bouton sur le bas du groupe flex.'
        );
    }
}
