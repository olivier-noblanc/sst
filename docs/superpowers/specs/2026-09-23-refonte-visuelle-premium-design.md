# Design Spec : Refonte visuelle premium — seconde passe

**Date :** 2026-09-23
**Scope :** couche visuelle `/public/css/style.css` (principale), `/public/js/wordcloud.js` (lisibilité du nuage) et une retouche markup justifiée dans `/src/helpers/registry_card_renderer.php`
**Approach :** refonte visuelle progressive pilotée par les tokens, sans toucher aux routes, à la logique métier ni à l'authentification IIS
**Statut :** design approuvé (document de référence ; aucune implémentation CSS/JS incluse)
**Prérequis :** première passe de modernisation (3.66.1, spec `2026-09-22-modernisation-visuelle-design.md`) déjà mergée ; les tokens `:root`, le shell et les composants sont déjà consommateurs de tokens.

## 1. Contexte et appareil cible

La première passe a posé un jeu canonique de tokens et une modernisation sobre. Cette seconde passe vise une **direction institutionnelle premium** sur un parc **laptop uniquement**, avec une hiérarchie visuelle plus affirmée : sidebar bleu nuit, header contemporain, cartes de registre sur surface claire teintée avec accents par registre, statistiques et actions lisibles, légende compacte et nuage de mots réellement lisible.

### Matrice d'appareils (contractuelle)

| Rôle | Résolution | Largeur utile (viewport) | Statut |
|---|---|---|---|
| Priorité de design | **1280 × 800** | 1280 px | cible principale, toutes les décisions visuelles sont arbitrées à cette taille |
| Contrôle | **1440 × 900** | 1440 px | vérification d'absence de sur-étirement et d'alignement |
| Minimum supporté | **1024 × 768** | 1024 px | plancher : aucun scroll horizontal, aucun chevauchement, birth fonctionnelle |
| Mobile (conservé) | ≤ 768 px et ≤ 480 px | — | préservé sans être axe de design (voir §9) |

### Contraintes

