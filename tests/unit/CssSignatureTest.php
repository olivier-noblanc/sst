<?php

declare(strict_types=1);

/**
 * CssSignatureTest — contrat de la tranche « direction artistique ».
 *
 * La direction est portée par les surfaces métier réellement visibles en
 * production : le seuil d'accueil (`pages/home.php`, `.home-hero`) et les
 * cartes de registre générées par `renderRegistryCards()`
 * (`.registry-cards > .registry-card`). L'écran de connexion, lui, est un
 * mode dev hors production (authentification IIS) : il conserve sa feuille
 * `login.css` et n'entre pas dans ce contrat.
 *
 * `public/css/style.css` reste la source unique (aucun JS, aucun style
 * inline). Le mouvement référence exclusivement les tokens `--motion-*` et
 * la réduction est portée par l'unique media query de la section 4.
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

    public function testHomeSignatureTokensAreDeclared(): void
    {
        foreach ([
            '--home-threshold-from',
            '--home-threshold-mid',
            '--home-threshold-to',
            '--home-halo',
            '--home-halo-2',
            '--home-grid',
            '--home-on-dark',
            '--confirm-bg-from',
            '--confirm-bg-to',
            '--confirm-border',
            '--confirm-ink',
            '--confirm-seal-bg',
        ] as $token) {
            $this->assertNotSame('', self::resolveToken($token), "Token signature $token manquant dans :root.");
        }
    }

    public function testHeroPaintsLayeredThreshold(): void
    {
        $hero = self::ruleBody('.home-hero');
        $this->assertNotSame('', $hero, 'Règle .home-hero introuvable.');

        $this->assertStringContainsString('radial-gradient', $hero, 'Le seuil doit poser des halos (radial-gradient).');
        $this->assertStringContainsString('repeating-linear-gradient', $hero, 'Le seuil doit porter une trame discrète.');
        $this->assertStringContainsString('var(--home-halo)', $hero);
        $this->assertStringContainsString('var(--home-halo-2)', $hero);
        $this->assertStringContainsString('var(--home-grid)', $hero);
        $this->assertStringContainsString('var(--home-threshold-from)', $hero);
        $this->assertStringContainsString('var(--home-threshold-mid)', $hero);
        $this->assertStringContainsString('var(--home-threshold-to)', $hero);
    }

    public function testHeroCarriesSpectralTopRule(): void
    {
        $rule = self::ruleBody('.home-hero::before');
        $this->assertNotSame('', $rule, 'Règle .home-hero::before introuvable.');
        $this->assertStringContainsString('position: absolute', $rule);
        $this->assertStringContainsString('height: 5px', $rule);
        $this->assertStringContainsString('linear-gradient(90deg', $rule);
        $this->assertStringContainsString('var(--ui-accent-2)', $rule);
        $this->assertStringContainsString('var(--role-chsct)', $rule);
    }

    public function testHeroPrimaryKpiIsEmphasised(): void
    {
        $tile = self::ruleBody('.home-hero__stats > .home-stat:first-child');
        $this->assertNotSame('', $tile, 'Le premier KPI du seuil doit porter une règle dédiée.');
        $this->assertStringContainsString('border-color:', $tile);
        $this->assertStringContainsString('box-shadow:', $tile);

        $value = self::ruleBody('.home-hero__stats > .home-stat:first-child .home-stat__value');
        $this->assertNotSame('', $value, 'Règle du compteur principal introuvable.');
        $this->assertStringContainsString('font-size: clamp(', $value, 'Le compteur principal doit dominer le seuil.');
    }

    public function testRegistryCardsCarrySpectralRuleAndEmphasisedStat(): void
    {
        $rule = self::ruleBody('.registry-cards > .registry-card::before');
        $this->assertNotSame('', $rule, 'Repère spectral de carte manquant.');
        $this->assertStringContainsString('linear-gradient(90deg', $rule);
        $this->assertStringContainsString('var(--theme-accent', $rule);

        $stat = self::ruleBody('.registry-cards > .registry-card .registry-card__stat-value');
        $this->assertNotSame('', $stat, 'Compteur de carte mis en avant manquant.');
        $this->assertStringContainsString('font-size: clamp(', $stat);
        $this->assertStringContainsString('color: var(--theme-accent', $stat);
    }

    public function testRegistryPrimaryActionIsReadable(): void
    {
        $btn = self::ruleBody('.registry-cards > .registry-card .registry-card__btn');
        $this->assertNotSame('', $btn, "L'action principale des cartes doit porter une règle dédiée.");
        $this->assertStringContainsString('display: inline-flex', $btn);
        $this->assertStringContainsString('min-height: 44px', $btn);
        $this->assertStringContainsString('font-size: var(--font-size-md)', $btn);
        $this->assertStringContainsString('font-weight: 700', $btn);
    }

    public function testRegistryEntranceIsStaggeredAndTokenised(): void
    {
        $body = self::ruleBody('.registry-cards > .registry-card');
        $this->assertNotSame('', $body, "Règle d'entrée des cartes introuvable.");
        $this->assertStringContainsString('animation: registry-rise', $body);
        $this->assertStringContainsString('var(--motion-duration-medium)', $body);
        $this->assertStringContainsString('var(--motion-ease-gentle)', $body);

        $this->assertStringContainsString('@keyframes registry-rise', self::$css);
        $this->assertStringContainsString('var(--motion-distance-md)', self::$css);

        $delays = [
            ':nth-child(1)' => '60ms',
            ':nth-child(2)' => '130ms',
            ':nth-child(3)' => '200ms',
            ':nth-child(4)' => '270ms',
            ':nth-child(n+5)' => '340ms',
        ];
        foreach ($delays as $suffix => $delay) {
            $selector = '.registry-cards > .registry-card' . $suffix;
            $this->assertStringContainsString(
                'animation-delay: ' . $delay,
                self::ruleBody($selector),
                "Le décalage $delay de $selector est attendu."
            );
        }
    }

    public function testReducedMotionNeutralisesRegistryStaggeredDelays(): void
    {
        $reduced = self::mediaBlocks('(prefers-reduced-motion: reduce)');
        $this->assertNotSame('', $reduced, 'Media query prefers-reduced-motion introuvable.');
        $this->assertStringContainsString('.registry-cards > .registry-card', $reduced);
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
            // Le texte du seuil est posé à gauche, sur les arrêts profond et
            // médian du dégradé ; l'arrêt clair ne porte que halos et tuiles
            // opaques, jamais de texte nu.
            'On-dark sur seuil profond' => ['--home-on-dark', '--home-threshold-from'],
            'On-dark sur seuil médian' => ['--home-on-dark', '--home-threshold-mid'],
            'Encre de confirmation sur fond haut' => ['--confirm-ink', '--confirm-bg-from'],
            'Encre de confirmation sur fond bas' => ['--confirm-ink', '--confirm-bg-to'],
        ];

        foreach ($pairs as $label => [$fg, $bg]) {
            $ratio = self::contrastRatio(self::resolveToken($fg), self::resolveToken($bg));
            $this->assertGreaterThanOrEqual(4.5, $ratio, sprintf('%s : %.2f:1, attendu ≥ 4.5:1.', $label, $ratio));
        }
    }
}
