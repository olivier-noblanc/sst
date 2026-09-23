<?php

declare(strict_types=1);

/**
 * CssContrastContractTest — les replis visuels restent lisibles (WCAG AA ≥ 4.5:1).
 *
 * Findings Important de la revue Tasks 8/9 :
 *   1. `.login-btn-desc` (login.css) s'affiche sur la carte blanche de connexion :
 *      sa couleur doit offrir ≥ 4.5:1 sur fond blanc.
 *   2. Le repli neutre `.badge` (thème de registre inconnu) porte du texte blanc :
 *      son fond doit offrir ≥ 4.5:1 sur blanc.
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

    private static string $styleCss = '';
    private static string $loginCss = '';

    /** @var array<string, string> */
    private static array $tokens = [];

    public static function setUpBeforeClass(): void
    {
        $style = file_get_contents(__DIR__ . '/../../public/css/style.css');
        $login = file_get_contents(__DIR__ . '/../../public/css/login.css');
        self::$styleCss = is_string($style) ? $style : '';
        self::$loginCss = is_string($login) ? $login : '';
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

    public function testLoginBtnDescColourMeetsContrastAAOnWhite(): void
    {
        $body = self::ruleBody(self::$loginCss, '.login-btn-desc');
        $this->assertNotSame('', $body, 'Règle .login-btn-desc introuvable dans login.css.');

        $declaration = self::declaration($body, 'color');
        $this->assertMatchesRegularExpression(
            '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
            $declaration,
            '.login-btn-desc doit consommer un token de couleur, pas une valeur littérale.'
        );

        $colour = self::resolveToken($declaration);
        $ratio = self::contrastRatio($colour, self::WHITE);

        $this->assertGreaterThanOrEqual(
            self::MIN_CONTRAST,
            $ratio,
            sprintf('.login-btn-desc (%s) doit offrir ≥ 4.5:1 sur blanc, mesuré %.2f:1.', $colour, $ratio)
        );
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
}
