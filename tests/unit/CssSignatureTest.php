<?php

declare(strict_types=1);

/**
 * CssSignatureTest — contrat de la tranche « direction artistique ».
 *
 * Verrouille le moment signature de l'écran de connexion (fond bleu nuit,
 * carte à règle spectrale, entrée échelonnée) et le traitement des messages
 * de confirmation (barre d'accent sémantique, bannière tokenisée).
 *
 * `public/css/style.css` reste la source unique (aucun JS, aucun style inline).
 * Le mouvement référence exclusivement les tokens `--motion-*` et la réduction
 * est portée par l'unique media query de la section 4.
 */

use PHPUnit\Framework\TestCase;

final class CssSignatureTest extends TestCase
{
    private static string $css = '';

    /** @var array<string, string> */
    private static array $tokens = [];

    public static function setUpBeforeClass(): void
    {
        $css = file_get_contents(__DIR__ . '/../../public/css/style.css');
        self::$css = is_string($css) ? $css : '';

        if (preg_match('/:root\s*\{([^}]*)\}/', self::$css, $root) === 1
            && preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', $root[1] ?? '', $rows, PREG_SET_ORDER)
        ) {
            foreach ($rows as $row) {
                self::$tokens[trim($row[1])] = trim($row[2]);
            }
        }
    }

    private static function ruleBody(string $selector): string
    {
        $pattern = '/^' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m';

        return preg_match($pattern, self::$css, $match) === 1 ? (string) ($match[1] ?? '') : '';
    }

