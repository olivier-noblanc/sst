# Modernisation visuelle de l'application SST — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refondre la couche de présentation de l'application SST (tokens CSS canoniques, shell header/sidebar/main, cartes/tableaux/formulaires, responsive 768/480, accessibilité AA, captures) dans `public/css/style.css`, **sans modifier un seul template, handler, route ou fichier PHP métier**, en conservant tous les noms de classes et la structure HTML existants. **La page de connexion (Task 9) est hors périmètre** : en production l'application est authentifiée directement par IIS ; `pages/login.php` / `public/css/login.css` ne sont qu'un mode dev hors production et ne font pas partie de la couche visuelle servie.

**Architecture:** Le CSS est l'unique source de vérité visuelle. On consolide le bloc `:root` existant (82 tokens attendus par la spec, `--space-7` manquant) comme contrat unique, puis on réécrit les composants du périmètre pour **consommer exclusivement ces tokens** (aucune valeur littérale en dur dans les règles ciblées). La refactorisation est pilotée par TDD : une classe PHPUnit `CssDesignSystemTest` lit `public/css/style.css` et asserte les contrats (tokens exacts, propriétés tokenisées, présence des blocs `@media`) ; chaque tâche fait échouer un test avant d'implémenter. Aucune dépendance, aucun bundler : le CSS reste un fichier statique servi par `css.php`.

**Tech Stack :** CSS statique (`public/css/style.css`, 4125 lignes / 102 769 octets ; `public/css/login.css`, 980 octets) ; PHP 8.3+ et PHPUnit 11 pour les tests de contrat CSS ; PHPStan level 8 + règles custom existantes (`NoInlineStyleRule`, `NoSqlOutsideRepositoryRule`) ; CI GitHub jobs `csp-checks` (`php tools/check_inline_styles.php`, `php tools/check_css_classes.php --missing`) et E2E Playwright (Firefox, shards) ; outils Python `tools/capture_screenshots.py` + `tools/annotate_screenshots.py` (Playwright/Pillow). **Aucune dépendance nouvelle.**

---

## Global Constraints

