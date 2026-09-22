# Design Spec : Modernisation visuelle de l'application SST

**Date :** 2026-09-22
**Scope :** couche visuelle (`public/css/style.css`) — shell, composants, tokens, responsive, accessibilité
**Approach :** refactorisation CSS progressive, sans modification de templates ni de logique PHP
**Statut :** design approuvé (document de référence, aucune implémentation CSS incluse)

## Objectif

Moderniser l'interface de l'application SST (DREETS BFC) pour la rendre plus lisible, plus
cohérente et plus sobre, **sans changer les parcours, les routes ni le HTML généré**. La
modernisation porte exclusivement sur la couche de présentation : un jeu de tokens CSS
canonique, un shell homogène (header / sidebar / main) et trois familles de composants
(cartes, tableaux, formulaires) gouvernées par ces tokens.

Le document fixe les décisions de design afin qu'une étape d'implémentation ultérieure
puisse être exécutée sans arbitrage visuel supplémentaire.

## Périmètre de la première étape

Inclus :

- **Tokens CSS** : consolidation d'un jeu unique de variables `:root` (couleurs, espacements,
  typographie, rayons, ombres, transitions, z-index) servant de source de vérité.
- **Shell** : `.header`, `.sidebar`, `.main` (voir `src/Router/Renderer.php`) et leurs
  éléments (`skip-link`, `sidebar-overlay`, `sidebar-toggle-checkbox`, `header__menu-btn`).
- **Composants** : `.card`, `.table-wrapper`, `.form-group` / `.form-control` / `.form-grid`.
- **Responsive** : points de rupture existants `768px` et `480px`, plus les garde-fous
  `prefers-reduced-motion` et `prefers-contrast`.
- **Accessibilité** : focus visible, contrastes AA, cibles tactiles, ordre de tabulation.

Le périmètre de la première étape **conserve les noms de classes et la structure HTML
existants** : la refonte se fait dans `public/css/style.css` uniquement. Aucun template,
aucun handler, aucun service n'est modifié à ce stade.

## Direction visuelle

- **Registre institutionnel sobre** : interface d'administration publique, densité
  d'information élevée, aucune décoration gratuite.
- **Bleu de confiance** : couleur primaire `#0056A3` (variantes `#003D75` / `#3498DB`)
  conservée comme ancrage de marque.
- **Plat, net, sans gradient de décoration** : séparations par bordures fines et ombres
  discrètes, pas d'effets de profondeur excessifs.
- **Hiérarchie par l'espace et la typographie**, pas par la couleur : titres, libellés et
  valeurs se distinguent par la taille, la graisse et les marges.
- **Un seul thème clair** : aucun mode sombre dans cette étape (voir Hors périmètre).
- **Couleur = information** : les couleurs saturées sont réservées aux états, rôles,
  thèmes de registres et actions destructrices. Le reste de l'interface est neutre (gris).

## Tokens CSS

Le vocabulaire de design est entièrement porté par des variables `:root`, déjà présentes
et à consolider. **Tout composant consomme des tokens ; aucun composant ne code une valeur
littérale en dur.**

### Couleurs de marque

| Token | Valeur | Usage |
|---|---|---|
| `--color-primary` | `#0056A3` | actions primaires, liens, accents |
| `--color-primary-dark` | `#003D75` | survol / état actif des actions primaires |
| `--color-primary-light` | `#3498DB` | surbrillance de navigation active |

### Neutres

| Token | Valeur | Usage |
|---|---|---|
| `--grey-50` … `--grey-900` | `#FAFAFA` → `#212121` | fonds, bordures, textes secondaires |
| `--border` | `var(--grey-300)` | bordure standard des cartes, tableaux, champs |
| `--hover-highlight` | `#E8F0FE` | survol de lignes et d'éléments de liste |

### Sémantique (états, rôles, visibilité)

| Token | Valeur |
|---|---|
| `--color-success-bg/-border/-text` | `#d4edda` / `#c3e6cb` / `#155724` |
| `--color-danger-bg/-border/-text` | `#f8d7da` / `#f5c6cb` / `#721c24` |
| `--color-warning-bg/-border/-text` | `#fff3cd` / `#ffeeba` / `#856404` |
| `--color-info-bg/-border/-text` | `#d1ecf1` / `#bee5eb` / `#0c5460` |
| `--state-nouveau/-en-cours/-traite/-abandonne` | `#2E5C8A` / `#E67E22` / `#27AE60` / `#7B8D8E` |
| `--role-agent/-superviseur/-chsct` | `#2E5C8A` / `#B22222` / `#8E44AD` |
| `--visibility-confidential/-public` | `#6b7280` / `#22c55e` |

### Typographie

| Token | Valeur | Usage |
|---|---|---|
| `--font-family` | `'Segoe UI', Tahoma, Geneva, Verdana, sans-serif` | police système unique |
| `--font-size-xs` … `--font-size-3xl` | échelle `clamp(...)` fluide | texte courant, libellés, titres |

L'échelle typographique reste en `clamp()` pour assurer une mise à l'échelle fluide entre
mobile et desktop sans media queries dédiées.

