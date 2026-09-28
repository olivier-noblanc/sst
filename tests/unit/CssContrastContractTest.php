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
    /** Seuil WCAG 1.4.11 / 2.4.11 pour les éléments non textuels (bordures, focus). */
    private const MIN_NON_TEXT_CONTRAST = 3.0;
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

    /**
     * Finding de revue Task 5 : la carte de registre n'a plus un fond saturé
     * mais un tint clair ; son texte secondaire (`.registry-card__desc`,
     * `.registry-card__stat-label`) doit rester ≥ 4.5:1 sur les 10 tints — et
     * sur le repli neutre `--surface` — ce qui exclut `--grey-600`.
     */
    public function testCardSecondaryTextMeetsContrastOnThemeTints(): void
    {
        $backgrounds = ['surface' => self::resolveToken(self::$tokens['--surface'] ?? '')];
        foreach (self::THEME_KEYS as $key) {
            $backgrounds[$key] = self::resolveToken(self::$tokens['--theme-' . $key . '-tint'] ?? '');
        }

        foreach (['.registry-card__desc', '.registry-card__stat-label'] as $selector) {
            $body = self::ruleBody(self::$styleCss, $selector);
            $this->assertNotSame('', $body, sprintf('Règle %s introuvable dans style.css.', $selector));

            $declaration = self::declaration($body, 'color');
            $this->assertMatchesRegularExpression(
                '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
                $declaration,
                sprintf('%s doit consommer un token de couleur, pas une valeur littérale.', $selector)
            );

            $color = self::resolveToken($declaration);
            foreach ($backgrounds as $name => $background) {
                $this->assertNotSame('', $background, "Fond $name manquant.");
                $ratio = self::contrastRatio($color, $background);
                $this->assertGreaterThanOrEqual(
                    self::MIN_CONTRAST,
                    $ratio,
                    sprintf('%s (%s) sur %s (%s) doit offrir ≥ 4.5:1, mesuré %.2f:1.', $selector, $color, $name, $background, $ratio)
                );
            }
        }
    }

    /**
     * Finding Important I1 : les libellés `.workflow-legend__text` sont posés
     * sur `--surface-sunken` ; `--grey-600` n'y offre que ~4.10:1. Le token
     * doit être `--grey-700` (≥ 4.5:1). Couvre aussi le libellé « Non
     * poursuivi » de l'item `--muted`, qui reprend cette couleur.
     */
    public function testWorkflowLegendTextMeetsContrastOnSunkenSurface(): void
    {
        $surface = self::resolveToken(self::$tokens['--surface-sunken'] ?? '');
        $this->assertNotSame('', $surface, 'Token --surface-sunken introuvable dans :root.');

        $legendBody = self::ruleBody(self::$styleCss, '.workflow-legend');
        $this->assertNotSame('', $legendBody, 'Règle .workflow-legend introuvable dans style.css.');

        $backgroundDeclaration = self::declaration($legendBody, 'background');
        $this->assertMatchesRegularExpression(
            '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
            $backgroundDeclaration,
            '.workflow-legend doit consommer un token de fond, pas une valeur littérale.'
        );
        $this->assertSame(
            $surface,
            self::resolveToken($backgroundDeclaration),
            'La légende doit reposer sur --surface-sunken, la surface mesurée.'
        );

        $textBody = self::ruleBody(self::$styleCss, '.workflow-legend__text');
        $this->assertNotSame('', $textBody, 'Règle .workflow-legend__text introuvable dans style.css.');

        $colorDeclaration = self::declaration($textBody, 'color');
        $this->assertMatchesRegularExpression(
            '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
            $colorDeclaration,
            '.workflow-legend__text doit consommer un token de couleur, pas une valeur littérale.'
        );

        $color = self::resolveToken($colorDeclaration);
        $ratio = self::contrastRatio($color, $surface);
        $this->assertGreaterThanOrEqual(
            self::MIN_CONTRAST,
            $ratio,
            sprintf('.workflow-legend__text (%s) sur --surface-sunken (%s) doit offrir ≥ 4.5:1, mesuré %.2f:1.', $color, $surface, $ratio)
        );
    }

    /**
     * Finding Important I1 (suite) : la mise en retrait du libellé
     * « Abandonné » ne doit pas passer par une opacité — `opacity: 0.6`
     * composée avec `--grey-600` fait chuter le contraste à ~2.15:1. La
     * désaturation de l'état est portée par le badge `--state-abandonne` et
     * la couleur de l'item muted reste un token ≥ 4.5:1 sur sunken.
     */
    public function testMutedWorkflowLegendItemDoesNotFadeTextWithOpacity(): void
    {
        $mutedBody = self::ruleBody(self::$styleCss, '.workflow-legend__item--muted');
        $this->assertNotSame('', $mutedBody, 'Règle .workflow-legend__item--muted introuvable dans style.css.');

        // Seules les déclarations comptent : les commentaires CSS sont retirés
        // avant de vérifier l'absence de la propriété `opacity`.
        $declarations = (string) preg_replace('~/\*.*?\*/~s', '', $mutedBody);
        $this->assertStringNotContainsString(
            'opacity',
            $declarations,
            '.workflow-legend__item--muted ne doit pas dégrader la lisibilité via opacity.'
        );

        $surface = self::resolveToken(self::$tokens['--surface-sunken'] ?? '');
        $this->assertNotSame('', $surface, 'Token --surface-sunken introuvable dans :root.');

        $colorDeclaration = self::declaration($mutedBody, 'color');
        $this->assertMatchesRegularExpression(
            '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
            $colorDeclaration,
            '.workflow-legend__item--muted doit porter une couleur de token, pas une valeur littérale.'
        );

        $color = self::resolveToken($colorDeclaration);
        $ratio = self::contrastRatio($color, $surface);
        $this->assertGreaterThanOrEqual(
            self::MIN_CONTRAST,
            $ratio,
            sprintf('.workflow-legend__item--muted (%s) sur --surface-sunken (%s) doit offrir ≥ 4.5:1, mesuré %.2f:1.', $color, $surface, $ratio)
        );
    }

    /**
     * Les cartes de registre premium posent le texte `--theme-<clé>-ink` sur le
     * tint clair `--theme-<clé>-tint` ; les 10 paires doivent offrir ≥ 4.5:1.
     */
    public function testThemeTintInkPairsMeetContrast(): void
    {
        foreach (self::THEME_KEYS as $key) {
            $ink = self::resolveToken(self::$tokens['--theme-' . $key . '-ink'] ?? '');
            $tint = self::resolveToken(self::$tokens['--theme-' . $key . '-tint'] ?? '');

            $this->assertNotSame('', $ink, "--theme-$key-ink manquant dans :root.");
            $this->assertNotSame('', $tint, "--theme-$key-tint manquant dans :root.");

            $ratio = self::contrastRatio($ink, $tint);
            $this->assertGreaterThanOrEqual(
                self::MIN_CONTRAST,
                $ratio,
                sprintf('--theme-%s-ink (%s) sur --theme-%s-tint (%s) doit offrir ≥ 4.5:1, mesuré %.2f:1.', $key, $ink, $key, $tint, $ratio)
            );
        }
    }

    /**
     * La sidebar premium bleu nuit porte `--sidebar-text` au repos, un libellé
     * actif blanc et la barre d'accent `--sidebar-active` ; chacune de ces trois
     * couches doit offrir ≥ 4.5:1 sur `--sidebar-bg`.
     */
    public function testSidebarPairsMeetContrast(): void
    {
        $bg = self::resolveToken(self::$tokens['--sidebar-bg'] ?? '');
        $text = self::resolveToken(self::$tokens['--sidebar-text'] ?? '');
        $active = self::resolveToken(self::$tokens['--sidebar-active'] ?? '');

        $this->assertNotSame('', $bg, '--sidebar-bg manquant.');
        $this->assertGreaterThanOrEqual(
            self::MIN_CONTRAST,
            self::contrastRatio($text, $bg),
            sprintf('--sidebar-text (%s) sur --sidebar-bg (%s) doit offrir ≥ 4.5:1.', $text, $bg)
        );
        // Libellé actif rendu en blanc sur le fond bleu nuit.
        $this->assertGreaterThanOrEqual(
            self::MIN_CONTRAST,
            self::contrastRatio(self::WHITE, $bg),
            sprintf('Libellé actif (blanc) sur --sidebar-bg (%s) doit offrir ≥ 4.5:1.', $bg)
        );
        // Barre d'accent active, doublure non chromatique de l'état actif.
        $this->assertGreaterThanOrEqual(
            self::MIN_CONTRAST,
            self::contrastRatio($active, $bg),
            sprintf('--sidebar-active (%s) sur --sidebar-bg (%s) doit offrir ≥ 4.5:1.', $active, $bg)
        );
    }

    /**
     * Passe d'accessibilité AA — Finding « tokens d'état assombris » : les
     * badges d'état (`--state-*`) et de visibilité (`--visibility-*`) posent un
     * texte blanc sur un fond tokenisé. Chaque paire doit offrir ≥ 4.5:1. En
     * particulier `--state-en-cours`, `--state-traite`, `--state-abandonne`,
     * `--state-reouvert` (nouveau) et `--visibility-public` doivent être
     * assombris, et `.badge--reouvert` doit consommer son token au lieu du
     * littéral `#8B5CF6`.
     */
    public function testStateAndVisibilityBadgesMeetContrastWithWhiteText(): void
    {
        $base = self::ruleBody(self::$styleCss, '.badge');
        $this->assertStringContainsString(
            'color: white',
            $base,
            'La base .badge porte le texte blanc hérité par tous ses modificateurs.'
        );

        $selectors = [
            '.badge--nouveau',
            '.badge--en-cours',
            '.badge--traite',
            '.badge--abandonne',
            '.badge--reouvert',
            '.badge--confidential',
            '.badge--public',
        ];

        foreach ($selectors as $selector) {
            $body = self::ruleBody(self::$styleCss, $selector);
            $this->assertNotSame('', $body, sprintf('Règle manquante : %s.', $selector));

            $declaration = self::declaration($body, 'background');
            $this->assertMatchesRegularExpression(
                '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
                $declaration,
                sprintf('%s doit consommer un token de fond, pas une couleur littérale.', $selector)
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

    /**
     * Finding « bouton secondary » : `.btn--secondary` porte un texte blanc sur
     * un gris tokenisé (`--grey-500` offrait ~2.68:1). Il doit consommer un
     * token ≥ 4.5:1.
     */
    public function testSecondaryButtonMeetsContrastWithWhiteText(): void
    {
        $body = self::ruleBody(self::$styleCss, '.btn--secondary');
        $this->assertNotSame('', $body, 'Règle .btn--secondary introuvable dans style.css.');
        $this->assertStringContainsString('color: white', $body, '.btn--secondary conserve un texte blanc.');

        $declaration = self::declaration($body, 'background');
        $this->assertMatchesRegularExpression(
            '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
            $declaration,
            '.btn--secondary doit consommer un token de fond, pas une couleur littérale.'
        );

        $background = self::resolveToken($declaration);
        $ratio = self::contrastRatio(self::WHITE, $background);
        $this->assertGreaterThanOrEqual(
            self::MIN_CONTRAST,
            $ratio,
            sprintf('.btn--secondary (texte blanc sur %s) doit offrir ≥ 4.5:1, mesuré %.2f:1.', $background, $ratio)
        );
    }

    /**
     * Finding « focus ring opaque AA » : `--focus-ring-color` était
     * `rgba(0,86,163,0.4)` (~2.93:1 sur blanc). Il doit être opaque et offrir
     * ≥ 3:1 sur toutes les surfaces claires (WCAG 1.4.11 / 2.4.11).
     */
    public function testFocusRingIsOpaqueAndMeetsNonTextContrast(): void
    {
        $raw = self::$tokens['--focus-ring-color'] ?? '';
        $this->assertMatchesRegularExpression(
            '/^#[0-9a-f]{6}$/i',
            $raw,
            sprintf('--focus-ring-color doit être opaque (format #rrggbb), reçu « %s ».', $raw)
        );

        foreach (['--surface', '--surface-sunken', '--grey-100'] as $token) {
            $surface = self::resolveToken(self::$tokens[$token] ?? '');
            $this->assertNotSame('', $surface, sprintf('Token %s introuvable dans :root.', $token));

            $ratio = self::contrastRatio($raw, $surface);
            $this->assertGreaterThanOrEqual(
                self::MIN_NON_TEXT_CONTRAST,
                $ratio,
                sprintf('--focus-ring-color (%s) sur %s (%s) doit offrir ≥ 3:1, mesuré %.2f:1.', $raw, $token, $surface, $ratio)
            );
        }
    }

    /**
     * Finding Important « focus sidebar sombre » : l'anneau global
     * `--focus-ring-color` (#0056A3) n'atteint que ~2.30:1 sur le fond bleu nuit
     * `--sidebar-bg`. La navigation doit porter un focus spécifique
     * (`.sidebar__item:focus-visible`) dont l'anneau consomme un token adapté au
     * fond sombre et offre ≥ 3:1 (WCAG 1.4.11 / 2.4.11), sans casser le focus
     * global ni les états hover / active de la sidebar.
     */
    public function testSidebarFocusRingMeetsNonTextContrastOnDarkBackground(): void
    {
        $bg = self::resolveToken(self::$tokens['--sidebar-bg'] ?? '');
        $this->assertNotSame('', $bg, '--sidebar-bg manquant.');

        // Le focus global reste inchangé pour les surfaces claires.
        $global = self::ruleBody(self::$styleCss, ':focus-visible');
        $this->assertStringContainsString(
            'var(--focus-ring-color)',
            $global,
            'Le focus global :focus-visible doit conserver --focus-ring-color.'
        );

        // Les états hover / active de la navigation restent intacts.
        $this->assertStringContainsString(
            'background: var(--sidebar-hover)',
            self::ruleBody(self::$styleCss, '.sidebar__item:hover'),
            '.sidebar__item:hover doit conserver son fond --sidebar-hover.'
        );
        $this->assertStringContainsString(
            'border-left-color: var(--sidebar-active)',
            self::ruleBody(self::$styleCss, '.sidebar__item--active'),
            '.sidebar__item--active doit conserver sa barre --sidebar-active.'
        );

        $body = self::ruleBody(self::$styleCss, '.sidebar__item:focus-visible');
        $this->assertNotSame('', $body, 'Règle .sidebar__item:focus-visible introuvable dans style.css.');

        // L'anneau spécifique ne doit pas réutiliser le token global, non
        // contrasté sur le fond sombre.
        $this->assertStringNotContainsString(
            'var(--focus-ring-color)',
            $body,
            '.sidebar__item:focus-visible ne doit pas réutiliser --focus-ring-color sur le fond sombre.'
        );

        $outline = self::declaration($body, 'outline');
        $this->assertMatchesRegularExpression(
            '/var\(\s*--[a-z0-9-]+\s*\)/i',
            $outline,
            sprintf('.sidebar__item:focus-visible doit consommer un token pour son anneau, reçu « %s ».', $outline)
        );

        preg_match('/var\(\s*(--[a-z0-9-]+)\s*\)/i', $outline, $match);
        $token = $match[1] ?? '';
        $this->assertNotSame('', $token, 'Token de focus sidebar introuvable dans la déclaration outline.');

        $color = self::resolveToken('var(' . $token . ')');
        $this->assertMatchesRegularExpression(
            '/^#[0-9a-f]{6}$/i',
            $color,
            sprintf('Le token de focus sidebar (%s) doit être opaque (#rrggbb).', $token)
        );

        $ratio = self::contrastRatio($color, $bg);
        $this->assertGreaterThanOrEqual(
            self::MIN_NON_TEXT_CONTRAST,
            $ratio,
            sprintf('.sidebar__item:focus-visible (%s) sur --sidebar-bg (%s) doit offrir ≥ 3:1, mesuré %.2f:1.', $color, $bg, $ratio)
        );
    }

    /**
     * Finding « bordures perceptibles » : les bordures de cartes, de tableaux
     * et des boutons outline étaient quasi invisibles (~1.2:1). Les tokens
     * `--border` / `--card-border` doivent offrir ≥ 3:1 sur les surfaces claires
     * (fond de page `--grey-100` et `--surface`).
     */
    public function testBordersArePerceptibleAgainstLightSurfaces(): void
    {
        $surfaces = [
            '--surface' => self::resolveToken(self::$tokens['--surface'] ?? ''),
            '--grey-100' => self::resolveToken(self::$tokens['--grey-100'] ?? ''),
            '--surface-sunken' => self::resolveToken(self::$tokens['--surface-sunken'] ?? ''),
        ];

        foreach (['--border', '--card-border'] as $token) {
            $border = self::resolveToken(self::$tokens[$token] ?? '');
            $this->assertNotSame('', $border, sprintf('Token %s introuvable dans :root.', $token));

            foreach ($surfaces as $name => $surface) {
                $this->assertNotSame('', $surface, sprintf('Token %s introuvable.', $name));
                $ratio = self::contrastRatio($border, $surface);
                $this->assertGreaterThanOrEqual(
                    self::MIN_NON_TEXT_CONTRAST,
                    $ratio,
                    sprintf('%s (%s) sur %s (%s) doit offrir ≥ 3:1, mesuré %.2f:1.', $token, $border, $name, $surface, $ratio)
                );
            }
        }

        foreach (['.card', '.table-wrapper', '.btn--outline'] as $selector) {
            $body = self::ruleBody(self::$styleCss, $selector);
            $this->assertNotSame('', $body, sprintf('Règle %s introuvable dans style.css.', $selector));
            $this->assertMatchesRegularExpression(
                '/border(-color)?:\s*(1px solid )?var\(\s*--[a-z0-9-]+\s*\)/i',
                $body,
                sprintf('%s doit consommer un token de bordure perceptible.', $selector)
            );
        }
    }

    /**
     * Finding « textes gris < 14px en grey-700 » : aucun texte ne doit
     * consommer `--grey-500`/`--grey-600`, qui tombent sous 4.5:1 sur la
     * surface la plus sombre (`--surface-sunken`, `--grey-600` ≈ 4.10:1).
     *
     * Hors périmètre — la page de connexion (`login.css` / `.login-*`) est un
     * mode dev non servi en production (authentification IIS), et la vue
     * d'impression `print` n'est pas dans le périmètre de la passe.
     */
    public function testGreyTextUsesAaCompliantToken(): void
    {
        $surface = self::resolveToken(self::$tokens['--surface-sunken'] ?? '');
        $this->assertNotSame('', $surface, 'Token --surface-sunken introuvable dans :root.');

        $offenders = [];
        foreach (self::leafRules() as $selector => $body) {
            if (stripos($selector, 'login') !== false || stripos($selector, 'print') !== false) {
                continue;
            }
            if (preg_match('/color:\s*var\(\s*(--grey-(?:500|600))\b/i', $body, $match) !== 1) {
                continue;
            }

            $token = $match[1] ?? '';
            $color = self::resolveToken('var(' . $token . ')');
            $ratio = self::contrastRatio($color, $surface);
            if ($ratio < self::MIN_CONTRAST) {
                $offenders[] = sprintf('%s (%s, %.2f:1)', trim($selector), $token, $ratio);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            sprintf("Textes gris < 4.5:1 sur --surface-sunken (%s) :\n%s", $surface, implode("\n", $offenders))
        );
    }

    /**
     * Finding « plancher font xs raisonnable » : le token `--font-size-xs`
     * descendait à 0.625rem (10px). Son plancher doit rester ≥ 0.75rem (12px).
     */
    public function testFontXsHasReasonableFloor(): void
    {
        $raw = self::$tokens['--font-size-xs'] ?? '';
        $this->assertMatchesRegularExpression(
            '/clamp\(\s*([0-9.]+)rem/',
            $raw,
            sprintf('--font-size-xs doit rester fluide (clamp rem), reçu « %s ».', $raw)
        );

        preg_match('/clamp\(\s*([0-9.]+)rem/', $raw, $match);
        $min = (float) ($match[1] ?? 0);
        $this->assertGreaterThanOrEqual(
            0.75,
            $min,
            sprintf('--font-size-xs : plancher %.3frem < 0.75rem (12px).', $min)
        );
    }

    /**
     * Retour UI : les en-têtes de tableau doivent être visuellement distincts
     * des lignes en projection. Le fond d'en-tête consomme un token dédié,
     * différent du zébrage (`--grey-50`) et de la surface blanche, et plus
     * marqué (luminance relative inférieure) que le zébrage.
     */
    public function testTableHeaderSurfaceIsDistinctFromDataRows(): void
    {
        $zebraBody = self::ruleBody(self::$styleCss, 'tr:nth-child(even) td');
        $this->assertNotSame('', $zebraBody, 'Règle de zébrage introuvable.');
        $zebraBg = self::resolveToken(self::declaration($zebraBody, 'background'));
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $zebraBg, 'Le zébrage doit résoudre une couleur.');

        $surface = self::resolveToken(self::$tokens['--surface'] ?? '');
        $this->assertNotSame('', $surface, 'Token --surface introuvable.');

        foreach (['.table-wrapper th', 'th'] as $selector) {
            $body = self::ruleBody(self::$styleCss, $selector);
            $this->assertNotSame('', $body, sprintf('Règle %s introuvable.', $selector));

            $declaration = self::declaration($body, 'background');
            $this->assertMatchesRegularExpression(
                '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
                $declaration,
                sprintf('%s doit consommer un token de fond, pas un littéral.', $selector)
            );

            $headerBg = self::resolveToken($declaration);
            $this->assertNotSame($headerBg, $zebraBg, sprintf('%s ne doit pas partager le fond du zébrage.', $selector));
            $this->assertNotSame($headerBg, $surface, sprintf('%s ne doit pas partager le fond blanc des lignes.', $selector));
            $this->assertLessThan(
                self::relativeLuminance($zebraBg),
                self::relativeLuminance($headerBg),
                sprintf('%s doit être plus marqué (plus sombre) que le zébrage.', $selector)
            );
        }
    }

    /**
     * Retour UI : le texte d'en-tête reste contrasté (WCAG AA ≥ 4.5:1) sur la
     * nouvelle surface d'en-tête.
     */
    public function testTableHeaderTextMeetsContrastOnHeaderSurface(): void
    {
        foreach (['.table-wrapper th', 'th'] as $selector) {
            $body = self::ruleBody(self::$styleCss, $selector);
            $this->assertNotSame('', $body, sprintf('Règle %s introuvable.', $selector));

            $backgroundDeclaration = self::declaration($body, 'background');
            $this->assertMatchesRegularExpression('/^var\(\s*--[a-z0-9-]+\s*\)$/i', $backgroundDeclaration);
            $headerBg = self::resolveToken($backgroundDeclaration);

            $colorDeclaration = self::declaration($body, 'color');
            $this->assertMatchesRegularExpression(
                '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
                $colorDeclaration,
                sprintf('%s doit consommer un token de couleur.', $selector)
            );
            $color = self::resolveToken($colorDeclaration);

            $ratio = self::contrastRatio($color, $headerBg);
            $this->assertGreaterThanOrEqual(
                self::MIN_CONTRAST,
                $ratio,
                sprintf('%s : texte %s sur %s doit offrir ≥ 4.5:1, mesuré %.2f:1.', $selector, $color, $headerBg, $ratio)
            );
        }
    }

    /**
     * Retour UI : la règle basse d'en-tête doit être perceptible (WCAG 1.4.11,
     * ≥ 3:1) et épaisse d'au moins 2px pour séparer nettement l'en-tête des
     * lignes.
     */
    public function testTableHeaderBottomBorderIsPerceptible(): void
    {
        foreach (['.table-wrapper th', 'th'] as $selector) {
            $body = self::ruleBody(self::$styleCss, $selector);
            $this->assertNotSame('', $body, sprintf('Règle %s introuvable.', $selector));

            $border = self::declaration($body, 'border-bottom');
            $this->assertMatchesRegularExpression(
                '/^\d+(\.\d+)?px\s+solid\s+var\(\s*--[a-z0-9-]+\s*\)$/i',
                $border,
                sprintf('%s : bordure basse tokenisée attendue, reçu « %s ».', $selector, $border)
            );

            preg_match('/^([\d.]+)px/i', $border, $widthMatch);
            $this->assertGreaterThanOrEqual(
                2.0,
                (float) ($widthMatch[1] ?? 0),
                sprintf('%s : la règle basse doit faire au moins 2px.', $selector)
            );

            preg_match('/var\(\s*(--[a-z0-9-]+)\s*\)/i', $border, $tokenMatch);
            $borderColor = self::resolveToken('var(' . ($tokenMatch[1] ?? '') . ')');
            $headerBg = self::resolveToken(self::declaration($body, 'background'));

            $ratio = self::contrastRatio($borderColor, $headerBg);
            $this->assertGreaterThanOrEqual(
                self::MIN_NON_TEXT_CONTRAST,
                $ratio,
                sprintf('%s : bordure %s sur fond %s doit offrir ≥ 3:1, mesuré %.2f:1.', $selector, $borderColor, $headerBg, $ratio)
            );
        }
    }

    /**
     * Retour UI : le bouton de pièce jointe (`.file-upload-wrapper__label`,
     * « Joindre un document ») ne doit plus s'atténuer via `opacity: 0.85` au
     * survol/focus — ce qui dégrade le contraste — mais passer par un fond
     * solide tokenisé qui conserve AA (texte blanc ≥ 4.5:1).
     */
    public function testAttachmentUploadButtonHoverIsSolidAndKeepsAa(): void
    {
        $bodies = self::ruleBodiesFor('.file-upload-wrapper__label:hover');
        $this->assertNotEmpty($bodies, 'Règle .file-upload-wrapper__label:hover introuvable.');

        $body = implode("\n", $bodies);
        $declarations = (string) preg_replace('~/\*.*?\*/~s', '', $body);
        $this->assertStringNotContainsString(
            'opacity: 0.85',
            $declarations,
            'Le survol ne doit plus atténuer le bouton via opacity: 0.85.'
        );

        if (preg_match('/opacity\s*:\s*([^;]+);/i', $declarations, $match) === 1) {
            $this->assertSame('1', trim((string) ($match[1] ?? '')), 'Si une opacité subsiste, elle doit valoir 1 (état solide).');
        }

        $backgroundDeclaration = self::declaration($body, 'background');
        $this->assertMatchesRegularExpression(
            '/^var\(\s*--[a-z0-9-]+\s*\)$/i',
            $backgroundDeclaration,
            'Le survol doit poser un fond solide tokenisé.'
        );

        $hoverBg = self::resolveToken($backgroundDeclaration);
        $ratio = self::contrastRatio(self::WHITE, $hoverBg);
        $this->assertGreaterThanOrEqual(
            self::MIN_CONTRAST,
            $ratio,
            sprintf('Bouton pièce jointe au survol : blanc sur %s doit offrir ≥ 4.5:1, mesuré %.2f:1.', $hoverBg, $ratio)
        );
    }

    /**
     * Retour UI (suite) : le focus clavier du contrôle de pièce jointe doit
     * rester perceptible. L'input `file` étant visuellement masqué, l'anneau
     * est porté par le label sur `:focus-within`.
     */
    public function testAttachmentUploadButtonFocusIsVisible(): void
    {
        $bodies = self::ruleBodiesFor('.file-upload-wrapper__label:focus-within');
        $this->assertNotEmpty($bodies, 'Règle .file-upload-wrapper__label:focus-within introuvable.');

        $visible = false;
        foreach ($bodies as $body) {
            if (preg_match('/outline\s*:\s*[^;]*var\(\s*--focus-ring-color\s*\)/i', $body) === 1) {
                $visible = true;
                break;
            }
        }

        $this->assertTrue($visible, 'Le label de pièce jointe doit porter un anneau de focus tokenisé sur :focus-within.');
    }

    /**
     * Le renforcement des en-têtes ne doit pas casser le zébrage ni le survol
     * des lignes.
     */
    public function testZebraAndHoverRowBackgroundsArePreserved(): void
    {
        $this->assertStringContainsString(
            'background: var(--grey-50)',
            self::ruleBody(self::$styleCss, 'tr:nth-child(even) td'),
            'Le zébrage des lignes paires doit rester --grey-50.'
        );
        $this->assertStringContainsString(
            'background: var(--hover-highlight)',
            self::ruleBody(self::$styleCss, 'tr:hover td'),
            'Le survol de ligne doit rester --hover-highlight.'
        );
    }

    /**
     * Le renforcement des en-têtes globaux ne doit pas transformer la colonne
     * de libellés de `report-detail__table` (fond transparent, colonne de
     * gauche) en bandeau d'en-tête.
     */
    public function testReportDetailLabelsKeepTransparentBackground(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.report-detail__table th\s*\{[^}]*background:\s*transparent/s',
            self::$styleCss,
            'Les libellés de report-detail__table doivent rester à fond transparent.'
        );
    }

    /**
     * Extrait les règles « feuilles » (sélecteur => corps), y compris celles
     * imbriquées dans les media queries, sans se laisser piéger par les accolades.
     *
     * @return array<string, string>
     */
    private static function leafRules(): array
    {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', self::$styleCss);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
        $rules = [];
        foreach ($matches as $match) {
            $selector = trim((string) ($match[1] ?? ''));
            if ($selector === '' || str_starts_with($selector, '@')) {
                continue;
            }
            $rules[$selector] = (string) ($match[2] ?? '');
        }

        return $rules;
    }

    /**
     * Corps de toutes les règles dont la liste de sélecteurs contient
     * `$selector` (une règle multi-sélecteurs compte pour chacun d'eux).
     *
     * @return list<string>
     */
    private static function ruleBodiesFor(string $selector): array
    {
        $bodies = [];
        foreach (self::leafRules() as $candidate => $body) {
            foreach (array_map('trim', explode(',', $candidate)) as $part) {
                if ($part === $selector) {
                    $bodies[] = $body;
                    break;
                }
            }
        }

        return $bodies;
    }
}