- **Périmètre serré** : cette étape modifie **uniquement** `public/css/style.css`, les tests PHPUnit de contrat CSS, et `tools/` pour la capture. **Aucun** template, handler, service, route ou fichier PHP métier n'est touché (spec §Périmètre).
- **Connexion hors périmètre (décision 2026-09-23)** : l'application de production est authentifiée directement par IIS. `pages/login.php` et `public/css/login.css` ne sont qu'un **mode dev hors production** ; la section « 22. Login Page » de `style.css` et `login.css` ne sont pas servies en production. Aucune tokenisation ni contrat de test ne doit porter sur ces styles — Task 9 annulée, `LoginCssTest` supprimé, couverture de contraste restreinte à `.badge`.
- **Noms de classes figés** : les tests existants assertent les classes (`btn--danger`, `badge--*`, `card--*`, `form-actions__group`, `btn--transmit`…) : **aucun renommage de classe** n'est autorisé.
- **Tokens = source de vérité** : `--color-primary` `#0056A3`, `--color-primary-dark` `#003D75`, `--color-primary-light` `#3498DB` ; échelle de gris `--grey-50` `#FAFAFA` → `--grey-900` `#212121` ; `--border` `var(--grey-300)` ; `--hover-highlight` `#E8F0FE`.
- **Sémantique figée** : `--color-success/-danger/-warning/-info` en triplets `-bg/-border/-text` ; `--state-nouveau/-en-cours/-traite/-abandonne` = `#2E5C8A`/`#E67E22`/`#27AE60`/`#7B8D8E` ; `--role-agent/-superviseur/-chsct` = `#2E5C8A`/`#B22222`/`#8E44AD` ; `--visibility-confidential/-public` = `#6b7280`/`#22c55e`.
- **Typographie** : `--font-family` = `'Segoe UI', Tahoma, Geneva, Verdana, sans-serif` ; `--font-size-xs` … `--font-size-3xl` en `clamp(...)` (échelle fluide, aucune media query dédiée).
- **Espacements** : `--space-1` `0.25rem` → `--space-8` `2rem` (pas de 4 px) ; aucun composant du périmètre ne code un `px`/`rem` de marge/padding en dur.
- **Rayons / ombres / transitions / z-index** : `--border-radius` `4px`, `--border-radius-lg` `8px` ; `--shadow-sm/-/-md/-lg/-xl` ; `--transition-fast` `0.15s ease`, `--transition-base` `0.2s ease` ; `--sidebar-width` `220px`, `--header-height` `60px`, `--content-padding` `24px` ; `--z-sidebar` `90`, `--z-header` `100`, `--z-mobile-menu` `200`, `--z-overlay` `150`, `--z-skip-link` `9999`.
- **Focus** : `--focus-ring-color` `rgba(0,86,163,0.4)`, `--focus-ring-offset` `2px`. Focus visible jamais supprimé sans remplacement équivalent.
- **Points de rupture** : `max-width: 768px` et `max-width: 480px`, plus garde-fous `prefers-reduced-motion: reduce`, `prefers-contrast: high`, `@media print` (mise en page épurée conservée).
- **Thèmes de registres** : 10 clés `rsst`, `rami`, `dgi`, `vert`, `violet`, `orange`, `teal`, `indigo`, `rose`, `ambre`. Un token `--theme-<clé>` + trois modificateurs `.card--<clé>` / `.badge--<clé>` / `.btn--<clé>`. Jamais de couleur codée en dur ; un thème inconnu dégrade vers le neutre, jamais vers une exception.
- **Accessibilité** : contraste AA (≥ 4.5:1), focus visible, cibles tactiles ≥ 44 px en mobile, ordre de tabulation naturel, `prefers-reduced-motion` respecté.
- **Terminologie** : toujours **CSA/CHSCT** dans tout texte visible (aucun texte visible n'est modifié ici).
- **Pas de styles inline** : `style=` interdit (CSP + `NoInlineStyleRule` + `tools/check_inline_styles.php`).
- **Qualité** : `rtk phpunit --no-coverage` **et** `rtk phpstan analyse --memory-limit=1G` verts avant chaque commit ; `php tools/check_inline_styles.php` et `php tools/check_css_classes.php --missing` sans erreur.
- **Aucun commit/push d'implémentation par le rédacteur de ce plan** : ce document ne modifie aucun fichier de production. Les étapes ci-dessous décrivent le travail à réaliser ultérieurement ; le seul commit produit *maintenant* est celui du plan lui-même.

---

## Baseline vérifiée du dépôt (2026-09-22)

Constats à connaître pour lire le plan :

- `public/css/style.css` : **4125 lignes**, **102 769 octets**, **88** variables `:root`.
- Sur les **82 tokens exigés par la spec, 81 existent** ; seul **`--space-7` est absent**.
- Les 10 tokens `--theme-*` et les 10 modificateurs `.card--*` / `.badge--*` / `.btn--*` existent déjà, **mais `.card--rsst` / `.card--rami` / `.card--dgi` consomment `--rsst-color` / `--rami-color` / `--dgi-color` au lieu de `--theme-*`** (incohérence à corriger, Task 8).
- Les règles du périmètre codent encore des valeurs littérales : `.card` (`padding: 20px`, `box-shadow: var(--shadow)`, pas de bordure), `.header` (`padding: 0 20px`, ombre `rgba(0,0,0,0.2)`), `.sidebar-overlay` (`z-index: calc(var(--z-sidebar) - 1)` au lieu de `var(--z-overlay)`), `.form-group` (`margin-bottom: 16px`), `.form-control` (`padding: 8px 12px`), `.form-group input:focus` (`box-shadow: 0 0 0 3px rgba(0,86,163,0.15)`), `th`/`td` (paddings `px`), etc.
- Le CSS contient **145 couleurs hexadécimales hors `:root`** (dont des doublons exacts de tokens). Le périmètre de ce plan tokenise les composants listés, pas les 145.
- `public/css/login.css` (dev quick-login) code des littéraux (`#555`, `#1e40af`, `#1e3a5f`, `#3b82f6`, `#6b7280`) avec `!important`. **Hors périmètre** (mode dev non servi en production, cf. Global Constraints) : ces littéraux sont laissés en l'état.
- `:focus-visible` existe déjà (outline `3px solid var(--focus-ring-color)`, offset token). `prefers-reduced-motion`, `prefers-contrast`, `@media print` existent.
- `tests/unit/UiLayoutCssTest.php` est le précédent de test CSS lisant `style.css` (helper `ruleBody()` ancré ligne) : ce plan le généralise dans un nouveau `CssDesignSystemTest`.
- `docs/screenshots/*.html` (17 fichiers) sont des **snapshots autonomes** : chacun embarque un `<style>` de **60 084 octets** (copie gelée d'une ancienne CSS, identique pour les 17, contenant 15 sélecteurs absents du CSS live, ex. `.sidebar--open`, `.sidebar-overlay--visible`). Ils **ne référencent pas** `style.css` : régénérer les PNG sans resynchroniser ne reflète donc aucun changement (voir Task 10, décision documentée).
- Suite de tests : **2225 méthodes** listées par `phpunit --list-tests`.

---

## File Structure

| Fichier | Rôle | Tâches |
|---|---|---|
| `public/css/style.css` | Unique feuille de style applicative ; propriété de tous les tokens et composants du périmètre | 1–8 |
| `public/css/login.css` | Styles de la page de connexion (dev quick-login) — **hors périmètre** : mode dev, non servi en production | — |
| `tests/unit/CssDesignSystemTest.php` | **Créé** — contrat CSS : tokens, composants tokenisés, matrices responsive/thèmes. Lit `style.css` | 1–8 |
| `tests/unit/LoginCssTest.php` | **Supprimé** — la connexion est hors périmètre (Task 9 annulée) | — |
| `tools/check_screenshot_css.js` | **Créé** — détecte si le `<style>` embarqué dans les snapshots diverge de `style.css` (mode `--check`, CI-friendly) | 10 |
| `docs/screenshots/*.html`, `docs/screenshots/*.png`, `public/screenshots/*.png` | Artefacts de capture : baseline + régénération | 10 |

Aucun autre fichier n'est créé ou modifié par ce plan.

---

## Phase 1 — Tokens CSS

### Task 1 : Contrat `:root` canonique + harnais de test CSS

**Files:**
- Modify: `public/css/style.css` (bloc `:root`, section « 1. CSS Custom Properties », lignes 7–139)
- Test: `tests/unit/CssDesignSystemTest.php` (créer)

**Interfaces:**
- Consumes: —
- Produces: `tests/unit/CssDesignSystemTest.php` expose `private static string $css`, `private static array $tokens`, `private static function expectedTokens(): array`, `private function ruleBody(string $selector): string`, `private function mediaBlocks(string $query): string`. **Toutes les tâches 2 à 8 ajoutent des méthodes à cette classe et réutilisent ces helpers.**

- [ ] **Step 1 : écrire le test qui échoue**

Créer `tests/unit/CssDesignSystemTest.php` :

```php
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
}
```

- [ ] **Step 2 : lancer le test pour vérifier l'échec**

Run: `rtk phpunit --no-coverage tests/unit/CssDesignSystemTest.php`
Expected: FAIL — `testSpacingScaleIsComplete` échoue sur `--space-7` (`null` au lieu de `1.75rem`). Les deux autres méthodes passent (régression épinglée).

- [ ] **Step 3 : implémenter**

Dans `public/css/style.css`, section « 1. CSS Custom Properties », réordonner/documenter le bloc `:root` en sous-sections commentées (`/* Couleurs de marque */`, `/* Neutres */`, `/* Sémantique */`, `/* Typographie */`, `/* Espacements */`, `/* Rayons, ombres, transitions, z-index */`, `/* Focus */`) et **ajouter la seule variable manquante** :

```css
    /* Espacements — pas de 4px (0.25rem), échelle 1 → 8 */
    --space-7: 1.75rem;
```

Insérer `--space-7` entre `--space-6` et `--space-8`. Ne modifier aucune autre valeur.

- [ ] **Step 4 : lancer le test pour vérifier le succès**

Run: `rtk phpunit --no-coverage tests/unit/CssDesignSystemTest.php`
Expected: PASS (3 tests, aucune erreur).

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): consolider les tokens :root et verrouiller leur contrat"
```

---

## Phase 2 — Composants

### Task 2 : Cartes (`.card`) pilotées par les tokens

**Files:**
- Modify: `public/css/style.css` (section « 11. Cards », lignes 681–711)
- Test: `tests/unit/CssDesignSystemTest.php` (ajouter des méthodes)

**Interfaces:**
- Consumes: `ruleBody()` (Task 1) ; tokens `--border`, `--border-radius`, `--shadow-sm`, `--space-4`, `--space-3`, `--space-8`, `--font-size-md`, `--color-danger-text`.
- Produces: `.card`, `.card__title`, `.card__subtitle`, `.card--danger`, `.card--spaced`, `.card--dashed`, `.card--narrow-center` tokenisées (contrats consommés par Task 8 pour les thèmes).

- [ ] **Step 1 : écrire le test qui échoue**

Ajouter à `CssDesignSystemTest` :

```php
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
```

- [ ] **Step 2 : lancer le test pour vérifier l'échec**

Run: `rtk phpunit --no-coverage --filter 'testCard' tests/unit/CssDesignSystemTest.php`
Expected: FAIL — `.card` n'a pas de `border`, utilise `var(--shadow)` et `padding: 20px` ; `.card--danger` utilise `var(--dgi-color)`.

- [ ] **Step 3 : implémenter**

Remplacer les règles de la section « 11. Cards » :

```css
.card {
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    padding: var(--space-4);
    margin-bottom: var(--space-4);
}

.card__title {
    margin-bottom: var(--space-4);
    font-size: var(--font-size-md);
}

.card__subtitle {
    margin-bottom: var(--space-3);
    font-size: var(--font-size-md);
}

.card--danger {
    margin-top: var(--space-4);
    border-top: 4px solid var(--color-danger-text);
}

.card--spaced { margin-bottom: var(--space-8); }
.card--dashed { border-top: 4px dashed var(--border); }
.card--narrow-center { max-width: 600px; margin: 0 auto; }
```

Conserver `.card--flush-top { border-top-left-radius: 0; }` et les modificateurs `.card--<thème>` inchangés (traités Task 8).

- [ ] **Step 4 : lancer le test pour vérifier le succès**

Run: `rtk phpunit --no-coverage --filter 'testCard' tests/unit/CssDesignSystemTest.php`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): cartes pilotées par les tokens"
```

---

### Task 3 : Formulaires (`.form-group` / `.form-control` / `.form-grid`) pilotés par les tokens

**Files:**
- Modify: `public/css/style.css` (section « 15. Forms », lignes 1171–1263, et `.form-error-summary` ~2038)
- Test: `tests/unit/CssDesignSystemTest.php` (ajouter des méthodes)

**Interfaces:**
- Consumes: `ruleBody()` ; tokens `--space-1`, `--space-2`, `--space-3`, `--space-4`, `--space-5`, `--space-6`, `--border`, `--border-radius`, `--font-size-sm`, `--font-size-base`, `--focus-ring-color`, `--focus-ring-offset`, `--color-danger-border`, `--color-success-border`, `--color-danger-text`, `--color-danger-bg`, `--color-danger-border`.
- Produces: `.form-group`, `.form-control`, `.form-control:not(:placeholder-shown):invalid/:valid`, `.form-grid`, `.form-hint`, `.form-actions`, `.form-actions__group`, `.form-error-summary` tokenisées (consommés par Task 6 responsive).

- [ ] **Step 1 : écrire le test qui échoue**

Ajouter à `CssDesignSystemTest` :

```php
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
```

- [ ] **Step 2 : lancer le test pour vérifier l'échec**

Run: `rtk phpunit --no-coverage --filter 'testForm' tests/unit/CssDesignSystemTest.php`
Expected: FAIL — `.form-control:not(:placeholder-shown):invalid` absent, `.form-control:focus` utilise `rgba(0,86,163,0.15)`.

- [ ] **Step 3 : implémenter**

Dans la section « 15. Forms », remplacer les valeurs littérales :

```css
.form-group { margin-bottom: var(--space-4); }

.form-group label {
    display: block;
    margin-bottom: var(--space-1);
    font-weight: 500;
    color: var(--grey-700);
    font-size: var(--font-size-sm);
    line-height: 1.4;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: var(--space-2) var(--space-3);
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    font-size: var(--font-size-base);
    font-family: var(--font-family);
    background: white;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
    min-height: 44px;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: 2px solid var(--color-primary);
    outline-offset: -1px;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 var(--focus-ring-offset) var(--focus-ring-color);
}

.form-control {
    width: 100%;
    padding: var(--space-2) var(--space-3);
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    font-size: var(--font-size-base);
    font-family: var(--font-family);
    background: white;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}

.form-control:focus {
    outline: 2px solid var(--color-primary);
    outline-offset: -1px;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 var(--focus-ring-offset) var(--focus-ring-color);
}

/* États signalés seulement quand le champ est rempli (évite les faux positifs). */
.form-control:not(:placeholder-shown):invalid {
    border-color: var(--color-danger-border);
}

.form-control:not(:placeholder-shown):valid {
    border-color: var(--color-success-border);
}
```

Adapter `.form-grid` (`gap: 0 var(--space-6)`), `.form-hint` (`margin-top: var(--space-1); font-size: var(--font-size-sm);`), `.form-actions` (`margin-top: var(--space-6); padding-top: var(--space-5); border-top: 1px solid var(--border);`), `.form-actions__group` (`gap: var(--space-3);`). Pour `.form-error-summary`, remplacer `border-left: 4px solid var(--dgi-color)` par `var(--color-danger-text)` et `padding: 16px 20px` par `var(--space-4) var(--space-5)`.

- [ ] **Step 4 : lancer le test pour vérifier le succès**

Run: `rtk phpunit --no-coverage --filter 'testForm' tests/unit/CssDesignSystemTest.php`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): formulaires pilotés par les tokens"
```

---

### Task 4 : Tableaux (`.table-wrapper`) pilotés par les tokens + empilement `data-label`

**Files:**
- Modify: `public/css/style.css` (section « 16. Tables », lignes 1717–1790, et `th`/`td` ~1796–1833)
- Test: `tests/unit/CssDesignSystemTest.php` (ajouter des méthodes)

**Interfaces:**
- Consumes: `ruleBody()`, `mediaBlocks()` ; tokens `--border`, `--border-radius`, `--grey-50`, `--grey-600`, `--hover-highlight`, `--font-size-xs`, `--space-1`, `--space-2`, `--space-3`, `--shadow-sm`.
- Produces: `.table-wrapper`, `.table-wrapper th`, `.table-wrapper td`, `.table-wrapper--responsive td::before` tokenisées.

- [ ] **Step 1 : écrire le test qui échoue**

Ajouter à `CssDesignSystemTest` :

```php
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

    public function testResponsiveTableStacksWithDataLabel(): void
    {
        $tablet = $this->mediaBlocks('(max-width: 768px)');
        $this->assertStringContainsString('.table-wrapper--responsive td::before', $tablet);
        $this->assertStringContainsString('attr(data-label)', $tablet);
        $this->assertStringContainsString('var(--font-size-xs)', $tablet);
    }
