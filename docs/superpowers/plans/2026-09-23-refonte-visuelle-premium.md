# Refonte visuelle premium (seconde passe) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Appliquer la direction visuelle « institutionnel premium laptop » de la spec `docs/superpowers/specs/2026-09-23-refonte-visuelle-premium-design.md` sur `public/css/style.css`, `public/js/wordcloud.js` et une unique retouche markup dans `src/helpers/registry_card_renderer.php`, sans toucher aux routes, à la logique métier, aux services, aux repositories, à la base de données ni à l'authentification IIS.

**Architecture :** `public/css/style.css` reste l'unique source de vérité visuelle. Le bloc `:root` est étendu (tokens de surface, accents `--theme-<clé>-tint` / `--theme-<clé>-ink`, tokens de nuage de mots, bornes laptop) puis les composants du périmètre (header, sidebar, cartes de registre, légende, nuage) sont recolorés en consommant exclusivement ces tokens. Le HTML ne code jamais une couleur : il porte `registry-card--<clé>` et le CSS résout les trois tokens de la clé ; un thème inconnu dégrade vers le neutre (`--surface` + `--border`). La refonte est pilotée par TDD : les contrats PHPUnit lisent `style.css` (contrat de tokens, contraste, matrice premium), chaque tâche fait échouer un test avant d'implémenter, et la matrice laptop 1024/1280/1440 est verrouillée par Playwright.

**Tech Stack :** CSS statique (`public/css/style.css`, 4228 lignes ; servi par `css.php`), JavaScript vanilla (`public/js/wordcloud.js`, servi par `js.php`), PHP 8.3+ et PHPUnit 11 pour les contrats CSS, PHPStan level 8 + règles custom (`NoInlineStyleRule`, `NoSqlOutsideRepositoryRule`), Playwright (projets `firefox` et `msedge`), outils Python `tools/capture_screenshots.py` + `tools/annotate_screenshots.py` (Playwright/Pillow). **Aucune dépendance nouvelle, aucun bundler, aucun framework CSS.**

---

## Global Constraints

- **Périmètre serré** : cette passe modifie `public/css/style.css`, `public/js/wordcloud.js`, `src/helpers/registry_card_renderer.php` (une seule retouche markup), `tests/unit/*`, `e2e/*`, `playwright.config.js`, `tools/capture_screenshots.py`, `tools/annotate_screenshots.py`, `CHANGELOG.md`. **Aucun** autre template, handler, service, repository, route, `web.config`, CSP ni configuration IIS (spec §1, §10).
- **Appareil cible laptop** : priorité **1280 × 800**, contrôle **1440 × 900**, plancher **1024 × 768**. Mobile ≤ 768 px et ≤ 480 px **conservé, non optimisé** (spec §1, §9).
- **Markup gelé sauf une exception** : la seule modification de markup autorisée est la scission de `.registry-card__stat` en `.registry-card__stat-value` + `.registry-card__stat-label` (spec §5.6). Aucun renommage de classe (les tests PHPUnit/Playwright assertent `registry-card__btn`, `badge--*`, `btn--danger`, `synthesis-th--*`, etc.).
- **Tokens = source de vérité** (valeurs cibles verbatim de la spec §4) :
  - Modifiés : `--sidebar-bg` `#0F1D33`, `--sidebar-text` `#C7D2E0`, `--sidebar-hover` `rgba(255,255,255,0.06)`, `--sidebar-active` `#4A9EE8`, `--border` `#E4E9F0`.
  - Surfaces ajoutées : `--surface` `#FFFFFF`, `--surface-muted` `#F7F9FC`, `--surface-sunken` `#EEF2F7`, `--card-border` `#E1E7EF`, `--card-shadow` `0 1px 2px rgba(15,29,51,0.04), 0 1px 3px rgba(15,29,51,0.06)`, `--card-shadow-hover` `0 2px 4px rgba(15,29,51,0.06), 0 6px 16px rgba(15,29,51,0.08)`, `--radius-xl` `12px`, `--radius-pill` `999px`, `--header-surface` `var(--surface)`, `--header-border` `var(--card-border)`.
  - Accents par registre (10 clés `rsst`, `rami`, `dgi`, `vert`, `violet`, `orange`, `teal`, `indigo`, `rose`, `ambre`) : `--theme-<clé>` inchangé, plus `--theme-<clé>-tint` et `--theme-<clé>-ink` (table spec §4.3).
  - Bornes/typo : `--stat-size` `clamp(1.5rem, 1.2rem + 1vw, 2rem)`, `--laptop-min` `1024px`, `--content-max-width` `1240px`.
  - Nuage de mots : `--word-cloud-min` `0.8rem`, `--word-cloud-max` `1.5rem` ; `--word-cloud-ink` défini par clé sur `.registry-card--<clé>` = `var(--theme-<clé>-ink)`.
- **Tokens non cités conservés à l'identique** : notamment `--sidebar-width` `220px`, `--header-height` `60px`, `--content-padding` `24px`, `--color-primary` `#0056A3`, l'échelle `--grey-*`, `--space-*`, `--shadow-*`, `--focus-ring-*`, les z-index.
- **`CssDesignSystemTest` mis à jour dans le même changement** : il verrouille les valeurs exactes des tokens (spec §4, §8.3).
- **Accessibilité** : contraste AA ≥ 4.5:1 (texte normal) ; état actif sidebar et accent de registre doublés d'un marqueur non chromatique (barre/pastille) ; focus visible tokenisé conservé ; `prefers-reduced-motion` neutralise l'effet de survol des cartes ; `prefers-contrast` conservé ; rôles `banner`/`navigation`/`main` et `aria-label` du nuage intacts (spec §7).
- **Pas de styles inline** : `style=` interdit (CSP + `NoInlineStyleRule` + `tools/check_inline_styles.php`).
- **Pas de couleur codée en dur dans le HTML** : le markup porte la classe de thème, jamais la couleur (spec §5.4).
- **Aucune requête HTTP supplémentaire**, polices système uniquement, pas d'icône externe (spec O10).
- **Terminologie** : toujours **CSA/CHSCT** dans tout texte visible (aucun texte visible modifié ici hormis le libellé du compteur, inchangé).
- **Qualité** : `rtk phpunit --no-coverage` et `rtk phpstan analyse --memory-limit=1G` verts avant chaque commit ; `php tools/check_inline_styles.php` et `php tools/check_css_classes.php --missing` sans erreur ; E2E Playwright verts.
- **Aucun commit d'implémentation par le rédacteur de ce plan** : ce document ne modifie aucun fichier de production. Le seul commit produit *maintenant* est celui du plan lui-même (`docs(plan): plan de refonte visuelle premium laptop`).

---

## Baseline vérifiée du dépôt (2026-09-23)

