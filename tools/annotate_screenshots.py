#!/usr/bin/env python3
"""
Add annotations (numbered callouts with arrows) to screenshot PNGs.
Uses Playwright to detect REAL element positions from HTML, then draws
annotations at the correct pixel coordinates on the captured PNGs.

v2 — Fixed annotation overlap and positioning issues:
  - Targets are drawn at element EDGES (not centers) to avoid obscuring content
  - Badges are placed in a dedicated margin area (right side)
  - Collision detection prevents badge overlap
  - Descriptions wrap if needed and stay within image bounds

Workflow:
  1. capture_screenshots.py  →  public/screenshots/*.png + docs/screenshots/*.png
  2. annotate_screenshots.py →  reads HTML positions via Playwright,
                                 annotates PNGs in public/screenshots/,
                                 copies to docs/screenshots/
"""
from PIL import Image, ImageDraw, ImageFont
from playwright.sync_api import sync_playwright
import os
import math
import shutil
import textwrap
from pathlib import Path

BASE_DIR = Path(__file__).parent.parent
HTML_DIR = BASE_DIR / "docs" / "screenshots"
SCREENSHOTS_DIR = BASE_DIR / "public" / "screenshots"
DOCS_DIR = BASE_DIR / "docs" / "screenshots"

# Colors
RED = (204, 51, 0)
WHITE = (255, 255, 255)
DARK = (51, 51, 51)
LIGHT_BG = (255, 255, 240)  # light yellow-white for description background

# Annotation layout constants
BADGE_RADIUS = 14
ARROW_GAP = 6        # gap between target edge and arrow start
DESC_FONT_SIZE = 13
BADGE_FONT_SIZE = 16
RIGHT_MARGIN = 20     # right margin for badge placement
TOP_MARGIN = 20       # top margin
VERT_SPACING = 50     # minimum vertical spacing between badges
MAX_DESC_WIDTH = 22    # max chars per line for description


def get_font(size=16):
    for fp in ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
               '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
               '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
               '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf']:
        if os.path.exists(fp):
            try:
                return ImageFont.truetype(fp, size)
            except Exception:
                continue
    return ImageFont.load_default()


