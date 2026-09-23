<?php

declare(strict_types=1);

/**
 * CssContrastContractTest — les replis visuels restent lisibles (WCAG AA ≥ 4.5:1).
 *
 * Finding Important de la revue Task 8 : le repli neutre `.badge` (thème de
 * registre inconnu) porte du texte blanc ; son fond doit offrir ≥ 4.5:1.
 *
 * Périmètre : uniquement les éléments réellement servis par l'application
 * (`style.css`). La page de connexion `login.css` est un mode dev hors
 * production, l'authentification applicative étant assurée par IIS : elle est
 * hors périmètre visuel.
 *
 * Le ratio est calculé depuis les tokens `:root` de style.css (source de vérité),
 * sans dépendance externe et sans couleur hexadécimale en dur dans les règles.
 */

use PHPUnit\Framework\TestCase;

final class CssContrastContractTest extends TestCase
{
    /** Seuil WCAG AA pour le texte de taille normale. */
    private const MIN_CONTRAST = 4.5;
    private const WHITE = '#ffffff';

    /** Les 10 clés de thème de registre (cf. `RegistryRepository::themeClasses`). */
    private const THEME_KEYS = ['rsst', 'rami', 'dgi', 'vert', 'violet', 'orange', 'teal', 'indigo', 'rose', 'ambre'];

    private static string $styleCss = '';

    /** @var array<string, string> */
    private static array $tokens = [];

    public static function setUpBeforeClass(): void
    {
        $style = file_get_contents(__DIR__ . '/../../public/css/style.css');
        self::$styleCss = is_string($style) ? $style : '';
        self::$tokens = self::rootTokens(self::$styleCss);
    }

    /** @return array<string, string> */
    private static function rootTokens(string $css): array
    {
        $tokens = [];
        if (preg_match('/:root\s*\{([^}]*)\}/', $css, $root) === 1
            && preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', $root[1] ?? '', $rows, PREG_SET_ORDER)
        ) {
            foreach ($rows as $row) {
                $tokens[trim($row[1])] = trim($row[2]);
            }
        }

        return $tokens;
    }

    private static function ruleBody(string $css, string $selector): string
    {
        $pattern = '/^' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m';

        return preg_match($pattern, $css, $match) === 1 ? (string) ($match[1] ?? '') : '';
    }

    private static function declaration(string $body, string $property): string
    {
        $pattern = '/' . preg_quote($property, '/') . '\s*:\s*([^;]+);/i';

        return preg_match($pattern, $body, $match) === 1 ? trim((string) ($match[1] ?? '')) : '';
    }

    /** Résout `var(--token)` de façon récursive via la table `:root`. */
    private static function resolveToken(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^var\(\s*(--[a-z0-9-]+)\s*\)$/i', $value, $match) === 1) {
            return self::resolveToken(self::$tokens[$match[1] ?? ''] ?? '');
        }

        return $value;
    }

    private static function relativeLuminance(string $hex): float
    {
        if (preg_match('/^#([0-9a-f]{6})$/i', $hex, $match) !== 1) {
            throw new InvalidArgumentException(sprintf('Couleur #rrggbb attendue, reçu « %s ».', $hex));
        }

        $digits = $match[1] ?? '';
        $channels = [];
        for ($i = 0; $i < 3; $i++) {
            $channel = (int) hexdec(substr($digits, $i * 2, 2)) / 255;
            $channels[] = $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function contrastRatio(string $foreground, string $background): float
    {
        $luminance = [self::relativeLuminance($foreground), self::relativeLuminance($background)];
        $lighter = max($luminance);
        $darker = min($luminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public function testBadgeFallbackBackgroundMeetsContrastWithWhiteText(): void
    {
        $body = self::ruleBody(self::$styleCss, '.badge');
        $this->assertNotSame('', $body, 'Règle .badge introuvable dans style.css.');
        $this->assertStringContainsString('color: white', $body, 'Le repli .badge conserve un texte blanc.');

        $declaration = self::declaration($body, 'background');
        $this->assertMatchesRegularExpression(
            '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
            $declaration,
            '.badge doit consommer un token de fond, pas une valeur littérale.'
        );

        $background = self::resolveToken($declaration);
        $ratio = self::contrastRatio(self::WHITE, $background);

        $this->assertGreaterThanOrEqual(
            self::MIN_CONTRAST,
            $ratio,
            sprintf('.badge (texte blanc sur %s) doit offrir ≥ 4.5:1, mesuré %.2f:1.', $background, $ratio)
        );
    }

    /**
     * Les 10 thèmes de registre portent du texte blanc sur trois surfaces :
     * le fond des badges (`--theme-<clé>` hérite du `color: white` de `.badge`),
     * le fond des boutons (`.btn--<clé>`, `color: white` explicite) et le fond
     * des en-têtes de synthèse (`.synthesis-th--<clé>`, `color: white` explicite).
     * Chacune doit offrir ≥ 4.5:1.
     */
    public function testEveryThemeSurfaceWithWhiteTextMeetsContrast(): void
    {
        $badgeBase = self::ruleBody(self::$styleCss, '.badge');
        $this->assertStringContainsString(
            'color: white',
            $badgeBase,
            'La base .badge porte le texte blanc hérité par les modificateurs .badge--<clé>.'
        );

        foreach (self::THEME_KEYS as $key) {
            $explicitWhite = [
                '.btn--' . $key,
                '.table-wrapper th.synthesis-th--' . $key,
            ];
            $surfaces = array_merge(['.badge--' . $key], $explicitWhite);

            foreach ($surfaces as $selector) {
                $body = self::ruleBody(self::$styleCss, $selector);
                $this->assertNotSame('', $body, sprintf('Règle manquante : %s.', $selector));

                if (in_array($selector, $explicitWhite, true)) {
                    $this->assertStringContainsString(
                        'color: white',
                        $body,
                        sprintf('%s doit porter un texte blanc.', $selector)
                    );
                }

                $declaration = self::declaration($body, 'background');
                $this->assertMatchesRegularExpression(
                    '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
                    $declaration,
                    sprintf('%s doit consommer un token de fond.', $selector)
                );

                $background = self::resolveToken($declaration);
                $ratio = self::contrastRatio(self::WHITE, $background);

                $this->assertGreaterThanOrEqual(
                    self::MIN_CONTRAST,
                    $ratio,
                    sprintf('%s (texte blanc sur %s) doit offrir ≥ 4.5:1, mesuré %.2f:1.', $selector, $background, $ratio)
                );
            }
        }
    }
}