- `public/css/style.css` : **4228 lignes**. Le bloc `:root` (lignes 10–135) contient les tokens canoniques de la première passe (3.66.1).
- `.header` (212–226) : fond `var(--color-primary)`, `color: white`, `box-shadow: var(--shadow-md)` ; `.header__logout` (265), `.header__menu-btn` (521), `.impersonate-btn` (294) et les masques `.impersonate-icon::before/::after` (317–338) sont en blanc/translucide blanc — ils deviennent illisibles si le header passe sur surface claire et doivent être recolorés (CSS seul).
- `.sidebar` (543–557), `.sidebar__item` (579–587), `.sidebar__item--active` (594–599) : consomment déjà `--sidebar-bg`/`--sidebar-text`/`--sidebar-hover`/`--sidebar-active`.
- `.registry-cards` (714–723) : `display:flex; flex-wrap:wrap` + `.registry-cards > * { flex:1; min-width:250px }` → ne peut pas produire 3 colonnes à 1024 px avec `--sidebar-width` 220 + `--content-padding` 24.
- `.registry-card` (725–741) : fond saturé plein par thème (`color: white`), `.registry-card--<clé> { background: var(--theme-<clé>); }` (760–922), `.registry-card--dgi` est une inversion rouge pleine (792–810).
- `.registry-card__stat` (927) : texte « N signalement(s) enregistré(s) » produit par `registry_card_renderer.php` ligne 37.
- `.workflow-legend` (1096–1107) : `padding: 12px 16px`, `box-shadow: var(--shadow)`, `font-size: 12px`.
- `.word-cloud` (4132–4137) `min-height: 180px` ; `.word-cloud__word` (4139–4148) `color: rgba(255,255,255,0.85)` + `text-shadow` (palette prévue pour l'ancien fond foncé) ; `.wc-s1` `0.6rem` → `.wc-s10` `1.5rem` (4161–4170) ; `.wc-c1..6` blanc/bleu clair (4173–4178).
- `public/js/wordcloud.js` (91 lignes) : palette `colors` blanc/bleu (ligne 23), taille `0.5 + p*0.07` (ligne 39), couleur inline aléatoire (ligne 42), collisions `+8`/`+4` (69–70), `step < 1500` (53), `el.style.height = '200px'` (14).
- `src/helpers/registry_card_renderer.php` ligne 37 : `.registry-card__stat` reçoit le libellé complet en un seul texte.
- `tests/unit/RegistryCardRendererTest.php` : 5 assertions sur le texte du compteur (lignes 173, 194, 589, 590, 591) à mettre à jour.
- `tests/unit/CssDesignSystemTest.php` : `expectedTokens()` (43–115) verrouille `--border => var(--grey-300)` + les 10 `--theme-<clé>` ; `testHeaderConsumesTokens()` (361–369) verrouille `background: var(--color-primary)` et `box-shadow: var(--shadow-md)`.
- `tests/unit/CssContrastContractTest.php` : helpers `rootTokens()`, `resolveToken()`, `contrastRatio()`, constante `MIN_CONTRAST = 4.5`.
- `playwright.config.js` ligne 126 : `viewport: { width: 1280, height: 720 }`.
- `tools/capture_screenshots.py` ligne 46 : viewport `1280×900` ; `tools/annotate_screenshots.py` ligne 603 : viewport `{"width": 1280, "height": 900}`.
- `src/Services/AssetService.php` : `cssLink()` construit `css.php?f=…&v=<version>` ; `templates/footer.php` charge `js.php?f=js/wordcloud.js&v=<version>`. La version provient de `ConfigService::getAppVersion()` = première entrée `## [x.y.z]` de `CHANGELOG.md` (actuellement `3.66.1`).
- Contrastes calculés sur valeurs cibles (WCAG, vérifiés) : `--theme-<clé>-ink` sur `--theme-<clé>-tint` ∈ [6.96, 9.08] ; `--sidebar-text #C7D2E0` sur `--sidebar-bg #0F1D33` = 11.03 ; `white` sur `--sidebar-bg` = 16.88 ; `--sidebar-active #4A9EE8` sur `--sidebar-bg` = 5.91. Tous ≥ 4.5:1.

---

## File Structure

| Fichier | Rôle | Tâches |
|---|---|---|
| `public/css/style.css` | Unique feuille applicative : tokens premium, shell, cartes, légende, nuage, grille laptop | 1–3, 5–7, 9, 10 |
| `src/helpers/registry_card_renderer.php` | Scission du compteur en `__stat-value` + `__stat-label` (unique retouche markup) | 4 |
| `public/js/wordcloud.js` | Encre sombre, taille bornée, collisions élargies, étapes augmentées | 8 |
| `tests/unit/CssDesignSystemTest.php` | Contrat de tokens mis à jour (valeurs §4) + `testHeaderConsumesTokens` | 1, 3 |
| `tests/unit/CssRegistryCardPremiumTest.php` | **Créé** — verrouille tint/ink, pastille, compteur dominant, repli neutre, marqueur DGI | 5 |
| `tests/unit/CssContrastContractTest.php` | Paires de contraste tint/ink et sidebar | 10 |
| `tests/unit/RegistryCardRendererTest.php` | Assertions du compteur scindé | 4 |
| `e2e/visual-premium.spec.js` | **Créé** — matrice laptop 1024/1280/1440 + captures `docs/screenshots/premium/` | 11 |
| `playwright.config.js` | Viewport par défaut `1280×800` | 11 |
| `tools/capture_screenshots.py` | Viewport `1280×800` | 12 |
| `tools/annotate_screenshots.py` | Viewport d'annotation `1280×800` | 12 |
| `CHANGELOG.md` | Entrée `3.66.2` (cache-busting des deux ressources) | 13 |

Aucun autre fichier n'est créé ou modifié par ce plan.

---

## Phase 1 — Tokens premium

### Task 1 : Tokens `:root` premium (surfaces, accents tint/ink, nuage, bornes) + contrat mis à jour

**Files:**
- Modify: `public/css/style.css` (bloc `:root`, lignes 10–135)
- Test: `tests/unit/CssDesignSystemTest.php` (`expectedTokens()`, `testEveryCanonicalTokenExistsWithExpectedValue`)

**Interfaces:**
- Consumes: —
- Produces: tokens `--surface`, `--surface-muted`, `--surface-sunken`, `--card-border`, `--card-shadow`, `--card-shadow-hover`, `--radius-xl`, `--radius-pill`, `--header-surface`, `--header-border`, `--sidebar-active-tint`, `--stat-size`, `--laptop-min`, `--content-max-width`, `--word-cloud-min`, `--word-cloud-max`, `--theme-<clé>-tint`, `--theme-<clé>-ink` (10 clés). Toutes les tâches suivantes consomment ces tokens.

- [ ] **Step 1 : mettre à jour le test qui échoue**

Dans `tests/unit/CssDesignSystemTest.php`, remplacer dans `expectedTokens()` la ligne `'--border' => 'var(--grey-300)',` par :

```php
            '--border' => '#E4E9F0',
```

Puis, juste après la ligne `'--focus-ring-offset' => '2px',` (avant la fermeture `];`), ajouter le bloc de tokens premium :

```php
            '--sidebar-bg' => '#0F1D33',
            '--sidebar-text' => '#C7D2E0',
            '--sidebar-hover' => 'rgba(255,255,255,0.06)',
            '--sidebar-active' => '#4A9EE8',
            '--sidebar-active-tint' => 'rgba(74,158,232,0.16)',
            '--surface' => '#FFFFFF',
            '--surface-muted' => '#F7F9FC',
            '--surface-sunken' => '#EEF2F7',
            '--card-border' => '#E1E7EF',
            '--card-shadow' => '0 1px 2px rgba(15,29,51,0.04), 0 1px 3px rgba(15,29,51,0.06)',
            '--card-shadow-hover' => '0 2px 4px rgba(15,29,51,0.06), 0 6px 16px rgba(15,29,51,0.08)',
            '--radius-xl' => '12px',
            '--radius-pill' => '999px',
            '--header-surface' => 'var(--surface)',
            '--header-border' => 'var(--card-border)',
            '--stat-size' => 'clamp(1.5rem, 1.2rem + 1vw, 2rem)',
            '--laptop-min' => '1024px',
            '--content-max-width' => '1240px',
            '--word-cloud-min' => '0.8rem',
            '--word-cloud-max' => '1.5rem',
```

Puis, à l'intérieur de la boucle `foreach ([ 'rsst' => … ] as $key => $value)` qui construit les tokens `--theme-<clé>`, ajouter les deux tables `tint`/`ink` (après la fermeture de la boucle, avant `return $tokens;`) :

```php
        foreach ([
            'rsst' => ['#EDF2F8', '#24486D'],
            'rami' => ['#F2F2F2', '#4F4F4F'],
            'dgi' => ['#FDF0F0', '#8F1616'],
            'vert' => ['#EDF7F0', '#116032'],
            'violet' => ['#F3EFFD', '#5F2DB5'],
            'orange' => ['#FDF1EB', '#93320A'],
            'teal' => ['#EAF6F4', '#0B5A54'],
            'indigo' => ['#EFEEFB', '#342CA0'],
            'rose' => ['#FCEEF2', '#92102E'],
            'ambre' => ['#FCF4E9', '#87400B'],
        ] as $key => [$tint, $ink]) {
            $tokens['--theme-' . $key . '-tint'] = $tint;
            $tokens['--theme-' . $key . '-ink'] = $ink;
        }
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest::testEveryCanonicalTokenExistsWithExpectedValue`
Expected: FAIL avec « Token --surface manquant dans :root. » (ou le premier token ajouté absent).

- [ ] **Step 3 : implémenter les tokens**

Dans `public/css/style.css`, modifier les lignes existantes :

```css
    /* Bordures */
    --border: #E4E9F0;
```

```css
    /* Barre latérale */
    --sidebar-bg: #0F1D33;
    --sidebar-text: #C7D2E0;
    --sidebar-hover: rgba(255,255,255,0.06);
    --sidebar-active: #4A9EE8;
    --sidebar-active-tint: rgba(74,158,232,0.16);
```

Juste après le bloc `--theme-ambre: #B45309;`, ajouter les fonds/encres accentués :

```css
    /* Accents par registre — fonds clairs et encres lisibles (≥ 4.5:1 sur leur tint) */
    --theme-rsst-tint: #EDF2F8;    --theme-rsst-ink: #24486D;
    --theme-rami-tint: #F2F2F2;    --theme-rami-ink: #4F4F4F;
    --theme-dgi-tint: #FDF0F0;     --theme-dgi-ink: #8F1616;
    --theme-vert-tint: #EDF7F0;    --theme-vert-ink: #116032;
    --theme-violet-tint: #F3EFFD;  --theme-violet-ink: #5F2DB5;
    --theme-orange-tint: #FDF1EB;  --theme-orange-ink: #93320A;
    --theme-teal-tint: #EAF6F4;    --theme-teal-ink: #0B5A54;
    --theme-indigo-tint: #EFEEFB;  --theme-indigo-ink: #342CA0;
    --theme-rose-tint: #FCEEF2;    --theme-rose-ink: #92102E;
    --theme-ambre-tint: #FCF4E9;   --theme-ambre-ink: #87400B;
```

Juste avant la fermeture `}` du bloc `:root` (après `--focus-ring-offset: 2px;`), ajouter :

```css
    /* Surfaces premium */
    --surface: #FFFFFF;
    --surface-muted: #F7F9FC;
    --surface-sunken: #EEF2F7;
    --card-border: #E1E7EF;
    --card-shadow: 0 1px 2px rgba(15,29,51,0.04), 0 1px 3px rgba(15,29,51,0.06);
    --card-shadow-hover: 0 2px 4px rgba(15,29,51,0.06), 0 6px 16px rgba(15,29,51,0.08);
    --radius-xl: 12px;
    --radius-pill: 999px;
    --header-surface: var(--surface);
    --header-border: var(--card-border);

    /* Bornes laptop */
    --laptop-min: 1024px;
    --content-max-width: 1240px;

    /* Typographie premium */
    --stat-size: clamp(1.5rem, 1.2rem + 1vw, 2rem);

    /* Nuage de mots */
    --word-cloud-min: 0.8rem;
    --word-cloud-max: 1.5rem;
```

- [ ] **Step 4 : lancer le test pour vérifier qu'il passe**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest`
Expected: PASS (toutes les méthodes `CssDesignSystemTest` vertes).

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): tokens premium (surfaces, accents tint/ink) et contrat mis à jour"
```

---

## Phase 2 — Shell

### Task 2 : Sidebar bleu nuit et item actif à double signal (couleur + barre)

**Files:**
- Modify: `public/css/style.css` (`.sidebar__item--active`, lignes 594–599)
- Test: `tests/unit/CssDesignSystemTest.php` (`testSidebarActiveUsesColourAndBorderMarker`)

**Interfaces:**
- Consumes: `--sidebar-bg`, `--sidebar-text`, `--sidebar-hover`, `--sidebar-active`, `--sidebar-active-tint` (Task 1).
- Produces: `.sidebar__item--active` porte un remplissage `--sidebar-active-tint` **et** une barre `border-left-color: var(--sidebar-active)` (marqueur non chromatique).

- [ ] **Step 1 : étendre le test qui échoue**

Dans `tests/unit/CssDesignSystemTest.php`, remplacer le corps de `testSidebarActiveUsesColourAndBorderMarker()` par :

```php
    public function testSidebarActiveUsesColourAndBorderMarker(): void
    {
        $active = $this->ruleBody('.sidebar__item--active');
        $this->assertStringContainsString('border-left-color: var(--sidebar-active)', $active);
        $this->assertStringContainsString('background: var(--sidebar-active-tint)', $active);
        $this->assertStringContainsString('color:', $active);
    }
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest::testSidebarActiveUsesColourAndBorderMarker`
Expected: FAIL — la règle actuelle porte `background: var(--sidebar-hover)`, pas `var(--sidebar-active-tint)`.

- [ ] **Step 3 : implémenter la règle**

Dans `public/css/style.css`, remplacer le bloc `.sidebar__item--active` par :

```css
.sidebar__item--active {
    background: var(--sidebar-active-tint);
    border-left-color: var(--sidebar-active);
    color: #FFFFFF;
    font-weight: 600;
}
```

- [ ] **Step 4 : lancer le test pour vérifier qu'il passe**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): sidebar bleu nuit et item actif à double signal"
```

