<?php

declare(strict_types=1);

/**
 * CssDesignSystemTest — contrat de la modernisation visuelle.
 *
 * `public/css/style.css` est la source de vérité (aucun JS, aucun style inline).
 * Ce test verrouille :
 *   - le jeu canonique de tokens :root (noms + valeurs exactes de la spec),
 *   - les composants/shell/responsive qui les consomment.
 */

use PHPUnit\Framework\TestCase;

final class CssDesignSystemTest extends TestCase
{
    private static string $css = '';

    /** @var array<string, string> nom du token => valeur brute */
    private static array $tokens = [];

    public static function setUpBeforeClass(): void
    {
        $path = __DIR__ . '/../../public/css/style.css';
        $css = file_get_contents($path);
        self::$css = is_string($css) ? $css : '';

        // Le premier bloc :root est la table canonique. La media query
        // prefers-contrast rouvre :root plus loin : elle ne doit pas l'écraser.
        $root = [];
        if (preg_match('/:root\s*\{([^}]*)\}/', self::$css, $m) === 1
            && preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', $m[1], $rows, PREG_SET_ORDER)
        ) {
            foreach ($rows as $row) {
                $root[trim($row[1])] = trim($row[2]);
            }
        }
        self::$tokens = $root;
    }

    /** @return array<string, string> */
    private static function expectedTokens(): array
    {
        $tokens = [
            '--color-primary' => '#0056A3',
            '--color-primary-dark' => '#003D75',
            '--color-primary-light' => '#3498DB',
            '--grey-50' => '#FAFAFA',
            '--grey-100' => '#F5F5F5',
            '--grey-200' => '#EEEEEE',
            '--grey-300' => '#E0E0E0',
            '--grey-400' => '#BDBDBD',
            '--grey-500' => '#9E9E9E',
            '--grey-600' => '#757575',
            '--grey-700' => '#616161',
            '--grey-800' => '#424242',
            '--grey-900' => '#212121',
            '--border' => 'var(--grey-300)',
            '--hover-highlight' => '#E8F0FE',
            '--color-success-bg' => '#d4edda',
            '--color-success-border' => '#c3e6cb',
            '--color-success-text' => '#155724',
            '--color-danger-bg' => '#f8d7da',
            '--color-danger-border' => '#f5c6cb',
            '--color-danger-text' => '#721c24',
            '--color-warning-bg' => '#fff3cd',
            '--color-warning-border' => '#ffeeba',
            '--color-warning-text' => '#856404',
            '--color-info-bg' => '#d1ecf1',
            '--color-info-border' => '#bee5eb',
            '--color-info-text' => '#0c5460',
            '--state-nouveau' => '#2E5C8A',
            '--state-en-cours' => '#E67E22',
            '--state-traite' => '#27AE60',
            '--state-abandonne' => '#7B8D8E',
            '--role-agent' => '#2E5C8A',
            '--role-superviseur' => '#B22222',
            '--role-chsct' => '#8E44AD',
            '--visibility-confidential' => '#6b7280',
            '--visibility-public' => '#22c55e',
            '--font-family' => "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
            '--border-radius' => '4px',
            '--border-radius-lg' => '8px',
            '--transition-fast' => '0.15s ease',
            '--transition-base' => '0.2s ease',
            '--sidebar-width' => '220px',
            '--header-height' => '60px',
            '--content-padding' => '24px',
            '--z-sidebar' => '90',
            '--z-header' => '100',
            '--z-mobile-menu' => '200',
            '--z-overlay' => '150',
            '--z-skip-link' => '9999',
            '--focus-ring-color' => 'rgba(0,86,163,0.4)',
            '--focus-ring-offset' => '2px',
        ];

        foreach ([
            'rsst' => '#2E5C8A',
            'rami' => '#6C6C6C',
            'dgi' => '#b91c1c',
            'vert' => '#15803D',
            'violet' => '#7C3AED',
            'orange' => '#C2410C',
            'teal' => '#0D9488',
            'indigo' => '#4338CA',
            'rose' => '#BE123C',
            'ambre' => '#B45309',
        ] as $key => $value) {
            $tokens['--theme-' . $key] = $value;
        }

        return $tokens;
    }

