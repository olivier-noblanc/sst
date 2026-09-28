<?php

declare(strict_types=1);

/**
 * CssRegistryCardPremiumTest — contrat visuel premium des cartes de registre.
 *
 * `public/css/style.css` est la source de vérité. La carte porte une classe de
 * thème (`registry-card--<clé>`) ; le CSS résout les trois tokens de la clé
 * (`--theme-<clé>`, `-tint`, `-ink`) sans couleur codée dans le HTML.
 */

use PHPUnit\Framework\TestCase;

final class CssRegistryCardPremiumTest extends TestCase
{
    private static string $css = '';

    /** @var list<string> */
    private const THEME_KEYS = ['rsst', 'rami', 'dgi', 'vert', 'violet', 'orange', 'teal', 'indigo', 'rose', 'ambre'];

    public static function setUpBeforeClass(): void
    {
        $css = file_get_contents(__DIR__ . '/../../public/css/style.css');
        self::$css = is_string($css) ? $css : '';
    }

    private function ruleBody(string $selector): string
    {
        $pattern = '/^' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m';

        return preg_match($pattern, self::$css, $m) === 1 ? (string) $m[1] : '';
    }

    public function testEveryRegistryCardUsesTintBackgroundAndAccentBorder(): void
    {
        foreach (self::THEME_KEYS as $key) {
            $body = $this->ruleBody('.registry-card--' . $key);
            $this->assertNotSame('', $body, "Règle .registry-card--$key manquante.");
            $this->assertStringContainsString(
                'background: var(--theme-' . $key . '-tint)',
                $body,
                ".registry-card--$key doit utiliser --theme-$key-tint en fond."
            );
            $this->assertStringContainsString(
                'border-left-color: var(--theme-' . $key . ')',
                $body,
                ".registry-card--$key doit utiliser --theme-$key en accent."
            );
            $this->assertStringContainsString(
                '--word-cloud-ink: var(--theme-' . $key . '-ink)',
                $body,
                ".registry-card--$key doit exposer --word-cloud-ink = --theme-$key-ink."
            );
        }
    }

    public function testIconIsAPillOnThemeAccent(): void
    {
        $body = $this->ruleBody('.registry-card__icon');
        $this->assertNotSame('', $body, 'Règle .registry-card__icon manquante.');
        $this->assertStringContainsString('border-radius: var(--radius-pill)', $body);
        $this->assertStringContainsString('background: var(--theme-accent', $body);
    }

    public function testStatValueDominatesDescription(): void
    {
        $value = $this->ruleBody('.registry-card__stat-value');
        $this->assertStringContainsString('font-size: var(--stat-size)', $value);
        $this->assertStringContainsString('font-weight: 700', $value);

        $desc = $this->ruleBody('.registry-card__desc');
        $this->assertStringContainsString('font-size: var(--font-size-sm)', $desc);
    }

    public function testUnknownThemeFallsBackToNeutral(): void
    {
        $body = $this->ruleBody('.registry-card');
        $this->assertStringContainsString('background: var(--theme-tint, var(--surface))', $body);
        $this->assertStringContainsString('border-left-color: var(--theme-accent, var(--border))', $body);
    }

    public function testDgiHasDecorativePriorityMarker(): void
    {
        $body = $this->ruleBody('.registry-card--dgi::after');
        $this->assertNotSame('', $body, 'Marqueur de priorité DGI manquant.');
        $this->assertStringContainsString('background: var(--theme-dgi)', $body);
    }
}