- L'application est utilisée sur laptop de bureau / télétravail ; le mobile reste supporté mais **n'est pas optimisé** dans cette passe.
- **CSS principalement dans `public/css/style.css`.** `public/css/login.css` reste hors périmètre (mode dev, l'authentification production est assurée par IIS).
- **Aucun changement** : authentification IIS, `web.config`/`pages/login.php`, routes, handlers, services, repositories, base de données, modèles d'e-mail, feuille d'impression.
- **Markup gelé**, avec **une seule exception explicitement justifiée** (§5.6 et §10).
- Aucune dépendance nouvelle, aucun framework CSS, aucun outil de build.

## 2. Objectifs mesurables

| # | Objectif | Mesure | Cible |
|---|---|---|---|
| O1 | Tenue du layout laptop | `document.documentElement.scrollWidth ≤ window.innerWidth` à 1024, 1280, 1440 px | vrai aux 3 largeurs, sur accueil + liste + fiche |
| O2 | Grille de registres maîtrisée | nombre de colonnes `.registry-cards` | 3 colonnes à 1024 et 1280 px ; 3 à 4 (plafonné à 4) à 1440 px ; jamais de carte < 250 px |
| O3 | Hiérarchie accueil | ordre visuel perçu | icône → titre → **compteur dominant** → description → action primaire → action secondaire |
| O4 | Contrastes texte | ratio WCAG (calculé sur valeurs `:root`) | texte normal ≥ 4.5:1 ; texte large (≥ 24 px ou ≥ 18.66 px gras) ≥ 3:1 ; **aucun texte de carte sous 4.5:1** |
| O5 | Nuage de mots lisible | min(contraste mot/fond) et font-size mini | ≥ 4.5:1 pour **chaque** mot ; `font-size` ∈ [0.8 rem, 1.5 rem] ; zéro mot rogné par `overflow` |
| O6 | Légende compacte | hauteur rendue à 1280 px | ≤ 44 px sur **une** ligne à 1280/1440 ; repli sur 2 lignes max à 1024 px |
| O7 | Sidebar bleu nuit | contraste libellé actif/fond | libellé actif ≥ 4.5:1 ; marqueur d'état actif non chromatique (bordure/remplissage) en plus de la couleur |
| O8 | Header contemporain | hauteur et densité | hauteur constante `--header-height` ; séparation nette avec le contenu ; identité et rôle alignés |
| O9 | Non-régression | `phpunit`, `phpstan`, E2E Playwright | 0 échec ; 0 erreur PHPStan (level 8) |
| O10 | Poids et budget réseau | delta CSS gzipé, requêtes HTTP | CSS ≤ +15 % ; **aucune requête HTTP supplémentaire** (polices système, pas d'icône externe) |
| O11 | Cache-busting | `?v=` servi par `css.php` et `asset.php` | version CHANGELOG incrémentée ⇒ nouvelle URL pour `style.css` **et** `wordcloud.js` |

## 3. Direction visuelle premium

- **Institutionnel premium** : surfaces claires, bordures fines, ombres basses et diffuses, densité d'information élevée, aucune décoration gratuite. La couleur reste **informationnelle** (état, registre, danger) et non décorative.
- **Sidebar bleu nuit** : fond `--sidebar-bg` recoloré vers un bleu nuit profond, contraste élevé avec les surfaces claires ; item actif signalé par un **remplissage teinté + barre d'accent**, pas par la couleur seule ; icônes alignées dans une gouttière régulière.
- **Header contemporain** : bandeau de surface claire, séparé par une bordure basse fine et un relief discret, logo DREETS conservé, titre d'application en graisse médiane, identité utilisateur et pastille de rôle regroupées à droite, déconnexion en action discrète. Le bleu de marque `--color-primary` reste l'ancre d'identité sur les accents et l'action primaire.
- **Accueil** : cartes de registre sur **surface blanche / teintée** (plus de fond saturé plein), **accent par registre** (barre latérale + pastille d'icône + accents typographiques), **icône en cercle**, **compteur dominant**, action primaire (créer) distinguée de l'action secondaire (consulter la liste).
- **Carte DGI alignée** : la carte DGI suit exactement la structure et la surface des autres registres, avec l'accent danger `--theme-dgi` renforcé et un marqueur de priorité décoratif ; elle n'est plus une inversion rouge pleine. Le **panneau d'alerte légal DGI** (`templates/report_card.php`, `pages/report_reopen.php`) est inchangé.
- **Légende compacte** : barre de statuts fine, une ligne à 1280/1440, textes explicatifs réduits mais conservés (jamais `display:none`, pour l'accessibilité).
- **Nuage de mots lisible** : palette d'encre sombre adaptée au fond teinté, taille minimale relevée, marges de collision augmentées.
- **Thème clair unique** : aucun mode sombre ; le mode sombre reste hors périmètre.

## 4. Tokens CSS

Les tokens `:root` restent la **source de vérité**. Les tokens existants non cités sont conservés à l'identique. Les valeurs ci-dessous sont les valeurs cibles de la spec ; `CssDesignSystemTest` (qui verrouille les valeurs exactes) est mis à jour **dans le même changement** (§8).

### 4.1 Tokens existants modifiés

| Token | Valeur actuelle | Valeur cible | Rationale |
|---|---|---|---|
| `--sidebar-bg` | `#2C3E50` | `#0F1D33` | bleu nuit profond (premium) |
| `--sidebar-text` | `#CCCCCC` | `#C7D2E0` | lisibilité sur bleu nuit |
| `--sidebar-hover` | `#34495E` | `rgba(255,255,255,0.06)` | survol doux, non chromatique |
| `--sidebar-active` | `var(--color-primary-light)` | `#4A9EE8` | accent d'activation lisible sur bleu nuit |
| `--border` | `var(--grey-300)` | `#E4E9F0` | bordure plus fine/nette sur surface claire |

### 4.2 Tokens ajoutés — surfaces premium

| Token | Valeur | Usage |
|---|---|---|
| `--surface` | `#FFFFFF` | fond cartes, header |
| `--surface-muted` | `#F7F9FC` | fonds secondaires (main, en-têtes légers) |
| `--surface-sunken` | `#EEF2F7` | zones en creux (legende, filtres) |
| `--card-border` | `#E1E7EF` | bordure des cartes premium |
| `--card-shadow` | `0 1px 2px rgba(15,29,51,0.04), 0 1px 3px rgba(15,29,51,0.06)` | élévation au repos |
| `--card-shadow-hover` | `0 2px 4px rgba(15,29,51,0.06), 0 6px 16px rgba(15,29,51,0.08)` | élévation au survol |
| `--radius-xl` | `12px` | rayon des cartes premium |
| `--radius-pill` | `999px` | pastille d'icône, badges |
| `--header-surface` | `var(--surface)` | fond header |
| `--header-border` | `var(--card-border)` | bordure basse header |

### 4.3 Tokens ajoutés — accents par registre

Chaque clé de registre expose désormais **trois** tokens (au lieu d'un) :

- `--theme-<clé>` : couleur d'accent (inchangée) — barre latérale, pastille, badges, boutons.
- `--theme-<clé>-tint` : fond clair de la carte.
- `--theme-<clé>-ink` : couleur de texte accentuée lisible sur `-tint` (≥ 4.5:1).

| Clé | Accent `--theme-<clé>` | Fond `-tint` | Encre `-ink` |
|---|---|---|---|
| rsst | `#2E5C8A` | `#EDF2F8` | `#24486D` |
| rami | `#6C6C6C` | `#F2F2F2` | `#4F4F4F` |
| dgi | `#b91c1c` | `#FDF0F0` | `#8F1616` |
| vert | `#15803D` | `#EDF7F0` | `#116032` |
| violet | `#7C3AED` | `#F3EFFD` | `#5F2DB5` |
| orange | `#C2410C` | `#FDF1EB` | `#93320A` |
| teal | `#0F766E` | `#EAF6F4` | `#0B5A54` |
| indigo | `#4338CA` | `#EFEEFB` | `#342CA0` |
| rose | `#BE123C` | `#FCEEF2` | `#92102E` |
| ambre | `#B45309` | `#FCF4E9` | `#87400B` |

### 4.4 Tokens ajoutés — nuage de mots

| Token | Valeur | Usage |
|---|---|---|
| `--word-cloud-ink` | `var(--theme-<clé>-ink)` (défini sur `.registry-card--<clé>`) | encre des mots, lue par `wordcloud.js` |
| `--word-cloud-min` | `0.8rem` | taille minimale d'un mot |
| `--word-cloud-max` | `1.5rem` | taille maximale d'un mot |

### 4.5 Tokens ajoutés — typographie et espacement premium

| Token | Valeur | Usage |
|---|---|---|
| `--stat-size` | `clamp(1.5rem, 1.2rem + 1vw, 2rem)` | compteur dominant de carte |
| `--laptop-min` | `1024px` | plancher de largeur supporté |
| `--content-max-width` | `1240px` | borne de lecture à 1440 px |

## 5. Architecture et flux

### 5.1 Chaîne de service CSS

`templates/header.php` appelle `cssLink('css/style.css')` → `src/helpers/assets.php` → `AssetService::cssLink()` → `css.php?f=css/style.css&v=<version>`. La version provient de `AssetService::getAppVersion()` (alimentée par `CHANGELOG.md`). `wordcloud.js` suit le même mécanisme via `assetLink()` → `asset.php?f=js/wordcloud.js&v=<version>`. Aucun build : les fichiers sont statiques et servis tels quels.

### 5.2 Shell

`src/Router/Renderer.php` émet la structure inchangée : `.header` (rôle `banner`), `.sidebar-overlay`, `.sidebar` (`#main-nav`, rôle `navigation`), `.main` (`#main-content`, rôle `main`), `.skip-link`. La refonte porte uniquement sur les règles CSS de ces sélecteurs.

### 5.3 Accueil

`pages/home.php` → `buildRegistryCards()` → `RegistryCardService::buildRegistryCards()` construit les `RegistryCardData` (thème résolu par `RegistryRepository::themeClasses($colorTheme)` → `registry-card--<clé>`) → `renderRegistryCards()` (`src/helpers/registry_card_renderer.php`) produit le HTML. Le nuage de mots par registre vient de `FormattingService::buildWordCloud($code)` et est injecté dans `.registry-card__extra`.

### 5.4 Contrat de thème

Le HTML ne code **jamais** une couleur : il porte `registry-card--<clé>` ; le CSS résout `--theme-<clé>`, `--theme-<clé>-tint`, `--theme-<clé>-ink`. Étendre le catalogue à un nouveau registre = ajouter trois tokens, sans toucher aux règles de composant. Un thème inconnu dégrade vers le neutre (`--surface` + `--card-border`), jamais vers une exception.

### 5.5 Nuage de mots

`public/js/wordcloud.js` place les mots en spiral avec détection de collision et applique des styles **inline** (dont une palette `colors` blanc/bleu clair prévue pour l'ancien fond saturé foncé). Deux corrections ciblées :

1. Lire l'encre depuis `--word-cloud-ink` via `getComputedStyle(el).getPropertyValue('--word-cloud-ink')`, avec repli sur une palette sombre neutre si la variable est absente.
2. Encadrer la taille entre `--word-cloud-min` et `--word-cloud-max`, et augmenter les marges de collision (`+8px` horizontal, `+4px` vertical actuels → `+10px` / `+6px`) ainsi que le nombre d'étapes de placement pour éviter les mots rognés.

Le conteneur `.word-cloud` conserve `role="img"` et `aria-label="Nuage de mots"` (accessibilité), et `min-height` passe de `180px` à `160px` pour rester compact à 1280×800.

### 5.6 Exception markup justifiée

Dans `src/helpers/registry_card_renderer.php`, la ligne de statistique actuelle (`.registry-card__stat` = « N signalement(s) enregistré(s) ») est scindée en deux spans :

```html
<div class="registry-card__stat">
  <span class="registry-card__stat-value">N</span>
  <span class="registry-card__stat-label">signalement(s) enregistré(s)</span>
</div>
```

Cette scission est la **seule** modification de markup de cette passe. Elle est nécessaire pour donner au compteur la dominance visuelle demandée (O3) sans dupliquer l'information ni introduire de style inline. Le libellé complet reste lisible par les lecteurs d'écran (les deux spans sont adjacents, pas de `display:none`). Aucun autre template, partial ou handler n'est modifié.

## 6. Sélecteurs concernés (état 2026-09-23, avant modification)

| Sélecteur | Fichier / lignes actuelles | Rôle dans cette passe |
|---|---|---|
| `:root` | `style.css` 10–135 | tokens modifiés/ajoutés (§4) |
| `.header`, `.header__logo`, `.header__logo-img`, `.header__logo-text`, `.header__title`, `.header__user`, `.header__username`, `.header__logout`, `.header__menu-btn` | 212–274, 521–536 | header contemporain, surface claire, relief et densité |
| `.sidebar`, `.sidebar__nav`, `.sidebar__item`, `.sidebar__item:hover`, `.sidebar__item--active`, `.sidebar__icon`, `.sidebar__footer` | 543–611 | bleu nuit, item actif à double signal |
| `.sidebar-overlay`, `.sidebar-toggle-checkbox` | 512–521, 614–621 | conservés (mobile) |
| `.main` | 626–637 | fond `--surface-muted`, borne `--content-max-width` à 1440 |
| `.page-title`, `.page-title--compact` | 666–680 | hiérarchie de titre alignée sur le premium |
| `.registry-cards`, `.registry-cards > *` | 714–723 | grille plafonnée à 4 colonnes, min 250 px |
| `.registry-card`, `.registry-card:hover` | 725–741 | surface claire, rembourrage, rayon `--radius-xl`, ombres basses |
| `.registry-card--<clé>` (10) | 760–922 | fond teinté + barre d'accent (plus de fond saturé) |
| `.registry-card--dgi` | 792–810 | alignement sur le tronc commun + accent danger |
| `.registry-card__icon` | 924 | pastille circulaire (`--radius-pill`) |
| `.registry-card__title`, `__subtitle`, `__desc`, `__stat` | 925–927, 942–947 | hiérarchie typographique, compteur dominant |
| `.registry-card__stat-value`, `__stat-label` (nouveaux) | — | compteur dominant (cf. §5.6) |
| `.registry-card__btn`, `.registry-card__link` | 929–957 | action primaire vs secondaire |
| `.registry-card__extra` | [complément registry-card] | hôte du nuage de mots, encre adaptée |
| `.workflow-legend`, `__item`, `__item--muted`, `__text`, `__arrow` | 1096–1127 | légende compacte |
| `.word-cloud`, `.word-cloud__word`, `.word-cloud__word--noscript`, `.wc-s1`…`.wc-s6` | 4132–4166 | lisibilité (couleur, tailles) |
| `.card`, `.card__title` | 685–702 | alignement premium des cartes génériques |
| MQ `max-width: 768px` / `480px` | 3300–3600 (zone responsive) | conservées, cf. §9 |
| MQ `prefers-reduced-motion`, `prefers-contrast` | 180–207 | conservées, contrastes premium rejoués |

## 7. Accessibilité

- **Contrastes** : `CssContrastContractTest` est étendu aux nouvelles paires (`--theme-<clé>-ink` sur `--theme-<clé>-tint`, libellé sidebar actif sur `--sidebar-bg`, `--sidebar-text` sur `--sidebar-bg`). Aucun texte de carte ni mot de nuage sous 4.5:1 (O4, O5).
- **Focus visible** : le contrat existant (`--focus-ring-color`, `--focus-ring-offset`) est conservé ; les nouvelles surfaces claires doivent garder un anneau visible sur header et cartes.
- **Non-chromatique** : l'état actif de la sidebar et l'accent de registre sont doublés d'un marqueur de forme (barre, pastille), jamais de la couleur seule. L'action de déconnexion et l'action primaire restent distinctes par forme et graisse, pas seulement par teinte.
- **Cibles tactiles** : plancher existant ≥ 44 px conservé pour boutons, liens d'action et items de menu (mobile).
- **Sémantique** : rôles `banner` / `navigation` / `main` et `aria-label` intacts ; le nuage de mots garde `role="img"` + `aria-label`.
- **Mouvement** : `prefers-reduced-motion` neutralise les transitions/transformations (dont l'effet de survol des cartes).
- **Ordre de tabulation** : inchangé (aucun réordonnancement DOM ; l'ordre visuel du compteur reste après les actions dans le DOM et n'introduit pas de piège clavier).
- **Zoom** : la mise en page reste utilisable à 200 % de zoom (équivalent ~640 px effectifs) via les points de rupture mobile conservés.

## 8. Stratégie de validation

### 8.1 Playwright — matrice laptop

La priorité est validée à **1280×800**, contrôlée à **1440×900** et éprouvée au plancher **1024×768**.

- `playwright.config.js` : le viewport par défaut passe de `1280×720` à **`1280×800`** (priorité laptop).
- Nouveau fichier `e2e/visual-premium.spec.js` (login via les helpers E2E existants) exécuté à trois viewports via `test.use({ viewport: … })` : `1280×800`, `1440×900`, `1024×768`.
- Assertions par page (accueil, `report_list`, `report_view`) :
  1. **O1** — `page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)` est vrai.
  2. **O2** — les cartes `.registry-card` sont visibles, non rognées, et le nombre de colonnes de `.registry-cards` est conforme (3 à 1024/1280, 3–4 à 1440).
  3. **O6** — la hauteur de `.workflow-legend` à 1280/1440 est ≤ 44 px.
  4. **O5** — pour chaque `.word-cloud__word`, la taille calculée est ≥ 12.8 px (0.8 rem) et le mot est entièrement dans la boîte du conteneur (pas de rognage).
  5. **O3** — `font-size` calculé de `.registry-card__stat-value` > `font-size` calculé de `.registry-card__desc`.
- Captures de contrôle : les fichiers `docs/screenshots/premium/accueil-{1024,1280,1440}.png` sont produits par la passe de validation.

### 8.2 Captures d'écran

- `tools/capture_screenshots.py` : viewport prioritaire porté de `1280×900` à **`1280×800`** ; la largeur de clip et la génération des captures restent alignées sur cette valeur.
- `tools/annotate_screenshots.py` : viewport d'annotation aligné sur `1280×800` (aujourd'hui `1280×900`).
- La matrice 1440×900 et 1024×768 est générée par le nouveau spec Playwright (§8.1), sans dupliquer la logique d'annotation.

### 8.3 Tests PHPUnit

- **`CssDesignSystemTest`** : jeu de tokens attendus mis à jour vers les valeurs cibles de §4 (c'est le contrat de la spec).
- **`CssContrastContractTest`** : ajout des paires de contraste §7 ; seuil 4.5:1 conservé.
- **Nouveau `CssRegistryCardPremiumTest`** : verrouille que `.registry-card--<clé>` utilise `--theme-<clé>-tint` en fond et `--theme-<clé>` en accent, que `.registry-card__icon` est en pastille (`--radius-pill`), et que les 10 clés restent couvertes (aucune régression, fallback neutre pour clé inconnue).
- **Non-régression markup** : les tests qui assertent les classes (`registry-card__btn`, `badge--*`, `btn--danger`, etc.) restent verts — aucun renommage de classe n'est autorisé.
- **PHPStan level 8** : 0 erreur.

### 8.4 E2E

Les shards Playwright existants (navigation, formulaires, rôles) restent verts : ils garantissent qu'aucun parcours n'est cassé, y compris l'accueil, les listes et les fiches.

## 9. Responsive et compatibilité

- **Laptop (≥ 1024 px)** : sidebar fixe, header fixe, main décalé de `--sidebar-width`, grille de registres 3–4 colonnes, légende sur une ligne à 1280/1440.
- **`max-width: 768px`** : comportements de la première passe intégralement conservés — sidebar en panneau superposé (`.sidebar-overlay` + checkbox CSS-only), tableaux empilés via `data-label`, grilles en une colonne, actions pleine largeur, cibles ≥ 44 px.
- **`max-width: 480px`** : densité et paddings conservés de la première passe.
- **`prefers-reduced-motion`** et **`prefers-contrast: high`** : conservés ; les contrastes premium sont rejoués sous `prefers-contrast`.
- **Impression** : feuille d'impression conservée sans modification.
- **Navigateurs** : Edge/Chrome et Firefox récents (les deux projets Playwright existants) ; pas de `color-mix()` ni de fonctionnalité non supportée par ces deux moteurs sans fallback explicite.

## 10. Déploiement et cache-busting

- **Version** : `CHANGELOG.md` passe à `3.66.2` (passe visuelle, pas de changement fonctionnel). `AssetService::getAppVersion()` alimente `?v=` pour **`css/style.css` (via `css.php`) et `js/wordcloud.js` (via `asset.php`)**, donc les deux ressources sont invalides d'un coup. Aucun paramètre manuel, aucune purge de cache serveur.
- **Aucune modification** de `web.config`, des en-têtes de cache, de la CSP existante (`templates/header.php`) ni de la configuration IIS.
- **CSS** : `public/css/style.css` reste un fichier statique ; `login.css` inchangé.
- **JS** : `public/js/wordcloud.js` modifié uniquement pour la lisibilité (§5.5) ; aucune dépendance ajoutée.
- **Rollback** : revenir au commit précédent restaure l'ancienne URL `?v=3.66.1` ; aucune migration de données.

## 11. Hors périmètre

- **Authentification IIS** (NTLM/Kerberos), `public/web.config`, `pages/login.php`, `public/css/login.css` (mode dev, hors périmètre visuel).
- **Routes, handlers, services, repositories, base de données, cron, notifications, e-mails** : aucune modification.
- **Modification du HTML des templates** autre que l'exception unique de §5.6.
- **Refonte mobile** : le responsive ≤ 768/480 px est conservé, pas optimisé.
- **Mode sombre / sélecteur de thème utilisateur**.
- **Framework CSS, bibliothèque de composants, bundler, préprocesseur** : aucune dépendance, aucun build.
- **Charte graphique** (logo, polices système) au-delà des tokens ; **feuille d'impression**.
- **Renommage de classes** ou de tokens existants consommés par le PHP (contrat de tests).
- **Accessibilité** : pas d'audit externe (axe/Lighthouse) ni de nouvelle dépendance de test ; les vérifications de contraste restent calculées en PHPUnit sur les tokens `:root`.