```

- [ ] **Step 2 : lancer le test pour vérifier l'échec**

Run: `rtk phpunit --no-coverage --filter 'testTable' tests/unit/CssDesignSystemTest.php`
Expected: FAIL — `.table-wrapper` n'a pas de bordure ; `.table-wrapper th` n'existe pas (les en-têtes sont styles par le `th` global).

- [ ] **Step 3 : implémenter**

```css
.table-wrapper {
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
}

.table-wrapper th {
    background: var(--grey-50);
    text-transform: uppercase;
    font-size: var(--font-size-xs);
    letter-spacing: 0.3px;
    color: var(--grey-600);
    border-bottom: 1px solid var(--border);
    padding: var(--space-2) var(--space-3);
}

.table-wrapper td {
    padding: var(--space-2) var(--space-3);
    border-bottom: 1px solid var(--border);
}
```

Dans le bloc `@media (max-width: 768px)` de la section 16, tokeniser `.table-wrapper--responsive` : `tr` (`border: 1px solid var(--border)`, `padding: var(--space-3)`, `box-shadow: var(--shadow-sm)`), `td` (`padding: var(--space-1) 0`, `border-bottom: 1px solid var(--border)`), `td::before` (`font-size: var(--font-size-xs)`, `color: var(--grey-600)`, `margin-right: var(--space-3)`). Conserver `content: attr(data-label)` et la neutralisation du uppercase pour `td[data-label="Actions"]`.

Vérifier que `pages/report_list.php` (`data-label="Référence"`, `"Date"`, `"Objet"`) et `pages/synthesis.php` (en-têtes dynamiques) restent couverts : aucun `data-label` manquant sur un `<td>` d'un tableau `.table-wrapper--responsive`.

- [ ] **Step 4 : lancer le test pour vérifier le succès**

Run: `rtk phpunit --no-coverage --filter 'testTable|testResponsiveTable' tests/unit/CssDesignSystemTest.php`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): tableaux pilotés par les tokens"
```

