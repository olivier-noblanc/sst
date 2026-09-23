<?php

declare(strict_types=1);

/**
 * LoginCssTest — la page de connexion consomme les tokens du design system.
 *
 * `login.css` est chargé APRÈS `style.css` (pages/login.php) : les tokens
 * :root sont donc disponibles. CSP connexion = style-src 'self' (aucun inline).
 */

use PHPUnit\Framework\TestCase;

final class LoginCssTest extends TestCase
{
    private static string $loginCss = '';
    private static string $styleCss = '';

    public static function setUpBeforeClass(): void
    {
        $login = file_get_contents(__DIR__ . '/../../public/css/login.css');
        $style = file_get_contents(__DIR__ . '/../../public/css/style.css');
        self::$loginCss = is_string($login) ? $login : '';
        self::$styleCss = is_string($style) ? $style : '';
    }

    private function styleRuleBody(string $selector): string
    {
        $pattern = '/^' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m';
        if (preg_match($pattern, self::$styleCss, $m) !== 1) {
            return '';
        }
        return (string) $m[1];
    }

    public function testLoginCssHasNoHardCodedColour(): void
    {
        // Les tokens sont la source de vérité : plus aucun hex littéral.
        $this->assertSame(
            0,
            preg_match_all('/#[0-9a-fA-F]{3,8}\b/', self::$loginCss),
            'login.css ne doit plus coder de couleur hexadécimale en dur.'
        );
    }

    public function testLoginQuickButtonsAreComfortableAndTokenised(): void
    {
        $this->assertStringContainsString('var(--font-size-lg)', self::$loginCss);
        $this->assertStringContainsString('min-height: 48px', self::$loginCss);
        $this->assertStringContainsString('var(--space-3)', self::$loginCss);
    }

    public function testLoginCardConsumesTokens(): void
    {
        $card = $this->styleRuleBody('.login-card');
        $this->assertStringContainsString('border-radius: var(--border-radius-lg)', $card);
        $this->assertStringContainsString('box-shadow: var(--shadow-lg)', $card);
        $this->assertStringContainsString('padding: var(--space-6)', $card);
    }

    public function testLoginBadgeConsumesSemanticTokens(): void
    {
        $badge = $this->styleRuleBody('.login-dev-badge');
        $this->assertStringContainsString('var(--color-warning-bg)', $badge);
        $this->assertStringContainsString('var(--color-warning-text)', $badge);
    }
}