    /** Extrait le corps d'une règle par sélecteur ancré en début de ligne. */
    private function ruleBody(string $selector): string
    {
        $pattern = '/^' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m';
        if (preg_match($pattern, self::$css, $m) !== 1) {
            return '';
        }
        return (string) $m[1];
    }

    /** Concatène tous les blocs `@media <query>` (équilibrage d'accolades). */
    private function mediaBlocks(string $query): string
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

    /** CSS sans commentaires, pour éviter de confondre exemples et règles réelles. */
    private static function withoutComments(): string
    {
        return (string) preg_replace('~/\*.*?\*/~s', '', self::$css);
    }

    /**
     * Extrait les règles « feuilles » (sélecteur => corps), y compris celles
     * imbriquées dans les media queries, sans se laisser piéger par les accolades.
     *
     * @return array<string, string>
     */
    private static function leafRules(): array
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', self::withoutComments(), $matches, PREG_SET_ORDER);
        $rules = [];
        foreach ($matches as $match) {
            $selector = trim($match[1]);
            if ($selector === '' || str_starts_with($selector, '@')) {
                continue;
            }
            $rules[$selector] = $match[2];
        }
        return $rules;
    }

    /**
     * Spécificité CSS (a, b, c) → entier comparable : ids, classes/attributs/
     * pseudo-classes, éléments/pseudo-éléments.
     */
    private static function specificity(string $selector): int
    {
        $ids = preg_match_all('/#[\w-]+/', $selector);
        $classes = preg_match_all('/\.[\w-]+|\[[^\]]*\]|:(?!:)[\w-]+(?:\([^)]*\))?/', $selector);
        $elements = preg_match_all('/(?:^|[\s>+~])[a-zA-Z][\w-]*/', $selector);

        return ((int) $ids) * 100 + ((int) $classes) * 10 + (int) $elements;
    }

    public function testEveryCanonicalTokenExistsWithExpectedValue(): void
    {
        foreach (self::expectedTokens() as $name => $value) {
            $this->assertArrayHasKey($name, self::$tokens, "Token $name manquant dans :root.");
            $this->assertSame($value, self::$tokens[$name], "Token $name : valeur inattendue.");
        }
    }

    public function testSpacingScaleIsComplete(): void
    {
        $scale = [
            '--space-1' => '0.25rem',
            '--space-2' => '0.5rem',
            '--space-3' => '0.75rem',
            '--space-4' => '1rem',
            '--space-5' => '1.25rem',
            '--space-6' => '1.5rem',
            '--space-7' => '1.75rem',
            '--space-8' => '2rem',
        ];
        foreach ($scale as $name => $value) {
            $this->assertSame($value, self::$tokens[$name] ?? null, "Échelle d'espacement : $name.");
        }
    }

    public function testTypographyScaleStaysFluid(): void
    {
        foreach ([
            '--font-size-xs', '--font-size-sm', '--font-size-base', '--font-size-md',
            '--font-size-lg', '--font-size-xl', '--font-size-2xl', '--font-size-3xl',
        ] as $name) {
            $this->assertStringStartsWith('clamp(', self::$tokens[$name] ?? '', "$name doit rester fluide (clamp()).");
        }
    }

    public function testCardBaseConsumesTokens(): void
    {
        $body = $this->ruleBody('.card');
        $this->assertStringContainsString('border: 1px solid var(--border)', $body);
        $this->assertStringContainsString('box-shadow: var(--shadow-sm)', $body);
        $this->assertStringContainsString('padding: var(--space-4)', $body);
        $this->assertStringContainsString('margin-bottom: var(--space-4)', $body);
    }

    public function testCardVariantsConsumeTokens(): void
    {
        $this->assertStringContainsString('margin-bottom: var(--space-4)', $this->ruleBody('.card__title'));
        $this->assertStringContainsString('margin-bottom: var(--space-3)', $this->ruleBody('.card__subtitle'));
        $this->assertStringContainsString('margin-top: var(--space-4)', $this->ruleBody('.card--danger'));
        $this->assertStringContainsString('var(--color-danger-text)', $this->ruleBody('.card--danger'));
        $this->assertStringContainsString('margin-bottom: var(--space-8)', $this->ruleBody('.card--spaced'));
        $this->assertStringContainsString('var(--border)', $this->ruleBody('.card--dashed'));
    }

    public function testFormControlsConsumeTokens(): void
    {
        $this->assertStringContainsString('margin-bottom: var(--space-4)', $this->ruleBody('.form-group'));

        $control = $this->ruleBody('.form-control');
        $this->assertStringContainsString('border: 1px solid var(--border)', $control);
        $this->assertStringContainsString('padding: var(--space-2) var(--space-3)', $control);
        $this->assertStringContainsString('border-radius: var(--border-radius)', $control);

        $this->assertStringContainsString('var(--focus-ring-color)', $this->ruleBody('.form-control:focus'));
    }

    public function testFormValidationStatesOnlyWhenFilled(): void
    {
        $this->assertStringContainsString('.form-control:not(:placeholder-shown):invalid', self::$css);
        $this->assertStringContainsString('.form-control:not(:placeholder-shown):valid', self::$css);
        $this->assertStringContainsString(
            'var(--color-danger-border)',
            $this->ruleBody('.form-control:not(:placeholder-shown):invalid')
        );
        $this->assertStringContainsString(
            'var(--color-success-border)',
            $this->ruleBody('.form-control:not(:placeholder-shown):valid')
        );
    }

    public function testFormLayoutConsumesTokens(): void
    {
        $this->assertStringContainsString('border-top: 1px solid var(--border)', $this->ruleBody('.form-actions'));
        $this->assertStringContainsString('gap: var(--space-3)', $this->ruleBody('.form-actions__group'));
        $this->assertStringContainsString('var(--font-size-sm)', $this->ruleBody('.form-hint'));
        $this->assertStringContainsString('var(--space-6)', $this->ruleBody('.form-grid'));
    }

    public function testTableWrapperConsumesTokens(): void
    {
        $wrapper = $this->ruleBody('.table-wrapper');
        $this->assertStringContainsString('border: 1px solid var(--border)', $wrapper);
        $this->assertStringContainsString('border-radius: var(--border-radius)', $wrapper);
    }

    public function testTableHeadersAreLightAndTokenised(): void
    {
        $th = $this->ruleBody('.table-wrapper th');
        $this->assertNotSame('', $th, 'La règle .table-wrapper th doit exister.');
        $this->assertStringContainsString('text-transform: uppercase', $th);
        $this->assertStringContainsString('var(--font-size-xs)', $th);
        $this->assertStringContainsString('background: var(--grey-50)', $th);
        $this->assertStringContainsString('border-bottom: 1px solid var(--border)', $th);
    }

    /**
     * Finding Important F1 (revue Task 4, commit 3c04af7) : `.table-wrapper th`
     * (0,1,1) écrase `.synthesis-th--<type>` (0,1,0) → en-têtes thématisés rendus
     * en gris. La règle thématisée doit gagner ET préserver les couleurs de
     * registre, avec une typographie d'en-tête décidée explicitement.
     */
    public function testSynthesisThematicHeadersOutrankTableHeaderDefaults(): void
    {
        $rules = self::leafRules();
        $base = self::specificity('.table-wrapper th');

        $themes = ['rsst', 'rami', 'dgi', 'vert', 'violet', 'orange', 'teal', 'indigo', 'rose', 'ambre'];
        foreach ($themes as $theme) {
            $needle = '.synthesis-th--' . $theme;
            $candidates = array_filter(
                array_keys($rules),
                static fn (string $selector): bool => str_contains($selector, $needle)
            );
            $this->assertNotEmpty($candidates, "Règle thématisée .synthesis-th--$theme introuvable.");

            foreach ($candidates as $selector) {
                $this->assertGreaterThan(
                    $base,
                    self::specificity($selector),
                    "Le sélecteur « $selector » doit battre « .table-wrapper th » (0,1,1)."
                );

                $body = $rules[$selector];
                $this->assertStringContainsString(
                    'var(--theme-' . $theme . ')',
                    $body,
                    "Couleur de registre $theme non préservée."
                );
                $this->assertMatchesRegularExpression(
                    '/color:\s*white/',
                    $body,
                    "Le texte de .synthesis-th--$theme doit rester blanc."
                );
                $this->assertStringContainsString(
                    'text-transform: uppercase',
                    $body,
                    "Traitement text-transform explicite attendu pour .synthesis-th--$theme."
                );
                $this->assertStringContainsString(
                    'var(--font-size-xs)',
                    $body,
                    "Taille d'en-tête explicite attendue pour .synthesis-th--$theme."
                );
            }
        }
    }

    public function testResponsiveTableStacksWithDataLabel(): void
    {
        $tablet = $this->mediaBlocks('(max-width: 768px)');
        $this->assertStringContainsString('.table-wrapper--responsive td::before', $tablet);
        $this->assertStringContainsString('attr(data-label)', $tablet);
        $this->assertStringContainsString('var(--font-size-xs)', $tablet);
    }

    public function testHeaderConsumesTokens(): void
    {
        $header = $this->ruleBody('.header');
        $this->assertStringContainsString('height: var(--header-height)', $header);
        $this->assertStringContainsString('background: var(--color-primary)', $header);
        $this->assertStringContainsString('z-index: var(--z-header)', $header);
        $this->assertStringContainsString('padding: 0 var(--space-5)', $header);
        $this->assertStringContainsString('box-shadow: var(--shadow-md)', $header);
    }

    public function testSidebarActiveUsesColourAndBorderMarker(): void
    {
        $active = $this->ruleBody('.sidebar__item--active');
        $this->assertStringContainsString('border-left-color: var(--sidebar-active)', $active);
        $this->assertStringContainsString('background:', $active, 'L’actif ne doit pas reposer sur la couleur seule.');
        $this->assertStringContainsString('color:', $active);
    }

    public function testOverlayAndSkipLinkUseDedicatedZTokens(): void
    {
        $this->assertStringContainsString('z-index: var(--z-overlay)', $this->ruleBody('.sidebar-overlay'));
        $this->assertStringContainsString('z-index: var(--z-skip-link)', $this->ruleBody('.skip-link'));
        $this->assertStringContainsString(
            'z-index: var(--z-mobile-menu)',
            $this->mediaBlocks('(max-width: 768px)')
        );
    }

    public function testMainContentUsesLayoutTokens(): void
    {
        $main = $this->ruleBody('.main');
        $this->assertStringContainsString('margin-left: var(--sidebar-width)', $main);
        $this->assertStringContainsString('margin-top: var(--header-height)', $main);
        $this->assertStringContainsString('padding: var(--content-padding)', $main);
    }

    /* ---------- Task 6 : Responsive 768 / 480 + garde-fous ---------- */

    public function testFormsCollapseToSingleColumnUnder768(): void
    {
        $tablet = $this->mediaBlocks('(max-width: 768px)');
        $this->assertStringContainsString('.form-grid--3', $tablet);
        $this->assertStringContainsString('grid-template-columns: 1fr', $tablet);
    }

    public function testActionsStackFullWidthUnder768(): void
    {
        $tablet = $this->mediaBlocks('(max-width: 768px)');
        $this->assertStringContainsString('.form-actions__group', $tablet);
        $this->assertStringContainsString('flex-direction: column', $tablet);
    }

    public function testSmallPhonesReduceDensityWithTokens(): void
    {
        $phone = $this->mediaBlocks('(max-width: 480px)');
        $this->assertStringContainsString('var(--space-3)', $phone);
    }

    public function testMotionContrastAndPrintGuardsExist(): void
    {
        $this->assertStringContainsString(
            'transition-duration',
            $this->mediaBlocks('(prefers-reduced-motion: reduce)')
        );
        $this->assertStringContainsString(
            '--border',
            $this->mediaBlocks('(prefers-contrast: high)'),
            'Le mode contraste élevé doit renforcer les bordures via un token.'
        );
        $this->assertNotSame('', $this->mediaBlocks('print'), 'La feuille d’impression épurée doit être conservée.');
    }
}