---

## Phase 3 — Shell

### Task 5 : Header / sidebar / main / skip-link pilotés par les tokens, ordre z et marqueur actif

**Files:**
- Modify: `public/css/style.css` (header lignes 208–278, sidebar 539–622, main 622–638, skip-link ~1655, sidebar-overlay ~1651/3320)
- Test: `tests/unit/CssDesignSystemTest.php` (ajouter des méthodes)

**Interfaces:**
- Consumes: `ruleBody()`, `mediaBlocks()` ; tokens `--header-height`, `--color-primary`, `--z-header`, `--shadow-md`, `--space-5`, `--sidebar-width`, `--sidebar-bg`, `--sidebar-text`, `--sidebar-active`, `--sidebar-hover`, `--content-padding`, `--z-sidebar`, `--z-mobile-menu`, `--z-overlay`, `--z-skip-link`, `--transition-base`, `--space-2`, `--space-4`.
- Produces: shell tokenisé ; contrat `.sidebar__item--active` = couleur **et** marqueur de bordure ; `.sidebar-overlay` consomme `--z-overlay` ; skip-links au-dessus (`--z-skip-link`).

**Décision de design (spec, incohérence tranchée) :** la prose « overlay sous le panneau, lui-même sous le header » contredit la table des valeurs (`--z-overlay` `150` > `--z-header` `100`, `--z-mobile-menu` `200` > `--z-header` `100`). La **table des valeurs fait foi** : l'overlay assombrit toute la page (header compris, d'où 150 > 100), le panneau mobile est au-dessus de l'overlay (200), et visuellement le panneau démarre **sous** le header via `top: var(--header-height)`. Les skip-links restent au-dessus de tout (9999).

- [ ] **Step 1 : écrire le test qui échoue**

Ajouter à `CssDesignSystemTest` :

```php
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
```

- [ ] **Step 2 : lancer le test pour vérifier l'échec**

Run: `rtk phpunit --no-coverage --filter 'testHeader|testSidebar|testOverlay|testMain' tests/unit/CssDesignSystemTest.php`
Expected: FAIL — `.header` a `padding: 0 20px` et `box-shadow: 0 2px 6px rgba(0,0,0,0.2)` ; `.sidebar-overlay` a `z-index: calc(var(--z-sidebar) - 1)`.

- [ ] **Step 3 : implémenter**

```css
.header {
    height: var(--header-height);
    background: var(--color-primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 var(--space-5);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: var(--z-header);
    box-shadow: var(--shadow-md);
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: var(--z-overlay);
    cursor: pointer;
}
```

Ne pas modifier `.sidebar`, `.main`, `.sidebar__item`, `.sidebar__item--active`, `.skip-link` (déjà tokenisés) : le test les verrouille. Si un `padding`/`gap` littéral subsiste dans `.sidebar__nav`, `.header__user` ou `.header__logo`, le remplacer par le token d'espacement correspondant (`--space-2`, `--space-3`).

- [ ] **Step 4 : lancer le test pour vérifier le succès**

Run: `rtk phpunit --no-coverage --filter 'testHeader|testSidebar|testOverlay|testMain' tests/unit/CssDesignSystemTest.php`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): shell header/sidebar/main piloté par les tokens"
```

---

## Phase 4 — Responsive & accessibilité

### Task 6 : Consolidation responsive (768 / 480, reduced-motion, contraste, impression)

**Files:**
- Modify: `public/css/style.css` (section « 34. Responsive » ~3289, « 35. Small phones » ~3470, `prefers-reduced-motion` 180, `prefers-contrast` 192, `@media print` 3253)
- Test: `tests/unit/CssDesignSystemTest.php` (ajouter des méthodes)

**Interfaces:**
- Consumes: `mediaBlocks()` ; tokens `--space-3`, `--space-4`, `--grey-300`, `--grey-400`, `--grey-500`.
- Produces: repli 1 colonne des `.form-grid*` sous 768 px, actions en colonne pleine largeur, densité réduite en 480, garde-fous mouvement/contraste/impression.

- [ ] **Step 1 : écrire le test qui échoue**

Ajouter à `CssDesignSystemTest` :

```php
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
```

- [ ] **Step 2 : lancer le test pour vérifier l'échec**

Run: `rtk phpunit --no-coverage --filter 'testFormsCollapse|testActionsStack|testSmallPhones|testMotionContrast' tests/unit/CssDesignSystemTest.php`
Expected: FAIL — aucun bloc 768 ne replie les `.form-grid*` ni `.form-actions__group` ; `prefers-contrast` ne mentionne pas `--border`.

- [ ] **Step 3 : implémenter**

Dans le bloc `@media (max-width: 768px)` de la section « 34. Responsive », ajouter :

```css
    .form-grid,
    .form-grid--2,
    .form-grid--3,
    .form-grid--4 {
        grid-template-columns: 1fr;
    }

    .form-actions__group {
        flex-direction: column;
        align-items: stretch;
    }

    .form-actions__group .btn {
        width: 100%;
    }