### Espacements

`--space-1` `0.25rem` → `--space-8` `2rem` (pas de 4 px). Les marges et paddings des
composants utilisent exclusivement ces tokens.

### Rayons, ombres, transitions, z-index

| Token | Valeur |
|---|---|
| `--border-radius` / `--border-radius-lg` | `4px` / `8px` |
| `--shadow-sm/-/-md/-lg/-xl` | échelle d'ombres discrètes existante |
| `--transition-fast` / `--transition-base` | `0.15s ease` / `0.2s ease` |
| `--sidebar-width` / `--header-height` / `--content-padding` | `220px` / `60px` / `24px` |
| `--z-sidebar` / `--z-header` / `--z-mobile-menu` / `--z-overlay` / `--z-skip-link` | `90` / `100` / `200` / `150` / `9999` |

### Focus

| Token | Valeur |
|---|---|
| `--focus-ring-color` | `rgba(0,86,163,0.4)` |
| `--focus-ring-offset` | `2px` |

## Shell : header / sidebar / main

Structure cible (inchangée, déjà émise par `src/Router/Renderer.php`) :

```
.header                       rôle banner, hauteur --header-height
  .header__logo / __logo-img / __logo-text / __title
  .header__user → .header__menu-btn (mobile), .header__username, badge de rôle
  .header__logout / .impersonate-dropdown
.sidebar-overlay              calque mobile (checkbox), aria-hidden
.sidebar  #main-nav           rôle navigation, largeur --sidebar-width
  .sidebar__nav > li > .sidebar__item (+ --active) > .sidebar__icon
.main  #main-content          rôle main, marge gauche --sidebar-width (desktop)
.skip-link                    deux liens d'évitement (contenu, navigation)
```

Règles de design :

- **Header** : bandeau fixe en haut, fond clair, bordure basse `--border`. Logo à gauche,
  identité utilisateur et déconnexion à droite. La pastille de rôle (`.badge--sm`) reste
  alignée verticalement avec le nom d'utilisateur.
- **Sidebar** : colonne fixe, fond `--sidebar-bg`, texte `--sidebar-text`, survol
  `--sidebar-hover`. L'élément actif (`.sidebar__item--active`) est signalé par la couleur
  `--sidebar-active` **et** un marqueur de bordure, jamais par la couleur seule.
- **Main** : contenu décalé de `--sidebar-width`, padding `--content-padding`. La largeur
  maximale de lecture est bornée pour éviter les lignes de texte trop longues.
- **Mobile** : la sidebar devient un panneau superposé (`.sidebar-overlay` + checkbox
  `.sidebar-toggle-checkbox`), togglé par `.header__menu-btn`. Interaction **CSS-only**,
  sans JavaScript.
- **Ordre z** : overlay sous le panneau mobile, lui-même sous le header ; les skip-links
  restent au-dessus de tout (`--z-skip-link`).

## Composants

### Cartes (`.card`)

- Fond blanc, bordure `1px solid var(--border)`, rayon `--border-radius`, ombre `--shadow-sm`.
- Padding `--space-4` (adapté en mobile).
- Variantes de thème `--card--<clé>` (voir Compatibilité thèmes registres).
- Variantes de disposition conservées : `.card--spaced`, `.card--flush-top`,
  `.card--narrow-center`, `.card--dashed`.
- `.card--danger` réserve la couleur danger au panneau de suppression/purge.

### Tableaux (`.table-wrapper`)

- Conteneur `.table-wrapper` assurant le défilement horizontal.
- En-têtes légers (fond neutre, majuscules, `--font-size-xs`), lignes séparées par
  `--border`, survol de ligne `--hover-highlight`, alternance de lignes via `nth-child(even)`.
- **Responsive** : la variante `.table-wrapper--responsive` empile les cellules sous
  `768px` en s'appuyant sur l'attribut `data-label` de chaque `td` (libellé de colonne
  affiché avant la valeur). Aucune donnée ne doit sortir de l'écran.
- Colonne d'actions alignée en fin de ligne, largeurs stables.

### Formulaires

- `.form-group` : libellé au-dessus du champ, marge `--space-2`, message d'aide
  `.form-hint` / `.form-hint--lg`.
- `.form-control` : hauteur homogène, bordure `--border`, rayon `--border-radius`, focus
  via `--focus-ring-*`. États `:invalid` / `:valid` signalés uniquement lorsque le champ
  est rempli (`:not(:placeholder-shown)`) pour éviter les faux positifs.
- `.form-grid` et variantes (`--2`, `--3`, `--4`, `__full`) : grille responsive, repli en
  une colonne sous `768px`.
- `.form-error-summary` : encart d'erreurs en tête de formulaire, `role`/focus gérés par
  le HTML existant.
- Actions : `.form-actions` / `.form-actions__group`, boutons alignés en fin de formulaire,
  `.btn--full` en mobile pour les actions principales.
- Boutons : hiérarchie `.btn--primary`, `.btn--secondary`, `.btn--outline`, `.btn--danger`,
  tailles `.btn--sm`. Aucun style inline.

## Responsive

Points de rupture, alignés sur l'existant :