---

### Task 3 : Header contemporain sur surface claire

**Files:**
- Modify: `public/css/style.css` (`.header` 212–226, `.header__logo-text` 238–245, `.header__title` 247–250, `.header__user` 252–257, `.header__logout` 265–277, `.header__menu-btn` 521–538, `.impersonate-btn` 294–308, `.impersonate-icon::before/::after` 317–338, hovers 340–352)
- Test: `tests/unit/CssDesignSystemTest.php` (`testHeaderConsumesTokens`)

**Interfaces:**
- Consumes: `--header-surface`, `--header-border`, `--card-border`, `--shadow-sm`, `--surface-sunken`, `--hover-highlight`, `--color-primary`, `--grey-900`, `--grey-700`, `--border`, `--space-5`, `--z-header`, `--header-height` (Task 1).
- Produces: `.header` sur surface claire avec bordure basse ; identité et rôle alignés ; déconnexion en action discrète. Le header conserve `height: var(--header-height)` et `z-index: var(--z-header)`.

- [ ] **Step 1 : mettre à jour le test qui échoue**

Dans `tests/unit/CssDesignSystemTest.php`, remplacer le corps de `testHeaderConsumesTokens()` par :

```php
    public function testHeaderConsumesTokens(): void
    {
        $header = $this->ruleBody('.header');
        $this->assertStringContainsString('height: var(--header-height)', $header);
        $this->assertStringContainsString('background: var(--header-surface)', $header);
        $this->assertStringContainsString('border-bottom: 1px solid var(--header-border)', $header);
        $this->assertStringContainsString('z-index: var(--z-header)', $header);
        $this->assertStringContainsString('padding: 0 var(--space-5)', $header);
        $this->assertStringContainsString('box-shadow: var(--shadow-sm)', $header);
    }
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest::testHeaderConsumesTokens`
Expected: FAIL — la règle actuelle porte `background: var(--color-primary)` et `box-shadow: var(--shadow-md)`.

- [ ] **Step 3 : implémenter le header et son contenu**

Dans `public/css/style.css`, remplacer le bloc `.header` par :

```css
.header {
    height: var(--header-height);
    background: var(--header-surface);
    color: var(--grey-900);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 var(--space-5);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: var(--z-header);
    border-bottom: 1px solid var(--header-border);
    box-shadow: var(--shadow-sm);
}
```

Remplacer `.header__logo-text` par :

```css
.header__logo-text {
    background: var(--color-primary);
    color: #FFFFFF;
    padding: 4px 12px;
    border-radius: var(--border-radius);
    font-weight: 700;
    font-size: 16px;
    letter-spacing: 1px;
}
```

Remplacer `.header__title` par :

```css
.header__title {
    font-size: 16px;
    font-weight: 500;
    color: var(--grey-900);
}
```

Remplacer `.header__user` par :

```css
.header__user {
    display: flex;
    align-items: center;
    gap: var(--space-4);
    font-size: 13px;
    color: var(--grey-700);
}
```

Remplacer `.header__logout` et son hover par :

```css
.header__logout {
    color: var(--grey-700);
    text-decoration: none;
    padding: 6px 12px;
    border-radius: var(--border-radius);
    transition: color var(--transition-base), background var(--transition-base);
}
.header__logout:hover {
    color: var(--color-primary);
    background: var(--surface-sunken);
    text-decoration: none;
}
```

Remplacer `.header__menu-btn` et son hover par :

```css
.header__menu-btn {
    display: none;
    background: none;
    border: 2px solid var(--border);
    border-radius: var(--border-radius);
    color: var(--grey-700);
    padding: 6px 10px;
    font-size: 20px;
    cursor: pointer;
    line-height: 1;
    transition: background var(--transition-fast), border-color var(--transition-fast);
    -webkit-user-select: none;
    user-select: none;
}
.header__menu-btn:hover,
.header__menu-btn:focus-visible {
    background: var(--surface-sunken);
    border-color: var(--color-primary);
}
```

Remplacer `.impersonate-btn` par :

```css
.impersonate-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--color-primary);
    background: var(--hover-highlight);
    border: 1px solid var(--card-border);
    border-radius: var(--border-radius);
    padding: 4px 10px;
    font-size: 0.85em;
    cursor: pointer;
    transition: background var(--transition-fast);
    -webkit-user-select: none;
    user-select: none;
}
```

Remplacer les masques et leurs hovers (blocs `.impersonate-icon::before`, `.impersonate-icon::after`, et les deux règles `:hover`/`:focus-visible`) par :

```css
.impersonate-icon::before,
.impersonate-icon::after {
    content: '';
    position: absolute;
    top: 0;
    width: 9px;
    height: 12px;
    border: 1.5px solid var(--color-primary);
    border-radius: 50% 50% 50% 50% / 35% 35% 65% 65%;
}
/* Left mask (happy — comedy) */
.impersonate-icon::before {
    left: 0;
    background: var(--hover-highlight);
}
/* Right mask (sad — tragedy), slightly offset */
.impersonate-icon::after {
    right: 0;
    top: 2px;
    background: transparent;
    border-style: dashed;
}

.impersonate-btn:hover,
.impersonate-btn:focus-visible {
    background: var(--surface-sunken);
    outline: none;
}
.impersonate-btn:hover .impersonate-icon::before,
.impersonate-btn:focus-visible .impersonate-icon::before {
    background: var(--color-primary-light);
}
.impersonate-btn:hover .impersonate-icon::after,
.impersonate-btn:focus-visible .impersonate-icon::after {
    background: var(--hover-highlight);
}
```

- [ ] **Step 4 : lancer le test pour vérifier qu'il passe**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): header contemporain sur surface claire"
```

---

## Phase 3 — Accueil

### Task 4 : Scission du compteur de carte (unique retouche markup)

**Files:**
- Modify: `src/helpers/registry_card_renderer.php` (lignes 24–26 et 37)
- Test: `tests/unit/RegistryCardRendererTest.php` (lignes 173, 194, 589, 590, 591)

**Interfaces:**
- Consumes: `RegistryCardData::$count` (int) et `e()` (échappement existant).
- Produces: markup `.registry-card__stat` contenant `.registry-card__stat-value` (le nombre) puis `.registry-card__stat-label` (le libellé « signalement(s) enregistré(s) »), séparés par une espace. **Aucune autre classe n'est renommée.**

- [ ] **Step 1 : mettre à jour les tests qui échouent**

Dans `tests/unit/RegistryCardRendererTest.php` :

Ligne 173, remplacer :

```php
        $this->assertStringContainsString('5 signalements enregistrés', $html);
```

par :

```php
        $this->assertStringContainsString('<span class="registry-card__stat-value">5</span>', $html);
        $this->assertStringContainsString('<span class="registry-card__stat-label">signalements enregistrés</span>', $html);
```

Ligne 194, remplacer :

```php
        $this->assertStringContainsString('1 signalement enregistré', $html);
```

par :

```php
        $this->assertStringContainsString('<span class="registry-card__stat-value">1</span>', $html);
        $this->assertStringContainsString('<span class="registry-card__stat-label">signalement enregistré</span>', $html);
```

Lignes 589–591, remplacer :

```php
        $this->assertStringContainsString('5 signalements enregistrés', $html);
        $this->assertStringContainsString('3 signalements enregistrés', $html);
        $this->assertStringContainsString('1 signalement enregistré', $html);
```

par :

```php
        $this->assertStringContainsString('<span class="registry-card__stat-value">5</span>', $html);
        $this->assertStringContainsString('<span class="registry-card__stat-value">3</span>', $html);
        $this->assertStringContainsString('<span class="registry-card__stat-value">1</span>', $html);
        $this->assertStringContainsString('<span class="registry-card__stat-label">signalements enregistrés</span>', $html);
        $this->assertStringContainsString('<span class="registry-card__stat-label">signalement enregistré</span>', $html);
```

- [ ] **Step 2 : lancer les tests pour vérifier qu'ils échouent**

Run: `rtk phpunit --no-coverage --filter RegistryCardRendererTest`
Expected: FAIL sur les 3 méthodes modifiées (le renderer produit encore le libellé en un seul texte).

- [ ] **Step 3 : implémenter la scission**

Dans `src/helpers/registry_card_renderer.php`, remplacer les lignes 24–26 :

```php
    $count = $card->count;
    $countLabel = $count . ' signalement' . ($count !== 1 ? 's' : '')
                . ' enregistré' . ($count !== 1 ? 's' : '');