```

Adapter le bloc `@media (max-width: 480px)` pour utiliser les tokens d'espacement (`--space-3`) sur les paddings réduits (`.header`, etc.) au lieu de `12px`. (Les styles de la page de connexion — `.login-container` — sont hors périmètre : mode dev non servi en production.)

Dans `@media (prefers-contrast: high)`, ajouter sous `:root` un renforcement explicite des bordures via token :

```css
        --border: #000;
```

(cf. valeurs `--grey-300`/`--grey-400`/`--grey-500` déjà redéfinies). Vérifier que `prefers-reduced-motion` et `@media print` restent présents sans régression.

- [ ] **Step 4 : lancer le test pour vérifier le succès**

Run: `rtk phpunit --no-coverage --filter 'testFormsCollapse|testActionsStack|testSmallPhones|testMotionContrast' tests/unit/CssDesignSystemTest.php`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): consolider le responsive 768/480 et les garde-fous"
```

---

### Task 7 : Focus visible, cibles tactiles et contrastes AA

**Files:**
- Modify: `public/css/style.css` (section « 3. Focus Styles » 164–175, `.form-control:focus` §15, bloc 768 pour `.header__menu-btn`/`.sidebar__item`)
- Test: `tests/unit/CssDesignSystemTest.php` (ajouter des méthodes)

**Interfaces:**
- Consumes: `ruleBody()`, `mediaBlocks()` ; tokens `--focus-ring-color`, `--focus-ring-offset`, `--color-primary`.
- Produces: anneau de focus `:focus-visible` + offsets, focus des champs tokenisé, cibles ≥ 44 px en mobile.

- [ ] **Step 1 : écrire le test qui échoue**

Ajouter à `CssDesignSystemTest` :

```php
    public function testFocusRingIsTokenised(): void
    {
        $ring = $this->ruleBody(':focus-visible');
        $this->assertStringContainsString('var(--focus-ring-color)', $ring);
        $this->assertStringContainsString('var(--focus-ring-offset)', $ring);
    }

    public function testFieldFocusUsesFocusRingToken(): void
    {
        $this->assertStringContainsString('var(--focus-ring-color)', $this->ruleBody('.form-control:focus'));
    }

    public function testTouchTargetsAreComfortableOnMobile(): void
    {
        $tablet = $this->mediaBlocks('(max-width: 768px)');
        $this->assertStringContainsString('.header__menu-btn', $tablet);
        $this->assertStringContainsString('min-height: 44px', $tablet);
        $this->assertStringContainsString('.sidebar__item', $tablet);
    }
```

- [ ] **Step 2 : lancer le test pour vérifier l'échec**

Run: `rtk phpunit --no-coverage --filter 'testFocusRing|testFieldFocus|testTouchTargets' tests/unit/CssDesignSystemTest.php`
Expected: FAIL — `.form-control:focus` utilise `var(--color-primary)` mais le test cible `--focus-ring-color` (absent jusqu'à Task 3 ; si Task 3 faite, `testFieldFocus` passe mais `testTouchTargets` échoue car le bloc 768 ne fixe pas `min-height: 44px` pour `.header__menu-btn`).

- [ ] **Step 3 : implémenter**

Compléter la règle `:focus-visible` (section 3) par un anneau additionnel :

```css
:focus-visible {
    outline: 3px solid var(--focus-ring-color);
    outline-offset: var(--focus-ring-offset);
    box-shadow: 0 0 0 var(--focus-ring-offset) var(--focus-ring-color);
}
```

Dans le bloc `@media (max-width: 768px)`, ajouter :

```css
    .header__menu-btn {
        min-width: 44px;
        min-height: 44px;
        justify-content: center;
    }

    .sidebar__item {
        min-height: 44px;
        display: flex;
        align-items: center;
    }
```

Vérifier que le focus n'est jamais supprimé sans remplacement : `.report-transmit:focus-within .tooltip` et `.consent-consigne:focus-within .tooltip` (contrat `UiLayoutCssTest`) doivent rester intacts.

- [ ] **Step 4 : lancer le test pour vérifier le succès**

Run: `rtk phpunit --no-coverage --filter 'testFocusRing|testFieldFocus|testTouchTargets' tests/unit/CssDesignSystemTest.php`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): focus visible et cibles tactiles 44px"
```

---

## Phase 5 — Thèmes registres

### Task 8 : Matrice des 10 thèmes (`--theme-*` + `.card--` / `.badge--` / `.btn--`) et repli neutre

**Files:**
- Modify: `public/css/style.css` (section « 11. Cards » lignes 692–694 ; sections badges ~1910 et boutons ~1109 si modificateurs manquants)
- Test: `tests/unit/CssDesignSystemTest.php` (ajouter des méthodes)

**Interfaces:**
- Consumes: `ruleBody()`, tokens `--theme-<clé>` (10), `--border`, `--grey-500`.
- Produces: chaque `.card--<clé>` / `.badge--<clé>` / `.btn--<clé>` consomme `var(--theme-<clé>)` ; base `.card`/`.badge` neutre pour les thèmes inconnus.

**Rappel du contrat (spec, `RegistryRepository::themeClasses`) :** le HTML choisit la classe via `color_theme` (`FormattingService::getRegistryBadgeClass()`, `RegistryCardService`) ; le CSS ne connaît que des tokens. Aucun `match` de couleurs côté CSS.

- [ ] **Step 1 : écrire le test qui échoue**

Ajouter à `CssDesignSystemTest` :

```php
    /** @return list<string> */
    private static function themeKeys(): array
    {
        return ['rsst', 'rami', 'dgi', 'vert', 'violet', 'orange', 'teal', 'indigo', 'rose', 'ambre'];
    }

    public function testEveryThemeModifierConsumesItsToken(): void
    {
        foreach (self::themeKeys() as $key) {
            foreach (['.card--', '.badge--', '.btn--'] as $prefix) {
                $selector = $prefix . $key;
                $body = $this->ruleBody($selector);
                $this->assertNotSame('', $body, "Règle manquante : $selector");
                $this->assertStringContainsString(
                    "var(--theme-$key)",
                    $body,
                    "$selector doit consommer var(--theme-$key)."
                );
            }
        }
    }

    public function testUnknownThemeFallsBackToNeutral(): void
    {
        $this->assertStringContainsString('var(--grey-500)', $this->ruleBody('.badge'));
        $this->assertStringContainsString('var(--border)', $this->ruleBody('.card'));
        $this->assertStringContainsString('var(--color-primary)', $this->ruleBody('.btn'));
    }