def draw_badge(draw, cx, cy, number, font):
    """Draw a numbered circle badge."""
    r = BADGE_RADIUS
    draw.ellipse([cx - r, cy - r, cx + r, cy + r], fill=RED, outline=(153, 38, 0), width=2)
    text = str(number)
    bbox = font.getbbox(text)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    draw.text((cx - tw // 2, cy - th // 2 - 1), text, fill=WHITE, font=font)


def draw_target(draw, cx, cy, w=0, h=0):
    """Draw a target indicator at the EDGE of an element, not on top of it.
    Places a small open circle at the nearest edge corner."""
    # Use a small open circle with crosshair — visible but not obscuring
    r = 6
    draw.ellipse([cx - r - 2, cy - r - 2, cx + r + 2, cy + r + 2], outline=RED, width=2)


def draw_arrow(draw, x1, y1, x2, y2):
    """Draw an arrow from badge to target."""
    # Dashed-style line (solid for simplicity)
    draw.line([(x1, y1), (x2, y2)], fill=RED, width=2)
    # Arrowhead at target end
    angle = math.atan2(y2 - y1, x2 - x1)
    arrow_len = 10
    for da in [2.5, -2.5]:
        ax = x2 - arrow_len * math.cos(angle + da * 0.3)
        ay = y2 - arrow_len * math.sin(angle + da * 0.3)
        draw.line([(x2, y2), (int(ax), int(ay))], fill=RED, width=2)


def draw_description(draw, x, y, text, font, img_width):
    """Draw a description text with background, wrapping if needed."""
    lines = textwrap.wrap(text, width=MAX_DESC_WIDTH) if len(text) > MAX_DESC_WIDTH else [text]

    padding = 4
    line_height = font.getbbox("Ay")[3] + 6  # approximate line height

    # Calculate total text block size
    max_tw = 0
    for line in lines:
        bbox = font.getbbox(line)
        tw = bbox[2] - bbox[0]
        max_tw = max(max_tw, tw)

    total_h = line_height * len(lines)

    # Ensure text stays within image bounds
    text_x = x
    text_y = y
    if text_x + max_tw + padding * 2 > img_width:
        text_x = max(img_width - max_tw - padding * 2 - 10, 10)
    if text_y < 10:
        text_y = 10

    # Draw background rectangle
    draw.rectangle(
        [text_x - padding, text_y - padding,
         text_x + max_tw + padding, text_y + total_h + padding],
        fill=LIGHT_BG, outline=(200, 200, 180), width=1
    )

    # Draw each line
    for i, line in enumerate(lines):
        draw.text((text_x, text_y + i * line_height), line, fill=DARK, font=font)

    return text_x, text_y, max_tw + padding * 2, total_h + padding * 2


def compute_badge_positions(positions, img_width, img_height):
    """
    Compute non-overlapping badge positions for all annotations.
    
    Strategy: Place badges in the right margin, spread vertically.
    If there are many badges, distribute them evenly along the right side.
    """
    n = len(positions)
    if n == 0:
        return []

    # Target: place each target at the RIGHT EDGE of the element
    targets = []
    for (x, y, desc, w, h) in positions:
        # Place target at the right edge of the element, vertically centered
        tx = min(x + w // 2 + 4, img_width - 10)  # just outside the right edge
        ty = y
        targets.append((tx, ty, desc))

    # Badge column: right side of the image
    badge_x = img_width - 40 - RIGHT_MARGIN

    # Spread badges vertically: evenly distribute in available height
    available_top = TOP_MARGIN + BADGE_RADIUS
    available_bottom = img_height - TOP_MARGIN - BADGE_RADIUS
    available_height = available_bottom - available_top

    if n == 1:
        badge_ys = [max(targets[0][1], available_top)]
    else:
        # Evenly space badges, but try to keep them near their targets
        spacing = max(VERT_SPACING, available_height / n)
        # Start from a position that centers the group
        ideal_start = sum(ty for (_, ty, _) in targets) / n - spacing * (n - 1) / 2
        start_y = max(available_top, min(ideal_start, available_bottom - spacing * (n - 1)))

        badge_ys = []
        for i in range(n):
            # Ideal position: near the target's y
            ideal_y = targets[i][1]
            # Constrained position: within the spread, with minimum spacing
            min_y = available_top + i * min(VERT_SPACING, spacing)
            max_y = available_bottom - (n - 1 - i) * min(VERT_SPACING, spacing)
            # Blend ideal with constrained
            by = max(min_y, min(ideal_y, max_y))
            badge_ys.append(by)

    # Ensure minimum spacing between consecutive badges
    for i in range(1, len(badge_ys)):
        if badge_ys[i] - badge_ys[i-1] < VERT_SPACING:
            badge_ys[i] = badge_ys[i-1] + VERT_SPACING

    result = []
    for i, (tx, ty, desc) in enumerate(targets):
        result.append((badge_x, int(badge_ys[i]), tx, ty, desc))

    return result


# ──────────────────────────────────────────────────────────────────────
# CSS selectors for each screenshot's annotated elements.
# Playwright will return the bounding box center of each matched element.
# Format:  filename → list of (CSS_selector, description)
# ──────────────────────────────────────────────────────────────────────
ELEMENT_ANNOTATIONS = {
    "cu1-accueil.html": [
        ("header.header", "Barre d'en-tête"),
        ("nav.sidebar", "Menu latéral"),
        (".registry-card--rsst", "Carte RSST"),
        (".registry-card--rami", "Carte RAMI"),
        (".registry-card--dgi", "Carte DGI"),
    ],
    "cu1-accueil-superviseur.html": [
        (".badge--superviseur", "Profil Superviseur"),
        ("nav.sidebar", "Menu élargi"),
        (".registry-cards", "3 registres"),
    ],
    "cu1-accueil-chsct.html": [
        (".badge--chsct", "Profil CSA/CHSCT"),
        ("nav.sidebar", "Menu restreint"),
        (".registry-cards", "Consultation seule"),
    ],
    "cu2-creation-rsst.html": [
        (".card--rsst h2, h2.mb-4", "Type RSST"),
        ("#date_evenement", "Date événement"),
        ("#description", "Description"),
        ("#site_id", "Site / Unité"),
        (".form-actions .btn--primary", "Bouton Envoyer"),
    ],
    "cu3-creation-rami.html": [
        (".card--rami h2, h2.mb-4", "Type RAMI"),
        ("#pour_compte", "Pour le compte de"),
        ("#nature_auteur", "Nature auteur"),
        ("#type_acte", "Type d'acte"),
        ("#description", "Description"),
    ],
    "cu4-creation-dgi.html": [
        (".card--dgi h2, h2.mb-4", "Type DGI"),
        (".card--dgi", "Formulaire DGI"),
        ("#description", "Description danger"),
        ("#lieu", "Lieu / Mesures"),
    ],
    "cu4-repondre-signalement.html": [
        (".card--rsst .card__subtitle", "Résumé signalement"),
        (".report-detail__table", "Détails signalement"),
        ("#nouvel_etat", "Changement statut"),
        ("#reponse", "Commentaire"),
        (".form-actions .btn--primary", "Envoyer"),
    ],
    "cu4-modifier-signalement.html": [
        (".card--rsst h2", "Modification"),
        ("form .form-group", "Champs modifiables"),
        (".confidential-toggle", "Confidentialité"),
        (".form-actions .btn--rsst", "Enregistrer"),
    ],
    "cu5-liste-signalements-sup.html": [
        (".btn-float-right", "Nouveau signalement"),
        ("#site", "Filtre Site"),
        ("input#q", "Recherche"),
        (".badge--nouveau, .badge--en-cours", "Badges d'état"),
        (".badge--confidential, .badge--public", "Visibilité"),
        (".btn--sm.btn--primary", "Répondre"),
    ],
    "consultation-liste-signalements.html": [
        (".btn-float-right", "Nouveau signalement"),
        (".filter-bar", "Filtres et recherche"),
        ("input#q", "Recherche"),
        (".badge--nouveau, .badge--en-cours", "Badges d'état"),
        (".badge--confidential, .badge--public", "Visibilité"),
        (".btn-group", "Actions"),
    ],
    "consultation-voir-rsst.html": [
        (".report-detail__header h2", "Signalement RSST"),
        (".report-detail__table", "Détails signalement"),
        (".btn-group .badge--nouveau", "État"),
        (".badge--confidential", "Visibilité"),
        (".btn--danger", "Abandonner"),
    ],
    "consultation-voir-rami.html": [
        (".report-detail__header h2", "Signalement RAMI"),
        (".report-detail__table", "Détails signalement"),
        (".report-detail__table tbody tr:nth-child(9) th", "Pour le compte de"),
        (".card h3", "Réponses"),
    ],
    "consultation-voir-dgi.html": [
        (".report-detail__header h2", "Signalement DGI"),
        (".danger-panel", "Procédure prioritaire"),
        (".report-detail__table", "Détails signalement"),
        (".card h3", "Réponses"),
    ],
    "cu6-statistiques.html": [
        (".filter-bar", "Filtres période"),
        (".indicateur-card--rsst", "Indicateur RSST"),
        (".indicateur-card--rami", "Indicateur RAMI"),
        (".indicateur-card--dgi", "Indicateur DGI"),
    ],
    "cu7-synthese.html": [
        (".filter-bar", "Filtres"),
        ("th.synthesis-th-rsst", "Colonne RSST"),
        ("th.synthesis-th-rami", "Colonne RAMI"),
        ("th.synthesis-th-dgi", "Colonne DGI"),
    ],
    "cu8-export.html": [
        ("#type", "Type registre"),
        (".date-range", "Plage de dates"),
        (".form-actions .btn--primary", "Exporter"),
    ],
    "cu9-parametres.html": [
        (".tab-bar .settings-tab:nth-child(1)", "Onglet Application"),
        (".tab-bar .settings-tab:nth-child(2)", "Onglet SMTP"),
        (".tab-bar .settings-tab:nth-child(3)", "Onglet Notifications"),
        (".card:first-of-type .card__subtitle", "Paramètres site"),
        (".form-actions .btn--success", "Enregistrer"),
    ],
    "cu10-utilisateurs.html": [
        (".form--inline", "Recherche"),
        (".btn--primary", "Rechercher"),
        ("table th:nth-child(4)", "Rôles"),
        ("table th:last-child", "Actions"),
    ],
    "cu11-journaux.html": [
        (".tab-bar", "Filtres onglets"),
        (".log-entry--info", "Entrées info"),
        (".log-entry--fatal", "Entrées fatales"),
    ],
    "cu12-aide.html": [
        ("h1.page-title", "Page Documentation"),
    ],
    "cu13-preambule.html": [
        ("h1.page-title", "Cadre juridique"),
        (".card--rsst", "Registre RSST"),
    ],
    "cu14-journal-modifs.html": [
        (".changelog-content h2:first-of-type", "Dernière version"),
    ],
    "cu15-choix-site.html": [
        (".choose-site-welcome", "Bienvenue"),
        ("#site_id", "Choix de l'unité"),
        (".btn--full", "Confirmer"),
    ],
}


def get_element_positions(page, html_file):
    """
    Use Playwright to find the bounding box of annotated elements.
    Returns list of (center_x, center_y, description, width, height).
    """
    selectors = ELEMENT_ANNOTATIONS.get(html_file, [])
    if not selectors:
        return []

    results = []
    for selector, description in selectors:
        try:
            pos = page.evaluate("""(selector) => {
                const parts = selector.split(',').map(s => s.trim());
                for (const part of parts) {
                    try {
                        const el = document.querySelector(part);
                        if (el) {
                            const rect = el.getBoundingClientRect();
                            if (rect.width > 0 && rect.height > 0) {
                                return {
                                    x: Math.round(rect.x + rect.width / 2),
                                    y: Math.round(rect.y + rect.height / 2),
                                    w: Math.round(rect.width),
                                    h: Math.round(rect.height),
                                    found: true
                                };
                            }
                        }
                    } catch(e) {}
                }
                return { found: false };
            }""", selector)

            if pos and pos.get('found'):
                results.append((pos['x'], pos['y'], description, pos['w'], pos['h']))
            else:
                print(f"    ⚠ NOT FOUND: {selector}")
        except Exception as e:
            print(f"    ⚠ ERROR: {selector}: {e}")

    return results


def main():
    SCREENSHOTS_DIR.mkdir(parents=True, exist_ok=True)

    font_badge = get_font(BADGE_FONT_SIZE)
    font_desc = get_font(DESC_FONT_SIZE)

    annotated = 0

    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context(
            viewport={"width": 1280, "height": 900},
            device_scale_factor=1.0,
        )
        page = context.new_page()

        for html_file in sorted(ELEMENT_ANNOTATIONS.keys()):
            html_path = HTML_DIR / html_file
            png_name = html_file.replace(".html", ".png")
            png_path = SCREENSHOTS_DIR / png_name

            if not html_path.exists():
                print(f"  SKIP: {html_file} (HTML introuvable)")
                continue

            if not png_path.exists():
                print(f"  SKIP: {png_name} (PNG introuvable)")
                continue

            try:
                page.goto(f"file://{html_path.resolve()}", wait_until="networkidle", timeout=15000)
            except Exception as e:
                print(f"  ERREUR: {html_file}: {e}")
                continue

            positions = get_element_positions(page, html_file)

            if not positions:
                print(f"  SKIP: {png_name} (aucun élément trouvé)")
                continue

            img = Image.open(png_path).convert("RGB")
            draw = ImageDraw.Draw(img)
            w, h = img.size

            # Compute non-overlapping badge positions
            badge_layouts = compute_badge_positions(positions, w, h)

            for i, (bx, by, tx, ty, desc) in enumerate(badge_layouts, 1):
                # Draw target at element edge
                draw_target(draw, tx, ty)

                # Draw arrow from badge to target
                draw_arrow(draw, bx, by, tx, ty)

                # Draw badge number
                draw_badge(draw, bx, by, i, font_badge)

                # Draw description BELOW the badge
                desc_x = bx - BADGE_RADIUS - 20  # aligned left of badge
                desc_y = by + BADGE_RADIUS + 6    # below the badge
                draw_description(draw, desc_x, desc_y, desc, font_desc, w)

            img.save(png_path, "PNG", optimize=True)

            dst = DOCS_DIR / png_name
            shutil.copy2(png_path, dst)

            size_kb = png_path.stat().st_size / 1024
            print(f"  OK: {png_name} ({len(positions)} annotations, {size_kb:.0f} KB)")
            annotated += 1

        browser.close()

    print(f"\n{annotated} screenshots annotés avec positions non-chevauchantes")


if __name__ == "__main__":
    main()