```

par :

```php
    $count = $card->count;
    $countLabel = 'signalement' . ($count !== 1 ? 's' : '')
                . ' enregistré' . ($count !== 1 ? 's' : '');
```

Et remplacer la ligne 37 :

```php
    $html .= '<div class="registry-card__stat">' . $countLabel . '</div>';
```

par :

```php
    $html .= '<div class="registry-card__stat">';
    $html .= '<span class="registry-card__stat-value">' . e((string) $count) . '</span>';
    $html .= ' <span class="registry-card__stat-label">' . e($countLabel) . '</span>';
    $html .= '</div>';
```

- [ ] **Step 4 : lancer les tests pour vérifier qu'ils passent**

Run: `rtk phpunit --no-coverage --filter RegistryCardRendererTest`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add src/helpers/registry_card_renderer.php tests/unit/RegistryCardRendererTest.php
git commit -m "refactor(home): scinder le compteur des cartes de registre en valeur + libellé"
```

---

### Task 5 : Cartes de registre premium pilotées par tint/ink + nouveau contrat CSS

**Files:**
- Modify: `public/css/style.css` (`.registry-card` 725–741, 10 classes `.registry-card--<clé>` 764/780/796–800/816/832/848/864/880/896/912, `.registry-card__icon`/`__title`/`__subtitle`/`__stat` 924–927, `.registry-card__btn` 929–940, `__desc` 942–947, `__link` 949–961, hovers 959–966, `.home-action--large .registry-card__*` 1002–1025)
- Test: `tests/unit/CssRegistryCardPremiumTest.php` (créer)

**Interfaces:**
- Consumes: `--theme-<clé>`, `--theme-<clé>-tint`, `--theme-<clé>-ink` (10 clés, Task 1), `--surface`, `--card-border`, `--card-shadow`, `--card-shadow-hover`, `--radius-xl`, `--radius-pill`, `--stat-size`, `--theme-accent`/`--theme-ink` (variables locales posées par clé).
- Produces: chaque `.registry-card--<clé>` a un fond `--theme-<clé>-tint`, une barre `border-left-color: var(--theme-<clé>)`, `--word-cloud-ink` = `--theme-<clé>-ink` ; `.registry-card__icon` est une pastille `--radius-pill` sur `--theme-accent` ; `.registry-card__stat-value` utilise `--stat-size`.

- [ ] **Step 1 : écrire le test qui échoue**

Créer `tests/unit/CssRegistryCardPremiumTest.php` :

```php
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
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `rtk phpunit --no-coverage --filter CssRegistryCardPremiumTest`
Expected: FAIL (`.registry-card--rsst` porte encore `background: var(--theme-rsst)`, pas `-tint`).

- [ ] **Step 3 : implémenter la base et la matrice**

Dans `public/css/style.css`, remplacer `.registry-card` et `.registry-card:hover` (725–741) par :

```css
.registry-card {
    position: relative;
    background: var(--theme-tint, var(--surface));
    border: 1px solid var(--card-border);
    border-left: 4px solid var(--theme-accent, var(--border));
    border-radius: var(--radius-xl);
    padding: var(--space-6) var(--space-5);
    color: var(--grey-900);
    text-align: left;
    min-height: 220px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: var(--card-shadow);
    transition: transform var(--transition-base), box-shadow var(--transition-base);
}

.registry-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--card-shadow-hover);
}
```

Pour chacune des 10 clés, remplacer la ligne `.registry-card--<clé> { background: var(--theme-<clé>); }` par le bloc (exemple pour `rsst`, à répliquer pour les 10 clés avec leur nom et leurs deux tokens `-tint`/`-ink`) :

```css
/* --- rsst (Blue) --- */
.registry-card--rsst {
    background: var(--theme-rsst-tint);
    border-left-color: var(--theme-rsst);
    --theme-accent: var(--theme-rsst);
    --theme-ink: var(--theme-rsst-ink);
    --word-cloud-ink: var(--theme-rsst-ink);
}
```

Blocs complets des 10 clés (remplacent respectivement les lignes 764, 780, 796–800, 816, 832, 848, 864, 880, 896, 912) :

```css
.registry-card--rsst {
    background: var(--theme-rsst-tint);
    border-left-color: var(--theme-rsst);
    --theme-accent: var(--theme-rsst);
    --theme-ink: var(--theme-rsst-ink);
    --word-cloud-ink: var(--theme-rsst-ink);
}

.registry-card--rami {
    background: var(--theme-rami-tint);
    border-left-color: var(--theme-rami);
    --theme-accent: var(--theme-rami);
    --theme-ink: var(--theme-rami-ink);
    --word-cloud-ink: var(--theme-rami-ink);
}

.registry-card--dgi {
    background: var(--theme-dgi-tint);
    border-left-color: var(--theme-dgi);
    --theme-accent: var(--theme-dgi);
    --theme-ink: var(--theme-dgi-ink);
    --word-cloud-ink: var(--theme-dgi-ink);
}

.registry-card--dgi::after {
    content: '';
    position: absolute;
    top: var(--space-3);
    right: var(--space-3);
    width: 10px;
    height: 10px;
    border-radius: var(--radius-pill);
    background: var(--theme-dgi);
}

.registry-card--vert {
    background: var(--theme-vert-tint);
    border-left-color: var(--theme-vert);
    --theme-accent: var(--theme-vert);
    --theme-ink: var(--theme-vert-ink);
    --word-cloud-ink: var(--theme-vert-ink);
}

.registry-card--violet {
    background: var(--theme-violet-tint);
    border-left-color: var(--theme-violet);
    --theme-accent: var(--theme-violet);
    --theme-ink: var(--theme-violet-ink);
    --word-cloud-ink: var(--theme-violet-ink);
}

.registry-card--orange {
    background: var(--theme-orange-tint);
    border-left-color: var(--theme-orange);
    --theme-accent: var(--theme-orange);
    --theme-ink: var(--theme-orange-ink);
    --word-cloud-ink: var(--theme-orange-ink);
}

.registry-card--teal {
    background: var(--theme-teal-tint);
    border-left-color: var(--theme-teal);
    --theme-accent: var(--theme-teal);
    --theme-ink: var(--theme-teal-ink);
    --word-cloud-ink: var(--theme-teal-ink);
}

.registry-card--indigo {
    background: var(--theme-indigo-tint);
    border-left-color: var(--theme-indigo);
    --theme-accent: var(--theme-indigo);
    --theme-ink: var(--theme-indigo-ink);
    --word-cloud-ink: var(--theme-indigo-ink);
}

.registry-card--rose {
    background: var(--theme-rose-tint);
    border-left-color: var(--theme-rose);
    --theme-accent: var(--theme-rose);
    --theme-ink: var(--theme-rose-ink);
    --word-cloud-ink: var(--theme-rose-ink);
}

.registry-card--ambre {
    background: var(--theme-ambre-tint);
    border-left-color: var(--theme-ambre);
    --theme-accent: var(--theme-ambre);
    --theme-ink: var(--theme-ambre-ink);
    --word-cloud-ink: var(--theme-ambre-ink);
}
```

> Note : les anciennes règles DGI `.registry-card--dgi .registry-card__btn`, `:hover`, `.registry-card--dgi .registry-card__link`, `.registry-card--dgi .registry-card__desc` (797–800) sont **supprimées** (remplacées par le tronc commun + le marqueur `::after`).

Remplacer `.registry-card__icon`, `.registry-card__title`, `.registry-card__subtitle`, `.registry-card__stat` (924–927) et `.registry-card__btn`, `.registry-card__desc`, `.registry-card__link`, leurs hovers (929–966) par :

```css
.registry-card__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    margin-bottom: var(--space-3);
    border-radius: var(--radius-pill);
    background: var(--theme-accent, var(--grey-700));
    color: #FFFFFF;
    font-size: 22px;
    line-height: 1;
}

.registry-card__title {
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--grey-900);
    margin-bottom: var(--space-1);
}

.registry-card__subtitle {
    display: inline-block;
    font-size: var(--font-size-xs);
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--theme-ink, var(--grey-700));
    margin-bottom: var(--space-2);
}

.registry-card__desc {
    font-size: var(--font-size-sm);
    color: var(--grey-600);
    margin-top: var(--space-1);
    line-height: 1.5;
}

.registry-card__stat {
    margin-top: var(--space-3);
}

.registry-card__stat-value {
    display: block;
    font-size: var(--stat-size);
    font-weight: 700;
    line-height: 1;
    color: var(--theme-ink, var(--grey-900));
}

.registry-card__stat-label {
    display: block;
    margin-top: var(--space-1);
    font-size: var(--font-size-xs);
    color: var(--grey-600);
}

.registry-card__btn {
    display: inline-block;
    margin-top: var(--space-4);
    padding: var(--space-2) var(--space-4);
    border: 1px solid var(--theme-accent, var(--color-primary));
    background: var(--theme-accent, var(--color-primary));
    color: #FFFFFF;
    border-radius: var(--radius-pill);
    text-decoration: none;
    font-weight: 600;
    font-size: var(--font-size-sm);
    transition: filter var(--transition-base), box-shadow var(--transition-base);
}

.registry-card__btn:hover {
    filter: brightness(1.08);
    box-shadow: var(--card-shadow-hover);
    text-decoration: none;
}

.registry-card__link {
    display: inline-block;
    margin-top: var(--space-2);
    color: var(--theme-ink, var(--color-primary));
    text-decoration: underline;
    font-size: var(--font-size-sm);
    transition: color var(--transition-base);
}