```

- [ ] **Step 2 : lancer le test pour vérifier l'échec**

Run: `rtk phpunit --no-coverage --filter 'testEveryTheme|testUnknownTheme' tests/unit/CssDesignSystemTest.php`
Expected: FAIL — `.card--rsst` consomme `var(--rsst-color)` (idem `rami`, `dgi`).

- [ ] **Step 3 : implémenter**

Aligner les trois cartes historiques sur le contrat `--theme-*` :

```css
.card--rsst { border-top: 4px solid var(--theme-rsst); }
.card--rami { border-top: 4px solid var(--theme-rami); }
.card--dgi  { border-top: 4px solid var(--theme-dgi); }
```

Vérifier par lecture que les 27 autres modificateurs (`.card--vert/orange/teal/indigo/rose/ambre`, `.badge--<10>`, `.btn--<10>`) consomment bien `var(--theme-<clé>)` ; corriger toute occurrence littérale. Conserver les variables `--rsst-color`/`--rami-color`/`--dgi-color` si elles sont référencées ailleurs (ne pas casser d'autres règles), mais plus aucune `.card--rsst/rami/dgi` ne doit les utiliser. Vérifier que `.badge` (base) porte `background: var(--grey-500)` et `.card`/`.btn` restent neutres par défaut (Task 2 / valeurs historiques).

- [ ] **Step 4 : lancer le test pour vérifier le succès**

Run: `rtk phpunit --no-coverage --filter 'testEveryTheme|testUnknownTheme' tests/unit/CssDesignSystemTest.php`
Expected: PASS (30 assertions de modificateurs + repli).

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): matrice des thèmes de registres pilotée par --theme-*"
```

---

## Phase 6 — Connexion (HORS PÉRIMÈTRE — annulée le 2026-09-23)

> **Décision 2026-09-23 : Task 9 annulée.** L'application de production est authentifiée directement par IIS ; `pages/login.php` / `public/css/login.css` sont un **mode dev hors production**, et la section « 22. Login Page » de `style.css` n'est pas servie en production. Les changements de Task 9 (`login.css` tokenisé, §22 tokenisée, `LoginCssTest`) ont été retirés. Le correctif de contraste du repli `.badge` (Task 8) et le reste du design sont conservés. Le contenu ci-dessous est gardé comme **référence historique** et **ne doit pas être ré-appliqué**.

### Task 9 (annulée) : Couche visuelle de la page de connexion (`login.css` + section 22)

**Files:**
- Modify: `public/css/login.css` (littéraux → tokens)
- Modify: `public/css/style.css` (section « 22. Login Page », lignes 2179–2256)
- Test: `tests/unit/LoginCssTest.php` (créer)

**Interfaces:**
- Consumes: tokens `:root` de `style.css` (chargé avant `login.css` par `pages/login.php`) ; `--role-agent`, `--role-superviseur`, `--role-chsct`, `--color-primary-dark`, `--grey-500`, `--grey-700`, `--font-size-lg`, `--font-size-md`, `--font-size-sm`, `--space-3`, `--space-6`, `--space-4`, `--space-5`, `--space-1`, `--border-radius`, `--border-radius-lg`, `--shadow-lg`, `--border`.
- Produces: `login.css` sans couleur littérale ; `style.css` §22 tokenisée ; cibles tactiles ≥ 48 px pour les boutons de profil.

- [ ] **Step 1 : écrire le test qui échoue**

Créer `tests/unit/LoginCssTest.php` :

```php
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
```

- [ ] **Step 2 : lancer le test pour vérifier l'échec**

Run: `rtk phpunit --no-coverage tests/unit/LoginCssTest.php`
Expected: FAIL — `login.css` contient `#555`, `#1e40af`, `#1e3a5f`, `#3b82f6`, `#6b7280` ; `.login-card` a `padding: 40px 32px` ; `.login-dev-badge` a `#fff3cd`/`#856404`.

- [ ] **Step 3 : implémenter**

Réécrire `public/css/login.css` en consommant les tokens :

```css
/* Login Page — dev mode quick-login styles (tokens du design system) */

.login-choose-profile {
    text-align: center;
    font-size: var(--font-size-lg);
    margin: var(--space-5) 0 var(--space-2);
    color: var(--grey-700);
}

.login-choose-hint {
    text-align: center;
    font-size: var(--font-size-md);
    color: var(--color-primary-dark);
    margin: 0 0 var(--space-4);
    font-weight: 600;
}

.login-quick-buttons {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    margin-top: var(--space-3);
}

.login-btn-wrapper { text-align: center; }

.login-quick-buttons .btn {
    justify-content: center;
    font-size: var(--font-size-lg);
    min-height: 48px;
    padding: var(--space-3) var(--space-6);
    width: 100%;
}

.login-btn--superviseur { background: var(--role-superviseur) !important; }
.login-btn--agent { background: var(--role-agent) !important; }
.login-btn--chsct { background: var(--role-chsct) !important; }

.login-btn-desc {
    display: block;
    font-size: var(--font-size-sm);
    color: var(--grey-500);
    margin-top: var(--space-1);
    font-style: italic;
}

.login-dev-notice { text-align: center; margin-top: var(--space-3); }
```

Dans `style.css` §22 : `.login-card { padding: var(--space-6); }` (au lieu de `40px 32px`), `.login-header { margin-bottom: var(--space-6); }`, `.login-dev-badge { background: var(--color-warning-bg); color: var(--color-warning-text); padding: var(--space-1) var(--space-3); font-size: var(--font-size-xs); margin-top: var(--space-2); }`, `.login-form .form-group { margin-bottom: var(--space-5); }`, `.login-dev-info { margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--border); font-size: var(--font-size-sm); }`.