    /** Corps de tous les blocs `@media <query>` (équilibrage d'accolades). */
    private static function mediaBlocks(string $query): string
    {
        $needle = '@media ' . $query;
        $result = '';
        $offset = 0;
        $len = strlen(self::$css);
        while (($start = strpos(self::$css, $needle, $offset)) !== false) {
            $open = strpos(self::$css, '{', $start);
            if ($open === false) {
                break;
            }
            $depth = 0;
            for ($i = $open; $i < $len; $i++) {
                $ch = self::$css[$i];
                if ($ch === '{') {
                    $depth++;
                } elseif ($ch === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $result .= substr(self::$css, $open + 1, $i - $open - 1) . "\n";
                        $offset = $i + 1;
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Corps de la règle dont la liste de sélecteurs contient `$selector`
     * (couvre les règles groupées comme `.alert--success, .alert--created`).
     */
    private static function ruleBodyFor(string $selector): string
    {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', self::$css);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $selectors = array_map('trim', explode(',', (string) $match[1]));
            if (in_array($selector, $selectors, true)) {
                return (string) $match[2];
            }
        }

        return '';
    }

    private static function resolveToken(string $name): string
    {
        return self::$tokens[$name] ?? '';
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

    private static function contrastRatio(string $a, string $b): float
    {
        $la = self::relativeLuminance($a);
        $lb = self::relativeLuminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public function testSignatureTokensAreDeclared(): void
    {
        foreach ([
            '--signature-bg-from',
            '--signature-bg-to',
            '--signature-halo',
            '--signature-halo-2',
            '--signature-grid',
            '--signature-on-dark',
            '--signature-card-shadow',
            '--role-agent-deep',
            '--role-superviseur-deep',
            '--role-chsct-deep',
            '--confirm-bg-from',
            '--confirm-bg-to',
            '--confirm-border',
            '--confirm-ink',
            '--confirm-seal-bg',
        ] as $token) {
            $this->assertNotSame('', self::resolveToken($token), "Token signature $token manquant dans :root.");
        }
    }

    public function testLoginStagePaintsLayeredSignatureBackground(): void
    {
        $body = self::ruleBody('.login-body');
        $this->assertNotSame('', $body, 'Règle .login-body introuvable.');

        $this->assertStringContainsString('radial-gradient', $body, 'Le fond doit poser des halos (radial-gradient).');
        $this->assertStringContainsString('repeating-linear-gradient', $body, 'Le fond doit porter une trame discrète.');
        $this->assertStringContainsString('var(--signature-halo)', $body);
        $this->assertStringContainsString('var(--signature-halo-2)', $body);
        $this->assertStringContainsString('var(--signature-grid)', $body);
        $this->assertStringContainsString('var(--signature-bg-from)', $body);
        $this->assertStringContainsString('var(--signature-bg-to)', $body);
        $this->assertStringContainsString('var(--signature-on-dark)', $body);
    }

    public function testLoginCardCarriesSpectralTopRuleAndElevation(): void
    {
        $card = self::ruleBody('.login-card');
        $this->assertNotSame('', $card, 'Règle .login-card introuvable.');
        $this->assertStringContainsString('var(--signature-card-shadow)', $card);
        $this->assertStringContainsString('background: var(--surface)', $card);

        $rule = self::ruleBody('.login-card::before');
        $this->assertNotSame('', $rule, 'Règle .login-card::before introuvable.');
        $this->assertStringContainsString('position: absolute', $rule);
        $this->assertStringContainsString('height: 6px', $rule);
        $this->assertStringContainsString('linear-gradient(90deg', $rule);
        $this->assertStringContainsString('var(--color-primary)', $rule);
        $this->assertStringContainsString('var(--role-chsct)', $rule);
    }

    public function testLoginRoleButtonsConsumeRoleAndDeepTokens(): void
    {
        $roles = [
            'superviseur' => 'var(--role-superviseur-deep)',
            'agent' => 'var(--role-agent-deep)',
            'chsct' => 'var(--role-chsct-deep)',
        ];

        foreach ($roles as $role => $deepToken) {
            $body = self::ruleBody('.login-btn--' . $role);
            $this->assertNotSame('', $body, "Règle .login-btn--$role introuvable.");
            $this->assertStringContainsString('linear-gradient', $body);
            $this->assertStringContainsString("var(--role-$role)", $body);
            $this->assertStringContainsString($deepToken, $body);
        }
    }

    public function testLoginEntranceIsStaggeredAndTokenised(): void
    {
        $card = self::ruleBody('.login-card');
        $this->assertStringContainsString('animation: signature-rise', $card);
        $this->assertStringContainsString('var(--motion-duration-medium)', $card);
        $this->assertStringContainsString('var(--motion-ease-gentle)', $card);

        $this->assertStringContainsString('animation: signature-rise', self::ruleBody('.login-btn-wrapper'));
        $this->assertStringContainsString('animation-delay: 90ms', self::ruleBody('.login-btn-wrapper:nth-child(1)'));
        $this->assertStringContainsString('animation-delay: 170ms', self::ruleBody('.login-btn-wrapper:nth-child(2)'));
        $this->assertStringContainsString('animation-delay: 250ms', self::ruleBody('.login-btn-wrapper:nth-child(3)'));

        // La trame se définit par des tokens de mouvement, jamais en dur.
        $this->assertStringContainsString(
            'var(--motion-distance-md)',
            self::$css,
            'Les amplitudes de la couche signature doivent consommer --motion-distance-md.'
        );
    }

    public function testReducedMotionNeutralisesStaggeredDelays(): void
    {
        $reduced = self::mediaBlocks('(prefers-reduced-motion: reduce)');
        $this->assertNotSame('', $reduced, 'Media query prefers-reduced-motion introuvable.');
        $this->assertStringContainsString('.login-btn-wrapper', $reduced);
        $this->assertStringContainsString('animation-delay: 0s !important', $reduced);
    }

    public function testAlertsCarrySemanticLeftAccent(): void
    {
        $base = self::ruleBody('.alert');
        $this->assertNotSame('', $base, 'Règle .alert introuvable.');
        $this->assertStringContainsString('border-left: 5px solid transparent', $base);

        $variants = [
            '.alert--success' => 'var(--color-success-text)',
            '.alert--created' => 'var(--color-success-text)',
            '.alert--error' => 'var(--color-danger-text)',
            '.alert--danger' => 'var(--color-danger-text)',
            '.alert--warning' => 'var(--color-warning-text)',
            '.alert--info' => 'var(--color-info-text)',
        ];

        foreach ($variants as $selector => $token) {
            $body = self::ruleBodyFor($selector);
            $this->assertNotSame('', $body, "Règle $selector introuvable.");
            $this->assertStringContainsString("border-left-color: $token", $body, "$selector doit porter l'accent sémantique.");
        }
    }

    public function testConfirmationBannerConsumesSignatureTokens(): void
    {
        $banner = self::ruleBody('.confirmation-banner');
        $this->assertNotSame('', $banner, 'Règle .confirmation-banner introuvable.');
        $this->assertStringContainsString('linear-gradient(135deg, var(--confirm-bg-from)', $banner);
        $this->assertStringContainsString('var(--confirm-bg-to)', $banner);
        $this->assertStringContainsString('border: 1px solid var(--confirm-border)', $banner);
        $this->assertStringContainsString('border-left-width: 5px', $banner);

        $this->assertStringContainsString('background: var(--confirm-seal-bg)', self::ruleBody('.confirmation-banner__icon'));
        $this->assertStringContainsString('color: var(--confirm-ink)', self::ruleBody('.confirmation-banner__title'));
        $this->assertStringContainsString('color: var(--confirm-ink)', self::ruleBody('.confirmation-banner__text'));
    }

    public function testSignatureTextMeetsAaContrast(): void
    {
        $pairs = [
            'On-dark sur fond signature haut' => ['--signature-on-dark', '--signature-bg-from'],
            'On-dark sur fond signature bas' => ['--signature-on-dark', '--signature-bg-to'],
            'Encre de confirmation sur fond haut' => ['--confirm-ink', '--confirm-bg-from'],
            'Encre de confirmation sur fond bas' => ['--confirm-ink', '--confirm-bg-to'],
        ];

        foreach ($pairs as $label => [$fg, $bg]) {
            $ratio = self::contrastRatio(self::resolveToken($fg), self::resolveToken($bg));
            $this->assertGreaterThanOrEqual(4.5, $ratio, sprintf('%s : %.2f:1, attendu ≥ 4.5:1.', $label, $ratio));
        }

        foreach (['--role-agent', '--role-superviseur', '--role-chsct', '--color-primary', '--ui-accent'] as $token) {
            $ratio = self::contrastRatio('#ffffff', self::resolveToken($token));
            $this->assertGreaterThanOrEqual(
                4.5,
                $ratio,
                sprintf('Texte blanc sur %s : %.2f:1, attendu ≥ 4.5:1.', $token, $ratio)
            );
        }
    }
}