.registry-card__link:hover {
    color: var(--theme-accent, var(--color-primary));
}
```

Remplacer les surcharges `.home-action--large` (1002–1025) par :

```css
.home-action--large .registry-card__subtitle {
    font-size: var(--font-size-sm);
    margin-bottom: var(--space-2);
}

.home-action--large .registry-card__desc {
    font-size: var(--font-size-base);
}

.home-action--large .registry-card__btn {
    font-size: var(--font-size-md);
    padding: var(--space-2) var(--space-5);
    margin-top: var(--space-4);
}

.home-action--large .registry-card__stat-value {
    font-size: var(--stat-size);
}

.home-action--large:hover {
    box-shadow: var(--card-shadow-hover);
}
```

- [ ] **Step 4 : lancer les tests pour vérifier qu'ils passent**

Run: `rtk phpunit --no-coverage --filter 'CssRegistryCardPremiumTest|CssDesignSystemTest|RegistryCardRendererTest'`
Expected: PASS (aucune régression du contrat de tokens ni du markup).

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssRegistryCardPremiumTest.php
git commit -m "style(css): cartes de registre premium pilotées par tint/ink"
```

---

### Task 6 : Légende de workflow compacte

**Files:**
- Modify: `public/css/style.css` (`.workflow-legend` 1096–1107, `.workflow-legend__text` 1119–1122, responsive 3541–3549)
- Test: `tests/unit/CssDesignSystemTest.php` (nouvelle méthode `testWorkflowLegendIsCompact`)

**Interfaces:**
- Consumes: `--surface-sunken`, `--card-border`, `--space-2`, `--space-4`, `--radius-lg`, `--font-size-xs`.
- Produces: `.workflow-legend` de hauteur ≤ 44 px sur une ligne à 1280/1440 ; repli sur 2 lignes max à 1024 (flex-wrap conservé) ; textes jamais `display:none`.

- [ ] **Step 1 : écrire le test qui échoue**

Dans `tests/unit/CssDesignSystemTest.php`, ajouter :

```php
    public function testWorkflowLegendIsCompact(): void
    {
        $legend = $this->ruleBody('.workflow-legend');
        $this->assertStringContainsString('padding: var(--space-2) var(--space-4)', $legend);
        $this->assertStringContainsString('background: var(--surface-sunken)', $legend);
        $this->assertStringContainsString('border: 1px solid var(--card-border)', $legend);
        $this->assertStringNotContainsString('box-shadow', $legend);
        $this->assertStringNotContainsString('display: none', $this->ruleBody('.workflow-legend__text'));
    }
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest::testWorkflowLegendIsCompact`
Expected: FAIL (la légende porte encore `padding: 12px 16px` et `box-shadow: var(--shadow)`).

- [ ] **Step 3 : implémenter la légende compacte**

Remplacer `.workflow-legend` (1096–1107) par :

```css
.workflow-legend {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: var(--space-4);
    padding: var(--space-2) var(--space-4);
    background: var(--surface-sunken);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-lg);
    font-size: var(--font-size-xs);
}
```

Remplacer `.workflow-legend__text` (1119–1122) par :

```css
.workflow-legend__text {
    color: var(--grey-600);
    font-size: var(--font-size-xs);
}
```

Dans la media query `@media (max-width: 768px)` (3541–3549), remplacer le bloc `.workflow-legend` par :

```css
    /* Workflow legend — stack on mobile */
    .workflow-legend {
        flex-wrap: wrap;
        gap: var(--space-1);
        padding: var(--space-2) var(--space-3);
    }
```

- [ ] **Step 4 : lancer le test pour vérifier qu'il passe**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): légende de workflow compacte"
```

---

## Phase 4 — Nuage de mots

### Task 7 : CSS du nuage de mots lisible sur surface teintée

**Files:**
- Modify: `public/css/style.css` (`.registry-card__extra` 4125–4130, `.word-cloud` 4132–4137, `.word-cloud__word` 4139–4148, `.word-cloud__word:hover` 4154–4158, `.wc-s1..s10` 4161–4170, `.wc-c1..c6` 4173–4178)
- Test: `tests/unit/CssDesignSystemTest.php` (nouvelle méthode `testWordCloudConsumesInkAndBounds`)

**Interfaces:**
- Consumes: `--word-cloud-ink` (posé par clé Task 5), `--card-border`, `--grey-800`, `--grey-900`.
- Produces: `.word-cloud__word` en encre sombre (`--word-cloud-ink` avec repli `--grey-800`) ; tailles `.wc-s*` ∈ [0.8rem, 1.5rem] ; `min-height` du conteneur `160px`.

- [ ] **Step 1 : écrire le test qui échoue**

Dans `tests/unit/CssDesignSystemTest.php`, ajouter :

```php
    public function testWordCloudConsumesInkAndBounds(): void
    {
        $word = $this->ruleBody('.word-cloud__word');
        $this->assertStringContainsString('color: var(--word-cloud-ink, var(--grey-800))', $word);
        $this->assertStringNotContainsString('rgba(255,255,255', $word);

        $cloud = $this->ruleBody('.word-cloud');
        $this->assertStringContainsString('min-height: 160px', $cloud);

        // Aucune classe de taille sous 0.8rem (plancher O5)
        foreach (['wc-s1', 'wc-s2', 'wc-s3', 'wc-s4', 'wc-s5', 'wc-s6'] as $cls) {
            $body = $this->ruleBody('.' . $cls);
            $this->assertStringNotContainsString('font-size: 0.6rem', $body, "$cls sous le plancher de 0.8rem.");
            $this->assertStringNotContainsString('font-size: 0.7rem', $body, "$cls sous le plancher de 0.8rem.");
        }
    }
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest::testWordCloudConsumesInkAndBounds`
Expected: FAIL (`.word-cloud__word` porte encore `rgba(255,255,255,0.85)` ; `.wc-s1` est à `0.6rem`).

- [ ] **Step 3 : implémenter le CSS du nuage**

Remplacer `.registry-card__extra` (4125–4130) par :

```css
.registry-card__extra {
    border-top: 1px solid var(--card-border);
    margin-top: var(--space-3);
    padding-top: var(--space-3);
    width: 100%;
}
```

Remplacer `.word-cloud` (4132–4137) par :

```css
.word-cloud {
    position: relative;
    width: 100%;
    min-height: 160px;
    overflow: hidden;
}
```

Remplacer `.word-cloud__word` (4139–4148) par :

```css
.word-cloud__word {
    position: absolute;
    display: inline-block;
    color: var(--word-cloud-ink, var(--grey-800));
    font-weight: 600;
    cursor: default;
    transition: transform 0.2s, color 0.2s;
    white-space: nowrap;
}
```

Remplacer `.word-cloud__word:hover` (4154–4158) par :

```css
.word-cloud__word:hover {
    transform: scale(1.15) !important;
    color: var(--word-cloud-ink, var(--grey-900));
}
```

Remplacer les tailles `.wc-s1` … `.wc-s10` (4161–4170) par la rampe bornée `[0.8rem, 1.5rem]` :

```css
/* Word cloud font sizes by weight class — bornées [0.8rem, 1.5rem] */
.wc-s1 { font-size: 0.8rem; font-weight: 400; }
.wc-s2 { font-size: 0.85rem; font-weight: 450; }
.wc-s3 { font-size: 0.9rem; font-weight: 500; }
.wc-s4 { font-size: 0.95rem; font-weight: 500; }
.wc-s5 { font-size: 1rem; font-weight: 550; }
.wc-s6 { font-size: 1.1rem; font-weight: 600; }
.wc-s7 { font-size: 1.2rem; font-weight: 600; }
.wc-s8 { font-size: 1.3rem; font-weight: 650; }
.wc-s9 { font-size: 1.4rem; font-weight: 700; }
.wc-s10 { font-size: 1.5rem; font-weight: 700; }
```

Remplacer les variantes de couleur `.wc-c1` … `.wc-c6` (4173–4178) par une rampe d'encre neutre (lisibles sur fond teinté) :

```css
/* Word cloud color variations — encres neutres lisibles sur surface claire */
.wc-c1 { color: var(--grey-900); }
.wc-c2 { color: var(--grey-800); }
.wc-c3 { color: var(--grey-700); }
.wc-c4 { color: var(--grey-800); }
.wc-c5 { color: var(--grey-700); }
.wc-c6 { color: var(--grey-900); }
```

- [ ] **Step 4 : lancer le test pour vérifier qu'il passe**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): nuage de mots lisible sur surface teintée"
```

---

### Task 8 : JavaScript du nuage (encre sombre, taille bornée, collisions élargies)

**Files:**
- Modify: `public/js/wordcloud.js` (fichier entier, 91 lignes)
- Test: `tests/unit/WordCloudRegressionTest.php` (exécution de non-régression ; pas de nouvelle assertion JS)

**Interfaces:**
- Consumes: `--word-cloud-ink` (hérité du conteneur `.word-cloud` depuis `.registry-card--<clé>`), `--word-cloud-min` (0.8rem), `--word-cloud-max` (1.5rem).
- Produces: mots colorés avec l'encre du thème, `font-size` ∈ [0.8rem, 1.5rem], collisions `+10px`/`+6px`, `step < 2500`.

- [ ] **Step 1 : réécrire le fichier**

Remplacer **tout** le contenu de `public/js/wordcloud.js` par :