Conserver `!important` uniquement là où il existait (surcharge de `.btn--primary`), et conserver la mention « dev » (aucun texte visible modifié par ce plan).

- [ ] **Step 4 : lancer le test pour vérifier le succès**

Run: `rtk phpunit --no-coverage tests/unit/LoginCssTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5 : commit**

```bash
git add public/css/login.css public/css/style.css tests/unit/LoginCssTest.php
git commit -m "style(css): couche visuelle de la connexion pilotée par les tokens"
```

---

## Phase 7 — Captures & validation finale

### Task 10 : Contrôle de synchronisation CSS des captures + régénération

**Files:**
- Create: `tools/check_screenshot_css.js`
- Modify: `docs/screenshots/*.png`, `public/screenshots/*.png` (régénérés), éventuellement `docs/screenshots/*.html` (voir décision)
- Test: exécution de l'outil en mode `--check`

**Interfaces:**
- Consumes: `public/css/style.css` (source de vérité) ; `docs/screenshots/*.html` (snapshots autonomes).
- Produces: `node tools/check_screenshot_css.js [--check]` — sortie 0 si chaque snapshot est synchronisé, 1 sinon.

**Décision documentée (état vérifié) :** les 17 snapshots embarquent une copie **gelée** de 60 084 octets du CSS, incluant 15 sélecteurs absents du CSS live (`.sidebar--open`, `.sidebar-overlay--visible`, `.sortable`, `.report-response`, `--placeholder`…). Remplacer en aveugle le `<style>` par le CSS courant supprimerait ces règles et casserait le rendu des snapshots. Le plan **ne réécrit donc pas** les snapshots automatiquement : il fournit un contrôle de dérive, puis décrit la régénération et la comparaison attendue. La validation visuelle de fond est la matrice **live** aux largeurs 480/768/1280 (Task 6/7 + E2E existants), les snapshots restant des références de documentation.

- [ ] **Step 1 : écrire l'outil (et vérifier son échec initial)**

Créer `tools/check_screenshot_css.js` :

```js
#!/usr/bin/env node
/**
 * tools/check_screenshot_css.js
 *
 * Les docs/screenshots/*.html sont des snapshots autonomes : chacun embarque
 * une copie complète du CSS dans un unique <style>. Après un changement de
 * public/css/style.css, ces copies divergent et les PNG ne reflètent plus
 * l'application. Cet outil signale la dérive sans réécrire (les snapshots
 * contiennent des sélecteurs locaux qu'une copie verbatim supprimerait).
 *
 * Usage: node tools/check_screenshot_css.js [--check]
 *   --check : sortie 1 si au moins un snapshot diverge (CI-friendly).
 */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const css = fs.readFileSync(path.join(ROOT, 'public', 'css', 'style.css'), 'utf8')
  .replace(/\s+/g, ' ')
  .trim();

const dir = path.join(ROOT, 'docs', 'screenshots');
const files = fs.readdirSync(dir).filter((f) => f.endsWith('.html'));
const checkOnly = process.argv.includes('--check');
let stale = 0;

for (const file of files) {
  const html = fs.readFileSync(path.join(dir, file), 'utf8');
  const m = html.match(/<style[^>]*>([\s\S]*?)<\/style>/);
  if (!m) {
    console.log(`SKIP  ${file} (pas de bloc <style>)`);
    continue;
  }
  const embedded = m[1].replace(/\s+/g, ' ').trim();
  if (embedded !== css) {
    stale++;
    console.log(`STALE ${file}`);
  }
}