| Seuil | Comportement |
|---|---|
| Desktop (`> 768px`) | sidebar fixe visible, main décalé de `--sidebar-width`, grilles multi-colonnes |
| `max-width: 768px` | sidebar en panneau superposé, tableaux empilés (`data-label`), grilles en une colonne, boutons pleine largeur |
| `max-width: 480px` | densité réduite, padding `--space-3`, titres à l'échelle basse |
| `prefers-reduced-motion: reduce` | transitions neutralisées |
| `prefers-contrast: high` | bordures renforcées, contrastes maximisés |
| `print` | mise en page épurée conservée |

La grille et les espacements sont pilotés par les tokens : réduire l'échelle ne doit
nécessiter aucune règle ad hoc dans les composants.

## Accessibilité

- **Contrastes** : texte et composants interactifs ≥ 4.5:1 (AA) sur fond clair ; les
  couleurs sémantiques sont toujours accompagnées d'un libellé ou d'une icône.
- **Focus visible** : anneau `--focus-ring-color` + `--focus-ring-offset` sur tous les
  éléments interactifs, jamais supprimé sans remplacement équivalent.
- **Navigation clavier** : ordre de tabulation naturel, skip-links `#main-content` et
  `#main-nav` opérationnels, contrôles CSS-only (checkbox) activables au clavier.
- **Sémantique** : rôles `banner` / `navigation` / `main`, `aria-label` du menu principal,
  `aria-hidden` des éléments décoratifs et du calque mobile (déjà en place).
- **Mouvement** : respect de `prefers-reduced-motion`.
- **Cibles tactiles** : taille minimale confortable (≥ 44 px recommandé) pour boutons et
  éléments de menu en mobile.

## Compatibilité thèmes registres

La couleur d'un registre provient de la colonne `registries.color_theme` et ne doit
**jamais être codée en dur** dans un composant. Le contrat est :

1. Un token `--theme-<clé>` définit la teinte (10 clés : `rsst`, `rami`, `dgi`, `vert`,
   `violet`, `orange`, `teal`, `indigo`, `rose`, `ambre`).
2. Trois classes modificatrices consomment ce token :
   `.card--<clé>`, `.badge--<clé>`, `.btn--<clé>`.
3. Le HTML choisit la classe à partir de la clé lue en base, via les helpers existants
   (`FormattingService::getRegistryBadgeClass()`, `RegistryCardService`) — pas via un
   `match` de couleurs.

Règles :

- Étendre le catalogue à un nouveau registre = **ajouter un token `--theme-<clé>` et trois
  modificateurs**, sans toucher aux règles des composants.
- Un thème custom inconnu dégrade proprement vers la couleur neutre/par défaut, jamais
  vers une exception.
- Les tokens d'état (`--state-*`), de rôle (`--role-*`) et de visibilité
  (`--visibility-*`) suivent le même principe : valeur unique, consommée par les classes
  `.badge--*` correspondantes.

## Stratégie de validation

La refactorisation CSS est validée par empilement de garde-fous, sans introduire de
dépendance nouvelle :

1. **Non-régression fonctionnelle** : `rtk phpunit --no-coverage` et
   `rtk phpstan analyse --memory-limit=1G` restent verts. Les tests qui assertent des
   classes (`btn--danger`, `badge--*`, etc.) constituent un contrat : aucun renommage de
   classe n'est autorisé.
2. **Conformité CSP** : le job CI « CSP compliance » (absence de styles inline, classes
   déclarées) doit passer ; le CSS reste dans `public/css/style.css`.
3. **Contrat visuel** : régénération des captures (`py -3 tools/capture_screenshots.py`
   puis `tools/annotate_screenshots.py`) et comparaison avant/après pour confirmer que
   seuls les changements voulus apparaissent.
4. **Matrice de thèmes** : vérification des 10 thèmes sur `.card--*`, `.badge--*`,
   `.btn--*` (aucune régression de couleur, fallback correct pour un thème inconnu).
5. **Responsive** : contrôle aux largeurs `480px`, `768px` et `1280px`, tableaux empilés
   et sidebar mobile inclus.
6. **Accessibilité** : parcours clavier complet, focus visible, contrastes AA,
   `prefers-reduced-motion` et `prefers-contrast`.
7. **E2E** : les shards Playwright existants (navigation, formulaires, rôles, etc.)
   doivent rester verts, ce qui garantit que la refonte n'a pas cassé un parcours.

## Hors périmètre

- Aucune modification du HTML des templates, des handlers, des routes ou de la logique
  métier dans cette étape.
- Aucun framework CSS ni bibliothèque de composants (Tailwind, Bootstrap, etc.) et aucune
  dépendance nouvelle.
- Aucun outil de build front (bundler, préprocesseur) : le CSS reste un fichier statique.
- Aucun mode sombre ni sélecteur de thème utilisateur.
- Aucune refonte de la structure de navigation, des libellés ou de l'iconographie.
- Aucun changement de charte (logo, couleurs de marque) au-delà des tokens.
- Aucune modification des modèles d'e-mail ni de la feuille de style d'impression
  au-delà de sa conservation.