```javascript
/**
 * Word Cloud — spiral placement with collision detection
 * Inspired by wordcloud2.js algorithm, adapted for HTML spans.
 *
 * Réglages premium (seconde passe) :
 *   - l'encre des mots vient de --word-cloud-ink (thème du registre), avec un
 *     repli sombre neutre si la variable est absente ;
 *   - la taille est bornée par --word-cloud-min / --word-cloud-max ;
 *   - les marges de collision et le nombre d'étapes sont élargis pour éviter
 *     les mots rognés sur surface claire.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.word-cloud[data-words]').forEach(function (el) {
        var raw = el.getAttribute('data-words');
        if (!raw) return;
        var words;
        try { words = JSON.parse(raw); } catch (e) { return; }
        if (!words.length) return;

        // Set explicit height
        el.style.height = '200px';
        el.style.position = 'relative';
        el.style.overflow = 'hidden';

        var W = el.offsetWidth;
        var H = el.offsetHeight;
        var cx = W / 2;
        var cy = H / 2;

        // Encre lue depuis le thème (--word-cloud-ink), repli sombre neutre.
        var computed = window.getComputedStyle(el);
        function readRem(name, fallback) {
            var value = computed.getPropertyValue(name).trim();
            var parsed = parseFloat(value);
            return isNaN(parsed) ? fallback : parsed;
        }
        function readColor(name, fallback) {
            var value = computed.getPropertyValue(name).trim();
            return value === '' ? fallback : value;
        }
        var minSize = readRem('--word-cloud-min', 0.8);
        var maxSize = readRem('--word-cloud-max', 1.5);
        var ink = readColor('--word-cloud-ink', '#1F2937');
        var fallbackInks = [ink, '#374151', '#111827', '#334155', '#0F172A'];

        // Sort by weight descending
        words.sort(function (a, b) { return b.p - a.p; });

        var placed = [];

        words.forEach(function (item) {
            var span = document.createElement('span');
            span.className = 'word-cloud__word';
            span.textContent = item.w;
            span.title = item.w + ' (poids ' + item.p + ')';
            span.style.position = 'absolute';
            el.appendChild(span);

            // Size: poids 1-20 → taille bornée [minSize, maxSize] (rem)
            var fs = 0.6 + item.p * 0.06;
            fs = Math.max(minSize, Math.min(maxSize, fs));
            span.style.fontSize = fs + 'rem';
            span.style.fontWeight = String(Math.min(700, 400 + item.p * 35));
            span.style.color = fallbackInks[Math.floor(Math.random() * fallbackInks.length)];

            // Measure
            var tw = span.offsetWidth;
            var th = span.offsetHeight;

            // Spiral placement
            var angle = Math.random() * Math.PI * 2;
            var radius = 0;
            var found = false;

            for (var step = 0; step < 2500; step++) {
                angle += 0.5;
                radius += 0.08;
                var x = cx + radius * Math.cos(angle) * 0.8 - tw / 2;
                var y = cy + radius * Math.sin(angle) * 0.6 - th / 2;

                // Bounds
                if (x < 4 || y < 4 || x + tw > W - 4 || y + th > H - 4) {
                    if (radius > Math.min(W, H) * 0.5) break;
                    continue;
                }

                // Collision (marges élargies pour éviter le rognage)
                var ok = true;
                for (var j = 0; j < placed.length; j++) {
                    var p = placed[j];
                    if (x < p.x + p.w + 10 && x + tw + 10 > p.x &&
                        y < p.y + p.h + 6 && y + th + 6 > p.y) {
                        ok = false;
                        break;
                    }
                }

                if (ok) {
                    span.style.left = Math.round(x) + 'px';
                    span.style.top = Math.round(y) + 'px';
                    placed.push({ x: x, y: y, w: tw, h: th });
                    found = true;
                    break;
                }
            }

            if (!found) {
                span.remove();
            }
        });
    });
});
```

- [ ] **Step 2 : vérifier la syntaxe JavaScript**

Run: `node --check public/js/wordcloud.js`
Expected: aucune sortie (fichier valide).

- [ ] **Step 3 : non-régression PHPUnit du service de nuage**

Run: `rtk phpunit --no-coverage --filter WordCloudRegressionTest`
Expected: PASS (le HTML `word-cloud`/`word-cloud__word`/`role="img"` reste inchangé).

- [ ] **Step 4 : commit**

```bash
git add public/js/wordcloud.js
git commit -m "fix(js): nuage de mots en encre sombre, taille bornée et collisions élargies"
```

---

## Phase 5 — Responsive laptop & contraste

### Task 9 : Grille de registres 3/4 colonnes et borne de lecture

**Files:**
- Modify: `public/css/style.css` (`.registry-cards` 714–723, `.main` 626–633, responsive 3403–3405)
- Test: `tests/unit/CssDesignSystemTest.php` (nouvelle méthode `testRegistryGridIsLaptopBounded`)

**Interfaces:**
- Consumes: `--space-4`, `--content-max-width`, `--surface-muted`.
- Produces: `.registry-cards` en grille 3 colonnes ≥ 1024 px, 4 colonnes ≥ 1440 px (plafonné à 4), 1 colonne ≤ 768 px ; `.main` sur `--surface-muted` avec contenus bornés à `--content-max-width`.

- [ ] **Step 1 : écrire le test qui échoue**

Dans `tests/unit/CssDesignSystemTest.php`, ajouter :