console.log(`\n${stale}/${files.length} snapshot(s) divergent(s) de style.css`);
if (checkOnly) {
  process.exit(stale === 0 ? 0 : 1);
}
```

Run: `node tools/check_screenshot_css.js --check`
Expected: sortie `17/17 snapshot(s) divergent(s)` puis **exit 1** (état actuel : CSS live plus récent que les snapshots).

- [ ] **Step 2 : baseline des captures (avant)**

```bash
mkdir -p "$(node -e "console.log(require('os').tmpdir())")/sst-captures-baseline"
cp docs/screenshots/*.png "$(node -e "console.log(require('os').tmpdir())")/sst-captures-baseline/"
```

(Copier les 17 PNG annotés hors du dépôt, dans le répertoire temporaire de la plateforme — jamais `/tmp` ni `C:\tmp`.)

- [ ] **Step 3 : régénérer les captures (pipeline existant)**

```bash
py -3 tools/capture_screenshots.py
py -3 tools/annotate_screenshots.py
```

Expected: `17/17 captures reussies` ; PNG régénérés dans `public/screenshots/` et copiés dans `docs/screenshots/`.

- [ ] **Step 4 : comparer et documenter les écarts**

Les snapshots embarquant un CSS gelé, les PNG régénérés doivent être **identiques** à la baseline (pipeline non régressé) : confirmer par comparaison octet/visuelle que `capture_screenshots.py` + `annotate_screenshots.py` produisent bien le même résultat. L'écart visuel attendu (cartes avec bordure, ombres `--shadow-sm`, tableaux en-têtes majuscules, boutons 44 px) est validé **sur l'application live** (serveur de dev Playwright) aux largeurs 480/768/1280 ; consigner les écarts voulus dans le message de commit.

Ne réécrire les `<style>` des snapshots que si une passe dédiée décide de les régénérer depuis l'application (hors périmètre de cette étape).

- [ ] **Step 5 : commit**

```bash
git add tools/check_screenshot_css.js docs/screenshots/*.png public/screenshots/*.png
git commit -m "docs(screenshots): contrôle de synchronisation CSS et régénération des captures"
```

---

### Task 11 : Validation finale (spec §Stratégie de validation)

**Files:**
- Aucun fichier attendu en création/modification. Si un test échoue à cause d'une régression CSS, corriger `public/css/style.css` (ou le test fautif) dans un commit dédié et relancer la boucle.

**Interfaces:**
- Consumes: toutes les tâches 1–10.
- Produces: jeu de preuves de validation (sorties de commandes consignées).

- [ ] **Step 1 : non-régression PHP**

```bash
rtk phpunit --no-coverage
```

Expected: **2225+ tests verts** (aucune classe renommée ; les tests assertant `btn--danger`, `badge--*`, `btn--transmit`, etc. restent verts).

- [ ] **Step 2 : analyse statique**

```bash
rtk phpstan analyse --memory-limit=1G
```

Expected: 0 erreur (level 8).

- [ ] **Step 3 : conformité CSP (job CI `csp-checks`)**

```bash
rtk php tools/check_inline_styles.php
rtk php tools/check_css_classes.php --missing
node tools/check_screenshot_css.js --check
```

Expected: aucune erreur de style inline ; aucune classe HTML manquante côté CSS ; le contrôle de snapshots est attendu **exit 1** tant que la Task 10 Step 4 n'est pas tranchée (à consigner, non bloquant pour la CI puisqu'il n'y est pas branché).

- [ ] **Step 4 : matrice de thèmes (10 clés × card/badge/btn)**

Vérifier manuellement dans un navigateur (ou via le serveur de dev) les 10 `color_theme` sur `.card--*`, `.badge--*`, `.btn--*`, **et** un registre custom avec un `color_theme` inconnu : rendu neutre (gris/primary), **aucune exception, aucun écran vide**. Confirmer la couverture par `testEveryThemeModifierConsumesItsToken` + `testUnknownThemeFallsBackToNeutral` (verts).

- [ ] **Step 5 : responsive + accessibilité**

Contrôler aux largeurs **480 px**, **768 px**, **1280 px** : sidebar en panneau superposé (CSS-only) sous 768, tableaux empilés via `data-label`, grilles en une colonne, boutons pleine largeur. Parcours clavier complet (skip-links `#main-content`/`#main-nav`, focus visible, checkbox CSS-only activable au clavier), contrastes AA, `prefers-reduced-motion` et `prefers-contrast` (neutres).

- [ ] **Step 6 : E2E Playwright (shards existants)**

```bash
npx playwright test --project=firefox
```

Expected: shards verts (navigation, formulaires, rôles, etc.) — garantit qu'aucun parcours n'a été cassé.

- [ ] **Step 7 : commit (uniquement si une correction a été nécessaire)**

```bash
git add public/css/style.css
git commit -m "fix(css): corriger la régression détectée par la validation finale"
```

Sinon, aucun commit : la validation est une preuve, pas un livrable.

---

## Spec coverage map

| Section de la spec | Tâche(s) |
|---|---|
| Tokens CSS (marque, neutres, sémantique, typo, espacements, rayons/ombres/transitions/z, focus) | Task 1 |
| Shell (header / sidebar / main / skip-link / overlay / toggle / ordre z / marqueur actif) | Task 5 |
| Composants — Cartes | Task 2 |
| Composants — Tableaux (`.table-wrapper`, `--responsive` + `data-label`) | Task 4 |
| Composants — Formulaires (group/control/grid/states/error-summary/actions) | Task 3 |
| Responsive (768 / 480 / reduced-motion / contrast / print) | Task 6 |
| Accessibilité (focus visible, cibles 44 px, contrastes AA, clavier, ordre z, sémantique) | Task 7 |
| Compatibilité thèmes registres (10 clés, 3 modificateurs, repli neutre) | Task 8 |
| Connexion (page de login, `login.css`, §22) | **Hors périmètre** — mode dev, non servi en production (Task 9 annulée) |
| Stratégie de validation — non-régression, CSP, contrat visuel, matrice thèmes, responsive, a11y, E2E | Task 11 |
| Stratégie de validation — captures avant/après | Task 10 |
| Périmètre serré / noms de classes / pas de style inline / terminologie / pas de manuel | Global Constraints |

---

## Risques & décisions

- **Snapshots de capture gelés** (Task 10) : les `docs/screenshots/*.html` embarquent une copie figée du CSS et contiennent 15 sélecteurs absents du CSS live. Une synchronisation verbatim casserait leur rendu. Décision : contrôle de dérive uniquement, validation visuelle sur l'application live. À rouvrir si l'équipe veut régénérer les snapshots depuis l'app.
- **Ordre z (Task 5)** : la prose de la spec contredit la table des valeurs. La table fait foi (overlay 150 > header 100 ; panneau 200 ; skip-link 9999) ; le panneau démarre sous le header via `top: var(--header-height)`.
- **`:invalid`/`:valid` (Task 3)** : conditionnés à `:not(:placeholder-shown)` pour éviter les faux positifs — les champs sans attribut `placeholder` ne seront donc jamais marqués invalides en direct. Si un formulaire doit exposer l'état d'erreur, il continue de s'appuyer sur `.form-error-summary` (déjà en place).
- **Changements visuels assumés** : `.card` gagne une bordure et passe `--shadow` → `--shadow-sm` ; `.card` padding 20 px → 16 px (`--space-4`) ; en-têtes de tableaux passent en majuscules. Ces écarts sont conformes à la spec et doivent être confirmés sur l'app live.
- **Connexion hors périmètre (2026-09-23)** : les styles de la page de connexion (mode dev, non servis en production) sont sortis du périmètre ; Task 9 et `LoginCssTest` sont annulés, `login.css` et la §22 de `style.css` restaurés à l'état pré-Task 9. Seul le correctif de contraste du repli `.badge` est conservé.
- **Pas de refonte globale des 145 hex hors `:root`** : le périmètre tokenise les composants listés par la spec. Une passe ultérieure pourra étendre la tokenisation aux pages utilitaires (statistiques, journaux, aide).

---

## Critères de validation globaux

1. **Contrat de tokens** : `testEveryCanonicalTokenExistsWithExpectedValue` vert (82 tokens, valeurs exactes de la spec).
2. **Composants tokenisés** : cartes, formulaires, tableaux, shell — aucun `px`/`rem`/hex littéral sur les propriétés ciblées.
3. **Responsive** : repli 1 colonne + actions pleine largeur sous 768 ; densité réduite en 480 ; `reduced-motion`/`contrast`/`print` conservés.
4. **Accessibilité** : focus visible tokenisé, cibles ≥ 44 px, contrastes AA, ordre de tabulation inchangé, contrôles CSS-only activables au clavier.
5. **Thèmes** : 30 modificateurs consomment `--theme-*` ; repli neutre pour un thème inconnu.
6. **Connexion (hors périmètre)** : `login.css` / §22 restent dans leur état d'origine (mode dev non servi en production) ; aucune assertion de contrat ne les couvre.
7. **Zéro renommage de classe** : suite PHPUnit complète verte (2225+ tests), PHPStan 0 erreur.
8. **CSP** : `check_inline_styles.php` et `check_css_classes.php --missing` sans erreur ; CSS confiné à `public/css/`.
9. **E2E** : shards Playwright Firefox verts.
10. **Périmètre** : aucun template/handler/service/route modifié ; aucun manuel Markdown ; terminologie CSA/CHSCT inchangée.