```php
    public function testRegistryGridIsLaptopBounded(): void
    {
        $grid = $this->ruleBody('.registry-cards');
        $this->assertStringContainsString('display: grid', $grid);
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr))', $grid);
        $this->assertStringContainsString('gap: var(--space-4)', $grid);

        $desktop = $this->mediaBlocks('(min-width: 1440px)');
        $this->assertStringContainsString('grid-template-columns: repeat(4, minmax(0, 1fr))', $desktop);

        $tablet = $this->mediaBlocks('(max-width: 768px)');
        $this->assertStringContainsString('grid-template-columns: 1fr', $tablet);

        $main = $this->ruleBody('.main');
        $this->assertStringContainsString('background: var(--surface-muted)', $main);

        $children = $this->ruleBody('.main > *');
        $this->assertStringContainsString('max-width: var(--content-max-width)', $children);
    }
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest::testRegistryGridIsLaptopBounded`
Expected: FAIL (`.registry-cards` est encore en `display: flex` ; `.main > *` n'existe pas).

- [ ] **Step 3 : implémenter la grille et la borne**

Remplacer `.registry-cards` et `.registry-cards > *` (714–723) par :

```css
.registry-cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--space-4);
}

.registry-cards--large {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
```

Dans `.main` (626–633), ajouter le fond, puis ajouter la règle suivante juste après `.main > :last-child` (635–637) :

```css
.main {
    margin-left: var(--sidebar-width);
    margin-top: var(--header-height);
    padding: var(--content-padding);
    min-height: calc(100vh - var(--header-height));
    display: flex;
    flex-direction: column;
    background: var(--surface-muted);
}

.main > * {
    width: 100%;
    max-width: var(--content-max-width);
}
```

Ajouter, à la fin de la section Responsive (après la media query `max-width: 768px`, avant la media query `480px`), la borne haute :

```css
@media (min-width: 1440px) {
    .registry-cards,
    .registry-cards--large {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
```

Dans la media query `@media (max-width: 768px)` (3403–3405), remplacer :

```css
    .registry-cards {
        flex-direction: column;
    }
```

par :

```css
    .registry-cards,
    .registry-cards--large {
        grid-template-columns: 1fr;
    }
```

- [ ] **Step 4 : lancer le test pour vérifier qu'il passe**

Run: `rtk phpunit --no-coverage --filter CssDesignSystemTest`
Expected: PASS.

- [ ] **Step 5 : commit**

```bash
git add public/css/style.css tests/unit/CssDesignSystemTest.php
git commit -m "style(css): grille de registres 3/4 colonnes et borne de lecture"
```

---

### Task 10 : Contrats de contraste tint/ink et sidebar

**Files:**
- Modify: `tests/unit/CssContrastContractTest.php` (ajout de deux méthodes)
- Test: `tests/unit/CssContrastContractTest.php`

**Interfaces:**
- Consumes: `rootTokens()`, `resolveToken()`, `contrastRatio()`, `MIN_CONTRAST`, `WHITE`, `THEME_KEYS` (déjà présents).
- Produces: preuve que `--theme-<clé>-ink` sur `--theme-<clé>-tint`, `--sidebar-text` sur `--sidebar-bg`, blanc sur `--sidebar-bg` et `--sidebar-active` sur `--sidebar-bg` sont ≥ 4.5:1.

- [ ] **Step 1 : écrire les tests**

Dans `tests/unit/CssContrastContractTest.php`, ajouter à la classe :

```php
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
```

- [ ] **Step 2 : lancer les tests pour vérifier qu'ils passent**

Run: `rtk phpunit --no-coverage --filter CssContrastContractTest`
Expected: PASS (les ratios cibles mesurés sont tous ≥ 4.5:1, cf. Baseline).

- [ ] **Step 3 : commit**

```bash
git add tests/unit/CssContrastContractTest.php
git commit -m "test(css): contrats de contraste tint/ink et sidebar"
```

---

## Phase 6 — Validation

### Task 11 : Viewport Playwright 1280×800 et matrice laptop 1024/1280/1440

**Files:**
- Modify: `playwright.config.js` (ligne 126)
- Create: `e2e/visual-premium.spec.js`

**Interfaces:**
- Consumes: `loginAs` (`e2e/helpers.js`), classes `.registry-cards`, `.registry-card__stat-value`, `.registry-card__desc`, `.workflow-legend`, `.word-cloud__word`.
- Produces: assertions O1/O2/O3/O5/O6 aux trois viewports + captures `docs/screenshots/premium/accueil-{1024,1280,1440}.png`.

- [ ] **Step 1 : aligner le viewport par défaut**

Dans `playwright.config.js`, remplacer :

```javascript
    viewport: { width: 1280, height: 720 },
```

par :

```javascript
    viewport: { width: 1280, height: 800 },
```

- [ ] **Step 2 : écrire le spec**

Créer `e2e/visual-premium.spec.js` :

```javascript
/**
 * SST Application — Refonte visuelle premium (seconde passe)
 *
 * Matrice laptop contractuelle : 1024×768 (plancher), 1280×800 (priorité de
 * design), 1440×900 (contrôle). Vérifie la tenue du layout (O1), la grille de
 * registres (O2), la dominance du compteur (O3), la lisibilité du nuage (O5)
 * et la compacité de la légende (O6), puis produit les captures de contrôle.
 */
import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { loginAs } from './helpers.js';

const VIEWPORTS = [
  { label: 'laptop-1024', width: 1024, height: 768 },
  { label: 'laptop-1280', width: 1280, height: 800 },
  { label: 'laptop-1440', width: 1440, height: 900 },
];

const PREMIUM_DIR = path.join(process.cwd(), 'docs', 'screenshots', 'premium');

for (const vp of VIEWPORTS) {
  test.describe(`Refonte premium — ${vp.label}`, () => {
    test.use({ viewport: { width: vp.width, height: vp.height } });

    test.beforeEach(async ({ page }) => {
      await loginAs(page);
    });

    test('accueil — pas de scroll horizontal (O1)', async ({ page }) => {
      await page.goto('/index.php?page=home');
      const ok = await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth
      );
      expect(ok).toBe(true);
    });

    test('accueil — grille de registres conforme (O2)', async ({ page }) => {
      await page.goto('/index.php?page=home');
      const grid = page.locator('.registry-cards').first();
      await expect(grid).toBeVisible();
      const columns = await grid.evaluate(
        (el) => getComputedStyle(el).gridTemplateColumns.split(' ').length
      );
      const expected = vp.width >= 1440 ? [3, 4] : [3];
      expect(expected).toContain(columns);
    });

    test('accueil — compteur dominant (O3)', async ({ page }) => {
      await page.goto('/index.php?page=home');
      const value = page.locator('.registry-card__stat-value').first();
      await expect(value).toBeVisible();
      const valueSize = await value.evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
      const descSize = await page
        .locator('.registry-card__desc')
        .first()
        .evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
      expect(valueSize).toBeGreaterThan(descSize);
    });

    test('accueil — nuage de mots lisible (O5)', async ({ page }) => {
      await page.goto('/index.php?page=home');
      const words = page.locator('.word-cloud__word');
      const count = await words.count();
      for (let i = 0; i < count; i++) {
        const size = await words.nth(i).evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
        expect(size).toBeGreaterThanOrEqual(12.8);
      }
    });

    test('accueil — légende compacte (O6)', async ({ page }) => {
      test.skip(vp.width < 1280, 'légende sur une ligne garantie à 1280/1440 seulement');
      await page.goto('/index.php?page=home');
      const legend = page.locator('.workflow-legend');
      if ((await legend.count()) === 0) return;
      const height = await legend.first().evaluate((el) => el.getBoundingClientRect().height);
      expect(height).toBeLessThanOrEqual(44);
    });

    test('accueil — capture de contrôle', async ({ page }) => {
      await page.goto('/index.php?page=home');
      fs.mkdirSync(PREMIUM_DIR, { recursive: true });
      await page.screenshot({ path: path.join(PREMIUM_DIR, `accueil-${vp.width}.png`) });
    });

    test('report_list — pas de scroll horizontal (O1)', async ({ page }) => {
      await page.goto('/index.php?page=report_list&type=rsst');
      const ok = await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth
      );
      expect(ok).toBe(true);
    });

    test('report_view — pas de scroll horizontal (O1)', async ({ page }) => {
      await page.goto('/index.php?page=report_list&type=rsst');
      const viewLink = page.locator('a.btn--outline:has-text("Voir")').first();
      if ((await viewLink.count()) === 0) {
        test.skip(true, 'aucun signalement RSST disponible pour ouvrir une fiche');
        return;
      }
      await viewLink.click();
      await expect(page).toHaveURL(/page=report_view/);
      const ok = await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth
      );
      expect(ok).toBe(true);
    });
  });
}
```

- [ ] **Step 3 : lancer la matrice (projet firefox)**

Run: `npx playwright test e2e/visual-premium.spec.js --project=firefox`
Expected: 24 tests verts (8 par viewport) ; `docs/screenshots/premium/accueil-1024.png`, `accueil-1280.png`, `accueil-1440.png` créés.

- [ ] **Step 4 : commit**

```bash
git add playwright.config.js e2e/visual-premium.spec.js docs/screenshots/premium
git commit -m "test(e2e): matrice laptop 1024/1280/1440 et viewport par défaut 1280x800"
```

---

### Task 12 : Viewports des outils de capture alignés sur 1280×800

**Files:**
- Modify: `tools/capture_screenshots.py` (ligne 46)
- Modify: `tools/annotate_screenshots.py` (ligne 603)

**Interfaces:**
- Consumes: — (outils autonomes).
- Produces: captures HTML→PNG et annotations produites à 1280×800.

- [ ] **Step 1 : aligner `capture_screenshots.py`**

Dans `tools/capture_screenshots.py`, remplacer :

```python
            viewport={"width": 1280, "height": 900},
```

par :

```python
            viewport={"width": 1280, "height": 800},
```

- [ ] **Step 2 : aligner `annotate_screenshots.py`**

Dans `tools/annotate_screenshots.py`, remplacer :

```python
            viewport={"width": 1280, "height": 900},
```

par :

```python
            viewport={"width": 1280, "height": 800},
```

- [ ] **Step 3 : vérifier la syntaxe Python**

Run: `py -3 -m py_compile tools/capture_screenshots.py tools/annotate_screenshots.py`
Expected: aucune sortie (fichiers valides).

- [ ] **Step 4 : commit**

```bash
git add tools/capture_screenshots.py tools/annotate_screenshots.py
git commit -m "docs(screenshots): viewports de capture alignés sur 1280x800"
```

---

### Task 13 : CHANGELOG 3.66.2, cache-busting et déploiement

**Files:**
- Modify: `CHANGELOG.md` (insérer l'entrée `3.66.2` avant `## [3.66.1]`, ligne 35)

**Interfaces:**
- Consumes: — .
- Produces: `ConfigService::getAppVersion()` renvoie `3.66.2` → `css.php?f=css/style.css&v=3.66.2` **et** `js.php?f=js/wordcloud.js&v=3.66.2` (nouvelle URL pour les deux ressources).

- [ ] **Step 1 : confirmer la suite verte avant documentation**

Run: `rtk phpunit --no-coverage`
Expected: suite verte (aucun échec) — prérequis avant de figer la version.

Run: `rtk phpstan analyse --memory-limit=1G`
Expected: 0 erreur (level 8).

- [ ] **Step 2 : vérifier la version servie avant bump**

Run: `node -e "const fs=require('fs');const m=fs.readFileSync('CHANGELOG.md','utf8').match(/^##\s*\[(\d+\.\d+\.\d+)\]/m);console.log(m[1])"`
Expected: `3.66.1`.

- [ ] **Step 3 : insérer l'entrée `3.66.2`**

Dans `CHANGELOG.md`, insérer immédiatement avant la ligne `## [3.66.1] — 2026-09-23` :

```markdown
## [3.66.2] — 2026-09-24

### Refonte visuelle premium — seconde passe (laptop)

- **Périmètre** — Seconde passe visuelle sans changement fonctionnel : `public/css/style.css`, `public/js/wordcloud.js`, une retouche markup dans `src/helpers/registry_card_renderer.php`. Aucune modification des routes, handlers, services, repositories, base de données ni de l'authentification IIS.
- **Tokens premium** — Nouvelles surfaces (`--surface`, `--surface-muted`, `--surface-sunken`, `--card-border`, `--card-shadow*`, `--radius-xl`, `--radius-pill`, `--header-surface`, `--header-border`), accents par registre `--theme-<clé>-tint` / `--theme-<clé>-ink` (10 clés) et bornes laptop `--laptop-min` / `--content-max-width`.
- **Shell** — Sidebar bleu nuit (`--sidebar-bg #0F1D33`) avec item actif à double signal (remplissage teinté + barre d'accent) ; header contemporain sur surface claire à bordure basse fine.
- **Accueil** — Cartes de registre sur surface teintée avec accent par registre (barre latérale, pastille d'icône), compteur dominant (`.registry-card__stat-value` / `.registry-card__stat-label`) ; carte DGI alignée sur le tronc commun avec marqueur de priorité décoratif ; légende de workflow compacte (≤ 44 px sur une ligne à 1280/1440).
- **Nuage de mots** — Encre sombre adaptée au fond teinté (`--word-cloud-ink`), taille bornée `[0.8rem, 1.5rem]`, collisions élargies et étapes de placement augmentées.
- **Accessibilité** — Contrastes AA vérifiés par contrat PHPUnit (`--theme-<clé>-ink` sur `-tint`, sidebar) ; états actifs doublés d'un marqueur non chromatique ; `prefers-reduced-motion`/`prefers-contrast` conservés.
- **Matrice laptop** — Priorité 1280×800, contrôle 1440×900, plancher 1024×768 verrouillés par `e2e/visual-premium.spec.js` ; viewport Playwright par défaut porté à 1280×800.
- **Cache-busting** — La version `CHANGELOG.md` (`3.66.2`) alimente `?v=` de `css.php` (`css/style.css`) **et** de `js.php` (`js/wordcloud.js`) via `getAppVersion()` : les deux ressources sont invalidées d'un coup. Aucune modification de `web.config`, des en-têtes de cache, de la CSP ni de la configuration IIS ; rollback = commit précédent (`?v=3.66.1`).
```

- [ ] **Step 4 : vérifier que la version servie est `3.66.2`**

Run: `node -e "const fs=require('fs');const m=fs.readFileSync('CHANGELOG.md','utf8').match(/^##\s*\[(\d+\.\d+\.\d+)\]/m);console.log(m[1])"`
Expected: `3.66.2`.

- [ ] **Step 5 : vérifier le cache-busting des deux ressources**

Run: `npx playwright test e2e/version-changelog.spec.js --project=firefox`
Expected: PASS — le footer affiche `v3.66.2`, identique au premier `h2` du changelog (le cache-busting est dérivé de la même source de version pour `style.css` et `wordcloud.js`).

- [ ] **Step 6 : commit**

```bash
git add CHANGELOG.md
git commit -m "docs(changelog): 3.66.2 — refonte visuelle premium et cache-busting"
```

---

### Task 14 : Validation finale (spec §8)

**Files:**
- Aucun fichier attendu en création/modification. Si un test échoue à cause d'une régression, corriger `public/css/style.css` (ou le test fautif) dans un commit dédié et relancer la boucle.

**Interfaces:**
- Consumes: toutes les tâches 1–13.
- Produces: jeu de preuves de validation (sorties de commandes consignées).

- [ ] **Step 1 : non-régression PHP**

```bash
rtk phpunit --no-coverage
```

Expected: suite complète verte ; `CssDesignSystemTest`, `CssRegistryCardPremiumTest`, `CssContrastContractTest`, `RegistryCardRendererTest`, `WordCloudRegressionTest` inclus.

- [ ] **Step 2 : analyse statique**

```bash
rtk phpstan analyse --memory-limit=1G
```

Expected: 0 erreur (level 8), y compris sur `src/helpers/registry_card_renderer.php` modifié.

- [ ] **Step 3 : conformité CSP et classes CSS**

```bash
php tools/check_inline_styles.php
php tools/check_css_classes.php --missing
```

Expected: aucune erreur — pas de style inline ; `registry-card__stat-value` et `registry-card__stat-label` présents à la fois dans le HTML et le CSS (préfixe `registry-card` reconnu).

- [ ] **Step 4 : E2E complets (projets existants + matrice premium)**

```bash
npx playwright test --project=firefox
npx playwright test --project=msedge
```

Expected: shards verts (navigation, formulaires, rôles, nuage, version) + `visual-premium.spec.js` vert aux 3 viewports. Aucun parcours cassé par le header clair, la grille ou le nuage.

- [ ] **Step 5 : revue visuelle laptop**

Ouvrir l'application de dev aux trois largeurs **1024**, **1280**, **1440** et vérifier : sidebar bleu nuit + item actif à double signal ; header clair séparé ; cartes de registre sur surface teintée avec accent et compteur dominant ; légende sur une ligne à 1280/1440 ; nuage lisible ; carte DGI alignée avec marqueur. Contrôler aussi les breakpoints ≤ 768 et ≤ 480 : sidebar en panneau, grille 1 colonne, actions pleine largeur, `prefers-reduced-motion` neutralise l'effet de survol.

- [ ] **Step 6 : commit (uniquement si une correction a été nécessaire)**

```bash
git add public/css/style.css public/js/wordcloud.js src/helpers/registry_card_renderer.php
git commit -m "fix(premium): corriger la régression détectée par la validation finale"
```

Sinon, aucun commit : la validation est une preuve, pas un livrable.

---

## Spec coverage map

| Section de la spec | Tâche(s) |
|---|---|
| §4.1 Tokens existants modifiés (sidebar, border) | Task 1 |
| §4.2 Surfaces premium | Task 1 |
| §4.3 Accents par registre (`-tint` / `-ink`) | Task 1, Task 5 |
| §4.4 Tokens nuage de mots | Task 1, Task 5, Task 7 |
| §4.5 Typographie / bornes laptop | Task 1, Task 9 |
| §5.3 Contrat de thème (HTML sans couleur) | Task 5 |
| §5.4 Repli neutre thème inconnu | Task 5 |
| §5.5 Nuage de mots (encre, bornes, collisions, min-height) | Task 7, Task 8 |
| §5.6 Exception markup (`.registry-card__stat`) | Task 4 |
| §6 `.header` (+ contenu) | Task 3 |
| §6 `.sidebar` / item actif | Task 2 |
| §6 `.main` (fond, borne) | Task 9 |
| §6 `.registry-cards` / `.registry-card` / DGI / `__icon`/`__title`/`__subtitle`/`__desc`/`__stat`/`__btn`/`__link`/`__extra` | Task 5 |
| §6 `.workflow-legend` | Task 6 |
| §6 `.word-cloud` / `.wc-s*` / `.wc-c*` | Task 7 |
| §7 Contrastes, non-chromatique, focus, sémantique, mouvement | Task 2, Task 5, Task 7, Task 10 |
| §8.1 Playwright matrice 1280/1440/1024 + captures | Task 11 |
| §8.2 Captures (`capture`/`annotate` viewport 1280×800) | Task 12 |
| §8.3 `CssDesignSystemTest`, `CssContrastContractTest`, `CssRegistryCardPremiumTest` | Task 1, Task 5, Task 10 |
| §8.4 E2E shards existants | Task 14 |
| §9 Responsive et compatibilité | Task 9, Task 14 |
| §10 Déploiement, version 3.66.2, cache-busting | Task 13 |
| §11 Hors périmètre (IIS, routes, mobile, mode sombre) | Global Constraints |

---

## Risques & gaps

- **O2 « jamais de carte < 250 px » à 1024 px — impossible en l'état des tokens gelés.** À 1024 px, la largeur utile du contenu est `1024 − var(--sidebar-width) 220 − 2 × var(--content-padding) 24 = 756 px` ; 3 colonnes à `minmax(0, 1fr)` avec `gap: var(--space-4)` donnent des cartes de ≈ 241 px. La spec gèle pourtant `--sidebar-width` (220px) et `--content-padding` (24px) « conservés à l'identique » (§4), et exige simultanément 3 colonnes à 1024. **Décision :** priorité au critère de validation Playwright explicitement chiffré (§8.1 : 3 colonnes à 1024/1280, 3–4 à 1440) ; le plancher 250 px reste un objectif de design tenu à 1280 (≈ 326 px) et 1440 (≈ 281 px), non tenu à 1024. Pour le satisfaire strictement à 1024, il faudrait réduire `--sidebar-width` ou `--content-padding`, ce que la spec interdit — à arbitrer hors de ce plan.
- **O3 ordre visuel « compteur avant description » — non atteignable sans changer le DOM.** Le renderer regroupe `[icon, title, subtitle, desc]` puis `[btn, link, stat]` ; le compteur ne peut pas être déplacé avant la description sans casser le gel du markup. La spec §7 acte explicitement que « l'ordre visuel du compteur reste après les actions dans le DOM ». **Décision :** la dominance est obtenue par la taille (`--stat-size`, `font-weight: 700`) et non par le réordonnancement ; conforme à §5.6 et §7.
- **`--content-max-width` inerte sous ~1508 px de viewport.** La largeur utile maximale (1440 − 220 − 48 = 1172 px) reste sous 1240 px : la borne ne se déclenche jamais aux largeurs cibles. **Décision :** token défini et consommé (`.main > *`) comme borne défensive de lecture, conformément à §4.5/§6 ; sans effet visuel mesurable à 1280/1440.
- **Cache-busting du JS : spec §5.1/§10 mentionne `assetLink()`/`asset.php`, le code réel utilise `js.php`.** `templates/footer.php` charge `js.php?f=js/wordcloud.js&v=<version>` (même `getAppVersion()` que `AssetService::cssLink()`). L'effet exigé (une seule incrémentation de version invalide `style.css` **et** `wordcloud.js`) est obtenu ; seule la route citée dans la spec diffère. Aucune action requise.
- **Header clair → recolorisation du contenu du header au-delà de la liste §6.** `.impersonate-btn`, `.impersonate-icon::before/::after` et `.header__menu-btn` sont dans `.header` mais absents de la table §6 ; sans recolorisation ils deviendraient illisibles (blanc sur surface claire). **Décision :** recolorés en CSS uniquement (aucun changement de markup), dans le périmètre du header.
- **Viewport Playwright par défaut 1280×720 → 1280×800.** Peut décaler des assertions dépendant de la hauteur sur les shards existants. **Mitigation :** `npx playwright test --project=firefox --project=msedge` en Task 14 ; tout échec lié à la hauteur est corrigé dans le commit de validation.
- **Captures HTML gelées.** Les `docs/screenshots/*.html` embarquent une copie figée du CSS et ne référencent pas `style.css` ; la Task 12 ne change que le viewport des outils. Les captures premium de contrôle proviennent de l'application live (Task 11), sans régénérer les snapshots HTML.

---

## Critères de validation globaux

1. **Contrat de tokens** : `CssDesignSystemTest::testEveryCanonicalTokenExistsWithExpectedValue` vert (tokens premium inclus, valeurs exactes §4).
2. **Contraste** : `CssContrastContractTest` vert (ink/tint ≥ 4.5:1, sidebar ≥ 4.5:1).
3. **Cartes premium** : `CssRegistryCardPremiumTest` vert (tint/ink, pastille, compteur dominant, repli neutre, marqueur DGI).
4. **Markup** : `RegistryCardRendererTest` vert ; compteur scindé, aucune autre classe renommée.
5. **Nuage** : `WordCloudRegressionTest` vert ; encre sombre, taille ∈ [0.8rem, 1.5rem], collisions élargies.
6. **Matrice laptop** : `visual-premium.spec.js` vert à 1024×768 / 1280×800 / 1440×900 ; captures `docs/screenshots/premium/` produites.
7. **Non-régression** : suite PHPUnit complète verte, PHPStan 0 erreur (level 8), shards E2E Firefox + msedge verts.
8. **CSP** : `check_inline_styles.php` et `check_css_classes.php --missing` sans erreur.
9. **Cache-busting** : `getAppVersion()` = `3.66.2` ; `version-changelog.spec.js` vert.
10. **Périmètre** : aucune modification hors des fichiers listés ; IIS/`web.config`/CSP/routes/services inchangés ; mobile conservé.